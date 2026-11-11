<?php

namespace App\Domain\Facility\Services;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\BillLine;
use App\Domain\Billing\Models\PaymentAllocation;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\Shift;
use App\Domain\Facility\Exceptions\MembershipRequiredException;
use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Finance\Services\JournalService;
use App\Domain\Finance\Services\RevenueRecognitionService;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Membership\Models\Member;
use App\Domain\Prepaid\Services\WalletService;
use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Shared\Enums\MemberStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\Exceptions\InsufficientFundsException;
use App\Domain\Shared\Exceptions\ShiftNotOpenException;
use App\Domain\Shared\Exceptions\UnauthorizedActionException;
use App\Domain\Shared\Services\BusinessDateService;
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

    /**
     * Resolve whether an active Tariff represents a facility subscription plan
     * (monthly/annual Badminton or Gym membership), excluding day passes & hourly court fees.
     *
     * @return array{facility_code: string, plan_type: string}|null
     */
    public function resolvePlanDetailsFromTariff(Tariff $tariff): ?array
    {
        if ($tariff->department_code !== 'sports') {
            return null;
        }

        $haystack = strtolower($tariff->code . ' ' . $tariff->name);

        foreach (['guest', 'day pass', 'hourly', 'hour', 'slot', 'shuttle', 'locker'] as $excluded) {
            if (str_contains($haystack, $excluded)) {
                return null;
            }
        }

        $facilityCode = match (true) {
            str_contains($haystack, 'badminton') => 'badminton',
            str_contains($haystack, 'gym') => 'gym',
            default => null,
        };

        $planType = match (true) {
            str_contains($haystack, 'annual') || str_contains($haystack, 'yearly') || str_contains($haystack, 'year') => 'annual',
            str_contains($haystack, 'month') => 'monthly',
            default => null,
        };

        if (!$facilityCode || !$planType) {
            return null;
        }

        return [
            'facility_code' => $facilityCode,
            'plan_type' => $planType,
        ];
    }

    /**
     * Split total collected subscription amount into exact integer base fee & GST paise.
     *
     * @return array{gross_paise: int, tax_paise: int, net_paise: int, tax_percent: float}
     */
    public function splitTariffFee(Tariff $tariff, int $totalFeePaise): array
    {
        $netPaise = max(0, $totalFeePaise);
        $taxPercent = (float) ($tariff->tax_percent ?? 0);

        if ($netPaise === 0 || $taxPercent <= 0) {
            return [
                'gross_paise' => $netPaise,
                'tax_paise' => 0,
                'net_paise' => $netPaise,
                'tax_percent' => $taxPercent,
            ];
        }

        $standardTaxPaise = (int) round(($tariff->rate_paise * $taxPercent) / 100);
        if ($netPaise === ((int) $tariff->rate_paise + $standardTaxPaise)) {
            return [
                'gross_paise' => (int) $tariff->rate_paise,
                'tax_paise' => $standardTaxPaise,
                'net_paise' => $netPaise,
                'tax_percent' => $taxPercent,
            ];
        }

        // Inclusive custom override (e.g., flat fee entered manually by Secretary/Admin)
        $grossPaise = (int) round($netPaise / (1 + ($taxPercent / 100)));
        $taxPaise = $netPaise - $grossPaise;

        return [
            'gross_paise' => $grossPaise,
            'tax_paise' => $taxPaise,
            'net_paise' => $netPaise,
            'tax_percent' => $taxPercent,
        ];
    }

    /**
     * Enroll a member in a sports facility subscription with mandatory upfront payment settlement.
     * Enforces Zero-Credit Policy, active club membership, atomic Bill + PaymentReceipt + GL Journal (4040/2030),
     * optional Cashier Shift linkage, Prepaid Wallet debit (if prepaid), and Outbox dispatch.
     */
    public function enrollWithPayment(
        Member $member,
        string $facilityCode,
        string $planType,
        Tariff $tariff,
        int $totalFeePaise,
        TenderType $tenderType,
        ?string $startDate = null,
        ?string $endDate = null,
        string $status = 'active',
        ?int $shiftId = null,
        ?string $paymentReference = null,
        ?string $idempotencyKey = null,
        ?User $user = null,
        ?string $notes = null,
    ): FacilitySubscription {
        if ($totalFeePaise < 0) {
            throw new InvalidArgumentException('Subscription fee cannot be negative.');
        }

        if ($status === 'active' && $tariff->rate_paise > 0 && $totalFeePaise <= 0) {
            throw new UnauthorizedActionException('Active sports subscription requires confirmed fee collection. No credit or unpaid bypass is permitted.');
        }

        $key = $idempotencyKey ?: 'sub_enroll_' . $member->id . '_' . Str::uuid();

        return DB::transaction(function () use (
            $member,
            $facilityCode,
            $planType,
            $tariff,
            $totalFeePaise,
            $tenderType,
            $startDate,
            $endDate,
            $status,
            $shiftId,
            $paymentReference,
            $key,
            $user,
            $notes
        ) {
            // 1. Idempotency check
            $existingReceipt = PaymentReceipt::where('idempotency_key', $key)->first();
            if ($existingReceipt) {
                $existingSub = FacilitySubscription::where('payment_receipt_id', $existingReceipt->id)->first();
                if ($existingSub) {
                    return $existingSub;
                }
            }

            // 2. Lock member & validate base club membership
            $lockedMember = Member::with('wallet')->where('id', $member->id)->lockForUpdate()->firstOrFail();
            $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfDay();
            $end = $endDate ? Carbon::parse($endDate)->startOfDay() : $this->calculateEndDate($planType, $start);

            if ($end->lte($start)) {
                throw new InvalidArgumentException('Plan expiration date must be strictly after the start date.');
            }

            if ($status === 'active' && !$lockedMember->isActive($start)) {
                throw new MembershipRequiredException("Cannot activate sports subscription: member {$lockedMember->membership_number} base membership is inactive or expired.");
            }

            $staffUserId = $user?->id ?? auth()->id() ?? User::value('id');
            if (!$staffUserId) {
                throw new UnauthorizedActionException('An authenticated staff user is required to record subscription collections.');
            }

            if ($shiftId !== null) {
                $shift = Shift::lockForUpdate()->findOrFail($shiftId);
                if (!$shift->isOpen()) {
                    throw new ShiftNotOpenException('Selected cashier shift is already closed. Choose an open shift or Back-Office Secretariat collection.');
                }
            }

            $receipt = null;

            // 3. Collect and post payment when fee > 0
            if ($totalFeePaise > 0) {
                $split = $this->splitTariffFee($tariff, $totalFeePaise);
                $grossPaise = $split['gross_paise'];
                $taxPaise = $split['tax_paise'];
                $netPaise = $split['net_paise'];
                $taxPercent = $split['tax_percent'];

                $businessDateStr = app(BusinessDateService::class)->getBusinessDate(now());
                $businessDate = Carbon::parse($businessDateStr);

                $billNumber = $this->generateUniqueBillNumber();
                $receiptNumber = $this->generateUniqueReceiptNumber();

                $bill = Bill::create([
                    'bill_number' => $billNumber,
                    'shift_id' => $shiftId,
                    'customer_type' => Member::class,
                    'customer_id' => $lockedMember->id,
                    'department_code' => 'sports',
                    'status' => BillStatus::DRAFT,
                    'business_date' => $businessDateStr,
                    'gross_amount_paise' => $grossPaise,
                    'discount_amount_paise' => 0,
                    'tax_amount_paise' => $taxPaise,
                    'net_amount_paise' => $netPaise,
                    'paid_amount_paise' => 0,
                    'idempotency_key' => $key,
                    'idempotency_hash' => hash('sha256', $key),
                    'notes' => $notes ?: "Sports Plan Subscription ({$facilityCode} / {$planType})",
                    'created_by_user_id' => $staffUserId,
                ]);

                $planLabel = ucfirst($facilityCode) . ' ' . ucfirst($planType) . ' Plan';
                BillLine::create([
                    'bill_id' => $bill->id,
                    'department_code' => 'sports',
                    'item_type' => 'tariff',
                    'item_id' => $tariff->id,
                    'item_name' => "{$tariff->name} ({$planLabel})",
                    'quantity' => 1,
                    'free_quantity' => 0,
                    'unit_price_paise' => $grossPaise,
                    'gross_amount_paise' => $grossPaise,
                    'discount_amount_paise' => 0,
                    'tax_percent' => $taxPercent,
                    'tax_amount_paise' => $taxPaise,
                    'line_total_paise' => $netPaise,
                ]);

                $walletTxId = null;
                if ($tenderType === TenderType::PREPAID) {
                    $wallet = $lockedMember->wallet;
                    if (!$wallet) {
                        throw new InsufficientFundsException("No prepaid wallet account found for member {$lockedMember->membership_number}.");
                    }

                    $walletTx = app(WalletService::class)->debit(
                        wallet: $wallet,
                        amountPaise: $netPaise,
                        referenceType: 'bill',
                        referenceId: $bill->id,
                        notes: "Sports Subscription {$bill->bill_number} ({$planLabel})",
                        userId: $staffUserId
                    );
                    $walletTxId = $walletTx->id;
                }

                $providerName = match ($tenderType) {
                    TenderType::CASH => 'cashier_manual',
                    TenderType::DIGITAL => 'upi_card_terminal',
                    TenderType::BANK_TRANSFER => 'bank_transfer',
                    TenderType::CHEQUE => 'cheque',
                    TenderType::PREPAID => 'prepaid_wallet',
                };

                $receipt = PaymentReceipt::create([
                    'receipt_number' => $receiptNumber,
                    'shift_id' => $shiftId,
                    'bill_id' => $bill->id,
                    'wallet_transaction_id' => $walletTxId,
                    'customer_type' => Member::class,
                    'customer_id' => $lockedMember->id,
                    'tender_type' => $tenderType,
                    'amount_paise' => $netPaise,
                    'provider_name' => $providerName,
                    'provider_reference' => $paymentReference ?: "RCP-{$receiptNumber}",
                    'provider_status' => 'confirmed',
                    'idempotency_key' => $key,
                    'confirmed_at' => now(),
                    'created_by_user_id' => $staffUserId,
                ]);

                PaymentAllocation::create([
                    'payment_receipt_id' => $receipt->id,
                    'bill_id' => $bill->id,
                    'amount_paise' => $netPaise,
                ]);

                $debitAccountCode = match ($tenderType) {
                    TenderType::CASH => '1010',
                    TenderType::PREPAID => '2010',
                    TenderType::DIGITAL, TenderType::BANK_TRANSFER, TenderType::CHEQUE => '1020',
                };

                $journalLines = [
                    [
                        'account_code' => $debitAccountCode,
                        'debit_paise' => $netPaise,
                        'credit_paise' => 0,
                        'narration' => "{$tenderType->label()} collection for {$bill->bill_number} ({$planLabel})",
                    ],
                ];

                if ($grossPaise > 0) {
                    $journalLines[] = [
                        'account_code' => '4040',
                        'debit_paise' => 0,
                        'credit_paise' => $grossPaise,
                        'department_code' => 'sports',
                        'narration' => "Sports subscription revenue for {$bill->bill_number} ({$planLabel})",
                    ];
                }

                if ($taxPaise > 0) {
                    $journalLines[] = [
                        'account_code' => '2030',
                        'debit_paise' => 0,
                        'credit_paise' => $taxPaise,
                        'department_code' => 'sports',
                        'narration' => "GST collected on {$bill->bill_number}",
                    ];
                }

                app(JournalService::class)->post(
                    sourceType: 'bill',
                    sourceId: $bill->id,
                    description: "Sports Subscription Settlement {$bill->bill_number} ({$lockedMember->membership_number})",
                    lines: $journalLines,
                    businessDate: $businessDate,
                    userId: $staffUserId
                );

                $bill->update([
                    'status' => BillStatus::PAID,
                    'paid_amount_paise' => $netPaise,
                    'business_date' => $businessDateStr,
                ]);

                OutboxMessage::create([
                    'event_type' => 'billing.bill.paid',
                    'aggregate_type' => 'Bill',
                    'aggregate_id' => $bill->id,
                    'payload' => [
                        'bill_number' => $bill->bill_number,
                        'net_amount_paise' => $netPaise,
                        'department_code' => 'sports',
                        'customer_id' => $lockedMember->id,
                    ],
                    'status' => 'pending',
                    'idempotency_key' => "outbox_bill_{$bill->id}",
                ]);
            }

            // 4. Issue FacilitySubscription
            $subscription = FacilitySubscription::create([
                'subscription_number' => 'SUB-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
                'member_id' => $lockedMember->id,
                'facility_code' => $facilityCode,
                'tariff_id' => $tariff->id,
                'plan_type' => $planType,
                'starts_at' => $start->toDateString(),
                'ends_at' => $end->toDateString(),
                'total_fee_paise' => $totalFeePaise,
                'payment_receipt_id' => $receipt?->id,
                'status' => $status,
                'created_by_user_id' => $staffUserId,
            ]);

            // 5. Audit revenue recognition schedule (marked linked to upfront Bill)
            if ($subscription->total_fee_paise > 0) {
                app(RevenueRecognitionService::class)->createSchedule($subscription);
            }

            return $subscription->fresh(['member.person', 'tariff', 'paymentReceipt']);
        });
    }

    /**
     * Activate or continuously extend a member's facility subscription from a paid POS BillLine.
     */
    public function activateFromPaidTariffLine(
        Member $member,
        Tariff $tariff,
        BillLine $billLine,
        ?PaymentReceipt $receipt = null,
        ?int $userId = null
    ): ?FacilitySubscription {
        $plan = $this->resolvePlanDetailsFromTariff($tariff);
        if (!$plan) {
            return null;
        }

        if ($receipt) {
            $existing = FacilitySubscription::where('payment_receipt_id', $receipt->id)
                ->where('tariff_id', $tariff->id)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($member, $tariff, $billLine, $receipt, $plan, $userId) {
            $lockedMember = Member::where('id', $member->id)->lockForUpdate()->firstOrFail();

            $today = now()->startOfDay();
            $latestEndsAt = FacilitySubscription::where('member_id', $lockedMember->id)
                ->where('facility_code', $plan['facility_code'])
                ->where('status', 'active')
                ->where('ends_at', '>', $today->toDateString())
                ->max('ends_at');

            $start = $latestEndsAt
                ? Carbon::parse($latestEndsAt)->startOfDay()
                : $today;

            if (!$lockedMember->isActive($start)) {
                throw new MembershipRequiredException("Cannot activate {$plan['facility_code']} plan: member {$lockedMember->membership_number} base membership is not active or expires before {$start->toDateString()}.");
            }

            $periods = max(1, (int) round((float) $billLine->quantity));
            $end = $start->copy();
            for ($i = 0; $i < $periods; $i++) {
                $end = $this->calculateEndDate($plan['plan_type'], $end);
            }

            $subscription = FacilitySubscription::create([
                'subscription_number' => 'SUB-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
                'member_id' => $lockedMember->id,
                'facility_code' => $plan['facility_code'],
                'tariff_id' => $tariff->id,
                'plan_type' => $plan['plan_type'],
                'starts_at' => $start->toDateString(),
                'ends_at' => $end->toDateString(),
                'total_fee_paise' => (int) $billLine->line_total_paise,
                'payment_receipt_id' => $receipt?->id,
                'status' => 'active',
                'created_by_user_id' => $userId ?? auth()->id(),
            ]);

            if ($subscription->total_fee_paise > 0) {
                app(RevenueRecognitionService::class)->createSchedule($subscription);
            }

            return $subscription;
        });
    }

    protected function generateUniqueBillNumber(): string
    {
        $yearMonth = now()->format('Ym');
        $count = Bill::where('bill_number', 'LIKE', "BILL-{$yearMonth}-%")->count() + 1;
        $candidate = sprintf('BILL-%s-%05d', $yearMonth, $count);

        while (Bill::where('bill_number', $candidate)->exists()) {
            $count++;
            $candidate = sprintf('BILL-%s-%05d', $yearMonth, $count);
        }

        return $candidate;
    }

    protected function generateUniqueReceiptNumber(): string
    {
        $yearMonth = now()->format('Ym');
        $count = PaymentReceipt::where('receipt_number', 'LIKE', "RCP-{$yearMonth}-%")->count() + 1;
        $candidate = sprintf('RCP-%s-%05d', $yearMonth, $count);

        while (PaymentReceipt::where('receipt_number', $candidate)->exists()) {
            $count++;
            $candidate = sprintf('RCP-%s-%05d', $yearMonth, $count);
        }

        return $candidate;
    }
}
