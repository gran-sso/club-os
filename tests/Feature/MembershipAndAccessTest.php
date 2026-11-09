<?php

namespace Tests\Feature;

use App\Domain\Access\Models\Guest;
use App\Domain\Access\Models\GuestPass;
use App\Domain\Access\Services\GuestService;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Membership\Models\AffiliatedClub;
use App\Domain\Membership\Models\AffiliatedMember;
use App\Domain\Membership\Models\Card;
use App\Domain\Membership\Models\Dependent;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Membership\Models\Person;
use App\Domain\Membership\Services\CardService;
use App\Domain\Membership\Services\MemberService;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Prepaid\Services\WalletService;
use App\Domain\Shared\Enums\CardStatus;
use App\Domain\Shared\Enums\PassStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\Exceptions\CardNotActiveException;
use App\Domain\Shared\Exceptions\UnauthorizedActionException;
use App\Domain\Tariff\Models\Tariff;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MembershipAndAccessTest extends TestCase
{
    use RefreshDatabase;

    protected MemberService $memberService;
    protected CardService $cardService;
    protected GuestService $guestService;
    protected BillingService $billingService;
    protected ShiftService $shiftService;
    protected WalletService $walletService;

    protected User $cashier;
    protected User $manager;
    protected User $admin;
    protected Member $member;
    protected Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->memberService = app(MemberService::class);
        $this->cardService = app(CardService::class);
        $this->guestService = app(GuestService::class);
        $this->billingService = app(BillingService::class);
        $this->shiftService = app(ShiftService::class);
        $this->walletService = app(WalletService::class);

        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->manager = User::where('email', 'manager@malabarclub.local')->firstOrFail();
        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $this->member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $this->wallet = $this->member->wallet;

        $counter = Counter::where('code', 'COUNTER-1')->firstOrFail();
        $this->shift = $this->shiftService->openShift(
            counterId: $counter->id,
            userId: $this->cashier->id,
            openingFloatPaise: 100000
        );
    }

    /**
     * Scenario A01:
     * Cashier requests a manager-only tariff/refund action.
     * Cashier lacks 'tariffs.manage' and 'refunds.approve'. Manager possesses them.
     */
    public function test_a01_cashier_denied_manager_permissions(): void
    {
        $this->assertFalse($this->cashier->hasPermissionTo('tariffs.manage'));
        $this->assertFalse($this->cashier->hasPermissionTo('refunds.approve'));

        $this->assertTrue($this->manager->hasPermissionTo('tariffs.manage'));
        $this->assertTrue($this->manager->hasPermissionTo('refunds.approve'));
    }

    /**
     * Scenario A02:
     * CSV Import includes duplicate membership numbers and malformed dates.
     * Preview identifies rows, applies no changes before confirmation,
     * and a repeated import does not duplicate approved records.
     */
    public function test_a02_csv_import_preview_validation_and_execution(): void
    {
        $csv = <<<CSV
membership_number,first_name,last_name,phone,email,category_code,joined_at
MC-NEW-01,Arun,Kumar,9800000001,arun@example.com,ORD,2026-01-15
MC-001,Duplicate,Member,9800000002,dup@example.com,ORD,2026-01-15
MC-NEW-02,Biju,George,9800000003,biju@example.com,ORD,invalid-date-format
MC-NEW-01,Inline,Duplicate,9800000004,inline@example.com,ORD,2026-01-15
MC-NEW-03,Chacko,Thomas,9800000005,chacko@example.com,ORD,2026-02-01
CSV;

        $preview = $this->memberService->previewCsvImport($csv);

        // Preview should detect:
        // - Row 2 (MC-001) duplicate of existing DB member
        // - Row 3 (invalid date format)
        // - Row 4 (MC-NEW-01 inline duplicate of Row 1)
        // Total valid = 2 (MC-NEW-01, MC-NEW-03), Failed = 3
        $this->assertEquals(5, $preview['total_rows']);
        $this->assertEquals(2, $preview['valid_count']);
        $this->assertEquals(3, $preview['failed_count']);

        // Assert database is UNCHANGED after preview
        $this->assertNull(Member::where('membership_number', 'MC-NEW-01')->first());
        $this->assertNull(Member::where('membership_number', 'MC-NEW-03')->first());

        // Execute import on the valid rows
        $batch = $this->memberService->executeImport($preview['valid_rows'], $this->manager->id);

        $this->assertEquals(2, $batch->imported_rows);
        $this->assertNotNull(Member::where('membership_number', 'MC-NEW-01')->first());
        $this->assertNotNull(Member::where('membership_number', 'MC-NEW-03')->first());

        // Ensure wallet accounts were automatically initialized with 0 balance
        $newMember1 = Member::where('membership_number', 'MC-NEW-01')->first();
        $this->assertNotNull($newMember1->wallet);
        $this->assertEquals(0, $newMember1->wallet->balance_paise);
    }

    /**
     * Scenario A04:
     * Register a guest with a club-member sponsor and another with an affiliated-member sponsor.
     * Both valid paths succeed; an invalid sponsor is rejected with a visible reason.
     */
    public function test_a04_guest_registration_with_member_and_affiliate_sponsor(): void
    {
        // 1. Club Member sponsor
        $guest1 = $this->guestService->registerGuest(
            personData: [
                'first_name' => 'Sanjiv',
                'last_name' => 'Kapoor',
                'phone' => '9991112233',
            ],
            sponsorType: Member::class,
            sponsorId: $this->member->id
        );
        $this->assertNotNull($guest1);
        $this->assertEquals(Member::class, $guest1->sponsor_type);
        $this->assertEquals($this->member->id, $guest1->sponsor_id);

        // 2. Affiliated Member sponsor
        $affMember = AffiliatedMember::where('home_membership_number', 'TC-5421')->firstOrFail();
        $guest2 = $this->guestService->registerGuest(
            personData: [
                'first_name' => 'Gaurav',
                'last_name' => 'Menon',
                'phone' => '9994445566',
            ],
            sponsorType: AffiliatedMember::class,
            sponsorId: $affMember->id
        );
        $this->assertNotNull($guest2);
        $this->assertEquals(AffiliatedMember::class, $guest2->sponsor_type);
        $this->assertEquals($affMember->id, $guest2->sponsor_id);

        // 3. Non-existent sponsor ID -> InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->guestService->registerGuest(
            personData: ['first_name' => 'Unknown', 'last_name' => 'Visitor'],
            sponsorType: Member::class,
            sponsorId: 999999
        );
    }

    /**
     * Scenario A05:
     * A guest has an unpaid/failed entry-fee attempt.
     * Bar/card-room pass remains inactive.
     * Exactly one confirmed fee creates exactly one appropriately scoped pass.
     */
    public function test_a05_guest_pass_inactive_while_unpaid_activated_upon_confirmed_checkout(): void
    {
        $guest = $this->guestService->registerGuest(
            personData: [
                'first_name' => 'Vinod',
                'last_name' => 'Khanna',
                'phone' => '9770001122',
            ],
            sponsorType: Member::class,
            sponsorId: $this->member->id
        );

        // Create draft bill for guest entry fee (₹200 + 18% GST = ₹236)
        $tariff = Tariff::where('code', 'GUEST-BAR-ENTRY')->firstOrFail();
        $bill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $guest,
            departmentCode: 'card_room',
            items: [
                [
                    'item_name' => 'Bar & Card Room Guest Entry Pass',
                    'quantity' => 1,
                    'unit_price_paise' => $tariff->rate_paise, // 20000
                    'tax_percent' => $tariff->tax_percent, // 18.00
                ]
            ],
            idempotencyKey: 'guest_bill_unpaid_test',
            userId: $this->cashier->id
        );

        // Before payment: No active passes for guest
        $passesBefore = GuestPass::where('guest_id', $guest->id)->count();
        $this->assertEquals(0, $passesBefore);

        // Complete checkout with Cash payment
        $checkoutResult = $this->billingService->checkout(
            bill: $bill,
            tenders: [
                [
                    'tender_type' => TenderType::CASH,
                    'amount_paise' => 23600, // ₹236
                ]
            ],
            userId: $this->cashier->id
        );

        // After confirmed checkout: Exactly ONE active pass is created
        $passesAfter = GuestPass::where('guest_id', $guest->id)->get();
        $this->assertCount(1, $passesAfter);
        $pass = $passesAfter->first();
        $this->assertEquals(PassStatus::ACTIVE, $pass->status);
        $this->assertEquals('bar_card_room', $pass->facility_code);
        $this->assertEquals($bill->lines->first()->id, $pass->bill_line_id);
    }

    /**
     * Scenario A07:
     * A dependent has no authorised use of the primary member's wallet by default.
     * Identification or sponsorship alone cannot debit the primary member.
     */
    public function test_a07_dependent_cannot_spend_primary_wallet_unless_authorized(): void
    {
        // Rohit Nair is child of Priya Nair (MC-002) with can_spend_wallet = false
        $member2 = Member::where('membership_number', 'MC-002')->firstOrFail();
        $dependentRohit = Dependent::where('member_id', $member2->id)
            ->where('can_spend_wallet', false)
            ->firstOrFail();

        // Give Priya ₹1,000 prepaid balance
        $this->walletService->recharge(
            wallet: $member2->wallet,
            amountPaise: 100000,
            tenderType: TenderType::CASH,
            idempotencyKey: 'priya_recharge_dep_test',
            shiftId: $this->shift->id,
            userId: $this->cashier->id
        );

        $bill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $dependentRohit,
            departmentCode: 'card_room',
            items: [['item_name' => 'Snack', 'quantity' => 1, 'unit_price_paise' => 15000, 'tax_percent' => 0]],
            idempotencyKey: 'rohit_unauthorized_bill',
            userId: $this->cashier->id
        );

        // Dependent attempts to checkout using member's prepaid wallet -> UnauthorizedActionException
        $this->expectException(UnauthorizedActionException::class);
        $this->billingService->checkout(
            bill: $bill,
            tenders: [['tender_type' => TenderType::PREPAID, 'amount_paise' => 15000]],
            userId: $this->cashier->id
        );
    }

    /**
     * Scenario A08:
     * Replace a lost card.
     * The old card is rejected, the new card finds the same member/account,
     * and prior transactions and balances remain intact.
     */
    public function test_a08_replace_lost_card_maintains_balance_and_rejects_old(): void
    {
        // 1. Give member ₹500
        $this->walletService->recharge(
            wallet: $this->wallet,
            amountPaise: 50000,
            tenderType: TenderType::CASH,
            idempotencyKey: 'card_replace_funding',
            shiftId: $this->shift->id,
            userId: $this->cashier->id
        );

        $oldCard = Card::where('card_number', 'CARD-VENU-001')->firstOrFail();
        $this->assertEquals(CardStatus::ACTIVE, $oldCard->status);

        // 2. Replace card
        $newCard = $this->cardService->replaceCard(
            oldCard: $oldCard,
            newCardNumber: 'CARD-VENU-002',
            reason: 'Card lost in parking area'
        );

        $this->assertEquals(CardStatus::ACTIVE, $newCard->status);
        $this->assertEquals(CardStatus::REPLACED, $oldCard->fresh()->status);
        $this->assertEquals($newCard->id, $oldCard->fresh()->replaced_by_card_id);

        // 3. Old card is rejected if validated
        try {
            $this->cardService->validateActiveCard($oldCard->fresh());
            $this->fail("Expected CardNotActiveException when validating replaced card");
        } catch (CardNotActiveException $e) {
            $this->assertStringContainsString('cannot be used', $e->getMessage());
        }

        // 4. New card points to the exact same member and balance is preserved
        $owner = $this->cardService->validateActiveCard($newCard);
        $this->assertInstanceOf(Member::class, $owner);
        $this->assertEquals($this->member->id, $owner->id);
        $this->assertEquals(50000, $owner->wallet->fresh()->balance_paise);
    }

    /**
     * Scenario A10:
     * Cashier tries to grant required guest entry using a zero/100%-discount fee.
     * Paid-entry policy denies it; no access pass is silently created.
     */
    public function test_a10_zero_price_complimentary_guest_entry_denied(): void
    {
        $guest = $this->guestService->registerGuest(
            personData: ['first_name' => 'Free', 'last_name' => 'Guest'],
            sponsorType: Member::class,
            sponsorId: $this->member->id
        );

        // Directly trying to activate a zero-priced pass throws UnauthorizedActionException
        $this->expectException(UnauthorizedActionException::class);
        $this->guestService->activateGuestPass(
            guest: $guest,
            facilityCode: 'bar_card_room',
            billLineId: 9999,
            paidAmountPaise: 0 // 0 paise forbidden!
        );
    }
}
