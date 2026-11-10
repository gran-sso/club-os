<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Shared\Enums\TenderType;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MembershipFeeCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected MembershipCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->category = MembershipCategory::where('code', 'ORD')->firstOrFail();
    }

    public function test_creating_member_without_fee_collection_flags_pending_payment(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateMember::class)
            ->fillForm([
                'first_name' => 'Govind',
                'last_name' => 'Padmasoorya',
                'phone' => '+919847119988',
                'email' => 'gp@example.com',
                'membership_number' => 'MC-TEST-FEE-01',
                'membership_category_id' => $this->category->id,
                'status' => 'active',
                'joined_at' => now()->toDateString(),
                'collect_initial_fee' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = Member::where('membership_number', 'MC-TEST-FEE-01')->firstOrFail();
        $this->assertEquals('pending', $member->fee_status);
        $this->assertNull($member->last_fee_paid_at);
        $this->assertNull($member->last_fee_receipt_number);

        // View Member Profile
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $member->id])
            ->assertSee('Fee Pending')
            ->assertSee('Fee Pending Payment')
            ->assertSee('Initial Membership Admission Fee Pending Payment')
            ->assertSee('Collect Fee Now');
    }

    public function test_creating_member_with_initial_fee_collection_records_receipt_and_activates_validity(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateMember::class)
            ->fillForm([
                'first_name' => 'Fahadh',
                'last_name' => 'Faasil',
                'phone' => '+919847778899',
                'email' => 'fahadh@example.com',
                'membership_number' => 'MC-TEST-FEE-02',
                'membership_category_id' => $this->category->id,
                'status' => 'active',
                'joined_at' => '2026-01-15',
                'collect_initial_fee' => true,
                'admission_fee_amount_rupees' => 50000,
                'annual_fee_amount_rupees' => 12000,
                'fee_tender_type' => 'digital',
                'fee_reference' => 'UPI-REF-FAHADH-999',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = Member::where('membership_number', 'MC-TEST-FEE-02')->firstOrFail();
        $this->assertEquals('paid', $member->fee_status);
        $this->assertEquals('paid', $member->admission_fee_status);
        $this->assertEquals(5000000, $member->admission_fee_paise);
        $this->assertNotNull($member->admission_fee_paid_at);
        $this->assertNotNull($member->admission_fee_receipt_number);
        $this->assertNotNull($member->last_fee_paid_at);
        $this->assertNotNull($member->last_fee_receipt_number);
        $this->assertNotNull($member->valid_until);

        // Assert Payment Receipt created for total (₹50,000 admission + ₹12,000 annual = ₹62,000)
        $receipt = PaymentReceipt::where('customer_type', Member::class)
            ->where('customer_id', $member->id)
            ->where('receipt_number', $member->last_fee_receipt_number)
            ->first();

        $this->assertNotNull($receipt);
        $this->assertEquals(6200000, $receipt->amount_paise); // ₹62,000 in paise
        $this->assertEquals(TenderType::DIGITAL, $receipt->tender_type);
        $this->assertEquals('UPI-REF-FAHADH-999', $receipt->provider_reference);

        // View Member Profile displays paid standing
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $member->id])
            ->assertSee('Good Standing')
            ->assertSee('Paid')
            ->assertSee('Lifetime')
            ->assertDontSee('Initial Membership Admission Fee Pending Payment');
    }

    public function test_admin_can_collect_pending_fee_from_member_profile_via_cash_receipt(): void
    {
        // 1. Member with pending fee
        $member = Member::create([
            'membership_number' => 'MC-TEST-FEE-03',
            'person_id' => \App\Domain\Membership\Models\Person::create(['first_name' => 'Tovino', 'last_name' => 'Thomas'])->id,
            'membership_category_id' => $this->category->id,
            'status' => 'active',
            'fee_status' => 'pending',
            'admission_fee_status' => 'pending',
            'admission_fee_paise' => 5000000,
            'joined_at' => now()->toDateString(),
        ]);

        \App\Domain\Prepaid\Models\WalletAccount::create([
            'walletable_type' => Member::class,
            'walletable_id' => $member->id,
            'balance_paise' => 0,
            'reserved_paise' => 0,
            'currency' => 'INR',
        ]);

        $this->assertEquals('pending', $member->fee_status);
        $this->assertEquals('pending', $member->admission_fee_status);

        // 2. Collect fee on profile (settling both pending admission fee and annual dues)
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $member->id])
            ->call('openRenewModal')
            ->assertSet('showRenewModal', true)
            ->assertSet('collectAdmissionFee', true)
            ->set('renewalAdmissionFeeRupees', 50000)
            ->set('renewalYears', 1)
            ->set('renewalFeeRupees', 12000)
            ->set('renewalTender', 'cash')
            ->call('processRenewMembership')
            ->assertSet('showRenewModal', false);

        $member->refresh();
        $this->assertEquals('paid', $member->fee_status);
        $this->assertEquals('paid', $member->admission_fee_status);
        $this->assertEquals(5000000, $member->admission_fee_paise);
        $this->assertNotNull($member->admission_fee_paid_at);
        $this->assertNotNull($member->last_fee_paid_at);
        $this->assertNotNull($member->last_fee_receipt_number);
        $this->assertNotNull($member->valid_until);

        $receipt = PaymentReceipt::where('receipt_number', $member->last_fee_receipt_number)->first();
        $this->assertNotNull($receipt);
        $this->assertEquals(TenderType::CASH, $receipt->tender_type);
        $this->assertEquals(6200000, $receipt->amount_paise); // ₹50k + ₹12k
    }

    public function test_subsequent_renewal_only_charges_annual_subscription_dues_not_admission_fee(): void
    {
        $admissionPaidAt = Carbon::parse('2025-01-10');
        $member = Member::create([
            'membership_number' => 'MC-TEST-FEE-RENEW',
            'person_id' => \App\Domain\Membership\Models\Person::create(['first_name' => 'Prithviraj', 'last_name' => 'Sukumaran'])->id,
            'membership_category_id' => $this->category->id,
            'status' => 'active',
            'admission_fee_status' => 'paid',
            'admission_fee_paise' => 5000000,
            'admission_fee_receipt_number' => 'RCP-202501-00001',
            'admission_fee_paid_at' => $admissionPaidAt,
            'fee_status' => 'paid',
            'last_fee_paid_at' => $admissionPaidAt,
            'last_fee_receipt_number' => 'RCP-202501-00001',
            'valid_until' => now()->addDays(15), // nearing expiration
            'joined_at' => $admissionPaidAt->toDateString(),
        ]);

        \App\Domain\Prepaid\Models\WalletAccount::create([
            'walletable_type' => Member::class,
            'walletable_id' => $member->id,
            'balance_paise' => 2000000, // ₹20,000 wallet balance
            'reserved_paise' => 0,
            'currency' => 'INR',
        ]);

        // Open Renew modal: collectAdmissionFee should be FALSE because admission fee was already paid!
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $member->id])
            ->call('openRenewModal')
            ->assertSet('showRenewModal', true)
            ->assertSet('collectAdmissionFee', false)
            ->set('renewalYears', 1)
            ->set('renewalFeeRupees', 12000)
            ->set('renewalTender', 'prepaid') // pay from wallet
            ->call('processRenewMembership')
            ->assertSet('showRenewModal', false);

        $member->refresh();

        // Admission fee status must remain paid and untouched
        $this->assertEquals('paid', $member->admission_fee_status);
        $this->assertEquals('RCP-202501-00001', $member->admission_fee_receipt_number);
        $this->assertEquals($admissionPaidAt->toDateTimeString(), $member->admission_fee_paid_at->toDateTimeString());

        // Annual dues extended and wallet debited by ₹12,000 (NOT including admission fee)
        $this->assertEquals('paid', $member->fee_status);
        $wallet = $member->wallet->fresh();
        $this->assertEquals(800000, $wallet->balance_paise); // ₹20,000 - ₹12,000 = ₹8,000
    }
}
