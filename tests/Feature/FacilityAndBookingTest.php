<?php

namespace Tests\Feature;

use App\Domain\Access\Models\Guest;
use App\Domain\Facility\Exceptions\FacilityNotReadyException;
use App\Domain\Facility\Exceptions\PaymentRequiredException;
use App\Domain\Facility\Exceptions\RoomUnavailableException;
use App\Domain\Facility\Exceptions\SlotUnavailableException;
use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Facility\Services\CourtBookingService;
use App\Domain\Facility\Services\RoomReservationService;
use App\Domain\Facility\Services\SubscriptionService;
use App\Domain\Membership\Models\Member;
use App\Domain\Prepaid\Services\WalletService;
use App\Domain\Shared\Enums\MemberStatus;
use App\Domain\Tariff\Models\Facility;
use App\Domain\Tariff\Models\Tariff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityAndBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * B01: Two requests confirm overlapping dates for one room. Only one confirms.
     * Non-overlapping checkout/check-in boundaries follow the configured half-open interval rule.
     */
    public function test_b01_room_reservation_half_open_interval_and_overlap_protection(): void
    {
        $service = app(RoomReservationService::class);
        $room = Facility::where('code', 'ROOM-101')->firstOrFail();
        $member = Member::firstOrFail();

        // 1. Booking A: Nov 1 to Nov 3 (2 nights: Nov 1, Nov 2)
        $resA = $service->createReservation([
            'facility_id' => $room->id,
            'check_in_date' => '2026-11-01',
            'check_out_date' => '2026-11-03',
            'rate_per_night_paise' => 150000,
            'payer_type' => 'Member',
            'payer_id' => $member->id,
        ]);

        $this->assertEquals(300000, $resA->total_charge_paise);

        // 2. Booking B: Nov 3 to Nov 5 (Starts on the day Booking A checks out)
        // Under half-open interval [check_in, check_out), this MUST succeed without conflict.
        $resB = $service->createReservation([
            'facility_id' => $room->id,
            'check_in_date' => '2026-11-03',
            'check_out_date' => '2026-11-05',
            'rate_per_night_paise' => 150000,
            'payer_type' => 'Member',
            'payer_id' => $member->id,
        ]);

        $this->assertNotNull($resB->id);
        $this->assertEquals('2026-11-03', $resB->check_in_date->toDateString());

        // 3. Overlapping booking: Nov 2 to Nov 4 (Collides with both A and B) -> Must fail
        $this->expectException(RoomUnavailableException::class);
        $service->createReservation([
            'facility_id' => $room->id,
            'check_in_date' => '2026-11-02',
            'check_out_date' => '2026-11-04',
            'rate_per_night_paise' => 150000,
        ]);
    }

    /**
     * B02: A reservation has only a partial deposit.
     * It may reserve availability, but check-in/extension is denied until accommodation charge is covered.
     */
    public function test_b02_partial_deposit_reserves_availability_but_checkin_denied_until_fully_paid(): void
    {
        $service = app(RoomReservationService::class);
        $room = Facility::where('code', 'ROOM-102')->firstOrFail();
        $member = Member::firstOrFail();

        // Total 2 nights = ₹3,000 (300,000 paise). Partial deposit ₹1,000 (100,000 paise).
        $res = $service->createReservation([
            'facility_id' => $room->id,
            'check_in_date' => '2026-11-10',
            'check_out_date' => '2026-11-12',
            'rate_per_night_paise' => 150000,
            'paid_amount_paise' => 100000,
            'payer_type' => 'Member',
            'payer_id' => $member->id,
        ]);

        $this->assertEquals(300000, $res->total_charge_paise);
        $this->assertEquals(100000, $res->paid_amount_paise);
        $this->assertFalse($res->isFullyPaid());

        // Check-in must be denied under no-credit invariant
        try {
            $service->checkIn($res);
            $this->fail("Expected PaymentRequiredException was not thrown.");
        } catch (PaymentRequiredException $e) {
            $this->assertStringContainsString("requires full payment", $e->getMessage());
        }

        // Pay remaining 200,000 paise
        $res = $service->recordPayment($res, 200000);
        $this->assertTrue($res->isFullyPaid());

        // Check-in now succeeds
        $checkedIn = $service->checkIn($res);
        $this->assertEquals('checked_in', $checkedIn->status);
        $this->assertNotNull($checkedIn->checked_in_at);
    }

    /**
     * B03: Guest, sponsor and payer are different people.
     * The correct reservation, payment allocation, guest record and receipt remain linked without charging sponsor.
     */
    public function test_b03_guest_sponsor_and_payer_are_distinct_without_charging_sponsor(): void
    {
        $service = app(RoomReservationService::class);
        $room = Facility::where('code', 'ROOM-103')->firstOrFail();

        $sponsor = Member::where('membership_number', 'MC-001')->firstOrFail();
        $payer = Member::where('membership_number', 'MC-002')->firstOrFail();

        $guestService = app(\App\Domain\Access\Services\GuestService::class);
        $guest = $guestService->registerGuest(
            ['first_name' => 'Dr. Radhakrishnan', 'phone' => '9847012345'],
            Member::class,
            $sponsor->id,
            '2026-12-01'
        );

        // Sponsor initial wallet balance
        $sponsorInitialBalance = $sponsor->wallet->balance_paise;

        $res = $service->createReservation([
            'facility_id' => $room->id,
            'check_in_date' => '2026-12-01',
            'check_out_date' => '2026-12-03',
            'rate_per_night_paise' => 200000,
            'guest_id' => $guest->id,
            'sponsor_member_id' => $sponsor->id,
            'payer_type' => 'Member',
            'payer_id' => $payer->id,
            'paid_amount_paise' => 400000,
        ]);

        $this->assertEquals($guest->id, $res->guest_id);
        $this->assertEquals($sponsor->id, $res->sponsor_member_id);
        $this->assertEquals('Member', $res->payer_type);
        $this->assertEquals($payer->id, $res->payer_id);

        // Sponsor's balance must be completely untouched
        $sponsor->wallet->refresh();
        $this->assertEquals($sponsorInitialBalance, $sponsor->wallet->available_balance_paise);
    }

    /**
     * B04: Room is vacant but dirty or blocked for maintenance.
     * It is not presented as check-in ready; maintenance blocks also prevent overlapping bookings.
     */
    public function test_b04_dirty_room_and_maintenance_blocks_prevent_checkin_and_booking(): void
    {
        $service = app(RoomReservationService::class);
        $room = Facility::where('code', 'ROOM-104')->firstOrFail();

        // 1. Dirty room check-in prevention
        $res = $service->createReservation([
            'facility_id' => $room->id,
            'check_in_date' => '2026-11-20',
            'check_out_date' => '2026-11-22',
            'rate_per_night_paise' => 200000,
            'paid_amount_paise' => 400000,
            'housekeeping_status' => 'dirty',
        ]);

        try {
            $service->checkIn($res);
            $this->fail("Expected FacilityNotReadyException for dirty room.");
        } catch (FacilityNotReadyException $e) {
            $this->assertStringContainsString("not clean", $e->getMessage());
        }

        // Mark clean
        $service->setHousekeepingStatus($res, 'clean');

        // 2. Maintenance block preventing new booking
        $service->createMaintenanceBlock($room->id, '2026-12-10', '2026-12-15', 'Annual AC servicing');

        $this->expectException(RoomUnavailableException::class);
        $service->createReservation([
            'facility_id' => $room->id,
            'check_in_date' => '2026-12-12',
            'check_out_date' => '2026-12-14',
            'rate_per_night_paise' => 200000,
        ]);
    }

    /**
     * B05: Two simultaneous bookings request the same badminton slot, including one plan-covered booking.
     * Only one confirms; a valid plan does not bypass capacity.
     */
    public function test_b05_court_booking_concurrency_and_plan_capacity_protection(): void
    {
        $bookingService = app(CourtBookingService::class);
        $subService = app(SubscriptionService::class);

        $court = Facility::where('code', 'COURT-1')->firstOrFail();
        $member1 = Member::where('membership_number', 'MC-001')->firstOrFail();
        $member2 = Member::where('membership_number', 'MC-002')->firstOrFail();
        $tariff = Tariff::where('code', 'BADMINTON-MONTHLY')->firstOrFail();

        // Give Member 1 an active badminton subscription
        $subService->createSubscription($member1, 'badminton', 'monthly', $tariff, null, '2026-11-01');

        // Booking 1 by Member 1 (Plan-covered) -> Confirms
        $booking1 = $bookingService->bookCourt($court, $member1, '2026-11-05', '06:00:00', '07:00:00', allowPast: true);
        $this->assertTrue($booking1->is_plan_covered);
        $this->assertEquals('confirmed', $booking1->status);

        // Booking 2 by Member 2 for the exact same slot -> Must be denied with SlotUnavailableException
        $this->expectException(SlotUnavailableException::class);
        $bookingService->bookCourt($court, $member2, '2026-11-05', '06:00:00', '07:00:00', allowPast: true);
    }

    /**
     * B06: Monthly plan begins on a month end or annual plan crosses leap day.
     * Validity and renewal boundaries follow the documented calendar rule consistently in booking and access.
     */
    public function test_b06_subscription_calendar_boundaries_month_end_and_leap_year(): void
    {
        $subService = app(SubscriptionService::class);
        $member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $tariffMonthly = Tariff::where('code', 'BADMINTON-MONTHLY')->firstOrFail();
        $tariffGym = Tariff::where('code', 'GYM-MONTHLY')->firstOrFail();

        // 1. Monthly plan starting on Jan 31, 2026 (month-end)
        // Ends on March 1, 2026 exclusive (covers up to Feb 28 inclusive)
        $subMonthEnd = $subService->createSubscription(
            $member,
            'badminton',
            'monthly',
            $tariffMonthly,
            null,
            '2026-01-31'
        );

        $this->assertEquals('2026-01-31', $subMonthEnd->starts_at->toDateString());
        $this->assertEquals('2026-03-01', $subMonthEnd->ends_at->toDateString());

        // Valid on Jan 31
        $this->assertTrue($subMonthEnd->isValidOn(Carbon::parse('2026-01-31')));
        // Valid on Feb 28
        $this->assertTrue($subMonthEnd->isValidOn(Carbon::parse('2026-02-28')));
        // Expired on March 1
        $this->assertFalse($subMonthEnd->isValidOn(Carbon::parse('2026-03-01')));

        // 2. Annual plan starting on Leap Day: Feb 29, 2024
        // Ends on March 1, 2025 exclusive (covers up to Feb 28, 2025 inclusive)
        $subLeap = $subService->createSubscription(
            $member,
            'gym',
            'annual',
            $tariffGym,
            null,
            '2024-02-29'
        );

        $this->assertEquals('2024-02-29', $subLeap->starts_at->toDateString());
        $this->assertEquals('2025-03-01', $subLeap->ends_at->toDateString());

        // Valid on leap day 2024-02-29
        $this->assertTrue($subLeap->isValidOn(Carbon::parse('2024-02-29')));
        // Valid on last day of year: 2025-02-28
        $this->assertTrue($subLeap->isValidOn(Carbon::parse('2025-02-28')));
        // Expired on 2025-03-01
        $this->assertFalse($subLeap->isValidOn(Carbon::parse('2025-03-01')));
    }

    /**
     * B07: Plan-covered member and daily guest use a facility.
     * Charges and entitlements follow their categories; every player is not accidentally charged the full court fee.
     */
    public function test_b07_plan_covered_member_and_daily_guest_charges_follow_categories(): void
    {
        $bookingService = app(CourtBookingService::class);
        $subService = app(SubscriptionService::class);

        $court = Facility::where('code', 'COURT-1')->firstOrFail();
        $member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $tariff = Tariff::where('code', 'BADMINTON-MONTHLY')->firstOrFail();

        // 1. Give member an active badminton subscription
        $subService->createSubscription($member, 'badminton', 'monthly', $tariff, null, '2026-11-01');

        // 2. Member books court: plan covered, fee is 0 paise
        $bookingDate = now()->addDay()->toDateString();
        $booking = $bookingService->bookCourt($court, $member, $bookingDate, '08:00:00', '09:00:00');
        $this->assertTrue($booking->is_plan_covered);
        $this->assertNull($booking->bill_line_id); // No direct court bill line required

        // 3. Daily guest accompanying member only pays daily sports guest pass
        $guestTariff = Tariff::where('code', 'GUEST-SPORTS-DAY')->firstOrFail();
        $this->assertEquals(30000, $guestTariff->rate_paise); // ₹300 guest pass, NOT a duplicate ₹1,500 court rental!
    }

    /**
     * A03: Membership expires while a gym plan remains valid.
     * The shared entitlement policy returns base-membership denial; top-up alone does not renew either entitlement.
     */
    public function test_a03_membership_expiry_denies_gym_plan_access_and_topup_does_not_renew_it(): void
    {
        $subService = app(SubscriptionService::class);
        $walletService = app(WalletService::class);

        $member = Member::where('membership_number', 'MC-003')->firstOrFail();
        $tariffGym = Tariff::where('code', 'GYM-MONTHLY')->firstOrFail();

        // Set base membership to expire on 2026-03-31
        $member->update([
            'status' => MemberStatus::ACTIVE,
            'valid_until' => '2026-03-31',
        ]);

        // Create gym subscription starting March 1, 2026 (valid until April 1, 2026)
        $sub = $subService->createSubscription(
            $member,
            'gym',
            'monthly',
            $tariffGym,
            null,
            '2026-03-01'
        );

        $this->assertTrue($sub->isValidOn(Carbon::parse('2026-03-15')));

        // 1. On March 15, 2026: both base membership and gym plan are active -> Access allowed
        $checkMarch = $subService->checkEntitlement($member, 'gym', Carbon::parse('2026-03-15'));
        $this->assertTrue($checkMarch['allowed']);
        $this->assertTrue($checkMarch['is_plan_covered']);

        // 2. On April 5, 2026: gym plan dates are still active, but base membership has expired!
        // Entitlement must deny access with base_membership_expired
        $checkApril = $subService->checkEntitlement($member, 'gym', Carbon::parse('2026-04-05'));
        $this->assertFalse($checkApril['allowed']);
        $this->assertEquals('base_membership_expired', $checkApril['reason']);

        // 3. Top-up member wallet with ₹1,000
        $staffUser = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $walletService->recharge($member->wallet, 100000, \App\Domain\Shared\Enums\TenderType::CASH, 'TEST-TOPUP-A03', null, null, $staffUser->id);

        // Wallet balance increased
        $member->wallet->refresh();
        $this->assertEquals(100000, $member->wallet->balance_paise);

        // But entitlement check STILL rejects access! Top-up does not renew membership!
        $checkAfterTopup = $subService->checkEntitlement($member, 'gym', Carbon::parse('2026-04-05'));
        $this->assertFalse($checkAfterTopup['allowed']);
        $this->assertEquals('base_membership_expired', $checkAfterTopup['reason']);
    }
}
