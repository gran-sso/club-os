<?php

namespace App\Domain\Access\Services;

use App\Domain\Access\Models\Guest;
use App\Domain\Access\Models\GuestPass;
use App\Domain\Membership\Models\AffiliatedMember;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Domain\Shared\Enums\PassStatus;
use App\Domain\Shared\Exceptions\UnauthorizedActionException;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GuestService
{
    /**
     * Register a guest with either a Club Member or Affiliated Member sponsor.
     */
    public function registerGuest(
        array $personData,
        string $sponsorType,
        int $sponsorId,
        ?string $visitDate = null
    ): Guest {
        // Validate sponsor
        if ($sponsorType === Member::class || $sponsorType === 'member') {
            $member = Member::find($sponsorId);
            if (!$member) {
                throw new InvalidArgumentException("Sponsoring member not found.");
            }
            if (!$member->isActive()) {
                throw new InvalidArgumentException("Sponsoring member #{$member->membership_number} is inactive or expired.");
            }
            $resolvedType = Member::class;
        } elseif ($sponsorType === AffiliatedMember::class || $sponsorType === 'affiliated_member') {
            $affMember = AffiliatedMember::find($sponsorId);
            if (!$affMember) {
                throw new InvalidArgumentException("Sponsoring affiliated member not found.");
            }
            if (!$affMember->isValid()) {
                throw new InvalidArgumentException("Sponsoring affiliated member is not verified or expired.");
            }
            $resolvedType = AffiliatedMember::class;
        } else {
            throw new InvalidArgumentException("Invalid sponsor type: {$sponsorType}. Must be club member or affiliated member.");
        }

        return DB::transaction(function () use ($personData, $resolvedType, $sponsorId, $visitDate) {
            $person = Person::create([
                'first_name' => $personData['first_name'],
                'last_name' => $personData['last_name'] ?? null,
                'phone' => $personData['phone'] ?? null,
                'email' => $personData['email'] ?? null,
                'id_proof_type' => $personData['id_proof_type'] ?? null,
                'id_proof_number' => $personData['id_proof_number'] ?? null,
                'notes' => $personData['notes'] ?? null,
            ]);

            return Guest::create([
                'person_id' => $person->id,
                'sponsor_type' => $resolvedType,
                'sponsor_id' => $sponsorId,
                'visit_date' => $visitDate ?? now()->toDateString(),
                'status' => 'registered',
            ]);
        });
    }

    /**
     * Activate a paid guest pass following confirmed payment.
     * Enforces policy: complimentary or zero-priced access bypass is denied.
     */
    public function activateGuestPass(
        Guest $guest,
        string $facilityCode,
        int $billLineId,
        int $paidAmountPaise,
        ?DateTimeInterface $validFrom = null,
        ?DateTimeInterface $validUntil = null
    ): GuestPass {
        if ($paidAmountPaise <= 0) {
            throw new UnauthorizedActionException("Paid guest entry policy forbids free or zero-priced admission passes.");
        }

        $from = $validFrom ? \Carbon\Carbon::instance($validFrom) : now();
        $until = $validUntil ? \Carbon\Carbon::instance($validUntil) : now()->endOfDay();

        $passCode = sprintf("PASS-%s-%04d", now()->format('Ymd'), rand(1000, 9999));

        return GuestPass::create([
            'guest_id' => $guest->id,
            'pass_code' => $passCode,
            'facility_code' => $facilityCode,
            'bill_line_id' => $billLineId,
            'status' => PassStatus::ACTIVE,
            'valid_from' => $from,
            'valid_until' => $until,
            'entry_count' => 0,
            'max_entries' => 1,
        ]);
    }

    /**
     * Cancel an unused guest pass and record financial refund/adjustment (Scenario A06).
     * Unrelated facility plans remain unaffected.
     */
    public function cancelUnusedGuestPass(GuestPass $pass, string $reason, \App\Models\User $approver): array
    {
        if (!$approver->hasAnyRole(['admin', 'manager', 'Administrator', 'Manager'])) {
            throw new UnauthorizedActionException("Only a Manager or Administrator can revoke passes and approve refunds.");
        }

        if ($pass->entry_count > 0 || $pass->status !== PassStatus::ACTIVE) {
            throw new InvalidArgumentException("Only active, unused passes with zero entry count can be cancelled for refund.");
        }

        return DB::transaction(function () use ($pass, $reason, $approver) {
            $pass->update(['status' => PassStatus::REVOKED]);

            $line = \App\Domain\Billing\Models\BillLine::with('bill')->find($pass->bill_line_id);
            $refund = null;
            if ($line && $line->bill && $line->bill->isPaid()) {
                $billingService = app(\App\Domain\Billing\Services\BillingService::class);
                $refund = $billingService->refund(
                    bill: $line->bill,
                    amountPaise: $line->line_total_paise,
                    reason: "Guest pass cancellation: {$reason}",
                    approver: $approver,
                    idempotencyKey: "pass_cancel_refund_{$pass->id}",
                    returnStock: false
                );
            }

            return [
                'pass' => $pass->fresh(),
                'refund' => $refund,
            ];
        });
    }
}
