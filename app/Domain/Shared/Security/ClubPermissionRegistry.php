<?php

namespace App\Domain\Shared\Security;

use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Single authoritative catalog of Malabar Club permissions, form/screen groupings,
 * human-readable descriptions, and default system role blueprints.
 */
final class ClubPermissionRegistry
{
    public const SYSTEM_ROLES = [
        'admin',
        'manager',
        'reception',
        'cashier',
        'accountant',
        'auditor',
    ];

    public const SUPER_ROLES = [
        'admin',
        'Administrator',
    ];

    /**
     * Metadata for built-in system roles.
     *
     * @return array<string, array{label: string, description: string, color: string, is_system: bool}>
     */
    public static function roleMeta(): array
    {
        return [
            'admin' => [
                'label' => 'Administrator',
                'description' => 'Unrestricted super-administrator access across every club module, financial approval, and Role & Form Access settings. Protected against lockout.',
                'color' => 'danger',
                'is_system' => true,
            ],
            'manager' => [
                'label' => 'Club Manager',
                'description' => 'Full operational oversight across POS, front desk, membership, tariffs, inventory, procurement, reports, and approvals (excludes user/role administration by default).',
                'color' => 'warning',
                'is_system' => true,
            ],
            'reception' => [
                'label' => 'Receptionist',
                'description' => 'Front-desk desk & counter staff: room rack, court schedule, member admission & KYC verification, guest passes, facility plans, notices, and POS backup.',
                'color' => 'success',
                'is_system' => true,
            ],
            'cashier' => [
                'label' => 'Cashier',
                'description' => 'Counter POS & desk operator: quick sales checkout, prepaid wallet recharges, walk-in room/court bookings, and guest day passes without member/KYC editing.',
                'color' => 'info',
                'is_system' => true,
            ],
            'accountant' => [
                'label' => 'Accountant',
                'description' => 'Finance, accounting, procurement & audit: general ledger journals, expenses, bank accounts, periods, purchase GRNs, contractor payouts, and reports.',
                'color' => 'primary',
                'is_system' => true,
            ],
            'auditor' => [
                'label' => 'Auditor',
                'description' => 'Read-only oversight across financial reports, bills, counter shifts, journals, purchases, inventory, and member/guest registers.',
                'color' => 'gray',
                'is_system' => true,
            ],
        ];
    }

    /**
     * Ordered permission sections for the Admin Role Matrix UI.
     *
     * @return array<string, array{title: string, description: string, icon: string}>
     */
    public static function groups(): array
    {
        return [
            'pos' => [
                'title' => 'Counter POS, Shifts & Billing',
                'description' => 'Live counter terminal (/pos), payments, prepaid wallet top-ups, shift logs, bills, and refund approvals.',
                'icon' => 'heroicon-o-bolt',
            ],
            'frontdesk' => [
                'title' => 'Front Desk, Rooms & Courts',
                'description' => 'Accommodation Deck, room check-in/out & bookings, Badminton Court schedule, and court slot bookings.',
                'icon' => 'heroicon-o-home-modern',
            ],
            'membership' => [
                'title' => 'Membership, Guests & Notices',
                'description' => 'Member profiles, confidential KYC documents, categories, sponsored guest passes, gym/badminton plans, and portal notices.',
                'icon' => 'heroicon-o-users',
            ],
            'inventory' => [
                'title' => 'Pricing, Promotions & Bar Stock',
                'description' => 'Tariff sheets, promotional campaigns, bar inventory catalogue, and manual stock adjustments.',
                'icon' => 'heroicon-o-cube',
            ],
            'procurement' => [
                'title' => 'Purchases, Suppliers & Contractors',
                'description' => 'Purchase receipts (GRNs), vendor directory, external food contractors, and next-day contractor settlements.',
                'icon' => 'heroicon-o-truck',
            ],
            'finance' => [
                'title' => 'Finance, Accounting & Reporting',
                'description' => 'Executive KPI Dashboard, Financial Reports hub, expenses, bank accounts, Chart of Accounts, journals, and periods.',
                'icon' => 'heroicon-o-banknotes',
            ],
            'system' => [
                'title' => 'Facilities, Integrations & Access Control',
                'description' => 'Room/court configuration, Tally/biometric sync status, club settings, and Staff Users / Role Matrix administration.',
                'icon' => 'heroicon-o-cog-6-tooth',
            ],
        ];
    }

    /**
     * Authoritative definition of every form/screen and privileged action permission.
     *
     * @return array<string, array{group: string, label: string, description: string, default_roles: list<string>, sensitive?: bool}>
     */
    public static function definitions(): array
    {
        return [
            // --- Counter POS, Shifts & Billing ---
            'counter.operate' => [
                'group' => 'pos',
                'label' => 'Access Counter POS Terminal (/pos)',
                'description' => 'Open the live Counter POS screen, open/close cash shifts, search members/guests, and hold counter tabs.',
                'default_roles' => ['manager', 'reception', 'cashier'],
            ],
            'sales.checkout' => [
                'group' => 'pos',
                'label' => 'Process POS Checkouts & Payments',
                'description' => 'Finalize counter bills via Prepaid Wallet, Cash, UPI, or Card.',
                'default_roles' => ['manager', 'reception', 'cashier'],
            ],
            'wallet.recharge' => [
                'group' => 'pos',
                'label' => 'Recharge Prepaid Wallets',
                'description' => 'Collect prepaid wallet top-ups at Counter POS or from a Member Profile.',
                'default_roles' => ['manager', 'reception', 'cashier'],
            ],
            'shifts.view' => [
                'group' => 'pos',
                'label' => 'View Counter Shifts (Read-Only)',
                'description' => 'View shift open/close records, expected vs counted cash, and variance notes.',
                'default_roles' => ['manager', 'accountant', 'auditor'],
            ],
            'shifts.manage' => [
                'group' => 'pos',
                'label' => 'Inspect & Manage Shift Records',
                'description' => 'Open detailed shift inspection forms and administrative shift actions.',
                'default_roles' => ['manager'],
            ],
            'bills.view' => [
                'group' => 'pos',
                'label' => 'View Posted Bills & Receipts (Read-Only)',
                'description' => 'Inspect posted club bills, line items, payment allocations, and printable receipts.',
                'default_roles' => ['manager', 'accountant', 'auditor'],
            ],
            'bills.manage' => [
                'group' => 'pos',
                'label' => 'Settle Open Bills in Admin',
                'description' => 'Open bill edit forms and settle pending/draft bills directly from Club Admin.',
                'default_roles' => ['manager'],
            ],
            'refunds.approve' => [
                'group' => 'pos',
                'label' => 'Approve Refunds & Void Passes',
                'description' => 'Authorize bill refunds, payment reversals, and unused guest-pass cancellations.',
                'default_roles' => ['manager'],
                'sensitive' => true,
            ],

            // --- Front Desk, Rooms & Courts ---
            'rooms.view_rack' => [
                'group' => 'frontdesk',
                'label' => 'View Accommodation Deck & Bookings',
                'description' => 'Open the Accommodation Deck live room board and inspect room reservation records.',
                'default_roles' => ['manager', 'reception', 'cashier'],
            ],
            'rooms.manage_reservations' => [
                'group' => 'frontdesk',
                'label' => 'Manage Room Reservations & Check-In/Out',
                'description' => 'Create room bookings, check guests in/out, update housekeeping dirty/clean status, and edit reservations.',
                'default_roles' => ['manager', 'reception', 'cashier'],
            ],
            'courts.view_schedule' => [
                'group' => 'frontdesk',
                'label' => 'View Badminton Court Schedule & Bookings',
                'description' => 'Open the live Badminton Court timetable and inspect court booking records.',
                'default_roles' => ['manager', 'reception', 'cashier'],
            ],
            'courts.manage_bookings' => [
                'group' => 'frontdesk',
                'label' => 'Manage Court Bookings & Slot Settlements',
                'description' => 'Create, collect, complete, or cancel badminton court slot reservations.',
                'default_roles' => ['manager', 'reception', 'cashier'],
            ],

            // --- Membership, Guests & Notices ---
            'members.view' => [
                'group' => 'membership',
                'label' => 'View Member Profiles & Directory (Read-Only)',
                'description' => 'Open Members list and detailed Member 360 profile view without editing membership data.',
                'default_roles' => ['manager', 'reception', 'accountant', 'auditor'],
            ],
            'members.manage' => [
                'group' => 'membership',
                'label' => 'Create & Edit Members, Cards & Lockers',
                'description' => 'Admit new members, edit profiles, issue/block RFID cards, assign lockers, manage dependents, and add notes.',
                'default_roles' => ['manager', 'reception'],
            ],
            'members.kyc_view' => [
                'group' => 'membership',
                'label' => 'Inspect Confidential Member KYC Documents',
                'description' => 'View/download private Aadhaar, PAN, passport, photo, and application documents (/documents/members/...).',
                'default_roles' => ['manager', 'reception', 'accountant'],
                'sensitive' => true,
            ],
            'categories.view' => [
                'group' => 'membership',
                'label' => 'View Membership Categories (Read-Only)',
                'description' => 'View membership tiers, admission fees, deposits, and renewal defaults.',
                'default_roles' => ['manager', 'reception', 'accountant', 'auditor'],
            ],
            'categories.manage' => [
                'group' => 'membership',
                'label' => 'Create & Edit Membership Categories',
                'description' => 'Create or modify membership category definitions, fee defaults, and category limits.',
                'default_roles' => ['manager'],
            ],
            'guests.view' => [
                'group' => 'membership',
                'label' => 'View Guest Register & Passes (Read-Only)',
                'description' => 'Inspect registered guests, sponsoring members, and historical guest pass records.',
                'default_roles' => ['manager', 'reception', 'accountant', 'auditor'],
            ],
            'guests.register' => [
                'group' => 'membership',
                'label' => 'Register Guests & Issue Day Passes',
                'description' => 'Create/edit sponsored guests and issue or redeem bar/card-room guest passes.',
                'default_roles' => ['manager', 'reception', 'cashier'],
            ],
            'subscriptions.view' => [
                'group' => 'membership',
                'label' => 'View Facility Plans & Subscriptions (Read-Only)',
                'description' => 'View Gym and Badminton recurring plan entitlements and validity dates.',
                'default_roles' => ['manager', 'reception', 'accountant', 'auditor'],
            ],
            'subscriptions.manage' => [
                'group' => 'membership',
                'label' => 'Create & Edit Facility Subscriptions',
                'description' => 'Subscribe members to Gym/Badminton plans and modify or cancel active plans.',
                'default_roles' => ['manager', 'reception'],
            ],
            'notices.manage' => [
                'group' => 'membership',
                'label' => 'Manage Member Portal Notices',
                'description' => 'Create, edit, publish, and remove club notices displayed to members.',
                'default_roles' => ['manager', 'reception', 'accountant'],
            ],

            // --- Pricing, Promotions & Bar Stock ---
            'tariffs.view' => [
                'group' => 'inventory',
                'label' => 'View Tariffs & Rate Cards (Read-Only)',
                'description' => 'View configured room, court, bar, F&B, and guest-pass tariffs.',
                'default_roles' => ['manager', 'auditor'],
            ],
            'tariffs.manage' => [
                'group' => 'inventory',
                'label' => 'Create & Edit Tariffs',
                'description' => 'Create and update pricing rules, GST rates, barcodes, and accounting ledger mappings.',
                'default_roles' => ['manager'],
            ],
            'promotions.view' => [
                'group' => 'inventory',
                'label' => 'View Promotions & Campaigns (Read-Only)',
                'description' => 'View configured promotional discounts and happy-hour offers.',
                'default_roles' => ['manager'],
            ],
            'promotions.manage' => [
                'group' => 'inventory',
                'label' => 'Create & Edit Promotions',
                'description' => 'Create, edit, activate, or deactivate club promotional campaigns.',
                'default_roles' => ['manager'],
            ],
            'stock.view' => [
                'group' => 'inventory',
                'label' => 'View Bar & Store Inventory (Read-Only)',
                'description' => 'Inspect inventory SKUs, current stock quantities, peg conversions, and reorder thresholds.',
                'default_roles' => ['manager', 'accountant', 'auditor'],
            ],
            'stock.manage' => [
                'group' => 'inventory',
                'label' => 'Manage Inventory & Stock Adjustments',
                'description' => 'Create/edit inventory SKUs and record manual stock wastage or count adjustments.',
                'default_roles' => ['manager'],
                'sensitive' => true,
            ],

            // --- Purchases, Suppliers & Contractors ---
            'purchases.view' => [
                'group' => 'procurement',
                'label' => 'View Purchase Receipts / GRNs (Read-Only)',
                'description' => 'Inspect posted goods receipt notes, supplier invoices, and batch stock additions.',
                'default_roles' => ['manager', 'accountant', 'auditor'],
            ],
            'purchases.manage' => [
                'group' => 'procurement',
                'label' => 'Record Purchase Receipts (GRNs)',
                'description' => 'Create and post supplier stock purchase receipts into bar/store inventory.',
                'default_roles' => ['manager', 'accountant'],
            ],
            'suppliers.view' => [
                'group' => 'procurement',
                'label' => 'View Suppliers Directory (Read-Only)',
                'description' => 'Inspect approved bar/store suppliers, GSTINs, and payable ledgers.',
                'default_roles' => ['manager', 'accountant', 'auditor'],
            ],
            'suppliers.manage' => [
                'group' => 'procurement',
                'label' => 'Create & Edit Suppliers',
                'description' => 'Create and maintain vendor records and supplier ledger mappings.',
                'default_roles' => ['manager'],
            ],
            'contractors.view' => [
                'group' => 'procurement',
                'label' => 'View Food Contractors (Read-Only)',
                'description' => 'Inspect external kitchen/catering contractor configurations and revenue splits.',
                'default_roles' => ['manager', 'accountant', 'auditor'],
            ],
            'contractors.manage' => [
                'group' => 'procurement',
                'label' => 'Create & Edit Food Contractors',
                'description' => 'Create and edit catering contractor profiles, bank details, and settlement rules.',
                'default_roles' => ['manager'],
            ],
            'contractor.view' => [
                'group' => 'procurement',
                'label' => 'View Contractor Settlement Batches (Read-Only)',
                'description' => 'Inspect next-day contractor settlement batches, bill allocations, and residual payables.',
                'default_roles' => ['manager', 'accountant', 'auditor'],
            ],
            'contractor.settle' => [
                'group' => 'procurement',
                'label' => 'Approve & Pay Contractor Settlements',
                'description' => 'Generate, approve, adjust, and disburse food contractor settlement batches.',
                'default_roles' => ['manager', 'accountant'],
                'sensitive' => true,
            ],

            // --- Finance, Accounting & Reporting ---
            'reports.view' => [
                'group' => 'finance',
                'label' => 'View Financial Reports & Executive KPIs',
                'description' => 'View Admin KPI widgets, Financial Reports Hub, P&L, Trial Balance, GST, and collections reports.',
                'default_roles' => ['manager', 'accountant', 'auditor'],
            ],
            'expenses.view' => [
                'group' => 'finance',
                'label' => 'View Club Expenses (Read-Only)',
                'description' => 'Inspect posted operational expense vouchers and cost breakdowns.',
                'default_roles' => ['manager', 'accountant', 'auditor'],
            ],
            'expenses.manage' => [
                'group' => 'finance',
                'label' => 'Record Club Expenses',
                'description' => 'Create and post club expense entries against expense and payment accounts.',
                'default_roles' => ['manager', 'accountant'],
            ],
            'bank_accounts.view' => [
                'group' => 'finance',
                'label' => 'View Bank Accounts (Read-Only)',
                'description' => 'Inspect configured club bank/UPI settlement accounts and current ledger links.',
                'default_roles' => ['manager', 'accountant', 'auditor'],
            ],
            'bank_accounts.manage' => [
                'group' => 'finance',
                'label' => 'Create & Edit Bank Accounts',
                'description' => 'Add or update club bank accounts and GL settlement mappings.',
                'default_roles' => ['manager', 'accountant'],
            ],
            'accounts.view' => [
                'group' => 'finance',
                'label' => 'View Chart of Accounts (Read-Only)',
                'description' => 'Inspect club general ledger accounts, account types, and hierarchy.',
                'default_roles' => ['manager', 'accountant', 'auditor'],
            ],
            'accounts.manage' => [
                'group' => 'finance',
                'label' => 'Create & Edit Chart of Accounts',
                'description' => 'Create or modify general ledger accounts in the Chart of Accounts.',
                'default_roles' => ['manager', 'accountant'],
            ],
            'journals.view' => [
                'group' => 'finance',
                'label' => 'View Accounting Journals (Read-Only)',
                'description' => 'Inspect double-entry journal headers, balanced debit/credit lines, and voucher references.',
                'default_roles' => ['manager', 'accountant', 'auditor'],
            ],
            'journals.manage' => [
                'group' => 'finance',
                'label' => 'Post Manual Journal Entries',
                'description' => 'Create balanced manual adjustment journals in the general ledger.',
                'default_roles' => ['manager', 'accountant'],
                'sensitive' => true,
            ],
            'periods.view' => [
                'group' => 'finance',
                'label' => 'View Financial Periods (Read-Only)',
                'description' => 'Inspect open and closed accounting period date ranges.',
                'default_roles' => ['manager', 'accountant', 'auditor'],
            ],
            'periods.manage' => [
                'group' => 'finance',
                'label' => 'Manage & Close Financial Periods',
                'description' => 'Create new accounting periods and close/lock past periods against further posting.',
                'default_roles' => ['manager', 'accountant'],
                'sensitive' => true,
            ],

            // --- Facilities, Integrations & Access Control ---
            'facilities.view' => [
                'group' => 'system',
                'label' => 'View Rooms & Facilities Master (Read-Only)',
                'description' => 'Inspect configured club guest rooms, badminton courts, and gym capacity settings.',
                'default_roles' => ['manager'],
            ],
            'facilities.manage' => [
                'group' => 'system',
                'label' => 'Create & Edit Rooms & Facilities',
                'description' => 'Add or modify guest rooms, badminton courts, maintenance blocks, and facility status.',
                'default_roles' => ['manager'],
            ],
            'integrations.view' => [
                'group' => 'system',
                'label' => 'View Integrations & Sync Status (Read-Only)',
                'description' => 'Inspect Tally outbox status, access-control logs, and payment gateway health.',
                'default_roles' => ['manager', 'accountant'],
            ],
            'integrations.manage' => [
                'group' => 'system',
                'label' => 'Manage Integrations & Outbox Retries',
                'description' => 'Retry failed Tally outbox messages and trigger integration recovery tasks.',
                'default_roles' => ['manager', 'accountant'],
                'sensitive' => true,
            ],
            'settings.manage' => [
                'group' => 'system',
                'label' => 'Manage Club System Settings',
                'description' => 'Update club name, GSTIN, default admission fees, printer/POS settings, and system toggles.',
                'default_roles' => ['manager'],
                'sensitive' => true,
            ],
            'users.manage' => [
                'group' => 'system',
                'label' => 'Manage Staff Users, Roles & Form Access',
                'description' => 'Create/deactivate staff logins, edit Role & Form Access permissions, and grant per-user overrides.',
                'default_roles' => [],
                'sensitive' => true,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function permissionNames(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return array<string, string>
     */
    public static function permissionOptionsFlat(): array
    {
        $options = [];
        foreach (self::definitions() as $name => $meta) {
            $groupTitle = self::groups()[$meta['group']]['title'] ?? ucfirst($meta['group']);
            $options[$name] = "{$groupTitle}: {$meta['label']} ({$name})";
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function groupPermissionOptions(string $groupKey): array
    {
        $options = [];
        foreach (self::definitions() as $name => $meta) {
            if ($meta['group'] === $groupKey) {
                $prefix = ($meta['sensitive'] ?? false) ? '[Sensitive] ' : '';
                $options[$name] = "{$prefix}{$meta['label']}";
            }
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function groupPermissionDescriptions(string $groupKey): array
    {
        $descriptions = [];
        foreach (self::definitions() as $name => $meta) {
            if ($meta['group'] === $groupKey) {
                $descriptions[$name] = "{$meta['description']} [key: {$name}]";
            }
        }

        return $descriptions;
    }

    /**
     * All permissions that grant access to at least one screen inside the /admin Filament panel.
     *
     * @return list<string>
     */
    public static function adminPanelPermissions(): array
    {
        return array_values(array_diff(
            self::permissionNames(),
            ['counter.operate', 'sales.checkout', 'wallet.recharge', 'refunds.approve']
        ));
    }

    /**
     * @return list<string>
     */
    public static function defaultPermissionsForRole(string $roleName): array
    {
        $normalized = self::normalizeRoleName($roleName);
        if ($normalized === 'admin') {
            return self::permissionNames();
        }

        $permissions = [];
        foreach (self::definitions() as $permission => $meta) {
            if (in_array($normalized, $meta['default_roles'], true)) {
                $permissions[] = $permission;
            }
        }

        return $permissions;
    }

    public static function isSystemRole(string $roleName): bool
    {
        return in_array(self::normalizeRoleName($roleName), self::SYSTEM_ROLES, true);
    }

    public static function normalizeRoleName(string $roleName): string
    {
        return match (strtolower(trim($roleName))) {
            'administrator' => 'admin',
            default => strtolower(trim($roleName)),
        };
    }

    public static function roleDisplayName(string $roleName): string
    {
        $normalized = self::normalizeRoleName($roleName);
        $meta = self::roleMeta()[$normalized] ?? null;

        if ($meta) {
            return $meta['label'];
        }

        return ucwords(str_replace(['_', '-'], ' ', $roleName));
    }

    public static function roleShortBadgeLabel(string $roleName): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $roleName));
    }

    public static function roleBadgeColor(string $roleName): string
    {
        $normalized = self::normalizeRoleName($roleName);

        return self::roleMeta()[$normalized]['color'] ?? 'purple';
    }

    /**
     * Options array formatted for Filament Select components covering both system and custom roles.
     *
     * @return array<string, string>
     */
    public static function availableRoleOptions(): array
    {
        if (! Schema::hasTable('roles')) {
            return collect(self::roleMeta())
                ->mapWithKeys(fn (array $meta, string $key): array => [$key => $meta['label']])
                ->all();
        }

        $roles = Role::query()->where('guard_name', 'web')->orderBy('name')->get();
        if ($roles->isEmpty()) {
            return collect(self::roleMeta())
                ->mapWithKeys(fn (array $meta, string $key): array => [$key => $meta['label']])
                ->all();
        }

        $options = [];
        // Keep system roles in canonical order first
        foreach (self::SYSTEM_ROLES as $systemRole) {
            $found = $roles->firstWhere('name', $systemRole);
            if ($found) {
                $options[$found->name] = self::roleDisplayName($found->name);
            }
        }

        foreach ($roles as $role) {
            if (! array_key_exists($role->name, $options)) {
                $options[$role->name] = self::roleDisplayName($role->name) . ' (Custom)';
            }
        }

        return $options;
    }

    /**
     * Ensure every defined permission exists in Spatie tables.
     *
     * @return list<string> Newly created permission names
     */
    public static function ensurePermissionsExist(): array
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $newlyCreated = [];
        foreach (self::permissionNames() as $name) {
            $permission = Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);

            if ($permission->wasRecentlyCreated) {
                $newlyCreated[] = $name;
            }
        }

        return $newlyCreated;
    }

    /**
     * Production-safe idempotent bootstrapper used by ProductionBootstrapSeeder.
     * Preserves any existing customized permissions on non-admin roles while keeping
     * admin synced to all permissions and populating defaults on newly created or empty roles.
     */
    public static function seedBootstrapRolesAndPermissions(): void
    {
        self::ensurePermissionsExist();

        foreach (self::SYSTEM_ROLES as $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            if ($roleName === 'admin') {
                $role->syncPermissions(Permission::where('guard_name', 'web')->get());

                continue;
            }

            if ($role->wasRecentlyCreated || $role->permissions()->count() === 0) {
                $defaultNames = self::defaultPermissionsForRole($roleName);
                $perms = Permission::where('guard_name', 'web')
                    ->whereIn('name', $defaultNames)
                    ->get();
                $role->syncPermissions($perms);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Migration-safe upgrade helper: registers missing permissions and attributes
     * ONLY newly created permissions to existing built-in roles without clobbering
     * fresh-database seeding or customized existing permissions.
     */
    public static function syncMigrationDefaults(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $newlyCreated = self::ensurePermissionsExist();
        $definitions = self::definitions();

        // Update existing admin role if present (skip creation so fresh DB seeders create roles cleanly)
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($adminRole) {
            $adminRole->syncPermissions(Permission::where('guard_name', 'web')->get());
        }

        // Attach newly added permissions to existing default roles without overwriting existing role customization
        foreach ($newlyCreated as $permissionName) {
            $defaultRoles = $definitions[$permissionName]['default_roles'] ?? [];
            foreach ($defaultRoles as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
                if ($role && ! $role->hasPermissionTo($permissionName)) {
                    $role->givePermissionTo($permissionName);
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
