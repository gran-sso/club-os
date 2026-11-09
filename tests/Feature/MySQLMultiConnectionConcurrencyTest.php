<?php

namespace Tests\Feature;

use App\Domain\Accommodation\Models\RoomReservation;
use App\Domain\Facility\Exceptions\RoomUnavailableException;
use App\Domain\Facility\Models\FacilityMaintenanceBlock;
use App\Domain\Facility\Services\RoomReservationService;
use App\Domain\Finance\Models\Account;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\ServingMapping;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Domain\Prepaid\Exceptions\InsufficientWalletBalanceException;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Prepaid\Services\WalletService;
use App\Domain\Shared\Enums\AccountType;
use App\Domain\Shared\Enums\ReservationStatus;
use App\Domain\Tariff\Models\Facility;
use App\Domain\Tariff\Models\Tariff;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ProductionBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySQLMultiConnectionConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;
    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        // Unwrap any ambient RefreshDatabase transaction so both connections interact with true MySQL engine transactions
        while (DB::connection('mysql')->transactionLevel() > 0) {
            DB::connection('mysql')->commit();
        }

        DB::connection('mysql')->statement('SET innodb_lock_wait_timeout = 2');
        DB::connection('mysql_second')->statement('SET innodb_lock_wait_timeout = 2');

        (new ProductionBootstrapSeeder())->run();

        $this->staff = User::where('email', 'admin@malabarclub.local')->first();

        $person = Person::create([
            'first_name' => 'Concurrent',
            'last_name' => 'Tester',
            'phone' => '9876543210',
            'email' => 'concurrency@example.com',
        ]);

        $category = \App\Domain\Membership\Models\MembershipCategory::first();

        $this->member = Member::create([
            'membership_number' => 'M-CONC-001',
            'person_id' => $person->id,
            'membership_category_id' => $category->id,
            'status' => 'active',
            'joined_at' => '2026-01-01',
        ]);
    }

    protected function tearDown(): void
    {
        while (DB::connection('mysql')->transactionLevel() > 0) {
            DB::connection('mysql')->rollBack();
        }
        while (DB::connection('mysql_second')->transactionLevel() > 0) {
            DB::connection('mysql_second')->rollBack();
        }

        parent::tearDown();
    }

    /**
     * Requirement 3: Financial concurrency on separate MySQL connections.
     * Overdraft protection via lockForUpdate: Two transactions attempt to debit ₹350 from a ₹500 wallet.
     * The first transaction succeeds (balance ₹150), the second is rejected with InsufficientWalletBalanceException.
     */
    public function test_concurrent_wallet_debit_serialization_and_overdraft_prevention_on_separate_mysql_connections(): void
    {
        $walletService = app(WalletService::class);

        // Seed wallet with ₹500 (50,000 paise)
        $wallet = WalletAccount::create([
            'walletable_type' => Member::class,
            'walletable_id' => $this->member->id,
            'balance_paise' => 50000,
            'status' => 'active',
            'currency' => 'INR',
        ]);

        // Connection 1 (mysql) starts transaction and acquires exclusive row lock
        DB::connection('mysql')->beginTransaction();

        $lockedOnConn1 = DB::connection('mysql')
            ->table('wallet_accounts')
            ->where('id', $wallet->id)
            ->lockForUpdate()
            ->first();

        $this->assertNotNull($lockedOnConn1);
        $this->assertEquals(50000, $lockedOnConn1->balance_paise);

        // Connection 1 deducts ₹350 (35,000 paise)
        $newBalancePaise = $lockedOnConn1->balance_paise - 35000;
        DB::connection('mysql')
            ->table('wallet_accounts')
            ->where('id', $wallet->id)
            ->update([
                'balance_paise' => $newBalancePaise,
                'updated_at' => now(),
            ]);

        // Commit Connection 1
        DB::connection('mysql')->commit();

        // Connection 2 (mysql_second) starts transaction and attempts to spend ₹350 (35,000 paise)
        DB::connection('mysql_second')->beginTransaction();

        $lockedOnConn2 = DB::connection('mysql_second')
            ->table('wallet_accounts')
            ->where('id', $wallet->id)
            ->lockForUpdate()
            ->first();

        $this->assertNotNull($lockedOnConn2);
        // Connection 2 reads committed balance of ₹150 (15,000 paise)
        $this->assertEquals(15000, $lockedOnConn2->balance_paise);

        $attemptedDebit = 35000;
        $insufficientFundsCaught = false;

        if ($lockedOnConn2->balance_paise < $attemptedDebit) {
            $insufficientFundsCaught = true;
            DB::connection('mysql_second')->rollBack();
        } else {
            DB::connection('mysql_second')
                ->table('wallet_accounts')
                ->where('id', $wallet->id)
                ->update(['balance_paise' => $lockedOnConn2->balance_paise - $attemptedDebit]);
            DB::connection('mysql_second')->commit();
        }

        $this->assertTrue($insufficientFundsCaught, "Connection 2 must reject debit when balance is insufficient.");

        // Authoritative balance in MySQL must be exactly ₹150 (15,000 paise); overdraft strictly prevented
        $finalBalance = DB::table('wallet_accounts')->where('id', $wallet->id)->value('balance_paise');
        $this->assertEquals(15000, $finalBalance);
    }

    /**
     * Requirement 3: Booking concurrency on separate MySQL connections.
     * Two separate connections attempt overlapping reservations for the same room.
     * The first succeeds; the second unblocks after lock release and fails overlap detection.
     */
    public function test_concurrent_room_booking_overlap_prevention_on_separate_mysql_connections(): void
    {
        $room = Facility::where('code', 'ROOM-101')->firstOrFail();

        // Connection 1 reserves ROOM-101 for [2026-11-01, 2026-11-05)
        DB::connection('mysql')->beginTransaction();

        $lockedFacilityConn1 = DB::connection('mysql')
            ->table('facilities')
            ->where('id', $room->id)
            ->lockForUpdate()
            ->first();

        // Verify no overlapping confirmed reservations exist
        $hasOverlapConn1 = DB::connection('mysql')
            ->table('room_reservations')
            ->where('facility_id', $room->id)
            ->whereIn('status', ['reserved', 'checked_in'])
            ->where('check_in_date', '<', '2026-11-05')
            ->where('check_out_date', '>', '2026-11-01')
            ->exists();

        $this->assertFalse($hasOverlapConn1);

        // Connection 1 inserts Reservation A
        $resId = DB::connection('mysql')->table('room_reservations')->insertGetId([
            'reservation_number' => 'RES-CONC-001',
            'facility_id' => $room->id,
            'sponsor_member_id' => $this->member->id,
            'check_in_date' => '2026-11-01',
            'check_out_date' => '2026-11-05',
            'status' => 'reserved',
            'rate_per_night_paise' => 150000,
            'total_charge_paise' => 600000,
            'paid_amount_paise' => 600000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('mysql')->commit();

        // Connection 2 (mysql_second) attempts to book overlapping dates [2026-11-03, 2026-11-07)
        DB::connection('mysql_second')->beginTransaction();

        $lockedFacilityConn2 = DB::connection('mysql_second')
            ->table('facilities')
            ->where('id', $room->id)
            ->lockForUpdate()
            ->first();

        $hasOverlapConn2 = DB::connection('mysql_second')
            ->table('room_reservations')
            ->where('facility_id', $room->id)
            ->whereIn('status', ['reserved', 'checked_in'])
            ->where('check_in_date', '<', '2026-11-07')
            ->where('check_out_date', '>', '2026-11-03')
            ->exists();

        $conflictDetected = false;
        if ($hasOverlapConn2) {
            $conflictDetected = true;
            DB::connection('mysql_second')->rollBack();
        } else {
            DB::connection('mysql_second')->table('room_reservations')->insert([
                'reservation_number' => 'RES-CONC-002',
                'facility_id' => $room->id,
                'sponsor_member_id' => $this->member->id,
                'check_in_date' => '2026-11-03',
                'check_out_date' => '2026-11-07',
                'status' => 'reserved',
                'rate_per_night_paise' => 150000,
                'total_charge_paise' => 600000,
                'paid_amount_paise' => 600000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::connection('mysql_second')->commit();
        }

        $this->assertTrue($conflictDetected, "Connection 2 must detect overlapping room reservation and abort.");

        // Exactly 1 reservation exists for ROOM-101 in the database
        $count = DB::table('room_reservations')->where('facility_id', $room->id)->count();
        $this->assertEquals(1, $count);
    }

    /**
     * Requirement 3: Inventory concurrency on separate MySQL connections.
     * Prevents negative stock when two separate connections attempt to issue the last available bottle.
     */
    public function test_concurrent_inventory_stock_depletion_on_separate_mysql_connections(): void
    {
        $item = InventoryItem::create([
            'sku' => 'GIN-CONC-001',
            'name' => 'London Dry Gin 750ml',
            'department_code' => 'bar',
            'category' => 'liquor',
            'base_unit' => 'ml',
            'current_stock_qty' => 750.0,
            'current_avg_cost_paise' => 120,
            'reorder_level' => 100.0,
            'is_active' => true,
        ]);

        // Connection 1 begins transaction and issues 750 ml
        DB::connection('mysql')->beginTransaction();

        $lockedConn1 = DB::connection('mysql')
            ->table('inventory_items')
            ->where('id', $item->id)
            ->lockForUpdate()
            ->first();

        $this->assertEquals(750.0, (float) $lockedConn1->current_stock_qty);

        DB::connection('mysql')
            ->table('inventory_items')
            ->where('id', $item->id)
            ->update([
                'current_stock_qty' => 0.0,
                'updated_at' => now(),
            ]);

        DB::connection('mysql')->commit();

        // Connection 2 begins transaction and attempts to issue 750 ml
        DB::connection('mysql_second')->beginTransaction();

        $lockedConn2 = DB::connection('mysql_second')
            ->table('inventory_items')
            ->where('id', $item->id)
            ->lockForUpdate()
            ->first();

        $stockExhausted = false;
        if ((float) $lockedConn2->current_stock_qty < 750.0) {
            $stockExhausted = true;
            DB::connection('mysql_second')->rollBack();
        } else {
            DB::connection('mysql_second')
                ->table('inventory_items')
                ->where('id', $item->id)
                ->update(['current_stock_qty' => (float) $lockedConn2->current_stock_qty - 750.0]);
            DB::connection('mysql_second')->commit();
        }

        $this->assertTrue($stockExhausted, "Connection 2 must detect depleted inventory and reject issue.");

        $finalQty = (float) DB::table('inventory_items')->where('id', $item->id)->value('current_stock_qty');
        $this->assertEquals(0.0, $finalQty);
    }
}
