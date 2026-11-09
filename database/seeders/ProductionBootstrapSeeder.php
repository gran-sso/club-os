<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\Counter;
use App\Domain\Finance\Models\Account;
use App\Domain\Membership\Models\AffiliatedClub;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Shared\Enums\AccountType;
use App\Domain\Tariff\Models\Department;
use App\Domain\Tariff\Models\Facility;
use App\Domain\Tariff\Models\Tariff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ProductionBootstrapSeeder extends Seeder
{
    /**
     * Seeds essential master infrastructure, charts of accounts, roles, and initial admin.
     * Safe for production execution. Does NOT seed synthetic members, mock cards, or test transactions.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Core Permissions
        $permissions = [
            'tariffs.manage',
            'refunds.approve',
            'members.manage',
            'guests.register',
            'counter.operate',
            'wallet.recharge',
            'sales.checkout',
            'reports.view',
            'integrations.manage',
            'stock.manage',
            'contractor.settle',
            'rooms.view_rack',
            'rooms.manage_reservations',
            'courts.view_schedule',
            'courts.manage_bookings',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // 2. Roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions(Permission::all());

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->syncPermissions([
            'tariffs.manage',
            'refunds.approve',
            'members.manage',
            'guests.register',
            'counter.operate',
            'wallet.recharge',
            'sales.checkout',
            'reports.view',
            'stock.manage',
            'contractor.settle',
            'rooms.view_rack',
            'rooms.manage_reservations',
            'courts.view_schedule',
            'courts.manage_bookings',
        ]);

        $cashierRole = Role::firstOrCreate(['name' => 'cashier']);
        $cashierRole->syncPermissions([
            'counter.operate',
            'wallet.recharge',
            'sales.checkout',
            'guests.register',
            'rooms.view_rack',
            'rooms.manage_reservations',
            'courts.view_schedule',
            'courts.manage_bookings',
        ]);

        $receptionRole = Role::firstOrCreate(['name' => 'reception']);
        $receptionRole->syncPermissions([
            'members.manage',
            'guests.register',
            'counter.operate',
            'wallet.recharge',
            'sales.checkout',
            'rooms.view_rack',
            'rooms.manage_reservations',
            'courts.view_schedule',
            'courts.manage_bookings',
        ]);

        $accountantRole = Role::firstOrCreate(['name' => 'accountant']);
        $accountantRole->syncPermissions([
            'reports.view',
            'integrations.manage',
            'contractor.settle',
        ]);

        Role::firstOrCreate(['name' => 'auditor'])->syncPermissions(['reports.view']);

        // 3. Departments
        $depts = [
            ['code' => 'admin', 'name' => 'Administration'],
            ['code' => 'membership', 'name' => 'Membership & Front Desk'],
            ['code' => 'bar', 'name' => 'Bar & Lounge'],
            ['code' => 'card_room', 'name' => 'Card Room'],
            ['code' => 'food', 'name' => 'Food & Restaurant'],
            ['code' => 'accommodation', 'name' => 'Rooms & Accommodation'],
            ['code' => 'sports', 'name' => 'Gym & Badminton'],
        ];
        foreach ($depts as $dept) {
            Department::firstOrCreate(['code' => $dept['code']], $dept);
        }

        // 4. Counters
        $counters = [
            ['code' => 'COUNTER-1', 'name' => 'Main Reception Counter', 'department_id' => Department::where('code', 'membership')->value('id')],
            ['code' => 'COUNTER-2', 'name' => 'Bar POS Counter', 'department_id' => Department::where('code', 'bar')->value('id')],
            ['code' => 'COUNTER-3', 'name' => 'Restaurant & Sports Counter', 'department_id' => Department::where('code', 'food')->value('id')],
        ];
        foreach ($counters as $cnt) {
            Counter::firstOrCreate(['code' => $cnt['code']], $cnt);
        }

        // 5. Facilities
        $facilities = [
            ['code' => 'ROOM-101', 'name' => 'Deluxe Room 101', 'type' => 'room', 'capacity' => 2],
            ['code' => 'ROOM-102', 'name' => 'Deluxe Room 102', 'type' => 'room', 'capacity' => 2],
            ['code' => 'ROOM-103', 'name' => 'Deluxe Room 103', 'type' => 'room', 'capacity' => 2],
            ['code' => 'ROOM-104', 'name' => 'Suite Room 104', 'type' => 'room', 'capacity' => 4],
            ['code' => 'ROOM-105', 'name' => 'Standard Room 105', 'type' => 'room', 'capacity' => 2],
            ['code' => 'COURT-1', 'name' => 'Indoor Wooden Badminton Court', 'type' => 'court', 'capacity' => 4],
        ];
        foreach ($facilities as $fac) {
            Facility::firstOrCreate(['code' => $fac['code']], $fac);
        }

        // 5b. Club Lockers
        $lockers = [
            ['locker_number' => 'LK-101', 'zone' => 'Badminton Hall', 'deposit_amount_paise' => 100000, 'key_number' => 'KEY-LK-101'],
            ['locker_number' => 'LK-102', 'zone' => 'Badminton Hall', 'deposit_amount_paise' => 100000, 'key_number' => 'KEY-LK-102'],
            ['locker_number' => 'LK-103', 'zone' => 'Badminton Hall', 'deposit_amount_paise' => 100000, 'key_number' => 'KEY-LK-103'],
            ['locker_number' => 'LK-201', 'zone' => "Gentlemen's Locker Room", 'deposit_amount_paise' => 100000, 'key_number' => 'KEY-LK-201'],
            ['locker_number' => 'LK-202', 'zone' => "Gentlemen's Locker Room", 'deposit_amount_paise' => 100000, 'key_number' => 'KEY-LK-202'],
            ['locker_number' => 'LK-301', 'zone' => "Ladies' Locker Room", 'deposit_amount_paise' => 100000, 'key_number' => 'KEY-LK-301'],
        ];
        foreach ($lockers as $lck) {
            \App\Domain\Facility\Models\Locker::firstOrCreate(['locker_number' => $lck['locker_number']], $lck);
        }

        // 6. Tariffs
        $tariffs = [
            [
                'code' => 'GUEST-BAR-ENTRY',
                'department_code' => 'card_room',
                'name' => 'Bar & Card Room Guest Entry Pass',
                'rate_paise' => 20000,
                'tax_percent' => 18.00,
                'effective_from' => '2026-01-01',
            ],
            [
                'code' => 'GUEST-SPORTS-DAY',
                'department_code' => 'sports',
                'name' => 'Sports & Badminton Daily Guest Pass',
                'rate_paise' => 30000,
                'tax_percent' => 18.00,
                'effective_from' => '2026-01-01',
            ],
            [
                'code' => 'GYM-MONTHLY',
                'department_code' => 'sports',
                'name' => 'Gym Monthly Subscription',
                'rate_paise' => 150000,
                'tax_percent' => 18.00,
                'effective_from' => '2026-01-01',
            ],
            [
                'code' => 'BADMINTON-MONTHLY',
                'department_code' => 'sports',
                'name' => 'Badminton Court Monthly Subscription',
                'rate_paise' => 200000,
                'tax_percent' => 18.00,
                'effective_from' => '2026-01-01',
            ],
        ];
        foreach ($tariffs as $t) {
            Tariff::firstOrCreate(['code' => $t['code']], $t);
        }

        // 7. General Ledger Chart of Accounts
        $accounts = [
            ['code' => '1010', 'name' => 'Cash in Hand (Counter Tills)', 'type' => AccountType::ASSET, 'description' => 'Physical cash float and collected cash'],
            ['code' => '1020', 'name' => 'Bank Clearing & Digital Settlements', 'type' => AccountType::ASSET, 'description' => 'UPI, card terminals, and gateway clearing'],
            ['code' => '1030', 'name' => 'Inventory Stock', 'type' => AccountType::ASSET, 'description' => 'Beverage and consumable stores'],
            ['code' => '2010', 'name' => 'Member Prepaid Advances Liability', 'type' => AccountType::LIABILITY, 'description' => 'Unspent member wallet balances'],
            ['code' => '2020', 'name' => 'Food Contractor Payable', 'type' => AccountType::LIABILITY, 'description' => 'Next-day food contractor remittances'],
            ['code' => '2030', 'name' => 'GST & Taxes Payable', 'type' => AccountType::LIABILITY, 'description' => 'Collected tax liability for statutory remittance'],
            ['code' => '3010', 'name' => 'Club Capital & General Reserve', 'type' => AccountType::EQUITY, 'description' => 'Corpus and general reserve'],
            ['code' => '4010', 'name' => 'Guest Entry & Pass Revenue', 'type' => AccountType::REVENUE, 'description' => 'Bar, card room and day entry fees'],
            ['code' => '4020', 'name' => 'Bar & Beverage Sales Revenue', 'type' => AccountType::REVENUE, 'description' => 'Direct bar liquor and beverage sales'],
            ['code' => '4030', 'name' => 'Room Accommodation Revenue', 'type' => AccountType::REVENUE, 'description' => 'Guest room night charges'],
            ['code' => '4040', 'name' => 'Sports & Facility Subscriptions', 'type' => AccountType::REVENUE, 'description' => 'Gym and badminton plan fees'],
            ['code' => '5010', 'name' => 'Cost of Goods Sold - Liquor', 'type' => AccountType::EXPENSE, 'description' => 'Weighted-average consumption cost'],
            ['code' => '5020', 'name' => 'Contractor Food Expense', 'type' => AccountType::EXPENSE, 'description' => 'Settled food contractor obligations'],
            ['code' => '5030', 'name' => 'Operating & Administrative Expenses', 'type' => AccountType::EXPENSE, 'description' => 'General clubhouse operating costs'],
        ];
        foreach ($accounts as $acc) {
            Account::firstOrCreate(['code' => $acc['code']], $acc);
        }

        // 8. Membership Categories
        MembershipCategory::firstOrCreate(
            ['code' => 'ORD'],
            ['name' => 'Ordinary Member', 'annual_fee_paise' => 1200000, 'has_voting_rights' => true, 'is_active' => true]
        );
        MembershipCategory::firstOrCreate(
            ['code' => 'LIFE'],
            ['name' => 'Life Member', 'annual_fee_paise' => 0, 'has_voting_rights' => true, 'is_active' => true]
        );
        MembershipCategory::firstOrCreate(
            ['code' => 'CORP'],
            ['name' => 'Corporate Member', 'annual_fee_paise' => 2500000, 'has_voting_rights' => true, 'is_active' => true]
        );

        // 9. Club Bank Accounts
        $bankGl = Account::where('code', '1020')->first();

        \App\Domain\Finance\Models\BankAccount::firstOrCreate(
            ['account_number' => '40912345678'],
            [
                'account_name' => 'SBI Main Operating Current Account',
                'bank_name' => 'State Bank of India',
                'branch_name' => 'Vadakkencherry Branch (001234)',
                'account_type' => 'current',
                'ifsc_code' => 'SBIN0001234',
                'upi_id' => 'malabarclub@sbi',
                'gl_account_id' => $bankGl?->id,
                'opening_balance_paise' => 50000000,
                'current_balance_paise' => 50000000,
                'is_primary' => true,
                'is_active' => true,
                'notes' => 'Primary account for daily counter cash deposits and statutory vendor disbursements.',
            ]
        );

        \App\Domain\Finance\Models\BankAccount::firstOrCreate(
            ['account_number' => '10482093847'],
            [
                'account_name' => 'Federal Bank Clubhouse Reserve Account',
                'bank_name' => 'Federal Bank',
                'branch_name' => 'Alathur Branch',
                'account_type' => 'savings',
                'ifsc_code' => 'FDRL0001048',
                'upi_id' => 'malabarclub@federal',
                'gl_account_id' => $bankGl?->id,
                'opening_balance_paise' => 25000000,
                'current_balance_paise' => 25000000,
                'is_primary' => false,
                'is_active' => true,
                'notes' => 'Secondary account for member lifetime fees and infrastructure capital fund.',
            ]
        );

        // 10. Core Wholesale Suppliers & Distributors
        \App\Domain\Inventory\Models\Supplier::firstOrCreate(
            ['code' => 'KSBC-FL4'],
            [
                'name' => 'Kerala State Beverages Corporation (KSBC Wholesale Depot)',
                'contact_person' => 'Depot Manager / FL-4 Officer',
                'phone' => '+91 491 2501234',
                'email' => 'ksbc.palakkad@kerala.gov.in',
                'gstin' => '32AAACK1234F1Z1',
                'address' => 'KSBC Warehouse Depot, Industrial Estate, Palakkad - 678001',
                'is_active' => true,
            ]
        );

        \App\Domain\Inventory\Models\Supplier::firstOrCreate(
            ['code' => 'MALABAR-BEV'],
            [
                'name' => 'Malabar Beverage & FMCG Distributors',
                'contact_person' => 'Suresh Kumar',
                'phone' => '+91 98470 55667',
                'email' => 'orders@malabarbev.com',
                'gstin' => '32AABCM5678G1Z2',
                'address' => 'Main Road, Vadakkencherry, Palakkad - 678683',
                'is_active' => true,
            ]
        );

        \App\Domain\Inventory\Models\Supplier::firstOrCreate(
            ['code' => 'APEX-HOSP'],
            [
                'name' => 'Apex Club & Hospitality Supplies',
                'contact_person' => 'Naveen George',
                'phone' => '+91 94471 22334',
                'email' => 'sales@apexhospitality.in',
                'gstin' => '32AAACA9988H1Z9',
                'address' => 'Market Road, Alathur, Palakkad - 678541',
                'is_active' => true,
            ]
        );

        // 11. Initial Production Administrator (Provisioned securely)
        // If an administrator already exists, reseeding does NOT modify or reset credentials
        if (User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->count() === 0) {
            $adminEmail = env('INITIAL_ADMIN_EMAIL', 'admin@malabarclub.local');
            $adminPassword = env('INITIAL_ADMIN_PASSWORD');

            if (empty($adminPassword)) {
                if (!app()->environment('production')) {
                    $adminPassword = 'Password@123';
                } else {
                    // Generate a cryptographically random 24-character password — NEVER a shared default in production
                    $adminPassword = Str::password(24, true, true);
                    $this->command?->warn("=================================================================");
                    $this->command?->warn("INITIAL PRODUCTION ADMINISTRATOR CREATED");
                    $this->command?->warn("Email:    {$adminEmail}");
                    $this->command?->warn("Password: {$adminPassword}");
                    $this->command?->warn("Please record this password securely. It will NOT be shown again.");
                    $this->command?->warn("=================================================================");
                }
            }

            $adminUser = User::firstOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => 'System Administrator',
                    'password' => Hash::make($adminPassword),
                ]
            );
            $adminUser->syncRoles([$adminRole]);
        }
    }
}
