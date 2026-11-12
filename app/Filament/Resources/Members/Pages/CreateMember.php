<?php

namespace App\Filament\Resources\Members\Pages;

use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberNote;
use App\Domain\Membership\Services\CardService;
use App\Domain\Membership\Services\MemberService;
use App\Domain\Shared\Enums\TenderType;
use App\Filament\Resources\Members\MemberResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $personData = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'id_proof_type' => $data['id_proof_type'] ?? null,
            'id_proof_number' => $data['id_proof_number'] ?? null,
            'id_proof_path' => $data['id_proof_path'] ?? null,
            'application_form_path' => $data['application_form_path'] ?? null,
            'photo_path' => $data['photo_path'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        $member = app(MemberService::class)->registerMember(
            $personData,
            (int) $data['membership_category_id'],
            $data['membership_number'],
            $data['joined_at'] ?? null,
            $data['valid_until'] ?? null
        );

        if (!empty($data['status']) && $data['status'] !== 'active') {
            $member->update(['status' => $data['status']]);
        }

        if (!empty($data['rfid_card_number'])) {
            app(CardService::class)->issueCard(
                $member,
                $data['rfid_card_number'],
                'Initial card issuance on member registration'
            );
        }

        if (!empty($data['collect_initial_fee']) && empty($data['historical_fees_already_paid'])) {
            $admissionFeeRupees = (float) ($data['admission_fee_amount_rupees'] ?? 0);
            $annualFeeRupees = (float) ($data['annual_fee_amount_rupees'] ?? $data['initial_fee_amount_rupees'] ?? 0);
            $totalPaise = (int) round(($admissionFeeRupees + $annualFeeRupees) * 100);

            $joinedDate = $member->joined_at ?? now();
            $validUntil = !empty($data['valid_until']) ? $data['valid_until'] : Carbon::parse($joinedDate)->addYear()->endOfDay()->toDateString();

            $tender = match ($data['fee_tender_type'] ?? 'cash') {
                'digital' => TenderType::DIGITAL,
                'bank_transfer' => TenderType::BANK_TRANSFER,
                'cheque' => TenderType::CHEQUE,
                default => TenderType::CASH,
            };

            $yearMonth = now()->format('Ym');
            $receiptCount = PaymentReceipt::where('receipt_number', 'LIKE', "RCP-{$yearMonth}-%")->count() + 1;
            $receiptNumber = sprintf("RCP-%s-%05d", $yearMonth, $receiptCount);

            if ($totalPaise > 0) {
                PaymentReceipt::create([
                    'receipt_number' => $receiptNumber,
                    'shift_id' => null,
                    'bill_id' => null,
                    'wallet_transaction_id' => null,
                    'customer_type' => Member::class,
                    'customer_id' => $member->id,
                    'tender_type' => $tender,
                    'amount_paise' => $totalPaise,
                    'provider_name' => 'secretariat_manual',
                    'provider_reference' => $data['fee_reference'] ?? "MEM-REG-{$member->membership_number}",
                    'provider_status' => 'confirmed',
                    'idempotency_key' => (string) Str::uuid(),
                    'confirmed_at' => now(),
                    'created_by_user_id' => auth()->id(),
                ]);
            }

            $member->update([
                'admission_fee_status' => $admissionFeeRupees > 0 ? 'paid' : ($member->category?->admission_fee_paise == 0 ? 'waived' : 'pending'),
                'admission_fee_paise' => (int) round($admissionFeeRupees * 100),
                'admission_fee_receipt_number' => $admissionFeeRupees > 0 ? $receiptNumber : null,
                'admission_fee_paid_at' => $admissionFeeRupees > 0 ? now() : null,
                'fee_status' => 'paid',
                'last_fee_paid_at' => now(),
                'last_fee_receipt_number' => $receiptNumber,
                'valid_until' => $validUntil,
            ]);

            $totalFormatted = number_format($admissionFeeRupees + $annualFeeRupees, 2);
            MemberNote::create([
                'member_id' => $member->id,
                'created_by_user_id' => auth()->id(),
                'category' => 'general',
                'note' => "Initial membership fees collected upon admission: Admission Fee ₹" . number_format($admissionFeeRupees, 2) . " + First-Year Dues ₹" . number_format($annualFeeRupees, 2) . " (Total ₹{$totalFormatted}) via " . ucfirst($data['fee_tender_type'] ?? 'cash') . " (Receipt #{$receiptNumber}). Valid until " . Carbon::parse($validUntil)->format('d M Y') . ".",
            ]);
        } elseif (!empty($data['historical_fees_already_paid'])) {
            $category = $member->category;
            $admissionPaise = (int) ($category?->admission_fee_paise ?? 0);
            $admissionStatus = $admissionPaise === 0 ? 'waived' : 'paid';
            $paidAt = !empty($data['historical_fee_paid_at'])
                ? Carbon::parse($data['historical_fee_paid_at'])
                : ($member->joined_at ?? now());
            $historicalRef = trim((string) ($data['historical_receipt_ref'] ?? '')) ?: null;

            $isExpired = ($data['status'] ?? 'active') === 'expired'
                || ($member->valid_until !== null && $member->valid_until->endOfDay()->lt(now()->startOfDay()));
            $annualStatus = $isExpired ? 'pending' : 'paid';

            $validUntil = $member->valid_until;
            if ($validUntil === null && $category && (int) $category->annual_fee_paise > 0 && $annualStatus === 'paid') {
                $validUntil = MemberService::defaultAnnualValidUntilDate();
            }

            $member->update([
                'admission_fee_status' => $admissionStatus,
                'admission_fee_paise' => $admissionPaise,
                'admission_fee_paid_at' => $admissionStatus === 'paid' ? $paidAt : null,
                'admission_fee_receipt_number' => $admissionStatus === 'paid' ? $historicalRef : null,
                'fee_status' => $annualStatus,
                'last_fee_paid_at' => $annualStatus === 'paid' ? $paidAt : null,
                'last_fee_receipt_number' => $annualStatus === 'paid' ? $historicalRef : null,
                'valid_until' => $validUntil,
            ]);

            $refLabel = $historicalRef ? " (Historical Ref: {$historicalRef})" : '';
            MemberNote::create([
                'member_id' => $member->id,
                'created_by_user_id' => auth()->id(),
                'category' => 'general',
                'note' => "Existing club member onboarded with historical fees marked as already paid{$refLabel}. Admission Fee: " . ucfirst($admissionStatus) . "; Annual Dues: " . ucfirst($annualStatus) . '. Zero counter receipt generated.',
            ]);
        } else {
            $category = $member->category;
            $member->update([
                'admission_fee_status' => ($category && $category->admission_fee_paise == 0) ? 'waived' : 'pending',
                'admission_fee_paise' => $category?->admission_fee_paise ?? 0,
                'fee_status' => 'pending',
            ]);
        }

        return $member;
    }
}
