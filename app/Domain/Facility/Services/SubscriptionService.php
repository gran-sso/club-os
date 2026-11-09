<?php

namespace App\Domain\Facility\Services;

use App\Domain\Facility\Exceptions\MembershipRequiredException;
use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Membership\Models\Member;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Shared\Enums\MemberStatus;
use App\Domain\Tariff\Models\Tariff;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SubscriptionService
{
    /**
     * Calculate precise exclusive end boundary following calendar month/year rules (D12).
     */
    public function calculateEndDate(string $planType, CarbonInterface $startDate): Carbon
    {
        $start = Carbon::parse($startDate)->startOfDay();

        if ($planType === 'monthly') {
            if ($start->isEndOfMonth()) {
                // If starting on month-end (e.g. Jan 31), covers up to end of next month (Feb 28/29), exclusive end March 1
                return $start->copy()->addMonthNoOverflow()->endOfMonth()->addDay()->startOfDay();
            }

            return $start->copy()->addMonth()->startOfDay();
        }

        if ($planType === 'annual') {
            if ($start->format('m-d') === '02-29') {
                // Starting on leap day, full year covers through Feb 28 next year, exclusive end March 1
                return $start->copy()->addYearNoOverflow()->addDay()->startOfDay();
            }

            return $start->copy()->addYear()->startOfDay();
        }

        throw new InvalidArgumentException("Unknown plan type: {$planType}");
    }

    /**
     * Create facility subscription (Gym or Badminton).
     * Enforces active base club membership prerequisite (Scenario A03) and confirmed payment.
     */
    public function createSubscription(
        Member $member,
        string $facilityCode,
        string $planType,
        Tariff $tariff,
        ?PaymentReceipt $receipt = null,
        ?string $startDate = null,
        ?User $user = null
    ): FacilitySubscription {
        if ($receipt !== null && $tariff->rate_paise > 0) {
            if (!$receipt->isConfirmed() || $receipt->amount_paise < $tariff->rate_paise) {
                throw new \App\Domain\Shared\Exceptions\UnauthorizedActionException("Subscription requires confirmed payment of the full tariff rate (₹" . number_format($tariff->rate_paise / 100, 2) . "). No credit permitted.");
            }
        }

        return DB::transaction(function () use ($member, $facilityCode, $planType, $tariff, $receipt, $startDate, $user) {
            $lockedMember = Member::where('id', $member->id)->lockForUpdate()->firstOrFail();
            $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfDay();

            if (!$lockedMember->isActive($start)) {
                throw new MembershipRequiredException("Cannot create sports subscription: member {$lockedMember->membership_number} base membership is not active or has expired.");
            }
            $end = $this->calculateEndDate($planType, $start);

            return FacilitySubscription::create([
                'subscription_number' => 'SUB-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
                'member_id' => $lockedMember->id,
                'facility_code' => $facilityCode,
                'tariff_id' => $tariff->id,
                'plan_type' => $planType,
                'starts_at' => $start->toDateString(),
                'ends_at' => $end->toDateString(),
                'total_fee_paise' => $tariff->rate_paise,
                'payment_receipt_id' => $receipt?->id,
                'status' => 'active',
                'created_by_user_id' => $user?->id,
            ]);
        });
    }

    /**
     * Renew an existing facility subscription.
     * Enforces non-negotiable confirmed payment and active base membership.
     */
    public function renewSubscription(
        FacilitySubscription $subscription,
        Tariff $tariff,
        ?PaymentReceipt $receipt,
        ?User $user = null
    ): FacilitySubscription {
        if (!$receipt || !$receipt->isConfirmed() || $receipt->amount_paise < $tariff->rate_paise) {
            throw new \App\Domain\Shared\Exceptions\UnauthorizedActionException("Subscription renewal requires confirmed payment. No credit permitted.");
        }

        return DB::transaction(function () use ($subscription, $tariff, $receipt, $user) {
            $member = Member::where('id', $subscription->member_id)->lockForUpdate()->firstOrFail();
            $renewalStart = Carbon::parse($subscription->ends_at)->startOfDay();

            if (!$member->isActive($renewalStart)) {
                throw new MembershipRequiredException("Cannot renew sports subscription: base club membership is expired or inactive.");
            }

            $renewalEnd = $this->calculateEndDate($subscription->plan_type, $renewalStart);

            $subscription->update([
                'tariff_id' => $tariff->id,
                'ends_at' => $renewalEnd->toDateString(),
                'total_fee_paise' => $subscription->total_fee_paise + $tariff->rate_paise,
                'status' => 'active',
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Check sports entitlement for a member on a given date (Scenario A03, B06, B07).
     * Returns whether access is allowed, whether covered by plan, and specific reason.
     */
    public function checkEntitlement(Member $member, string $facilityCode, ?CarbonInterface $date = null): array
    {
        $checkDate = $date ? Carbon::parse($date)->startOfDay() : now()->startOfDay();

        // 1. Enforce base membership validity (Scenario A03)
        if ($member->status !== MemberStatus::ACTIVE || ($member->valid_until !== null && $checkDate->gt($member->valid_until->endOfDay()))) {
            return [
                'allowed' => false,
                'reason' => 'base_membership_expired',
                'message' => 'Base club membership has expired or is inactive. Top-up alone does not renew membership.',
                'is_plan_covered' => false,
                'subscription' => null,
            ];
        }

        // 2. Check for active subscription covering this facility on checkDate (Scenario B06)
        $sub = FacilitySubscription::where('member_id', $member->id)
            ->where('facility_code', $facilityCode)
            ->where('status', 'active')
            ->where('starts_at', '<=', $checkDate->toDateString())
            ->where('ends_at', '>', $checkDate->toDateString())
            ->first();

        if ($sub) {
            return [
                'allowed' => true,
                'reason' => 'subscription_active',
                'message' => 'Access covered by active ' . $sub->plan_type . ' subscription.',
                'is_plan_covered' => true,
                'subscription' => $sub,
            ];
        }

        return [
            'allowed' => false,
            'reason' => 'no_active_plan',
            'message' => "Member has no active subscription covering {$facilityCode}. Daily access pass required.",
            'is_plan_covered' => false,
            'subscription' => null,
        ];
    }
}
