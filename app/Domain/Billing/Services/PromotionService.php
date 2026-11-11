<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Promotion;
use App\Domain\Membership\Models\Dependent;
use App\Domain\Membership\Models\Member;
use App\Domain\Tariff\Models\Tariff;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PromotionService
{
    /**
     * Guest entry / paid-entry fees must never be zeroed out by promotional rules (Rule A10).
     */
    public function isTariffProtectedFromPromotion(?Tariff $tariff): bool
    {
        if (!$tariff) {
            return true;
        }

        $code = strtoupper((string) $tariff->code);
        $name = strtolower((string) $tariff->name);
        $dept = strtolower((string) $tariff->department_code);

        if (str_starts_with($code, 'GUEST-')) {
            return true;
        }

        if (in_array($dept, ['membership', 'card_room'], true) && str_contains($name, 'guest')) {
            return true;
        }

        if ((int) $tariff->rate_paise <= 0) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the customer context qualifies for a promotion's scope.
     *
     * @param array{is_room_resident?: bool, is_guest_sale?: bool} $context
     */
    public function isCustomerEligible(?Model $customer, Promotion $promo, array $context = []): bool
    {
        if ($promo->customer_scope === Promotion::SCOPE_ALL) {
            return true;
        }

        // Day Guest pass checkout does not inherit sponsoring member's member-only promo privileges
        if (!empty($context['is_guest_sale']) && empty($context['is_room_resident'])) {
            return false;
        }

        if (!empty($context['is_room_resident'])) {
            return true;
        }

        return $customer instanceof Member || $customer instanceof Dependent;
    }

    /**
     * Fetch active, date-valid promotions applicable to a tariff ID.
     *
     * @return Collection<int, Promotion>
     */
    public function getActivePromotionsForTariff(int $tariffId, ?CarbonInterface $businessDate = null): Collection
    {
        $targetDate = ($businessDate ?? now())->format('Y-m-d');

        return Promotion::query()
            ->active()
            ->where('effective_from', '<=', $targetDate)
            ->where(function ($q) use ($targetDate) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $targetDate);
            })
            ->whereHas('tariffs', fn ($q) => $q->where('tariffs.id', $tariffId))
            ->orderByDesc('discount_percent')
            ->orderBy('id')
            ->get();
    }

    /**
     * Select best active eligible promotion for a tariff & customer.
     *
     * @param array{is_room_resident?: bool, is_guest_sale?: bool} $context
     */
    public function resolvePromotionForTariff(
        int $tariffId,
        ?Model $customer = null,
        ?CarbonInterface $businessDate = null,
        array $context = []
    ): ?Promotion {
        $tariff = Tariff::find($tariffId);
        if ($this->isTariffProtectedFromPromotion($tariff)) {
            return null;
        }

        $promos = $this->getActivePromotionsForTariff($tariffId, $businessDate);

        foreach ($promos as $promo) {
            if (!$promo->hasAvailableQuota(1)) {
                continue;
            }
            if (!$this->isCustomerEligible($customer, $promo, $context)) {
                continue;
            }

            return $promo;
        }

        return null;
    }

    /**
     * Short UI badge for POS search / serving modal (even before a customer card is swiped).
     *
     * @param array{is_room_resident?: bool, is_guest_sale?: bool} $context
     */
    public function getOfferBadgeForTariff(
        int $tariffId,
        ?Model $customer = null,
        ?CarbonInterface $businessDate = null,
        array $context = []
    ): ?string {
        $tariff = Tariff::find($tariffId);
        if ($this->isTariffProtectedFromPromotion($tariff)) {
            return null;
        }

        foreach ($this->getActivePromotionsForTariff($tariffId, $businessDate) as $promo) {
            if (!$promo->hasAvailableQuota(1)) {
                continue;
            }

            // If a customer is already selected, only show badge if eligible; if unset, preview available member offer
            if (($customer !== null || !empty($context['is_guest_sale']) || !empty($context['is_room_resident']))
                && !$this->isCustomerEligible($customer, $promo, $context)
            ) {
                continue;
            }

            return $promo->badgeLabel();
        }

        return null;
    }

    /**
     * Evaluate all lines in a cart/bill using shared integer-paise arithmetic and track shared promo quotas.
     *
     * @param array<int, array<string, mixed>> $lines
     * @param array{is_room_resident?: bool, is_guest_sale?: bool} $context
     * @return array{
     *   lines: array<int, array<string, mixed>>,
     *   gross_paise: int,
     *   discount_paise: int,
     *   tax_paise: int,
     *   net_paise: int
     * }
     */
    public function evaluateCart(
        array $lines,
        ?Model $customer = null,
        ?CarbonInterface $businessDate = null,
        array $context = []
    ): array {
        $evaluatedLines = [];
        $totalGrossPaise = 0;
        $totalDiscountPaise = 0;
        $totalTaxPaise = 0;
        $claimedQuotaByPromo = [];

        foreach ($lines as $line) {
            $evaluated = $this->evaluateLine(
                line: $line,
                customer: $customer,
                businessDate: $businessDate,
                context: $context,
                claimedQuotaByPromo: $claimedQuotaByPromo
            );

            $evaluatedLines[] = $evaluated;
            $totalGrossPaise += $evaluated['gross_amount_paise'];
            $totalDiscountPaise += $evaluated['discount_amount_paise'];
            $totalTaxPaise += $evaluated['tax_amount_paise'];
        }

        $taxableNet = max(0, $totalGrossPaise - $totalDiscountPaise);

        return [
            'lines' => $evaluatedLines,
            'gross_paise' => $totalGrossPaise,
            'discount_paise' => $totalDiscountPaise,
            'tax_paise' => $totalTaxPaise,
            'net_paise' => $taxableNet + $totalTaxPaise,
        ];
    }

    /**
     * Evaluate a single bill or cart line against active promotions.
     *
     * @param array<string, mixed> $line
     * @param array{is_room_resident?: bool, is_guest_sale?: bool} $context
     * @param array<int, int> $claimedQuotaByPromo
     * @return array<string, mixed>
     */
    public function evaluateLine(
        array $line,
        ?Model $customer = null,
        ?CarbonInterface $businessDate = null,
        array $context = [],
        array &$claimedQuotaByPromo = []
    ): array {
        $qty = (float) ($line['quantity'] ?? 1);
        $unitPricePaise = (int) ($line['unit_price_paise'] ?? 0);
        $taxPercent = (float) ($line['tax_percent'] ?? 0);

        $lineGrossPaise = (int) round($qty * $unitPricePaise);
        $freeUnits = 0;
        $discountPaise = 0;
        $promoId = null;
        $promoName = null;
        $promoBadge = null;
        $promoCtaAddQty = 0;
        $promoPendingMemberLabel = null;

        $itemType = $line['item_type'] ?? null;
        $tariffId = $itemType === 'tariff'
            ? (int) ($line['item_id'] ?? $line['tariff_id'] ?? 0)
            : (int) ($line['tariff_id'] ?? 0);

        $skipPromotions = !empty($line['skip_promotions']);

        if (!$skipPromotions && $tariffId > 0 && $qty > 0 && $unitPricePaise > 0) {
            $tariff = Tariff::find($tariffId);
            if ($tariff && !$this->isTariffProtectedFromPromotion($tariff)) {
                $promos = $this->getActivePromotionsForTariff($tariffId, $businessDate);

                // First pick an immediately eligible promotion
                foreach ($promos as $promo) {
                    if ($promo->type !== Promotion::TYPE_BUY_X_GET_Y) {
                        continue;
                    }

                    $alreadyClaimed = $claimedQuotaByPromo[$promo->id] ?? 0;
                    $remainingQuota = $promo->max_free_units !== null
                        ? max(0, (int) $promo->max_free_units - (int) $promo->redeemed_free_units - $alreadyClaimed)
                        : null;

                    if ($remainingQuota !== null && $remainingQuota <= 0) {
                        continue;
                    }

                    if (!$this->isCustomerEligible($customer, $promo, $context)) {
                        // Inform POS staff if selecting a member would unlock this offer
                        if ($customer === null && empty($context['is_guest_sale']) && empty($context['is_room_resident'])
                            && $promo->customer_scope === Promotion::SCOPE_MEMBERS_ONLY
                        ) {
                            $promoPendingMemberLabel = "Members Only: " . $promo->badgeLabel();
                        }
                        continue;
                    }

                    $buyQty = max(1, (int) $promo->buy_qty);
                    $getQty = max(1, (int) $promo->get_qty);
                    $groupSize = $buyQty + $getQty;
                    $wholeUnits = (int) floor($qty);

                    $completeSets = intdiv($wholeUnits, $groupSize);
                    $potentialFreeUnits = $completeSets * $getQty;

                    if ($remainingQuota !== null) {
                        $freeUnits = min($potentialFreeUnits, $remainingQuota);
                    } else {
                        $freeUnits = $potentialFreeUnits;
                    }

                    // Show "+1 Free Peg" CTA when reaching exact buy threshold (e.g. qty = 2 for 2+1)
                    if (($wholeUnits % $groupSize) === $buyQty) {
                        if ($remainingQuota === null || $remainingQuota >= $getQty) {
                            $promoCtaAddQty = $getQty;
                        }
                    }

                    $promoId = $promo->id;
                    $promoName = $promo->name;
                    $promoBadge = $promo->badgeLabel();

                    if ($freeUnits > 0) {
                        $claimedQuotaByPromo[$promo->id] = $alreadyClaimed + $freeUnits;
                        $freeGrossPaise = (int) round($freeUnits * $unitPricePaise);
                        $discountPaise = min(
                            $lineGrossPaise,
                            (int) round(($freeGrossPaise * (float) $promo->discount_percent) / 100)
                        );
                    }

                    break;
                }
            }
        }

        $taxablePaise = max(0, $lineGrossPaise - $discountPaise);
        $taxPaise = (int) round(($taxablePaise * $taxPercent) / 100);
        $lineTotalPaise = $taxablePaise + $taxPaise;

        return array_merge($line, [
            'quantity' => $qty,
            'paid_quantity' => max(0.0, $qty - $freeUnits),
            'free_quantity' => (float) $freeUnits,
            'unit_price_paise' => $unitPricePaise,
            'gross_amount_paise' => $lineGrossPaise,
            'discount_amount_paise' => $discountPaise,
            'taxable_amount_paise' => $taxablePaise,
            'tax_percent' => $taxPercent,
            'tax_amount_paise' => $taxPaise,
            'line_total_paise' => $lineTotalPaise,
            'promotion_id' => $freeUnits > 0 ? $promoId : null,
            'promotion_name' => $freeUnits > 0 ? $promoName : null,
            'eligible_promotion_id' => $promoId,
            'eligible_promotion_name' => $promoName,
            'promotion_badge' => $promoBadge,
            'promo_cta_add_qty' => $promoCtaAddQty,
            'promo_pending_member_label' => $promoPendingMemberLabel,
        ]);
    }
}

