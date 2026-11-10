<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class HelpPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Help';

    protected static ?string $slug = 'help';

    protected string $view = 'filament.pages.help';

    public string $activeTab = 'cashier'; // 'cashier', 'reception', 'finance', 'inventory', 'executive'

    public string $activeTopic = 'cashier-shift';

    public string $searchQuery = '';

    public function mount(): void
    {
        $tab = request()->query('tab');
        $topic = request()->query('topic');

        if ($topic && array_key_exists($topic, $this->getAllTopics())) {
            $this->selectTopic($topic);
        } elseif ($tab && in_array($tab, ['cashier', 'reception', 'finance', 'inventory', 'executive'], true)) {
            $this->setTab($tab);
        }
    }

    public function setTab(string $tab): void
    {
        if (!in_array($tab, ['cashier', 'reception', 'finance', 'inventory', 'executive'], true)) {
            return;
        }

        $this->activeTab = $tab;
        
        // Automatically select the first topic belonging to this tab
        foreach ($this->getAllTopics() as $id => $topic) {
            if ($topic['tab'] === $tab) {
                $this->activeTopic = $id;
                break;
            }
        }

        $this->searchQuery = '';
    }

    public function selectTopic(string $topicId): void
    {
        $all = $this->getAllTopics();
        if (isset($all[$topicId])) {
            $this->activeTopic = $topicId;
            $this->activeTab = $all[$topicId]['tab'];
        }
    }

    public function jumpTo(string $tab, string $query = ''): void
    {
        $this->setTab($tab);
        $this->searchQuery = $query;
    }

    public function clearSearch(): void
    {
        $this->searchQuery = '';
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    /**
     * Get the active topic details.
     *
     * @return array<string, mixed>
     */
    public function getCurrentTopicProperty(): array
    {
        $all = $this->getAllTopics();
        return $all[$this->activeTopic] ?? $all['cashier-shift'];
    }

    /**
     * Get topics grouped by navigation module / tab.
     *
     * @return array<string, array{label: string, icon: string, topics: array<int, array<string, mixed>>}>
     */
    public function getGroupedTopicsProperty(): array
    {
        $groups = [
            'cashier' => ['label' => 'Counter & POS', 'icon' => 'banknotes', 'topics' => []],
            'reception' => ['label' => 'Front Desk & Facilities', 'icon' => 'building-office-2', 'topics' => []],
            'finance' => ['label' => 'Finance & Accounts', 'icon' => 'scale', 'topics' => []],
            'inventory' => ['label' => 'Procurement & Stores', 'icon' => 'archive-box', 'topics' => []],
            'executive' => ['label' => 'Executive & Admin', 'icon' => 'shield-check', 'topics' => []],
        ];

        $query = trim(strtolower($this->searchQuery));

        foreach ($this->getAllTopics() as $id => $topic) {
            if ($query !== '') {
                $match = str_contains(strtolower($topic['title']), $query)
                    || str_contains(strtolower($topic['summary']), $query)
                    || str_contains(strtolower($topic['tab_label']), $query);

                if (!$match) {
                    foreach ($topic['tags'] as $tag) {
                        if (str_contains(strtolower($tag), $query)) {
                            $match = true;
                            break;
                        }
                    }
                }

                if (!$match) {
                    continue;
                }
            }

            if (isset($groups[$topic['tab']])) {
                $groups[$topic['tab']]['topics'][] = array_merge(['id' => $id], $topic);
            }
        }

        return $groups;
    }

    /**
     * Backwards-compatible searchable articles index for test assertions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSearchableArticles(): array
    {
        $articles = [];
        foreach ($this->getAllTopics() as $id => $topic) {
            $articles[] = array_merge(['id' => $id], $topic);
        }
        return $articles;
    }

    /**
     * Filtered articles for search query.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredArticlesProperty(): array
    {
        $query = trim(strtolower($this->searchQuery));
        if ($query === '') {
            return [];
        }

        return array_values(array_filter($this->getSearchableArticles(), function ($article) use ($query) {
            if (str_contains(strtolower($article['title']), $query)) {
                return true;
            }
            if (str_contains(strtolower($article['summary']), $query)) {
                return true;
            }
            if (str_contains(strtolower($article['tab_label']), $query)) {
                return true;
            }
            foreach ($article['tags'] as $tag) {
                if (str_contains(strtolower($tag), $query)) {
                    return true;
                }
            }
            return false;
        }));
    }

    /**
     * Authoritative registry of all comprehensive help topics.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllTopics(): array
    {
        return [
            // =========================================================================
            // 1. COUNTER & POS OPERATIONS
            // =========================================================================
            'cashier-shift' => [
                'tab' => 'cashier',
                'tab_label' => 'Counter & POS',
                'icon' => '⏱️',
                'title' => 'Cashier Shift Lifecycle & Float Reconciliation',
                'summary' => 'How to open a daily cashier shift, record opening cash float, track real-time drawer expected cash, and perform closing count reconciliation with discrepancy auditing.',
                'target_role' => 'Counter Cashiers & Shift Supervisors',
                'estimated_time' => '3 mins read',
                'tags' => ['shift', 'float', 'opening cash', 'closing cash', 'drawer', 'reconciliation', 'cashier', 'discrepancy'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Open Shift with Physical Float',
                        'instruction' => 'At the start of your shift, open the Counter POS. An opening modal appears prompting for your physical cash drawer float (e.g. ₹2,000.00). Enter the exact counted float and press Enter.',
                        'result' => 'The system validates your staff credentials, unlocks the billing workspace, and starts an active Shift ID.',
                        'tip' => 'Never start billing without counting your physical float first. The float is the base for expected cash calculation.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'High-Speed Counter Billing & Customer Tender',
                        'instruction' => 'Scan items with barcode scanner (F3) or select from touch menu. Tap member RFID card (F2) to attach customer. Tender via Prepaid Wallet (F8), Drawer Cash (F9), or UPI/EDC (F10).',
                        'result' => 'Bill is created atomically, financial entries post to the ledger, and an official thermal receipt prints.',
                        'tip' => 'Member wallet debits (F8) strictly enforce zero credit. If wallet balance is insufficient, offer a quick top-up first.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Monitor Live Expected Drawer Cash',
                        'instruction' => 'Look at the bottom docked status bar during your shift. It updates after every transaction with Expected Cash, Total Shift Sales, and Active Bill Count.',
                        'result' => 'Expected cash is dynamically calculated as: Opening Float + Cash Sales + Cash Wallet Recharges.',
                        'tip' => 'Prepaid wallet redemptions (F8) and digital UPI (F10) do NOT add cash to your till drawer.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Count Physical Cash & Close Shift',
                        'instruction' => 'At end of shift, click "Close Shift" in the bottom status bar. Physically count all currency notes and coins in your drawer. Enter the exact physical closing count.',
                        'result' => 'The system compares Counted Cash against Expected Cash. Any difference is logged to the Shift Discrepancy Audit Register.',
                        'tip' => 'Do not adjust your count to match the computer. Accurate counts maintain audit integrity.',
                    ],
                ],
                'formula' => 'Expected Drawer Cash = Opening Cash Float + Cash Sales + Cash Wallet Recharges',
                'policy_note' => 'Shift records are permanent legal audit documents. Once closed, shifts cannot be edited.',
            ],

            'cashier-shortcuts' => [
                'tab' => 'cashier',
                'tab_label' => 'Counter & POS',
                'icon' => '⌨️',
                'title' => 'High-Speed Keyboard Shortcuts (F2–F10)',
                'summary' => 'Comprehensive tactile keyboard hotkeys to operate the counter billing interface at maximum speed without reaching for the mouse.',
                'target_role' => 'Counter Cashiers',
                'estimated_time' => '2 mins read',
                'tags' => ['shortcuts', 'f2', 'f3', 'f4', 'f8', 'f9', 'f10', 'keyboard', 'hotkeys', 'peg', 'barcode', 'rfid', 'tender'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Press F2: Member Identification & RFID Card Tap',
                        'instruction' => 'Press F2 to immediately focus the Member Search field. Tap the member\'s contactless RFID card on the counter reader, or type their membership number, name, or phone number.',
                        'result' => 'Member name, photo, status, and verified prepaid ledger balance display in the header card.',
                        'tip' => 'Tapping an RFID card works from anywhere on screen when F2 is focused.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Press F3: Inventory Item Search & Barcode Scanner',
                        'instruction' => 'Press F3 to jump cursor focus directly into the Item Search bar. Scan physical bottle barcodes with handheld scanner, or type item name (e.g. "Signature", "Kingfisher").',
                        'result' => 'Matched items appear in autocomplete dropdown. Press Enter or click to add to cart.',
                        'tip' => 'Barcode scanners simulate typing followed by Enter, instantly adding items to cart.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Press F4: Cart Navigation & Quantity Edit',
                        'instruction' => 'Press F4 to jump directly into the active cart lines table. Use Up and Down arrow keys to select lines, type new numbers to update quantities, or press Delete to remove a line.',
                        'result' => 'Cart subtotal, applicable taxes (GST), and grand total recalculate immediately.',
                        'tip' => 'Pressing F4 twice highlights the quantity field of the last added item.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Numeric Keys 1, 2, 3: Liquor Serving Peg Selector',
                        'instruction' => 'When adding spirits or liquor to the bill, press numeric key 1 for 30ml Small Peg, 2 for 60ml Large Peg, or 3 for Full Bottle (750ml).',
                        'result' => 'The exact milliliter quantity and corresponding tariff tier are automatically applied to the cart line.',
                        'tip' => 'Eliminates mis-clicks between peg sizes during busy counter rushes.',
                    ],
                    [
                        'number' => 5,
                        'title' => 'Press F8: Member Prepaid Wallet Debit Tender',
                        'instruction' => 'Press F8 to tender the bill against the customer\'s verified prepaid balance. The server ledger locks the account atomically, verifies sufficient funds, and deducts the exact bill total.',
                        'result' => 'Invoice status updates to Paid, receipt prints automatically, and balance updates on server.',
                        'tip' => 'Strict zero-credit policy is enforced. If balance is short by even 1 Rupee, transaction is rejected.',
                    ],
                    [
                        'number' => 6,
                        'title' => 'Press F9: Physical Cash Payment Tender',
                        'instruction' => 'Press F9 to tender via physical drawer cash. Enter customer cash received; the screen displays exact change to return.',
                        'result' => 'Draws cash from customer, calculates change, records cash receipt, and increments drawer float.',
                        'tip' => 'Change calculation prevents mental arithmetic errors at busy counters.',
                    ],
                    [
                        'number' => 7,
                        'title' => 'Press F10: Digital UPI / EDC Card Terminal Tender',
                        'instruction' => 'Press F10 for electronic digital payments. Enter card machine approval code or customer UPI transaction ID, then confirm.',
                        'result' => 'Records electronic collection without adding physical cash to the till drawer.',
                        'tip' => 'Always verify transaction success on card EDC machine before pressing Enter on F10.',
                    ],
                    [
                        'number' => 8,
                        'title' => 'Press Esc (Cancel) & Enter (Confirm)',
                        'instruction' => 'Press Esc to dismiss any active modal, error alert, or receipt preview. Press Enter to confirm actions and submit forms.',
                        'result' => 'Provides fast keyboard-only dialog control without needing mouse clicks.',
                        'tip' => 'Esc safely cancels without altering cart or database state.',
                    ],
                ],
                'formula' => 'F2 (Member) → F3 (Item) → 1/2/3 (Peg Size) → F8/F9/F10 (Tender) → Enter (Print)',
                'policy_note' => 'Function keys F1 to F12 are reserved standard keys configured to not conflict with web browser controls.',
            ],

            'cashier-recharge' => [
                'tab' => 'cashier',
                'tab_label' => 'Counter & POS',
                'icon' => '💳',
                'title' => 'Member Prepaid Wallet Quick Recharge',
                'summary' => 'Step-by-step instructions for topping up a member\'s prepaid smartcard wallet balance at the counter POS using cash or UPI.',
                'target_role' => 'Counter Cashiers & Front Desk',
                'estimated_time' => '2 mins read',
                'tags' => ['recharge', 'top-up', 'wallet', 'prepaid', 'card', 'rfid', 'balance', 'f8'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Identify Member at POS',
                        'instruction' => 'Press F2 and tap member\'s RFID smartcard or type their membership number/phone.',
                        'result' => 'Member details and current wallet balance appear at the top of the counter screen.',
                        'tip' => 'Confirm member name with customer before taking cash.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Click Quick Top-Up',
                        'instruction' => 'Click the "⚡ Quick Top-Up" button located in the Member Card header.',
                        'result' => 'A clean recharge modal pops up with presets (₹500, ₹1,000, ₹2,000, ₹5,000) and custom input.',
                        'tip' => 'You can also press Alt+R as a keyboard shortcut.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Enter Amount & Select Tender Mode',
                        'instruction' => 'Select a quick amount preset or type the custom recharge amount. Choose payment mode: Cash or UPI / Card POS.',
                        'result' => 'System computes new balance preview.',
                        'tip' => 'If paid via Cash, verify physical banknotes before clicking Confirm.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Confirm Recharge & Issue Receipt',
                        'instruction' => 'Click "Confirm Recharge" (or press Enter).',
                        'result' => 'The server ledger immediately credits Account 2010 (Prepaid Wallet Liability) and debits Till Cash (1010) or Bank Clearing (1020). Official thermal top-up voucher prints.',
                        'tip' => 'Member receives instant balance SMS/WhatsApp notification if configured.',
                    ],
                ],
                'formula' => 'Journal: Debit Till Cash / Bank Clearing (1010/1020) &bull; Credit Prepaid Member Liability (2010)',
                'policy_note' => 'Wallet recharge is an advance deposit liability. It does not count as restaurant food or liquor revenue until items are actually billed.',
            ],

            'cashier-guest-pass' => [
                'tab' => 'cashier',
                'tab_label' => 'Counter & POS',
                'icon' => '🎟️',
                'title' => 'Visiting Guest Day Passes & Gate Slips',
                'summary' => 'How to register sponsored visiting guests and issue paid bar, card room, or sports facility day passes with turnstile verification barcodes.',
                'target_role' => 'Reception & Cashiers',
                'estimated_time' => '2 mins read',
                'tags' => ['guest', 'day pass', 'turnstile', 'gate', 'visitor', 'slip', 'bar pass', 'sponsor'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Switch Customer Type to Visiting Guest',
                        'instruction' => 'On the billing screen customer selector, change the toggle from "Club Member" to "Visiting Guest".',
                        'result' => 'The interface switches to Guest Mode and shows the guest search/register panel.',
                        'tip' => 'Guests must be registered with their name, mobile phone, and sponsoring member.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Select Registered Guest & Sponsor',
                        'instruction' => 'Search for guest name or click "+ Register Guest". Ensure an active club member or affiliated reciprocal club is linked as the official sponsor.',
                        'result' => 'Sponsorship eligibility is validated by the system.',
                        'tip' => 'Suspended members cannot sponsor guests.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Add Applicable Guest Pass Tariff',
                        'instruction' => 'Add the required tariff item: "Bar & Card Room Day Pass" or "Sports Facility Guest Pass".',
                        'result' => 'Statutory day pass tariff with applicable GST is added to the cart.',
                        'tip' => 'Guest fees cannot be discounted or waived to ₹0.00 under club bylaws.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Collect Payment & Print Gate Permit',
                        'instruction' => 'Tender via Cash (F9) or UPI (F10). Upon confirmation, the thermal printer produces the Guest Entry Permit with encrypted turnstile barcode.',
                        'result' => 'Turnstile controller activates for barcode scan; access is valid for the current calendar day only.',
                        'tip' => 'Unpaid draft bills never activate turnstile doors.',
                    ],
                ],
                'formula' => 'Guest Pass Status = ACTIVE only upon confirmed payment receipt.',
                'policy_note' => 'Zero-credit policy applies equally to guests. Complimentary or zero-price guest passes are strictly prohibited by club bylaws.',
            ],

            // =========================================================================
            // 2. FRONT DESK & FACILITIES
            // =========================================================================
            'reception-deck' => [
                'tab' => 'reception',
                'tab_label' => 'Front Desk & Facilities',
                'icon' => '🏨',
                'title' => 'Accommodation Deck (Rooms 101–105)',
                'summary' => 'Operating the live graphical room board, taking advance reservations with deposits, handling check-in/out, and coordinating housekeeping status.',
                'target_role' => 'Front Desk Reception & Duty Managers',
                'estimated_time' => '3 mins read',
                'tags' => ['accommodation deck', 'room rack', 'rooms', '101', '102', '103', '104', '105', 'check-in', 'checkout', 'housekeeping', 'vacant ready', 'occupied'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Read Room Status Palette',
                        'instruction' => 'Open the Accommodation Deck. The 5 guest rooms (101 to 105) display with color-coded operational states: Vacant Ready (Green), Occupied (Teal), Reserved (Blue), Needs Cleaning (Amber), Maintenance (Rose).',
                        'result' => 'Provides instant visual overview of room occupancy for today and upcoming dates.',
                        'tip' => 'Use the Date Navigator at top to inspect room availability for tomorrow or future dates.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Create New Room Reservation',
                        'instruction' => 'Click "+ New Room Reservation". Select room number, guest/member, arrival and departure dates. Collect advance room tariff via Cash, UPI, or Member Wallet.',
                        'result' => 'Room card changes to Blue (Reserved). The booking interval is locked in database to prevent double bookings.',
                        'tip' => 'All bookings require confirmed advance payment under the zero-credit rule.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Process Guest Check-In on Arrival',
                        'instruction' => 'When guest arrives, click the Reserved room card and click "Check-In". Verify identity document and hand over room key.',
                        'result' => 'Room status flips from Reserved (Blue) to Occupied (Teal). Folio tracking activates.',
                        'tip' => 'Room key cards can be encoded with guest stay dates if digital door locks are installed.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Process Check-Out & Release Room',
                        'instruction' => 'On departure date, click the Occupied room card and select "Check-Out". Review any pending minibar or restaurant room charges.',
                        'result' => 'Folio settles, bill closes, and room automatically flips to Amber (Needs Cleaning).',
                        'tip' => 'Housekeeping staff is immediately notified on their turnaround dashboard.',
                    ],
                    [
                        'number' => 5,
                        'title' => 'Housekeeping Turnaround: Mark Vacant Ready',
                        'instruction' => 'Once housekeeping staff cleans the room, replaces bed linen, and inspects amenities, click "Mark Cleaned & Ready".',
                        'result' => 'Room status restores to Emerald Green (Vacant Ready) for immediate walk-in or next check-in.',
                        'tip' => 'Never check a guest into a room before it is marked Vacant Ready.',
                    ],
                ],
                'formula' => 'Lifecycle: Reservation (Advance Paid) → Check-In (Occupied) → Check-Out (Needs Cleaning) → Mark Ready (Vacant)',
                'policy_note' => 'Overbooking is prevented by pessimistic interval database locking across MySQL connections.',
            ],

            'reception-courts' => [
                'tab' => 'reception',
                'tab_label' => 'Front Desk & Facilities',
                'icon' => '🏸',
                'title' => 'Badminton Court Daily Schedule (6:00 AM – 10:00 PM)',
                'summary' => 'How to book 60-minute badminton court slots, apply subscription plan fee waivers automatically, and collect pay-per-play tariffs.',
                'target_role' => 'Front Desk & Sports Desk Staff',
                'estimated_time' => '2 mins read',
                'tags' => ['badminton', 'court', 'schedule', 'slots', 'sports', 'subscription', 'hourly', 'waiver', 'reservation'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Open Court Schedule Matrix',
                        'instruction' => 'Navigate to Sports & Recreation -> Court Schedule. The schedule displays hourly slots from 6:00 AM to 10:00 PM.',
                        'result' => 'Available, booked, and maintenance slots are displayed in a clean timeline grid.',
                        'tip' => 'Pick the desired court (Court 1 or Court 2) and date from top bar.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Select Member or Guest',
                        'instruction' => 'Click an available slot and select the playing member or visiting guest.',
                        'result' => 'System queries active subscription memberships for that player.',
                        'tip' => 'If member has an active Monthly or Annual Sports Plan, the system automatically waives the fee to ₹0.00.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Collect Pay-Per-Play Fee (Casual Players)',
                        'instruction' => 'If the player has no active sports subscription, the standard hourly court tariff displays. Tender via Member Wallet, Cash, or UPI.',
                        'result' => 'Slot booking is confirmed and locked. A confirmation receipt is generated.',
                        'tip' => 'Unpaid slot bookings cannot be held.',
                    ],
                ],
                'formula' => 'Active Subscription = ₹0.00 Hourly Fee &bull; Casual Play = Standard Hourly Tariff',
                'policy_note' => 'Concurrent double bookings for the same court hour are strictly blocked by pessimistic row locking.',
            ],

            // =========================================================================
            // 3. FINANCE & ACCOUNTS
            // =========================================================================
            'finance-coa' => [
                'tab' => 'finance',
                'tab_label' => 'Finance & Accounts',
                'icon' => '⚖️',
                'title' => 'General Ledger & 5-Digit Numbering Standard (COA)',
                'summary' => 'Understanding the chart of accounts structure, 5-digit account codes, protected system accounts, and creating operational expense heads.',
                'target_role' => 'Club Accountant, Finance Manager & Auditors',
                'estimated_time' => '4 mins read',
                'tags' => ['chart of accounts', 'coa', '5-digit', 'gl', 'expense heads', '1010', '1020', '2010', '2020', '5110', '5120', '5210', 'kseb', 'diesel', 'system accounts'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => '1000s Series: Asset Accounts',
                        'instruction' => 'Assets represent club holdings. Key accounts include 1010 Cash in Hand (till cash), 1020 Bank Clearing (Canara Bank current account), and 1030 Bar Inventory Stores.',
                        'result' => 'Debits increase assets; credits decrease assets.',
                        'tip' => '1010 and 1020 reconcile directly to physical cash drawers and bank passbooks.',
                    ],
                    [
                        'number' => 2,
                        'title' => '2000s Series: Liability Accounts',
                        'instruction' => 'Liabilities represent obligations. Key accounts include 2010 Prepaid Wallet Advances (members\' unspent deposits), 2020 External Contractor Payable, and 2030 GST Output Liability.',
                        'result' => 'Credits increase liabilities; debits decrease liabilities when paid.',
                        'tip' => 'Account 2010 must always reconcile exactly to the sum of all active member prepaid balances.',
                    ],
                    [
                        'number' => 3,
                        'title' => '3000s Series: Capital & Equity Accounts',
                        'instruction' => 'Represents club capital, general reserves (3010), and retained operational surplus accumulated across fiscal years.',
                        'result' => 'Forms the net worth baseline on the club balance sheet.',
                        'tip' => 'Year-end surplus/deficit from Income & Expenditure transfers to Equity.',
                    ],
                    [
                        'number' => 4,
                        'title' => '4000s Series: Revenue & Income Accounts',
                        'instruction' => 'Earnings from club operations: 4010 Guest Passes, 4020 Bar Sales, 4030 Room Accommodations, and 4040 Sports Subscriptions.',
                        'result' => 'Credits record earned revenue upon service delivery.',
                        'tip' => 'Wallet recharge is NOT revenue; it becomes revenue only when spent.',
                    ],
                    [
                        'number' => 5,
                        'title' => '5000s Series: Expenses & Cost of Goods Sold',
                        'instruction' => 'Operating expenses: 5010 Liquor COGS, 5020 Food Contractor Pass-Through, 5100s Utilities (5110 KSEB, 5120 Diesel), 5200s Repairs & Maintenance, 5400s Administration.',
                        'result' => 'Debits record expenditures; credits occur only on linked reversals.',
                        'tip' => 'Expense heads can be created on the fly directly from expense vouchers.',
                    ],
                ],
                'formula' => '5-Digit Standard: 1000s Assets &bull; 2000s Liabilities &bull; 3000s Equity &bull; 4000s Revenue &bull; 5000s Expenses',
                'policy_note' => 'Accounts marked with 🔒 System Lock (1010, 1020, 1030, 2010, 2020, 2030) are automated system accounts required for software operations; their codes cannot be changed or deleted.',
            ],

            'finance-expenses' => [
                'tab' => 'finance',
                'tab_label' => 'Finance & Accounts',
                'icon' => '🧾',
                'title' => 'Recording Expense Vouchers & In-Line Head Creation',
                'summary' => 'How to record clubhouse operational expenses, attach supporting supplier bills, and create new expense heads in-line without leaving the form.',
                'target_role' => 'Accountants & Cashiers',
                'estimated_time' => '3 mins read',
                'tags' => ['expense', 'voucher', 'kseb', 'diesel', 'maintenance', 'in-line', 'modal'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Open Expense Voucher Form',
                        'instruction' => 'Navigate to Finance & Audit Ledger -> Expense Vouchers and click "New Expense Voucher".',
                        'result' => 'A clean voucher entry form opens with voucher number auto-generated.',
                        'tip' => 'Voucher dates default to today but can be set to the bill date within the open fiscal period.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Select or Create Expense Head In-Line',
                        'instruction' => 'In the Account Head dropdown, select the relevant operating head (e.g. 5110 KSEB Electricity, 5120 Generator Diesel). If the head doesn\'t exist, click the "+" button in the dropdown.',
                        'result' => 'A quick modal opens allowing you to type the new account code (e.g. 5230), name, and category. Saving automatically selects the newly created head in the voucher.',
                        'tip' => 'Use standard 5-digit codes matching the series (e.g. 5100s for utilities, 5200s for maintenance).',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Enter Amount & Payment Source',
                        'instruction' => 'Enter the exact expense amount in Rupees. Select the disbursing account: Till Cash (1010) for petty cash or Bank Clearing (1020) for cheques/NEFT.',
                        'result' => 'System computes double-entry debit and credit lines.',
                        'tip' => 'Attach vendor bill number or invoice reference for audit compliance.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Post Voucher',
                        'instruction' => 'Click "Create Expense Voucher".',
                        'result' => 'Journal posts permanently: Debits Expense Head (5xxx) and Credits Cash/Bank (1xxx). An official voucher prints.',
                        'tip' => 'Posted vouchers cannot be modified; corrections require linked reversal vouchers.',
                    ],
                ],
                'formula' => 'Journal: Debit Expense Head (5xxx) &bull; Credit Cash in Hand (1010) or Bank Clearing (1020)',
                'policy_note' => 'All expense vouchers require authorized management sign-off and valid physical supporting tax invoices.',
            ],

            'finance-contractor' => [
                'tab' => 'finance',
                'tab_label' => 'Finance & Accounts',
                'icon' => '🍲',
                'title' => 'External Food Contractor Daily Settlement & Bank Payout Cycle',
                'summary' => 'Comprehensive operational procedure for reconciling daily dining sales, generating the morning settlement batch, management approval, bank payout disbursement via NEFT/Cheque, handling customer refund adjustments, and Tally synchronization for the club\'s external catering contractor.',
                'target_role' => 'Accountant, General Manager & Financial Auditor',
                'estimated_time' => '4 mins read',
                'tags' => ['food contractor', 'contractor', 'settlement', 'kitchen', 'dining', 'batch', 'payable', 'utr', 'tally', 'payout', 'caterer', 'catering', 'restaurant', 'food bill', 'neft', 'rtgs', 'cheque', '5020', '2020'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Daily Counter Billing at POS (Cashiers)',
                        'instruction' => 'Cashiers ring up restaurant dishes on the Counter POS Terminal under the "food" department. Orders are tendered immediately via Member Prepaid Wallet, Counter Cash, or Digital UPI QR.',
                        'result' => 'Every paid food bill line is immutably recorded and queued for next-morning contractor settlement. Unused member wallet recharges do NOT belong to the contractor until spent.',
                        'tip' => 'Never issue food without confirmed payment; contractor disbursements must be backed 100% by confirmed collections under the club\'s strict zero-credit policy.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Generate Yesterday\'s Settlement Batch',
                        'instruction' => 'Every morning, navigate to Procurement & Food Contractor -> Contractor Settlements (/admin/contractor-settlements/contractor-settlement-batches). Click the top-right button "Generate Daily Settlement Batch", select yesterday\'s business date (defaults to yesterday), and click Submit.',
                        'result' => 'The system scans all paid food lines, aggregates Gross Food Sales, Taxes, Net Sales, and applies any pending deductions, generating a unique batch (e.g. SET-YYYYMMDD-001) in Draft status.',
                        'tip' => 'Batch generation is idempotent. Generating for the same date will safely return the existing batch without duplicate payouts.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Audit & Approve Settlement Batch',
                        'instruction' => 'Open the newly generated Draft batch in the table. Verify the Gross Food Sales, Tax, Deductions, and Net Payable to Contractor. An authorized Manager or Accountant clicks "Approve Batch" and confirms.',
                        'result' => 'Status updates to Approved. The system automatically posts a balanced General Ledger voucher: Debits Contractor Food Expense (5020) and Credits Food Contractor Payable (2020).',
                        'tip' => 'Only authorized administrators, managers, or accountants have permission to approve contractor settlements.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Execute & Record Bank Payout (NEFT / RTGS / Cheque)',
                        'instruction' => 'On the approved batch, click "Record Payout". In the modal, select the Disbursing Club Bank Account (e.g. SBI Main Operating Current Account), choose Payment Instrument (NEFT, RTGS, IMPS, or Cheque), enter the Bank Transfer UTR / Cheque Number, and verify the Payout Amount. Click Submit.',
                        'result' => 'Status changes to Paid (or Partially Paid if residual remains). The system automatically posts a disbursement voucher (Debit 2020 Contractor Payable / Credit 1020 Bank Clearing) and decrements the club\'s bank account ledger balance.',
                        'tip' => 'The bank UTR or cheque reference ensures 100% traceability against club bank statements.',
                    ],
                    [
                        'number' => 5,
                        'title' => 'Automated Refund Handling (Post-Settlement Adjustments)',
                        'instruction' => 'If a member returns an unsatisfactory dish or receives an authorized refund for food from an already locked/paid batch, the system preserves the locked batch intact and automatically generates a linked Negative Contractor Adjustment (ContractorSettlementAdjustment).',
                        'result' => 'When the next day\'s settlement batch is generated, the refund is automatically carried forward and deducted from the contractor\'s next payout.',
                        'tip' => 'This ensures historical audit integrity while preventing double payment or manual spreadsheet tracking.',
                    ],
                    [
                        'number' => 6,
                        'title' => 'Reconciliation & Tally Prime Audit',
                        'instruction' => 'Go to Finance & Audit Ledger -> Executive Reporting Hub -> Food Contractor Settlement & Payables Audit to inspect daily turnover, deductions, bank references, and residual payables. The transactional outbox automatically syncs approved vouchers with Tally Prime.',
                        'result' => 'One-click export to CSV for external auditors and contractor sign-off.',
                        'tip' => 'The residual payable column confirms zero unrecorded balances between the contractor and club.',
                    ],
                ],
                'formula' => 'Net Payout = Gross Food Sales - Taxes (if applicable) - Post-Settlement Refund Deductions | Journal 1 (Approval): Dr 5020 / Cr 2020 | Journal 2 (Payout): Dr 2020 / Cr 1020',
                'policy_note' => 'Strict Club Bylaw: Unused member prepaid wallet funds do NOT belong to the contractor. Only paid food bill lines feed the contractor settlement. Once approved and paid, historical settlement batches are permanently locked.',
            ],

            // =========================================================================
            // 4. PROCUREMENT & STORES
            // =========================================================================
            'inventory-purchases' => [
                'tab' => 'inventory',
                'tab_label' => 'Procurement & Stores',
                'icon' => '📦',
                'title' => 'Inward Stock Purchases & Supplier Management',
                'summary' => 'Recording incoming liquor consignments from KSBC depots and commercial distributors, GSTIN validation, and moving weighted-average costing.',
                'target_role' => 'Store Custodian & Beverage Manager',
                'estimated_time' => '3 mins read',
                'tags' => ['purchase receipt', 'ksbc', 'supplier', 'gstin', 'consignment', 'stock inward', 'weighted average', 'mac', 'inventory'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Open Purchase Receipts Form',
                        'instruction' => 'Go to Procurement & Food Contractor -> Purchase Receipts and click "Create Purchase Receipt".',
                        'result' => 'Opens consignment intake form.',
                        'tip' => 'Have the physical delivery challan and excise transport permit ready.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Select Supplier (or Register In-Line)',
                        'instruction' => 'Select distributor from dropdown (e.g. "KSBC-FL4 Wholesale Depot"). If onboarding a new vendor, click "+" to register them with their verified 15-digit GSTIN.',
                        'result' => 'Supplier details and GSTIN compliance are recorded.',
                        'tip' => 'System regex strictly validates 15-digit GSTIN format.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Enter Line Items in Milliliters (ml)',
                        'instruction' => 'Add delivered items. For bottled liquor, enter base quantities in milliliters (e.g. 10 cases of 12 bottles = 120 bottles x 750ml = 90,000 ml). Enter total cost.',
                        'result' => 'System computes cost per milliliter and unit bottle cost.',
                        'tip' => 'Always verify physical seal and case counts before confirming receipt.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Confirm & Post to Ledger',
                        'instruction' => 'Click "Create Purchase Receipt".',
                        'result' => 'Physical stock increments, Moving Average Cost recalculates, and financial journal posts: Debit Bar Inventory Stores (1030) / Credit Bank Clearing (1020).',
                        'tip' => 'Stock is immediately available for counter dispensing.',
                    ],
                ],
                'formula' => 'New Weighted Cost = (Current Stock Value + Invoice Value) / (Current Stock Qty + Inward Qty)',
                'policy_note' => 'All liquor purchases must match Kerala State Beverages Corporation (KSBC) FL-4 wholesale invoice credentials.',
            ],

            'inventory-dispensing' => [
                'tab' => 'inventory',
                'tab_label' => 'Procurement & Stores',
                'icon' => '🍸',
                'title' => 'Bar Liquor Dispensing & Milliliter (ml) Accuracy',
                'summary' => 'How liquor inventory is tracked down to the exact milliliter (ml) across 30ml pegs, 60ml pegs, and 750ml bottles to eliminate dispensing variance.',
                'target_role' => 'Bar Staff & Stock Auditors',
                'estimated_time' => '2 mins read',
                'tags' => ['bar', 'liquor', 'ml', 'peg', '30ml', '60ml', '750ml', 'bottle', 'dispensing', 'cogs', 'stock'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => '30ml Small Peg Dispensing',
                        'instruction' => 'When a 30ml peg is billed (hotkey 1), exactly 30 units are deducted from physical ml stock.',
                        'result' => 'Cost of Goods Sold (Account 5010) is credited with 30ml at current moving weighted-average cost.',
                        'tip' => 'A 750ml bottle yields exactly 25 small pegs of 30ml.',
                    ],
                    [
                        'number' => 2,
                        'title' => '60ml Large Peg Dispensing',
                        'instruction' => 'When a 60ml peg is billed (hotkey 2), exactly 60 units are deducted from physical stock.',
                        'result' => 'COGS is logged for 60ml. Stock reports display both ml and bottle equivalents.',
                        'tip' => 'A 750ml bottle yields exactly 12.5 large pegs.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Full Bottle (750ml) Sales',
                        'instruction' => 'Sealed bottle sales deduct exactly 750ml from inventory stores.',
                        'result' => 'Immediate full bottle COGS recognized.',
                        'tip' => 'Barcode scanning instantly identifies bottle packaging size.',
                    ],
                ],
                'formula' => 'Stock Deduction: 30ml (Small) &bull; 60ml (Large) &bull; 750ml (Bottle) &bull; COGS = Units &times; Moving Average Cost',
                'policy_note' => 'Spillages and breakages must be recorded immediately as an authorized Stock Wastage entry; they cannot be concealed.',
            ],

            // =========================================================================
            // 5. EXECUTIVE GOVERNANCE & ADMIN
            // =========================================================================
            'executive-staff' => [
                'tab' => 'executive',
                'tab_label' => 'Executive & Admin',
                'icon' => '🛡️',
                'title' => 'Staff User Management & Role-Based Access Control',
                'summary' => 'Registering staff members, assigning granular operational roles, deactivating departing employees, and preserving historical audit trails.',
                'target_role' => 'Club Secretary, General Manager & System Admins',
                'estimated_time' => '3 mins read',
                'tags' => ['staff', 'users', 'rbac', 'roles', 'admin', 'manager', 'cashier', 'reception', 'accountant', 'deactivate', 'access control'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Register New Staff Member',
                        'instruction' => 'Go to System & Access Control -> Staff & User Access and click "Register New Staff User". Enter Full Name, Employee ID (e.g. EMP-104), Designation, Email, Mobile Phone, and Password.',
                        'result' => 'User account is created in active status.',
                        'tip' => 'Always use official club employee IDs for clean audit cross-referencing.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Assign Operational Roles',
                        'instruction' => 'Select one or more roles: Admin (full system access), Manager (operations, stock, refunds), Accountant (ledgers, settlements), Auditor (read-only audit registers), Reception (Accommodation Deck, courts), Cashier (POS, wallet recharges).',
                        'result' => 'User menus and permissions are immediately restricted to the assigned roles.',
                        'tip' => 'Adhere to the principle of least privilege: assign Cashier role to counter staff, not Admin.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Deactivating Staff Accounts',
                        'instruction' => 'When an employee leaves or changes role, toggle their status from Active to "Deactivated".',
                        'result' => 'Login sessions are terminated immediately. The user can no longer log into back-office or POS.',
                        'tip' => 'Deactivation is immediate and does not delete historical records.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Audit Preservation (Deletion Lock)',
                        'instruction' => 'Users who have historical transactional records (shifts, bills, purchase receipts, journal vouchers) cannot be hard-deleted from the database.',
                        'result' => 'Legal and financial audit integrity is preserved permanently.',
                        'tip' => 'If an employee departs, always deactivate their account rather than attempting deletion.',
                    ],
                ],
                'formula' => 'Staff Roles: Admin &bull; Manager &bull; Accountant &bull; Auditor &bull; Reception &bull; Cashier',
                'policy_note' => 'Administrator role cannot bypass financial rules or zero-credit mandates. All financial safeguards apply equally to all users.',
            ],

            'executive-reports' => [
                'tab' => 'executive',
                'tab_label' => 'Executive & Admin',
                'icon' => '📊',
                'title' => 'Executive Reporting Hub (8 Statutory Reports)',
                'summary' => 'Comprehensive guide to the 8 core management and statutory audit reports generated by the Malabar Club reporting engine.',
                'target_role' => 'Management Committee, Secretary & Auditors',
                'estimated_time' => '3 mins read',
                'tags' => ['reports', 'executive reports', 'p&l', 'income expenditure', 'till recon', 'statutory', 'audit'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Sales & Collections Report',
                        'instruction' => 'Segregates external cash and digital UPI collections from internal prepaid wallet card redemptions. Essential for daily cash flow analysis.',
                        'result' => 'Ensures prepaid redemptions are not falsely counted as cash receipts.',
                        'tip' => 'Reconciles daily drawer collections against bank deposits.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Income & Expenditure Statement',
                        'instruction' => 'Official operational P&L showing earned revenues vs operating expenses across chosen audit date range.',
                        'result' => 'Computes net operating surplus or deficit.',
                        'tip' => 'Exportable to CSV or formatted print layout for committee meetings.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Daily Till Drawer Reconciliation',
                        'instruction' => 'Shift-by-shift audit comparing expected cash against physically counted drawer cash with variance notes.',
                        'result' => 'Flags any cashier shortage or surplus immediately.',
                        'tip' => 'Reviewed daily by the Club Accountant before shift archiving.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Food Contractor Settlement Audit',
                        'instruction' => 'Detailed turnover, commission deductions, bank payout references (UTR), and residual payables.',
                        'result' => 'Verifies 100% pass-through transparency with external kitchen operator.',
                        'tip' => 'Includes post-settlement refund adjustments.',
                    ],
                    [
                        'number' => 5,
                        'title' => 'Bar Stock & Consumption Report',
                        'instruction' => 'Moving weighted-average valuation, sales consumption COGS, opening/closing ml volumes, and physical bottle reserves.',
                        'result' => 'Tracks inventory shrinkages and dispensing yields.',
                        'tip' => 'Matches physical stocktaking counts against book balances.',
                    ],
                    [
                        'number' => 6,
                        'title' => 'Sports Facility & Plan Income',
                        'instruction' => 'Gym and badminton monthly/annual subscription revenues, court utilization rates, and pay-per-play earnings.',
                        'result' => 'Measures facility profitability and member engagement.',
                        'tip' => 'Tracks plan renewals and pending expirations.',
                    ],
                    [
                        'number' => 7,
                        'title' => 'Zero-Credit & Payables Audit',
                        'instruction' => 'Audits the entire member ledger to prove 0 bad debts, 0 overdrafts, and 100% zero-credit compliance.',
                        'result' => 'Statutory proof that no member received unbacked credit.',
                        'tip' => 'Mandatory document for annual statutory audit submission.',
                    ],
                    [
                        'number' => 8,
                        'title' => 'Departmental Profitability (P&L)',
                        'instruction' => 'Segmented profitability reports separating Bar, Dining/Restaurant, Accommodation Rooms, and Sports Facilities.',
                        'result' => 'Identifies high-performing and loss-making club departments.',
                        'tip' => 'Uses consumption costing rather than raw cash flow.',
                    ],
                ],
                'formula' => '8 Core Statements: Sales &bull; Income/Exp &bull; Till Recon &bull; Food &bull; Bar Stock &bull; Sports &bull; Zero-Credit &bull; Dept P&L',
                'policy_note' => 'Reports use immutable double-entry ledger entries. Backdated entries in locked fiscal periods are prohibited.',
            ],
        ];
    }
}
