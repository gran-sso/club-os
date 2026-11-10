<?php

namespace Database\Seeders;

use App\Domain\Access\Models\Guest;
use App\Domain\Access\Models\GuestPass;
use App\Domain\Contractor\Models\Contractor;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\ServingMapping;
use App\Domain\Membership\Models\AffiliatedClub;
use App\Domain\Membership\Models\AffiliatedMember;
use App\Domain\Membership\Models\Card;
use App\Domain\Membership\Models\Dependent;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Membership\Models\Person;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Shared\Enums\CardStatus;
use App\Domain\Shared\Enums\MemberStatus;
use App\Domain\Shared\Enums\PassStatus;
use App\Domain\Tariff\Models\Tariff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    /**
     * Seeds synthetic fixtures for local development and user acceptance testing only.
     * Enforces strict production safety guard.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException("CRITICAL SAFETY GUARD: DemoDataSeeder contains test users and mock accounts and cannot be run in production!");
        }

        $defaultPassword = Hash::make('Password@123');

        $adminRole = Role::findByName('admin');
        $managerRole = Role::findByName('manager');
        $cashierRole = Role::findByName('cashier');
        $receptionRole = Role::findByName('reception');
        $accountantRole = Role::findByName('accountant');

        // 1. Staff Users for Testing
        $admin = User::firstOrCreate(
            ['email' => 'admin@malabarclub.local'],
            ['name' => 'K. Ramanathan (Admin)', 'password' => $defaultPassword]
        );
        $admin->syncRoles([$adminRole]);

        $manager = User::firstOrCreate(
            ['email' => 'manager@malabarclub.local'],
            ['name' => 'V. Jayaprakash (Manager)', 'password' => $defaultPassword]
        );
        $manager->syncRoles([$managerRole]);

        $cashier1 = User::firstOrCreate(
            ['email' => 'cashier1@malabarclub.local'],
            ['name' => 'Suresh Pillai (Cashier 1)', 'password' => $defaultPassword]
        );
        $cashier1->syncRoles([$cashierRole]);

        $cashier2 = User::firstOrCreate(
            ['email' => 'cashier2@malabarclub.local'],
            ['name' => 'Deepa Nair (Cashier 2)', 'password' => $defaultPassword]
        );
        $cashier2->syncRoles([$cashierRole]);

        $reception = User::firstOrCreate(
            ['email' => 'reception@malabarclub.local'],
            ['name' => 'Anil Kumar (Reception)', 'password' => $defaultPassword]
        );
        $reception->syncRoles([$receptionRole]);

        $accountant = User::firstOrCreate(
            ['email' => 'accountant@malabarclub.local'],
            ['name' => 'M. Unnikrishnan (Accountant)', 'password' => $defaultPassword]
        );
        $accountant->syncRoles([$accountantRole]);

        // 2. Affiliated Clubs & Members
        $trvClub = AffiliatedClub::firstOrCreate(
            ['code' => 'TRV-CLUB'],
            ['name' => 'Trivandrum Club', 'city' => 'Thiruvananthapuram', 'contact_phone' => '0471-2726000', 'is_active' => true]
        );
        $cochinClub = AffiliatedClub::firstOrCreate(
            ['code' => 'COCHIN-CLUB'],
            ['name' => 'Cochin Club', 'city' => 'Kochi', 'contact_phone' => '0484-2215200', 'is_active' => true]
        );

        $affPerson = Person::firstOrCreate(
            ['email' => 'madhavan.nair@example.com'],
            ['first_name' => 'Madhavan', 'last_name' => 'Nair', 'phone' => '9847012345']
        );
        AffiliatedMember::firstOrCreate(
            ['home_membership_number' => 'TC-5421'],
            [
                'affiliated_club_id' => $trvClub->id,
                'person_id' => $affPerson->id,
                'valid_until' => '2027-12-31',
                'is_verified' => true,
            ]
        );

        $ord = MembershipCategory::where('code', 'ORD')->first();
        $life = MembershipCategory::where('code', 'LIFE')->first();
        $corp = MembershipCategory::where('code', 'CORP')->first();

        $isTesting = app()->environment('testing');

        // 3. Synthetic Development Members with Cards and Pre-funded Wallets
        $membersData = [
            ['num' => 'MC-001', 'first' => 'Venu', 'last' => 'Gopal', 'phone' => '9895011111', 'email' => 'venu.gopal@example.com', 'card' => 'CARD-VENU-001', 'cat' => $ord, 'initial_balance_paise' => $isTesting ? 0 : 500000], // ₹5,000.00
            ['num' => 'MC-002', 'first' => 'Priya', 'last' => 'Nair', 'phone' => '9895022222', 'email' => 'priya.nair@example.com', 'card' => 'CARD-PRIYA-002', 'cat' => $life, 'initial_balance_paise' => $isTesting ? 0 : 100000], // ₹1,000.00
            ['num' => 'MC-003', 'first' => 'Rajesh', 'last' => 'Menon', 'phone' => '9895033333', 'email' => 'rajesh.menon@example.com', 'card' => 'CARD-RAJESH-003', 'cat' => $ord, 'initial_balance_paise' => 0],
            ['num' => 'MC-004', 'first' => 'Suresh', 'last' => 'Kumar', 'phone' => '9895044444', 'email' => 'suresh.kumar@example.com', 'card' => 'CARD-SURESH-004', 'cat' => $corp, 'initial_balance_paise' => 0],
            ['num' => 'MC-005', 'first' => 'K.', 'last' => 'Radhakrishnan', 'phone' => '9895055555', 'email' => 'k.radha@example.com', 'card' => 'CARD-RADHA-005', 'cat' => $life, 'initial_balance_paise' => 0],
        ];

        foreach ($membersData as $m) {
            $person = Person::firstOrCreate(
                ['email' => $m['email']],
                ['first_name' => $m['first'], 'last_name' => $m['last'], 'phone' => $m['phone']]
            );

            $member = Member::firstOrCreate(
                ['membership_number' => $m['num']],
                [
                    'person_id' => $person->id,
                    'membership_category_id' => $m['cat']->id,
                    'status' => MemberStatus::ACTIVE,
                    'fee_status' => 'paid',
                    'last_fee_paid_at' => '2024-01-01',
                    'password' => Hash::make('Member@123'),
                    'joined_at' => '2024-01-01',
                    'valid_until' => '2027-12-31',
                ]
            );

            Card::firstOrCreate(
                ['card_number' => $m['card']],
                [
                    'cardable_type' => Member::class,
                    'cardable_id' => $member->id,
                    'status' => CardStatus::ACTIVE,
                    'issued_at' => now(),
                    'notes' => 'Active membership card',
                ]
            );

            $wallet = WalletAccount::firstOrCreate(
                [
                    'walletable_type' => Member::class,
                    'walletable_id' => $member->id,
                ],
                [
                    'balance_paise' => $m['initial_balance_paise'],
                    'reserved_paise' => 0,
                    'currency' => 'INR',
                    'status' => 'active',
                    'version' => 1,
                ]
            );

            // Ensure pre-funded test balance if wallet already existed with 0
            if (!$isTesting && $wallet->balance_paise < $m['initial_balance_paise']) {
                $wallet->update(['balance_paise' => $m['initial_balance_paise']]);
            }
        }

        // Add dependents
        $member2 = Member::where('membership_number', 'MC-002')->first();
        if ($member2) {
            $rohitPerson = Person::firstOrCreate(
                ['phone' => '9895022223'],
                ['first_name' => 'Rohit', 'last_name' => 'Nair']
            );
            Dependent::firstOrCreate(
                ['member_id' => $member2->id, 'person_id' => $rohitPerson->id],
                ['relationship' => 'Child', 'can_spend_wallet' => false]
            );
        }

        $member3 = Member::where('membership_number', 'MC-003')->first();
        if ($member3) {
            $anitaPerson = Person::firstOrCreate(
                ['phone' => '9895033334'],
                ['first_name' => 'Anita', 'last_name' => 'Menon']
            );
            Dependent::firstOrCreate(
                ['member_id' => $member3->id, 'person_id' => $anitaPerson->id],
                ['relationship' => 'Spouse', 'can_spend_wallet' => true]
            );
        }

        // 4. Clearly Labelled Registered Guest with Active Pass
        $venuMember = Member::where('membership_number', 'MC-001')->firstOrFail();
        $guestPerson = Person::firstOrCreate(
            ['phone' => '9847199999'],
            ['first_name' => 'Rahul', 'last_name' => 'Verma', 'email' => 'rahul.verma@example.com']
        );
        $guest = Guest::firstOrCreate(
            [
                'person_id' => $guestPerson->id,
                'sponsor_type' => Member::class,
                'sponsor_id' => $venuMember->id,
            ],
            [
                'visit_date' => now()->toDateString(),
                'status' => 'active',
                'notes' => 'Registered guest sponsored by MC-001 Venu Gopal',
            ]
        );

        GuestPass::firstOrCreate(
            ['pass_code' => 'PASS-GUEST-001'],
            [
                'guest_id' => $guest->id,
                'facility_code' => 'bar_card_room',
                'status' => PassStatus::ACTIVE,
                'valid_from' => now()->startOfDay(),
                'valid_until' => now()->addDays(30)->endOfDay(),
                'entry_count' => 0,
                'max_entries' => 1,
            ]
        );

        // 5. Food Contractor
        Contractor::firstOrCreate(
            ['code' => 'CONT-FOOD-01'],
            [
                'name' => 'Malabar Spices Catering & Hospitality',
                'department_code' => 'food',
                'contact_person' => 'Moideen Kutty',
                'phone' => '9847123456',
                'email' => 'catering@malabarspices.local',
                'bank_name' => 'State Bank of India',
                'bank_account_number' => '12345678901',
                'bank_ifsc' => 'SBIN0001234',
                'commission_rate_percent' => 0.00,
                'is_active' => true,
            ]
        );

        // 6. Food Menu Tariffs
        $foodTariffs = [
            [
                'code' => 'FOOD-MEALS',
                'barcode' => '890123400010',
                'department_code' => 'food',
                'name' => 'Kerala Traditional Sadhya Meals',
                'rate_paise' => 15000, // ₹150.00
                'tax_percent' => 5.00,
                'effective_from' => '2026-01-01',
                'is_active' => true,
            ],
            [
                'code' => 'FOOD-BIRYANI',
                'barcode' => '890123400011',
                'department_code' => 'food',
                'name' => 'Thalassery Chicken Biryani',
                'rate_paise' => 28000, // ₹280.00
                'tax_percent' => 5.00,
                'effective_from' => '2026-01-01',
                'is_active' => true,
            ],
            [
                'code' => 'FOOD-SNACKS',
                'barcode' => '890123400012',
                'department_code' => 'food',
                'name' => 'Evening Tea & Malabar Snacks',
                'rate_paise' => 6000, // ₹60.00
                'tax_percent' => 0.00,
                'effective_from' => '2026-01-01',
                'is_active' => true,
            ],
        ];
        foreach ($foodTariffs as $ft) {
            Tariff::updateOrCreate(['code' => $ft['code']], $ft);
        }

        // 7. Bar Inventory Items with Stock & Selling Tariffs
        $beerItem = InventoryItem::updateOrCreate(
            ['sku' => 'BAR-KF-650'],
            [
                'barcode' => '890123400001',
                'name' => 'Kingfisher Premium Beer 650ml',
                'department_code' => 'bar',
                'category' => 'beverage',
                'base_unit' => 'bottle',
                'current_stock_qty' => 120.00,
                'current_avg_cost_paise' => 14000, // ₹140.00
                'reorder_level' => 24.00,
                'is_active' => true,
            ]
        );

        $whiskyItem = InventoryItem::updateOrCreate(
            ['sku' => 'BAR-SIG-750'],
            [
                'barcode' => '890123400002',
                'name' => 'Signature Premium Whisky 750ml',
                'department_code' => 'bar',
                'category' => 'liquor',
                'base_unit' => 'bottle',
                'current_stock_qty' => 25.00,
                'current_avg_cost_paise' => 90000, // ₹900.00
                'reorder_level' => 5.00,
                'is_active' => true,
            ]
        );

        // Out-of-stock item for negative testing
        $vodkaItem = InventoryItem::updateOrCreate(
            ['sku' => 'BAR-VOD-750'],
            [
                'barcode' => '890123400020',
                'name' => 'Absolut Vodka 750ml (Out of Stock)',
                'department_code' => 'bar',
                'category' => 'liquor',
                'base_unit' => 'bottle',
                'current_stock_qty' => 0.00, // Zero stock
                'current_avg_cost_paise' => 120000,
                'reorder_level' => 5.00,
                'is_active' => true,
            ]
        );

        // Kingfisher Beer single serving
        $barBeerTariff = Tariff::updateOrCreate(
            ['code' => 'BAR-BEER-KF'],
            [
                'barcode' => '890123400001',
                'department_code' => 'bar',
                'name' => 'Kingfisher Premium Beer 650ml',
                'rate_paise' => 24000, // ₹240.00
                'tax_percent' => 18.00,
                'effective_from' => '2026-01-01',
                'is_active' => true,
            ]
        );
        ServingMapping::updateOrCreate(
            ['tariff_id' => $barBeerTariff->id, 'inventory_item_id' => $beerItem->id],
            [
                'serving_name' => 'Full Bottle (650ml)',
                'serving_qty' => 1.00,
            ]
        );

        // Signature Whisky multiple servings (30ml, 60ml, Bottle)
        $whisky30 = Tariff::updateOrCreate(
            ['code' => 'BAR-WHISKY-30ML'],
            [
                'barcode' => '890123400003',
                'department_code' => 'bar',
                'name' => 'Signature Whisky (30ml Small Peg)',
                'rate_paise' => 10000, // ₹100.00
                'tax_percent' => 18.00,
                'effective_from' => '2026-01-01',
                'is_active' => true,
            ]
        );
        ServingMapping::updateOrCreate(
            ['tariff_id' => $whisky30->id, 'inventory_item_id' => $whiskyItem->id],
            [
                'serving_name' => '30 ml (Small Peg)',
                'serving_qty' => 0.04,
            ]
        );

        $whisky60 = Tariff::updateOrCreate(
            ['code' => 'BAR-WHISKY-60ML'],
            [
                'barcode' => '890123400004',
                'department_code' => 'bar',
                'name' => 'Signature Whisky (60ml Large Peg)',
                'rate_paise' => 18000, // ₹180.00
                'tax_percent' => 18.00,
                'effective_from' => '2026-01-01',
                'is_active' => true,
            ]
        );
        ServingMapping::updateOrCreate(
            ['tariff_id' => $whisky60->id, 'inventory_item_id' => $whiskyItem->id],
            [
                'serving_name' => '60 ml (Large Peg)',
                'serving_qty' => 0.08,
            ]
        );

        $whiskyBottle = Tariff::updateOrCreate(
            ['code' => 'BAR-WHISKY-BOTTLE'],
            [
                'barcode' => '890123400005',
                'department_code' => 'bar',
                'name' => 'Signature Whisky (Full Bottle 750ml)',
                'rate_paise' => 180000, // ₹1,800.00
                'tax_percent' => 18.00,
                'effective_from' => '2026-01-01',
                'is_active' => true,
            ]
        );
        ServingMapping::updateOrCreate(
            ['tariff_id' => $whiskyBottle->id, 'inventory_item_id' => $whiskyItem->id],
            [
                'serving_name' => 'Full Bottle (750ml)',
                'serving_qty' => 1.00,
            ]
        );

        // Vodka out-of-stock servings
        $vodka30 = Tariff::updateOrCreate(
            ['code' => 'BAR-VOD-30ML'],
            [
                'barcode' => '890123400021',
                'department_code' => 'bar',
                'name' => 'Absolut Vodka (30ml Small Peg)',
                'rate_paise' => 15000,
                'tax_percent' => 18.00,
                'effective_from' => '2026-01-01',
                'is_active' => true,
            ]
        );
        ServingMapping::updateOrCreate(
            ['tariff_id' => $vodka30->id, 'inventory_item_id' => $vodkaItem->id],
            [
                'serving_name' => '30 ml (Small Peg)',
                'serving_qty' => 0.04,
            ]
        );

        $vodka60 = Tariff::updateOrCreate(
            ['code' => 'BAR-VOD-60ML'],
            [
                'barcode' => '890123400022',
                'department_code' => 'bar',
                'name' => 'Absolut Vodka (60ml Large Peg)',
                'rate_paise' => 26000,
                'tax_percent' => 18.00,
                'effective_from' => '2026-01-01',
                'is_active' => true,
            ]
        );
        ServingMapping::updateOrCreate(
            ['tariff_id' => $vodka60->id, 'inventory_item_id' => $vodkaItem->id],
            [
                'serving_name' => '60 ml (Large Peg)',
                'serving_qty' => 0.08,
            ]
        );

        Tariff::updateOrCreate(
            ['code' => 'BAR-LIME-SODA'],
            [
                'department_code' => 'bar',
                'name' => 'Fresh Lime Soda',
                'rate_paise' => 5000, // ₹50.00
                'tax_percent' => 5.00,
                'effective_from' => '2026-01-01',
                'is_active' => true,
            ]
        );

        // 8. Room Accommodation Tariffs
        $roomTariffs = [
            [
                'code' => 'ROOM-DELUXE',
                'department_code' => 'accommodation',
                'name' => 'Deluxe Room Daily Tariff',
                'rate_paise' => 250000, // ₹2,500.00
                'tax_percent' => 12.00,
                'effective_from' => '2026-01-01',
                'is_active' => true,
            ],
            [
                'code' => 'ROOM-SUITE',
                'department_code' => 'accommodation',
                'name' => 'Executive Suite Daily Tariff',
                'rate_paise' => 450000, // ₹4,500.00
                'tax_percent' => 18.00,
                'effective_from' => '2026-01-01',
                'is_active' => true,
            ],
            [
                'code' => 'ROOM-STANDARD',
                'department_code' => 'accommodation',
                'name' => 'Standard Room Daily Tariff',
                'rate_paise' => 180000, // ₹1,800.00
                'tax_percent' => 12.00,
                'effective_from' => '2026-01-01',
                'is_active' => true,
            ],
        ];
        foreach ($roomTariffs as $rt) {
            Tariff::firstOrCreate(['code' => $rt['code']], $rt);
        }
    }
}
