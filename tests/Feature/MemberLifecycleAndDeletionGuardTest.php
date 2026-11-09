<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Bill;
use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Membership\Models\Card;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Membership\Models\Person;
use App\Domain\Membership\Services\CardService;
use App\Domain\Membership\Services\MemberService;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Prepaid\Models\WalletTransaction;
use App\Domain\Shared\Enums\CardStatus;
use App\Domain\Shared\Enums\MemberStatus;
use App\Domain\Tariff\Models\Facility;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberLifecycleAndDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    protected MemberService $memberService;
    protected CardService $cardService;
    protected MembershipCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        $this->memberService = app(MemberService::class);
        $this->cardService = app(CardService::class);

        $this->category = MembershipCategory::create([
            'code' => 'TEST-ORD',
            'name' => 'Ordinary Member',
            'annual_fee_paise' => 1200000,
            'has_voting_rights' => true,
            'is_active' => true,
        ]);
    }

    public function test_member_with_positive_wallet_balance_cannot_be_deleted(): void
    {
        $member = $this->memberService->registerMember(
            personData: ['first_name' => 'Anand', 'last_name' => 'Kumar'],
            categoryId: $this->category->id,
            membershipNumber: 'MC-TEST-001'
        );

        // Deposit funds into member wallet
        $member->wallet->update(['balance_paise' => 50000]); // ₹500

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Account holds an unrefunded prepaid balance');

        $member->delete();
    }

    public function test_member_with_wallet_transaction_history_cannot_be_deleted(): void
    {
        $member = $this->memberService->registerMember(
            personData: ['first_name' => 'Bipin', 'last_name' => 'Chandran'],
            categoryId: $this->category->id,
            membershipNumber: 'MC-TEST-002'
        );

        // Record a transaction in ledger, balance returned to 0
        WalletTransaction::create([
            'wallet_account_id' => $member->wallet->id,
            'type' => 'recharge',
            'amount_paise' => 100000,
            'balance_after_paise' => 100000,
        ]);
        $member->wallet->update(['balance_paise' => 0]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Member has an immutable financial transaction history on the prepaid ledger');

        $member->delete();
    }

    public function test_member_with_posted_bills_cannot_be_deleted(): void
    {
        $member = $this->memberService->registerMember(
            personData: ['first_name' => 'Chetan', 'last_name' => 'Nair'],
            categoryId: $this->category->id,
            membershipNumber: 'MC-TEST-003'
        );

        $user = User::factory()->create();

        Bill::create([
            'bill_number' => 'BILL-TEST-999',
            'customer_type' => Member::class,
            'customer_id' => $member->id,
            'department_code' => 'bar',
            'status' => 'paid',
            'gross_amount_paise' => 10000,
            'net_amount_paise' => 10000,
            'paid_amount_paise' => 10000,
            'idempotency_key' => 'idemp-bill-test-999',
            'created_by_user_id' => $user->id,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Member is associated with historical club bills');

        $member->delete();
    }

    public function test_member_with_court_booking_cannot_be_deleted(): void
    {
        $member = $this->memberService->registerMember(
            personData: ['first_name' => 'Deepak', 'last_name' => 'Menon'],
            categoryId: $this->category->id,
            membershipNumber: 'MC-TEST-004'
        );

        $court = Facility::create([
            'code' => 'COURT-TEST',
            'name' => 'Badminton Court',
            'type' => 'court',
            'capacity' => 4,
            'is_active' => true,
        ]);

        CourtBooking::create([
            'booking_number' => 'CB-TEST-001',
            'facility_id' => $court->id,
            'booking_date' => today()->toDateString(),
            'start_time' => '07:00:00',
            'end_time' => '08:00:00',
            'member_id' => $member->id,
            'status' => 'confirmed',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Active or historical badminton court bookings exist');

        $member->delete();
    }

    public function test_pristine_member_with_zero_history_can_be_deleted_without_orphaning_wallet_or_card(): void
    {
        $member = $this->memberService->registerMember(
            personData: ['first_name' => 'Eashwar', 'last_name' => 'Varma'],
            categoryId: $this->category->id,
            membershipNumber: 'MC-TEST-005'
        );

        $card = $this->cardService->issueCard($member, 'CARD-TEST-005', 'Test issue');

        $walletId = $member->wallet->id;
        $cardId = $card->id;

        $this->assertDatabaseHas('wallet_accounts', ['id' => $walletId]);
        $this->assertDatabaseHas('cards', ['id' => $cardId]);

        // Pristine member (0 balance, 0 entries, 0 bills, 0 bookings)
        $member->delete();

        $this->assertDatabaseMissing('members', ['id' => $member->id]);
        $this->assertDatabaseMissing('wallet_accounts', ['id' => $walletId]);
        $this->assertDatabaseMissing('cards', ['id' => $cardId]);
    }

    public function test_transitioning_status_to_suspended_or_resigned_automatically_blocks_rfid_cards(): void
    {
        $member = $this->memberService->registerMember(
            personData: ['first_name' => 'Gopal', 'last_name' => 'Pillai'],
            categoryId: $this->category->id,
            membershipNumber: 'MC-TEST-006'
        );

        $card = $this->cardService->issueCard($member, 'CARD-TEST-006', 'Active Card');
        $this->assertEquals(CardStatus::ACTIVE, $card->fresh()->status);

        // Suspend member
        $member->transitionStatus(MemberStatus::SUSPENDED, 'Disciplinary review');

        $this->assertEquals(MemberStatus::SUSPENDED, $member->fresh()->status);
        $this->assertEquals(CardStatus::BLOCKED, $card->fresh()->status);
        $this->assertNotNull($card->fresh()->blocked_at);
        $this->assertStringContainsString('Disciplinary review', $card->fresh()->notes);
    }
}

