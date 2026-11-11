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
            'reception' => ['label' => 'Front Desk, Sports & Members', 'icon' => 'building-office-2', 'topics' => []],
            'finance' => ['label' => 'Finance & Accounts', 'icon' => 'scale', 'topics' => []],
            'inventory' => ['label' => 'Procurement & Stores', 'icon' => 'archive-box', 'topics' => []],
            'executive' => ['label' => 'Executive & Admin', 'icon' => 'shield-check', 'topics' => []],
        ];

        $query = trim(strtolower($this->searchQuery));

        foreach ($this->getAllTopics() as $id => $topic) {
            if ($query !== '' && !$this->topicMatchesQuery($topic, $query)) {
                continue;
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

        return array_values(array_filter(
            $this->getSearchableArticles(),
            fn (array $article): bool => $this->topicMatchesQuery($article, $query),
        ));
    }

    /**
     * Match query across topic metadata, tags, formulas, and all procedural steps.
     */
    protected function topicMatchesQuery(array $topic, string $normalizedQuery): bool
    {
        $fields = [
            $topic['title'] ?? '',
            $topic['summary'] ?? '',
            $topic['tab_label'] ?? '',
            $topic['target_role'] ?? '',
            $topic['formula'] ?? '',
            $topic['policy_note'] ?? '',
        ];

        foreach ($fields as $value) {
            if ($value !== '' && str_contains(strtolower($value), $normalizedQuery)) {
                return true;
            }
        }

        foreach ($topic['tags'] ?? [] as $tag) {
            if (str_contains(strtolower((string) $tag), $normalizedQuery)) {
                return true;
            }
        }

        foreach ($topic['steps'] ?? [] as $step) {
            foreach (['title', 'instruction', 'result', 'tip'] as $stepKey) {
                $text = (string) ($step[$stepKey] ?? '');
                if ($text !== '' && str_contains(strtolower($text), $normalizedQuery)) {
                    return true;
                }
            }
        }

        return false;
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
                'summary' => 'How to open a daily cashier shift, record opening cash float, track real-time drawer expected cash, clear open running tabs, and complete closing count reconciliation.',
                'target_role' => 'Counter Cashiers & Shift Supervisors',
                'estimated_time' => '3 mins read',
                'tags' => ['shift', 'float', 'opening cash', 'closing cash', 'drawer', 'reconciliation', 'cashier', 'discrepancy'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Open Shift with Physical Float',
                        'instruction' => 'At the start of your shift, open the Counter POS (/pos). Choose your assigned counter and enter the exact counted opening cash float (e.g. ₹2,000.00), then press Enter.',
                        'result' => 'The system validates your staff credentials, unlocks the billing workspace, and starts an active Shift ID.',
                        'tip' => 'Never start billing without counting your physical float first. The float is the base for expected cash calculation.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'High-Speed Counter Billing & Customer Tender',
                        'instruction' => 'Identify the Member, Dependent, Resident Room Guest, or Visiting Guest (F2), scan or search items (F3), park open bar tabs when patrons order rounds (F6), or settle immediately via Member Prepaid Wallet, Drawer Cash, or Digital UPI / Card POS.',
                        'result' => 'Paid bills commit atomically to the general ledger and stock register, and the thermal receipt dialog opens.',
                        'tip' => 'Prepaid wallet debits strictly enforce zero credit. If balance is short, recharge the wallet before checkout.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Monitor Live Expected Drawer Cash',
                        'instruction' => 'Look at the docked bottom status bar during your shift. It updates after every transaction with Expected Cash, Open Running Tabs count, and Total Shift Sales.',
                        'result' => 'Expected cash dynamically equals Opening Float + Cash Sales + Cash Wallet Recharges.',
                        'tip' => 'Prepaid wallet redemptions and digital UPI payments do NOT add physical cash to your drawer.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Settle Tabs, Count Physical Cash & Close Shift',
                        'instruction' => 'Before closing, ensure every open Running Bar Tab for your shift is either settled in full or voided with remarks. Click "Close Shift" in the bottom status bar and enter the exact physical cash counted in your drawer.',
                        'result' => 'Any difference between Counted Cash and Expected Cash is permanently recorded to the Shift Discrepancy Audit Register.',
                        'tip' => 'The system strictly blocks shift closure while unsettled open tabs remain. Never alter your physical count to match the expected figure.',
                    ],
                ],
                'formula' => 'Expected Drawer Cash = Opening Cash Float + Cash Sales + Cash Wallet Recharges',
                'policy_note' => 'Shift records are permanent legal audit documents. Once closed, shifts cannot be edited.',
            ],

            'cashier-shortcuts' => [
                'tab' => 'cashier',
                'tab_label' => 'Counter & POS',
                'icon' => '⌨️',
                'title' => 'High-Speed Keyboard Shortcuts (F2–F10, Arrows & P)',
                'summary' => 'Complete keyboard hotkeys for rapid Counter POS operation: customer identification, arrow-key autocomplete, liquor serving sizes, running tabs (F6), tender modes, and thermal receipt printing.',
                'target_role' => 'Counter Cashiers',
                'estimated_time' => '2 mins read',
                'tags' => ['shortcuts', 'f2', 'f3', 'f4', 'f6', 'f8', 'f9', 'f10', 'arrows', 'keyboard', 'hotkeys', 'peg', 'barcode', 'rfid', 'tender', 'print'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Press F2: Member, Room Guest & RFID Search',
                        'instruction' => 'Press F2 to focus the customer search input. Tap an RFID card on the reader, or type a membership number, member name, mobile number, or checked-in Room Number (e.g. "101"). Use Up/Down arrow keys to highlight and Enter to select.',
                        'result' => 'Selected member (with live wallet balance) or resident room guest details appear in the active customer card.',
                        'tip' => 'Arrow-key selection works identically for customer and item search lists without touching the mouse.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Press F3: Item Search, Autocomplete & Barcode Scanner',
                        'instruction' => 'Press F3 to focus the Item / Tariff search bar. Scan a bottle barcode directly, or type the item name and navigate results with Up/Down arrows and press Enter.',
                        'result' => 'Single-tariff items add immediately to the bill; multi-serving liquor brands open the Serving Size modal.',
                        'tip' => 'Handheld barcode scanners send characters followed by Enter automatically.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Numeric Keys 1, 2, 3 or Arrows: Liquor Serving Size',
                        'instruction' => 'When the Serving Size selector opens for liquor, press 1, 2, or 3 (or use Up/Down arrows and Enter) to choose Small Peg (30ml), Large Peg (60ml), or Full Bottle (750ml).',
                        'result' => 'Exact milliliter volume, tariff rate, and any active promo eligibility calculate into the bill.',
                        'tip' => 'Eliminates accidental peg vs bottle billing mistakes during peak bar hours.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Press F4 or Delete: Cart Quantity & Line Removal',
                        'instruction' => 'Press F4 to highlight the active cart quantity input, adjust counts directly, or press Delete (outside text inputs) to clear/adjust selected items quickly.',
                        'result' => 'Taxable value, GST slabs, promotional savings, and net payable update instantly.',
                        'tip' => 'Review green promotional bonus badges directly beneath eligible liquor lines.',
                    ],
                    [
                        'number' => 5,
                        'title' => 'Press F6: Park / Hold Running Bar Tab',
                        'instruction' => 'Press F6 with an active customer and non-empty cart to open the Hold Tab modal. Optionally add table/lounge location remarks and press Enter to park the order.',
                        'result' => 'Order saves into the active Running Bar Tabs strip and clears the workspace for the next patron.',
                        'tip' => 'Switching between active tab chips auto-saves unsaved cart changes.',
                    ],
                    [
                        'number' => 6,
                        'title' => 'Press F8, F9, or F10: Tender & Confirm Payment',
                        'instruction' => 'Press F8 for Member Prepaid Wallet debit, F9 for Drawer Cash (calculates customer change), or F10 for Digital UPI / Card EDC (with transaction reference), then press Enter to confirm.',
                        'result' => 'Server posts payment atomically, updates stock/journal, and opens the Thermal Receipt modal.',
                        'tip' => 'F8 Prepaid is blocked for Visiting Guests and non-authorized dependents under Zero-Credit rules.',
                    ],
                    [
                        'number' => 7,
                        'title' => 'Press P, Enter, or Esc: Thermal Receipt & Dialog Control',
                        'instruction' => 'When the Thermal Receipt modal is open, press P to open the print dialog immediately. Press Enter to accept/advance alerts and Esc to cancel or dismiss any active dialog.',
                        'result' => 'Prints only the formatted 80mm thermal slip cleanly without sidebars or POS buttons.',
                        'tip' => 'Esc safely closes modals without discarding your current order lines.',
                    ],
                ],
                'formula' => 'F2 (Customer) → F3 + ↑/↓/Enter (Item) → 1/2/3 (Serving) → F6 (Hold Tab) or F8/F9/F10 (Pay) → P (Print)',
                'policy_note' => 'Keyboard hotkeys are bound inside Counter POS so cashiers can serve busy lounge rushes without conflicting with browser shortcuts.',
            ],

            'cashier-running-tabs' => [
                'tab' => 'cashier',
                'tab_label' => 'Counter & POS',
                'icon' => '🍸',
                'title' => 'Bar / Lounge Running Tabs & Multi-Round Billing (F6)',
                'summary' => 'Parking open member orders concurrently across bar rounds, Member-First naming, switching tabs with auto-save, voiding tabs, and closing shifts safely.',
                'target_role' => 'Bar Tenders, Waitstaff & Counter Cashiers',
                'estimated_time' => '3 mins read',
                'tags' => ['running tabs', 'held tabs', 'f6', 'bar', 'lounge', 'rounds', 'park bill', 'hold tab', 'void tab'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Identify Member First & Build Initial Round',
                        'instruction' => 'Press F2 to attach the Member (required under Malabar Club\'s Member-First Naming Policy, since patrons move around tables and lounges). Add their first drinks/food items via F3.',
                        'result' => 'Order links to the verified member identity rather than just an anonymous table number.',
                        'tip' => 'If no customer or cart item is selected, Hold Tab [F6] cannot park an empty order.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Park Tab with F6 & Optional Table / Remark',
                        'instruction' => 'Click "Hold Tab" (or press F6). Optionally type table/bar-stool location (e.g. "Card Lounge T3" or "Bar Stool 4") and staff notes, then click "Hold Tab & Clear Workspace [Enter]".',
                        'result' => 'The tab is saved to the active shift up to the configured limit (default up to 50 concurrent open tabs) and appears in the top Running Tabs strip.',
                        'tip' => 'Use clear remarks so another cashier covering your counter recognizes the patron immediately.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Add Subsequent Rounds & Switch Between Patrons',
                        'instruction' => 'Click any member chip in the top Running Tabs strip (or open the All Tabs Manager modal to search by name, member ID, or table) to load their tab, add new rounds, or edit quantities.',
                        'result' => 'Switching from one open tab to another automatically preserves your changes on the previous tab.',
                        'tip' => 'Promotional Buy X Get Y offers evaluate cumulatively across all rounds on the open tab.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Settle in Full (or Void) Before Closing Shift',
                        'instruction' => 'When the member is ready to leave, load their tab and collect payment via Prepaid Wallet, Cash, or Digital UPI. If an entry was created by mistake before service, use Void Tab.',
                        'result' => 'Payment transitions the tab to Settled linked to the official Bill and Payment Receipt. Close Shift requires zero open tabs.',
                        'tip' => 'Held tabs are strictly in-session draft orders within an open shift—overnight credit tabs are prohibited.',
                    ],
                ],
                'formula' => 'Up to 50 Concurrent Open Tabs per Shift • Member-First Required • Open Tabs Must Equal 0 to Close Shift',
                'policy_note' => 'Running Bar Tabs comply with the strict Zero-Credit policy: goods must be paid in full before departure and cannot carry over across closed shifts.',
            ],

            'cashier-promotions' => [
                'tab' => 'cashier',
                'tab_label' => 'Counter & POS',
                'icon' => '🎁',
                'title' => 'Promotional Offers ("Buy X Get Y Free") on Counter POS',
                'summary' => 'How pre-configured liquor and beverage promotions activate automatically on Counter POS, claiming bonus units, quota tracking, and stock COGS accounting.',
                'target_role' => 'Counter Cashiers, Bar Managers & Supervisors',
                'estimated_time' => '3 mins read',
                'tags' => ['promotions', 'offers', 'buy x get y', 'free', 'discount', 'happy hour', 'bar', 'scheme', 'claim'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Configure Approved Offers in Back Office',
                        'instruction' => 'Authorized Managers navigate to Commercial & Counters -> Promotions & Offers, set Buy X Paid Units and Get Y Bonus Units, select eligible bar tariffs, set customer eligibility (Members Only by default), optional free-unit quota caps, and validity dates.',
                        'result' => 'Active offers publish automatically to Counter POS with tamper-proof server calculation; cashiers cannot manually discount items.',
                        'tip' => 'Protected Day Guest Entry Passes and ₹0.00 tariffs are strictly blocked from promotional discounting.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Spot Eligible Promo Badges During Search',
                        'instruction' => 'When searching items (F3) or selecting serving sizes (1/2/3), eligible liquors display a gift promo badge (e.g. "Buy 2 Get 1 Free").',
                        'result' => 'Both cashiers and members see qualifying offers upfront before billing.',
                        'tip' => 'Promotions apply strictly to eligible Members and Resident Room Guests—Visiting Day Guests pay standard tariffs.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Claim Bonus Free Unit with 1 Click',
                        'instruction' => 'Once the qualifying paid quantity (e.g. 2 pegs) is in the cart, a 1-click green button appears below the item (e.g. "+1 Free Offer Available — Add Free Unit"). Click it if the patron wants their complimentary serving poured.',
                        'result' => 'Total poured quantity becomes 3 pegs, customer pays only for 2 paid pegs + GST on net taxable value, and Promo Savings print on the thermal slip.',
                        'tip' => 'Every bonus unit still checks physical ml availability and deducts stock + COGS accurately when poured.',
                    ],
                ],
                'formula' => 'Net Payable = (Paid Units × Tariff Rate) + GST on Net Taxable Value • Stock Deducted = Paid ml + Free ml',
                'policy_note' => 'All promotional discounts require active server configuration under Commercial & Counters -> Promotions & Offers. Manual cashier price overrides are disabled.',
            ],

            'cashier-room-guest' => [
                'tab' => 'cashier',
                'tab_label' => 'Counter & POS',
                'icon' => '🛎️',
                'title' => 'Resident Room Guest Counter Sales & Zero-Credit Payment',
                'summary' => 'Looking up guests checked into club guest rooms by room number on POS and collecting immediate settlement without unbacked room tabs.',
                'target_role' => 'Counter Cashiers & Bar/Dining Staff',
                'estimated_time' => '2 mins read',
                'tags' => ['room guest', 'resident', 'room 101', 'room number', 'folio', 'zero credit', 'pos', 'dining'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Search by Room Number or Resident Name (F2)',
                        'instruction' => 'Press F2 in Customer Search and type the room number (e.g. "101", "Room 101"), resident guest name, or mobile phone.',
                        'result' => 'Active checked-in Room Reservations display alongside their room tag, check-in/out dates, and sponsoring club member.',
                        'tip' => 'Only currently Checked-In room stays appear for POS billing.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Ring Items & Collect Immediate Payment',
                        'instruction' => 'Add bar, food, or facility items via F3 and tender immediately using Cash (F9) or Digital UPI / Card POS (F10). Prepaid wallet debit is permitted only when the sponsoring member\'s authorized wallet is used directly.',
                        'result' => 'System records room and occupant details inside the bill and thermal receipt without creating unbacked post-pay credit.',
                        'tip' => 'Resident guests cannot sign bills "to room" for checkout settlement under Malabar Club zero-credit bylaws.',
                    ],
                ],
                'formula' => 'Resident POS Sale = Checked-In Room Reservation Lookup + Immediate Cash / Digital UPI Settlement',
                'policy_note' => 'Charging counter food or bar orders onto an unbacked hotel room folio for later checkout is strictly prohibited.',
            ],

            'cashier-recharge' => [
                'tab' => 'cashier',
                'tab_label' => 'Counter & POS',
                'icon' => '💳',
                'title' => 'Member Prepaid Wallet Quick Recharge',
                'summary' => 'Step-by-step instructions for topping up a member\'s prepaid smartcard wallet balance at the counter POS using cash, UPI, or card.',
                'target_role' => 'Counter Cashiers & Front Desk',
                'estimated_time' => '2 mins read',
                'tags' => ['recharge', 'top-up', 'wallet', 'prepaid', 'card', 'rfid', 'balance', 'f8'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Identify Member at POS',
                        'instruction' => 'Press F2 and tap the member\'s RFID smartcard or search their membership number, name, or mobile phone.',
                        'result' => 'Member details, card status, and verified server wallet balance display in the active customer card.',
                        'tip' => 'Confirm the member\'s full name on screen before accepting cash or UPI transfer.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Click Quick Top-Up',
                        'instruction' => 'Click the "⚡ Quick Top-Up" button inside the active Member Wallet card header.',
                        'result' => 'The Wallet Recharge dialog opens with configured min/max limits and preset rupee amounts (₹500, ₹1,000, ₹2,000, ₹5,000) plus custom input.',
                        'tip' => 'Ensure you have an open cashier shift before accepting cash top-ups.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Enter Amount & Select Tender Mode',
                        'instruction' => 'Choose a preset button or type the exact recharge amount within allowed limits, then select Cash or Digital UPI / Card POS (with bank/UPI reference if digital).',
                        'result' => 'System validates the amount and previews the updated balance.',
                        'tip' => 'Verify physical banknotes or confirmed UPI credit alert before submitting.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Confirm Recharge & Issue Receipt',
                        'instruction' => 'Click "Confirm Recharge" (or press Enter).',
                        'result' => 'The server ledger credits Account 2010 (Prepaid Wallet Liability) and debits Cash (1010) or Bank Clearing (1020). Official thermal voucher prints.',
                        'tip' => 'Updated wallet balance syncs immediately to the Member Portal (/member) and turnstile gates.',
                    ],
                ],
                'formula' => 'Journal: Debit Cash / Bank Clearing (1010/1020) • Credit Prepaid Member Liability (2010)',
                'policy_note' => 'Wallet recharge is an advance deposit liability. It does not count as restaurant food, bar, or contractor revenue until spent on items.',
            ],

            'cashier-guest-pass' => [
                'tab' => 'cashier',
                'tab_label' => 'Counter & POS',
                'icon' => '🎟️',
                'title' => 'Visiting Guest Day Passes & Gate Slips',
                'summary' => 'Registering sponsored visiting guests and issuing paid Bar, Card Room, or Sports day entry passes with barcode turnstile verification.',
                'target_role' => 'Reception & Counter Cashiers',
                'estimated_time' => '2 mins read',
                'tags' => ['guest', 'day pass', 'turnstile', 'gate', 'visitor', 'slip', 'bar pass', 'sponsor'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Switch Customer Type to Visiting Guest',
                        'instruction' => 'In the Counter POS customer panel, toggle customer mode to "Visiting Guest", or register the guest first under Membership & Guest Desk -> Guests.',
                        'result' => 'The workspace validates guest identity alongside an active Sponsoring Member or Affiliated Reciprocal Member.',
                        'tip' => 'Suspended or inactive members cannot sponsor guests.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Add Applicable Guest Pass Tariff',
                        'instruction' => 'Add the applicable Day Pass item (Bar & Card Room Day Pass, Badminton Guest Pass, or Gym Guest Pass) via F3.',
                        'result' => 'Statutory entry tariff and GST add to the order; promotional discounts are blocked on guest passes.',
                        'tip' => 'Guest passes cannot be discounted or reduced to ₹0.00.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Collect Payment & Print Gate Permit',
                        'instruction' => 'Collect immediate payment via Cash (F9) or Digital UPI (F10) (or sponsor-paid wallet if settled upfront by sponsor). Print the receipt or Guest Entry Permit modal from Back Office.',
                        'result' => 'Guest pass activates immediately for the valid visit window and turnstile access.',
                        'tip' => 'Unpaid draft orders never activate access permits.',
                    ],
                ],
                'formula' => 'Guest Pass ACTIVE = Valid Sponsor + Confirmed Payment Receipt (No ₹0 Passes)',
                'policy_note' => 'Zero-credit rules apply equally to all visiting guests.',
            ],

            'cashier-zero-stock' => [
                'tab' => 'cashier',
                'tab_label' => 'Counter & POS',
                'icon' => '📉',
                'title' => 'Stock Verification, Negative Stock Mode & POS Sports Plans',
                'summary' => 'Understanding strict out-of-stock blocking vs Admin-enabled zero/negative stock billing on POS, plus automatic Sports Subscription activation from POS tariff sales.',
                'target_role' => 'Counter Cashiers, Beverage Managers & Supervisors',
                'estimated_time' => '3 mins read',
                'tags' => ['zero stock', 'negative stock', 'out of stock', 'allow_zero_stock_sales', 'sports plan', 'pos', 'badminton', 'gym'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Default Strict Inventory Enforcement vs Zero-Stock Override',
                        'instruction' => 'By default, POS blocks selling bar items whose recorded milliliter stock is insufficient. When physical stock has arrived before storekeepers post Supplier Receipts, Management can enable "Allow Selling Zero / Negative Stock Items on POS" under Club Master Setup -> System Settings (Tab 2).',
                        'result' => 'When enabled, cashiers can bill smoothly while inventory tracks temporarily into negative ml until the Supplier Receipt is entered.',
                        'tip' => 'Always reconcile negative inventory promptly by posting inward stock receipts under Procurement & Food Contractor.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Automatic Sports Plan Activation via POS Tariffs',
                        'instruction' => 'Select the club member via F2 and add a sports plan tariff (e.g. BADMINTON-MONTHLY or GYM-MONTHLY) via F3, then collect payment.',
                        'result' => 'Checkout automatically issues or continuously extends the member\'s active FacilitySubscription alongside the paid Bill, PaymentReceipt, and GL 4040 entry.',
                        'tip' => 'Monthly and annual member sports plans cannot be billed to non-member Visiting Guests.',
                    ],
                ],
                'formula' => 'Negative Stock + Subsequent Supplier Receipt Inward = Reconciled Physical ml Balance',
                'policy_note' => 'Zero/Negative stock mode does not bypass payment collection or financial COGS logging.',
            ],

            // =========================================================================
            // 2. FRONT DESK, SPORTS & MEMBERS
            // =========================================================================
            'reception-deck' => [
                'tab' => 'reception',
                'tab_label' => 'Front Desk, Sports & Members',
                'icon' => '🏨',
                'title' => 'Accommodation Deck (Rooms 101–105), Housekeeping & Maintenance',
                'summary' => 'Operating the live Accommodation Deck: advance room bookings, balance collection before key handover, Check-Out Due alerts, housekeeping status, and Under Maintenance locks.',
                'target_role' => 'Front Desk Reception & Duty Managers',
                'estimated_time' => '4 mins read',
                'tags' => ['accommodation deck', 'room rack', 'rooms', '101', '102', '103', '104', '105', 'check-in', 'checkout', 'housekeeping', 'maintenance', 'dirty', 'clean'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Read Live Room Status & Departure Badges',
                        'instruction' => 'Open Operations & Front Desk -> Accommodation Deck. Each room (101–105) shows live state: Vacant Ready (Green), Occupied (Teal), Reserved (Blue), Needs Cleaning / DIRTY (Amber), or Maintenance (Rose). Watch for "Check-Out Due Today" and "Overdue Check-Out" alerts on occupied rooms.',
                        'result' => 'Staff immediately identify occupied stays, back-to-back arrivals, dirty turnovers, and maintenance holds.',
                        'tip' => 'Use the Date Navigator at top to inspect future availability or planned maintenance return dates.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Book Room with Advance Payment',
                        'instruction' => 'Click "Book Room" on an available card (or Operations & Front Desk -> Room Bookings). Select guest/member, dates, nightly rate, and collect payment via Cash, UPI, or Member Wallet.',
                        'result' => 'Reserves [check_in, check_out) with database interval locking so same-day check-out followed by afternoon check-in works without double booking.',
                        'tip' => 'Rooms remain blocked from check-in until full tariff balance is settled.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Collect Balance & Process Check-In',
                        'instruction' => 'When a guest arrives on a Reserved card, verify identity documents. If any balance remains, click "Collect Balance Before Key Handover", settle via Cash/UPI/Wallet, and confirm Check-In.',
                        'result' => 'Check-In requires the room to be Clean & Ready and fully paid; status switches to Occupied.',
                        'tip' => 'Dirty or Under Maintenance rooms are blocked at the server level from receiving check-ins.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Process Check-Out & Housekeeping Turnaround',
                        'instruction' => 'On departure, click Check-Out on the occupied card. The system closes the stay and sets the room to Needs Cleaning (DIRTY) on both the reservation and master facility record.',
                        'result' => 'Occupied cards release immediately and flag housekeeping turnover.',
                        'tip' => 'Once housekeeping inspects the room, click "Mark as Cleaned" (or "Status") to restore Clean & Ready.',
                    ],
                    [
                        'number' => 5,
                        'title' => 'Switch Status / Place Room Under Maintenance',
                        'instruction' => 'Click "Status" or "Change Room Status" on any vacant, unpaid reserved, or maintenance card. Choose Clean & Ready, Needs Cleaning, or Under Maintenance (entering reason and expected return date).',
                        'result' => 'Placing a room Under Maintenance checks against occupied stays and overlapping bookings before creating a maintenance block. Use "Change Status / End Maintenance" to reopen the room.',
                        'tip' => 'Even newly created vacant rooms with no stay history store cleanliness status permanently.',
                    ],
                ],
                'formula' => 'Booking (Locked Interval) → Fully Paid + Clean Check-In → Check-Out (DIRTY) → Mark as Cleaned / End Maintenance',
                'policy_note' => 'Half-open intervals [check_in, check_out), full upfront funding, and Clean readiness checks prevent double bookings and unpaid stays.',
            ],

            'reception-courts' => [
                'tab' => 'reception',
                'tab_label' => 'Front Desk, Sports & Members',
                'icon' => '🏸',
                'title' => 'Indoor Badminton Court Schedule (6:00 AM – 9:00 PM)',
                'summary' => 'Booking 60-minute hourly slots on the club\'s Indoor Wooden Badminton Court, automatic sports plan waivers, pay-per-use tariff previews, and past-slot locking.',
                'target_role' => 'Front Desk & Sports Desk Staff',
                'estimated_time' => '2 mins read',
                'tags' => ['badminton', 'court', 'schedule', 'slots', 'sports', 'subscription', 'hourly', 'waiver', 'reservation', '4 players'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Open Indoor Badminton Court Daily Schedule',
                        'instruction' => 'Navigate to Operations & Front Desk -> Badminton Court Schedule. Inspect the 15 hourly 60-minute slots from 6:00 AM to 9:00 PM for Today or any future date.',
                        'result' => 'Shows counts for Available vs Booked slots. Elapsed hours for today appear locked as "Slot Elapsed / Time Expired" and cannot be booked retroactively.',
                        'tip' => 'One Indoor Wooden Badminton Court is shared club-wide; up to 4 players may share a booked hourly slot under the booking member\'s name.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Select Booking Member (Player 1) & Check Entitlement',
                        'instruction' => 'Click "Book This Slot" on an Open card and search/select the primary active Club Member reserving the court.',
                        'result' => 'The booking modal immediately queries active Sports Subscriptions and displays either "Active Subscription (Free Entry) • ₹0.00" or the applicable Pay-Per-Use Court Tariff.',
                        'tip' => 'The booking member manages joining co-players (up to 4 total). Non-marking gum-sole shoes are mandatory.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Confirm Reservation or Cancel Release',
                        'instruction' => 'Review the entitlement preview and click "Confirm & Reserve Slot". To free up an upcoming slot, click "Cancel Booking" on the Booked slot card and confirm.',
                        'result' => 'Confirmed bookings receive a reference number and lock the hour atomically against concurrent double bookings.',
                        'tip' => 'Casual players without an active Monthly/Annual plan should clear applicable day-use charges per club sports billing policy.',
                    ],
                ],
                'formula' => '15 Hourly Slots (06:00–21:00) • Max 4 Players/Slot • Active Plan = ₹0.00 Waiver | Casual Play = Standard Tariff',
                'policy_note' => 'Past time slots cannot be booked back-dated, and simultaneous bookings for the same date and hour are blocked by database locks.',
            ],

            'reception-subscriptions' => [
                'tab' => 'reception',
                'tab_label' => 'Front Desk, Sports & Members',
                'icon' => '🏋️',
                'title' => 'Sports Plans Enrollment & Upfront Payment Settlement',
                'summary' => 'Subscribing active club members to Monthly or Annual Badminton and Gym plans with mandatory upfront payment, GST split, receipt generation, and GL 4040 posting.',
                'target_role' => 'Front Desk, Membership Desk & Accounts',
                'estimated_time' => '3 mins read',
                'tags' => ['sports subscription', 'badminton monthly', 'gym monthly', 'enrollment', 'upfront payment', 'receipt', '4040', 'gst'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Open Sports Subscription Enrollment',
                        'instruction' => 'Open Membership & Guest Desk -> Sports Subscriptions -> "Add Sports Membership" (or click "+ Enroll & Collect Sports Plan Fee" directly inside a Member\'s 360° Profile Facilities tab).',
                        'result' => 'Selects or prefills the active Club Member and opens the subscription + settlement form.',
                        'tip' => 'Only members in active club standing can hold Monthly or Annual sports subscriptions.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Select Facility, Plan Duration & Tariff',
                        'instruction' => 'Choose Facility (Badminton Court or Fitness Gym), Plan Type (Monthly or Annual), and Sports Tariff (e.g. BADMINTON-MONTHLY). Start date auto-calculates exact calendar end date.',
                        'result' => 'Form calculates taxable base, GST (18%), and payable total automatically (or handles custom tax-inclusive totals).',
                        'tip' => 'You can also sell BADMINTON-MONTHLY or GYM-MONTHLY tariffs at Counter POS to activate the subscription automatically on checkout.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Collect Mandatory Payment Upfront & Save',
                        'instruction' => 'Choose Payment Tender (Cash, Digital UPI/Card, Bank Transfer, Cheque, or Member Prepaid Wallet), optionally link an open Cashier Shift (when collecting cash into a drawer), enter any bank/UPI reference, and submit.',
                        'result' => 'Atomically creates the FacilitySubscription, paid Bill (department: sports), PaymentReceipt, Wallet debit (if prepaid), and balanced GL journal (DR Cash/Bank/Wallet → CR 4040 Sports Revenue + 2030 GST).',
                        'tip' => 'Subscriptions never post to credit—zero-rupee bypasses on paid tariffs are blocked automatically.',
                    ],
                ],
                'formula' => 'Upfront Paid Plan: DR 1010/1020/2010 → CR 4040 Sports Revenue (+ 2030 GST) • Links PaymentReceipt & Bill',
                'policy_note' => 'Paid subscription bills post directly to Sports Income (4040) without double-counting against Prepaid Member Liability (2010).',
            ],

            'reception-members-fees' => [
                'tab' => 'reception',
                'tab_label' => 'Front Desk, Sports & Members',
                'icon' => '🪪',
                'title' => 'Member Registration, Two-Tier Dues, Renewal & KYC Vault',
                'summary' => 'Separating One-Time Admission Fees from recurring Annual Subscriptions, on-registration settlement, Member 360° Profile renewal, private KYC scans, and RFID cards.',
                'target_role' => 'Membership Secretary, Front Desk & Management',
                'estimated_time' => '3 mins read',
                'tags' => ['membership', 'admission fee', 'annual subscription', 'renewal', 'kyc', 'vault', 'rfid', 'dependents', 'locker', 'intro letter'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Understand Two-Tier Membership Fee Structure',
                        'instruction' => 'Membership Categories configure two independent fees: One-Time Admission/Entrance Fee (lifetime capital fee collected once) and Annual Subscription Dues (recurring yearly maintenance charge).',
                        'result' => 'Life Members may have ₹0/year recurring dues while Ordinary/Corporate categories renew annually.',
                        'tip' => 'Subsequent annual renewals strictly avoid re-charging the one-time admission fee once paid.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Register Member, Upload Private KYC & Collect Fees',
                        'instruction' => 'Under Membership & Guest Desk -> Members, enter personal & statutory ID details, optionally upload scanned Identity Proof & signed Application Form to the private vault, assign RFID card UID if ready, and toggle "Collect Membership Fees Now" to record immediate tender.',
                        'result' => 'Creates Person, Member, zero-balance WalletAccount, RFID token, encrypted private KYC scans, and PaymentReceipt if collected.',
                        'tip' => 'Private KYC documents stream securely via permission-gated routes and are blocked for cashiers.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Manage Dues, Cards, Dependents & Letters on Member 360° Profile',
                        'instruction' => 'Open any Member to inspect Overview, Wallet Ledger, Bills, Facilities & Sports, Gate Access, and Dependents tabs. Use "Collect Fee Now" or "Renew Subscription" for dues, manage RFID Card block/replacement, toggle dependent wallet authorization, assign Lockers, or generate Affiliated Club Introduction Letters.',
                        'result' => 'All member administration actions keep wallet balances, audit notes, and gate credentials synchronized.',
                        'tip' => 'Dependents default to wallet debit disabled until explicitly authorized by primary member approval.',
                    ],
                ],
                'formula' => 'Initial Registration = One-Time Admission + Year-1 Annual Dues • Renewal = Recurring Annual Subscription Only',
                'policy_note' => 'KYC document access requires explicit authorization; uncollected membership fees flag "Fee Pending Payment" prominently until settled.',
            ],

            'reception-notices' => [
                'tab' => 'reception',
                'tab_label' => 'Front Desk, Sports & Members',
                'icon' => '🔔',
                'title' => 'Member Bulletins, Dues Reminders & Member Portal Notices',
                'summary' => 'Publishing targeted bulletins and urgent alerts from Admin to the Member Portal PWA, broadcasting 1-click pending dues reminders, and tracking read receipts.',
                'target_role' => 'Reception, Accounts & Club Secretariat',
                'estimated_time' => '2 mins read',
                'tags' => ['notices', 'bulletins', 'dues reminder', 'member portal', 'pwa', 'broadcast', 'announcement'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Publish Targeted Club Bulletins',
                        'instruction' => 'Open Membership & Guest Desk -> Member Bulletins & Notices. Set title, department, priority (Info, Reminder, Urgent, Event), sticky banner flag, and audience scope (All Members, By Category, Pending Dues, or Individual Member).',
                        'result' => 'Published bulletins appear immediately inside the Member PWA header bell counter and Club Notices tab.',
                        'tip' => 'Mark Urgent or Reminder notices as sticky banners when immediate member visibility on login is required.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Broadcast 1-Click Annual Dues Reminders',
                        'instruction' => 'On the Member Bulletins & Notices table header, click "Broadcast Dues Reminder". Review the live count of overdue members and confirm.',
                        'result' => 'Publishes a targeted dues notice to pending-fee members (and queues WhatsApp/SMS outbox dispatches when enabled).',
                        'tip' => 'Members can dismiss banners and mark items as read inside /member/dashboard -> Club Notices.',
                    ],
                ],
                'formula' => 'Targeted Audiences: All Members • By Category • Pending Annual Dues • Specific Member',
                'policy_note' => 'Member notices support audit read-receipts and multi-channel outbox queuing without blocking staff workflows.',
            ],

            // =========================================================================
            // 3. FINANCE & ACCOUNTS
            // =========================================================================
            'finance-coa' => [
                'tab' => 'finance',
                'tab_label' => 'Finance & Accounts',
                'icon' => '⚖️',
                'title' => 'General Ledger & 5-Digit Numbering Standard (COA)',
                'summary' => 'Understanding the Chart of Accounts hierarchy under Finance & Audit Ledger -> Chart of Accounts, protected system ledgers, and operational expense heads.',
                'target_role' => 'Club Accountant, Finance Manager & Auditors',
                'estimated_time' => '4 mins read',
                'tags' => ['chart of accounts', 'coa', '5-digit', 'gl', 'expense heads', '1010', '1020', '2010', '2020', '4040', '5110', '5120', 'system accounts'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => '1000s Series: Asset Accounts',
                        'instruction' => 'Assets represent club holdings: 1010 Cash in Hand (till drawers & cash-in-safe), 1020 Club Bank Accounts & Clearing, and 1030 Bar Inventory Stores.',
                        'result' => 'Debits increase assets; credits decrease assets.',
                        'tip' => '1010 reconciles to till counts and cash deposits; 1020 series reconcile to club bank passbooks.',
                    ],
                    [
                        'number' => 2,
                        'title' => '2000s Series: Liability Accounts',
                        'instruction' => 'Liabilities track obligations: 2010 Prepaid Wallet Advances (active unspent member wallet balances), 2020 External Food Contractor Payable, and 2030 GST Output Liability.',
                        'result' => 'Credits increase liabilities; debits reduce liabilities when settled or redeemed.',
                        'tip' => 'Account 2010 must reconcile identically to the sum of all member prepaid wallet balances.',
                    ],
                    [
                        'number' => 3,
                        'title' => '3000s Series: Capital & Equity Accounts',
                        'instruction' => 'Represents club capital reserves (3010) and retained surplus accumulated across fiscal years.',
                        'result' => 'Establishes the baseline net worth on the club Balance Sheet.',
                        'tip' => 'Year-end surplus or deficit transfers into retained capital.',
                    ],
                    [
                        'number' => 4,
                        'title' => '4000s Series: Revenue & Income Accounts',
                        'instruction' => 'Operational income streams: 4010 Guest Passes & Cards, 4020 Bar Sales, 4030 Guest Room Accommodations, and 4040 Sports & Facility Subscriptions.',
                        'result' => 'Credits recognize earned revenue when bills settle.',
                        'tip' => 'Wallet recharge never credits 4xxx; revenue posts only when goods or services are billed.',
                    ],
                    [
                        'number' => 5,
                        'title' => '5000s Series: Cost of Goods Sold & Operating Expenses',
                        'instruction' => 'Includes 5010 Liquor COGS, 5020 Food Contractor Pass-Through, 5100s Utilities (KSEB Electricity, Generator Diesel), 5200s Repairs & Maintenance, and 5400s Administration.',
                        'result' => 'Debits capture expenditures; reversals post via linked adjustments only.',
                        'tip' => 'Additional 5xxx expense heads can be added via Chart of Accounts or in-line from Expense Vouchers.',
                    ],
                ],
                'formula' => '5-Digit Standard: 1000s Assets • 2000s Liabilities • 3000s Equity • 4000s Revenue • 5000s Expenses',
                'policy_note' => 'Core automated accounts (1010, 1020, 1030, 2010, 2020, 2030, 3010, 4010–4040, 5010–5030) carry System Lock protection and cannot be deleted or renumbered.',
            ],

            'finance-banking' => [
                'tab' => 'finance',
                'tab_label' => 'Finance & Accounts',
                'icon' => '🏦',
                'title' => 'Club Bank Accounts, Cash Deposits & Inter-Bank Transfers',
                'summary' => 'Managing club Current/Savings/FD bank accounts, depositing physical till cash via Contra Vouchers (DR 1020 / CR 1010), inter-bank transfers, and printable bank passbooks.',
                'target_role' => 'Club Accountant, Treasurer & Duty Managers',
                'estimated_time' => '3 mins read',
                'tags' => ['bank account', 'deposit cash', 'contra voucher', 'inter-bank transfer', 'passbook', 'ifsc', 'upi', 'challan', '1010', '1020'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Register Club Bank Accounts',
                        'instruction' => 'Navigate to Finance & Audit Ledger -> Bank Accounts & Deposits -> "Add Bank Account". Record bank name, branch, account number, IFSC code, classification, optional Club UPI VPA, linked GL asset head, and opening balance.',
                        'result' => 'Accounts become selectable across cash deposits, operating expense payouts, and contractor settlements.',
                        'tip' => 'Mark your main operating current account as "Primary" for default disbursement visibility.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Record Cash-to-Bank Remittance (Contra Deposit)',
                        'instruction' => 'When counter cash is physically deposited at the bank, click "Deposit Counter Cash" (or row "Deposit Cash"). Choose Destination Bank Account, deposit amount, date, pay-in slip/challan #, and depositing staff name.',
                        'result' => 'Validates open fiscal period, increments the selected bank ledger balance, and posts Contra Journal: Debit Bank Asset (1020/102x) / Credit Cash in Hand (1010).',
                        'tip' => 'Always record the bank pay-in slip or CDM transaction ID for reconciliation.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Inter-Bank Transfers & Printable Passbook',
                        'instruction' => 'Use "Inter-Bank Transfer" to move funds between club bank accounts (with UTR/cheque reference). Click "Passbook" on any row to inspect historical deposits/payouts and print an A4 statement.',
                        'result' => 'Maintains complete treasury audit traceability across all club bank ledgers.',
                        'tip' => 'Transfer and deposit journals never alter revenue or P&L totals.',
                    ],
                ],
                'formula' => 'Cash Remittance (Contra): DR Bank Account (1020) / CR Cash in Hand (1010)',
                'policy_note' => 'Deposits and transfers validate open Fiscal Periods and cannot be backdated into closed accounting periods.',
            ],

            'finance-expenses' => [
                'tab' => 'finance',
                'tab_label' => 'Finance & Accounts',
                'icon' => '🧾',
                'title' => 'Recording Operating Expenses & In-Line Head Creation',
                'summary' => 'Recording clubhouse operating expenses under Procurement & Food Contractor -> Operating Expenses, choosing Cash or Club Bank payout, and creating 5xxx heads in-line.',
                'target_role' => 'Accountants & Finance Supervisors',
                'estimated_time' => '3 mins read',
                'tags' => ['expense', 'voucher', 'kseb', 'diesel', 'maintenance', 'in-line', 'operating expenses', 'bank account'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Open Operating Expenses Form',
                        'instruction' => 'Navigate to Procurement & Food Contractor -> Operating Expenses and click "Record Operating Expense".',
                        'result' => 'Opens the voucher entry form with an automatically sequenced voucher reference.',
                        'tip' => 'Ensure the expense date falls inside an Open Fiscal Period.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Select or Create Expense Head In-Line',
                        'instruction' => 'Pick the operating expense head (e.g. KSEB Electricity, Generator Diesel, Building Maintenance). If needed, click the "+" action beside the dropdown to add a new 5xxx account head on the fly.',
                        'result' => 'The new 5000-series expense head saves immediately and stays selected in the voucher.',
                        'tip' => 'Assign accurate department attribution so Departmental P&L remains truthful.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Enter Amount, Payee & Payment Source',
                        'instruction' => 'Enter payee, voucher amount in Rupees, bill/invoice number, and payment method (Cash, NEFT/RTGS, Cheque, Bank Transfer) along with the disbursing Club Bank Account when paid from bank.',
                        'result' => 'Prepares double-entry debit to Expense Head (5xxx) and credit to Cash (1010) or selected Bank (1020).',
                        'tip' => 'Recording bank UTR or cheque reference links the disbursement into that Bank Account\'s Passbook.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Approve & Print Expense Voucher',
                        'instruction' => 'Submit and approve the voucher. Open the View Voucher modal whenever a signed physical printout is needed.',
                        'result' => 'Posts immutably to the General Ledger and decrements Cash or Bank balance accordingly.',
                        'tip' => 'Posted expense vouchers cannot be edited or deleted; use linked reversal journals for corrections.',
                    ],
                ],
                'formula' => 'Journal: Debit Expense Head (5xxx) • Credit Cash in Hand (1010) or Bank Clearing (1020)',
                'policy_note' => 'Closed fiscal periods block backdated expense postings; all vouchers require supporting vendor bills.',
            ],

            'finance-contractor' => [
                'tab' => 'finance',
                'tab_label' => 'Finance & Accounts',
                'icon' => '🍲',
                'title' => 'External Food Contractor Daily Settlement & Bank Payout Cycle',
                'summary' => 'End-to-end procedure for reconciling daily kitchen sales, generating morning settlement batches, approval, bank payouts (NEFT/RTGS/Cheque), refund adjustments, and Tally sync.',
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
                        'instruction' => 'Every morning, navigate to Procurement & Food Contractor -> Contractor Settlements. Click "Generate Daily Settlement Batch", select yesterday\'s business date, and click Submit.',
                        'result' => 'Scans all paid food lines, aggregates Gross Food Sales, Taxes, Net Sales, and applies pending deductions, creating a batch (e.g. SET-YYYYMMDD-001) in Draft status.',
                        'tip' => 'Batch generation is idempotent. Generating again for the same date returns the existing batch without duplicates.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Audit & Approve Settlement Batch',
                        'instruction' => 'Inspect Gross Food Sales, Tax, Deductions, and Net Payable on the Draft batch. An authorized Manager or Accountant clicks "Approve Batch" and confirms.',
                        'result' => 'Status moves to Approved and posts GL voucher: Debit Contractor Food Expense (5020) / Credit Food Contractor Payable (2020).',
                        'tip' => 'Requires settlement approval permission (settlements.approve) under Roles & Form Access.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Execute & Record Bank Payout (NEFT / RTGS / Cheque)',
                        'instruction' => 'Click "Record Payout" on the approved batch. Select the Disbursing Club Bank Account, choose instrument (NEFT, RTGS, IMPS, or Cheque), enter the Bank UTR / Cheque Number and Payout Amount, and submit.',
                        'result' => 'Status updates to Paid (or Partially Paid), posts Debit 2020 Food Contractor Payable / Credit 1020 Bank Clearing, and updates the bank ledger balance.',
                        'tip' => 'Always record the exact bank UTR/cheque number for monthly bank reconciliation.',
                    ],
                    [
                        'number' => 5,
                        'title' => 'Automated Refund Handling (Post-Settlement Adjustments)',
                        'instruction' => 'When an authorized refund is issued on a food bill belonging to a locked/paid batch, the historical batch stays intact and a Negative Contractor Adjustment records automatically.',
                        'result' => 'The next daily settlement batch deducts that adjustment cleanly from the contractor\'s upcoming payout.',
                        'tip' => 'Eliminates manual spreadsheet tracking while keeping settled batches immutable.',
                    ],
                    [
                        'number' => 6,
                        'title' => 'Reconciliation & Tally Prime Audit',
                        'instruction' => 'Open Finance & Audit Ledger -> Executive Reports Hub -> Food Contractor Settlement & Payables Audit to review turnover, adjustments, bank payouts, and residual payables while the outbox syncs vouchers to Tally.',
                        'result' => 'Export formatted CSV sheets or print A4 audit reports anytime.',
                        'tip' => 'Residual payables should equal zero after each full settlement bank payout.',
                    ],
                ],
                'formula' => 'Net Payout = Gross Food Sales - Deductions • Journal 1 (Approve): Dr 5020 / Cr 2020 • Journal 2 (Payout): Dr 2020 / Cr 1020',
                'policy_note' => 'Strict Club Bylaw: Unused member prepaid wallet funds do NOT feed contractor settlements. Once approved and paid, settlement batches remain permanently locked.',
            ],

            'finance-printing' => [
                'tab' => 'finance',
                'tab_label' => 'Finance & Accounts',
                'icon' => '🖨️',
                'title' => 'Printing Receipts, Vouchers, Member Statements & Desk Reference',
                'summary' => 'Best-practice guide for printing every document cleanly on white paper without dashboard toolbars: POS 80mm thermal receipts, Admin modal vouchers, Member statements, reports, and POS cheat sheets.',
                'target_role' => 'All Staff & Members',
                'estimated_time' => '2 mins read',
                'tags' => ['print', 'receipt', 'thermal', 'invoice', 'voucher', 'statement', 'passbook', 'pdf', 'desk reference'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Counter POS 80mm Thermal Receipts (Shortcut: P)',
                        'instruction' => 'After tendering a sale or recharge at /pos, the Thermal Receipt dialog opens. Click "Print Receipt" or press P.',
                        'result' => 'Isolates only the 80mm receipt (with club GSTIN, itemized lines, promo savings, and wallet balance) and hides the POS grid and tabs bar.',
                        'tip' => 'Set your thermal printer paper width to 80mm under Club Master Setup -> System Settings -> Receipt Printing.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Back-Office Printable Modals (Invoices, Vouchers, Passes, Passbooks)',
                        'instruction' => 'Open any View/Print modal in Admin — Bills & Invoices, General Ledger Journals, Supplier Receipts, Operating Expenses, Guests Day Passes, Member Statements, Bank Passbooks, or Member Profile Invoices & Intro Letters — and click its Print button.',
                        'result' => 'Background tables, sidebars, and modal close buttons automatically hide so only the crisp light-mode document prints.',
                        'tip' => 'Member Wallet Ledger statements include an automatic Club Statement Letterhead and full remark wrapping.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Executive Reports & Laminated POS Desk Reference',
                        'instruction' => 'On Executive Reports Hub, click Print Report (or Export CSV for Excel). On this Help page, click "Print this procedure" for the active SOP or "Print Desk Reference" in the top right for a 1-page POS hotkey sheet.',
                        'result' => 'Automatically formats tables without horizontal scrollbar clipping and resets Help print mode afterward.',
                        'tip' => 'In the Member Portal (/member), the Wallet tab prints an official Prepaid Statement and individual Bills print full GST Tax Invoices.',
                    ],
                ],
                'formula' => 'POS Receipt: Press P • Modal Documents & Member Portal: Print Dialog Auto-Isolates White Paper View',
                'policy_note' => 'All printable outputs force high-contrast light backgrounds with dark ink regardless of screen theme.',
            ],

            // =========================================================================
            // 4. PROCUREMENT & STORES
            // =========================================================================
            'inventory-purchases' => [
                'tab' => 'inventory',
                'tab_label' => 'Procurement & Stores',
                'icon' => '📦',
                'title' => 'Inward Stock Purchases, Bulk Discounts & Wholesale Suppliers',
                'summary' => 'Posting incoming liquor consignments under Procurement & Food Contractor -> Supplier Receipts, registering GSTIN suppliers in-line, and applying wholesale bulk discounts cleanly.',
                'target_role' => 'Store Custodian & Beverage Manager',
                'estimated_time' => '3 mins read',
                'tags' => ['purchase receipt', 'supplier receipts', 'ksbc', 'supplier', 'gstin', 'discount', 'scheme', 'weighted average', 'mac', 'inventory'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Open Supplier Receipts Form',
                        'instruction' => 'Navigate to Procurement & Food Contractor -> Supplier Receipts and click "Record Stock Inward".',
                        'result' => 'Opens the supplier consignment intake form.',
                        'tip' => 'Keep the KSBC FL-4 or distributor tax invoice and transport permit beside you.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Select Wholesale Supplier (or Add with + Button)',
                        'instruction' => 'Pick the distributor from the dropdown (or manage them anytime under Wholesale Suppliers). Click "+" inside the field to onboard a new vendor with their 15-digit GSTIN.',
                        'result' => 'Validates GSTIN format and links statutory supplier audit metadata.',
                        'tip' => 'Pre-seed recurring distributors under Wholesale Suppliers to speed up receiving.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Enter Case/Bottle Quantities & Wholesale Discounts',
                        'instruction' => 'Select the bar item, enter bottle count & pack size (ml) or total base quantity in ml, gross invoice value, any bulk cash/trade discount (in Rupees) plus scheme remarks, and GST %.',
                        'result' => 'All physical ml enter bar stock while moving weighted-average cost per ml calculates using exact net discounted purchase value.',
                        'tip' => 'If any item previously went negative during Zero-Stock POS sales mode, inward receipt quantities reconcile it back automatically.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Post to Stores & General Ledger',
                        'instruction' => 'Click Create to save the inward receipt.',
                        'result' => 'Increments Bar Inventory Stores (Account 1030) at net discounted cost, credits Clearing/Payable (1020), and makes stock immediately billable.',
                        'tip' => 'Use View Receipt modal anytime to print a Goods Received Note (GRN) for audit files.',
                    ],
                ],
                'formula' => 'Net Weighted Cost/ml = (Prior Stock Value + Net Discounted Line Value) / (Prior ml + Inward ml)',
                'policy_note' => 'All wholesale liquor receipts must match valid excise invoices; supplier bulk discounts adjust inventory asset value rather than cashier POS prices.',
            ],

            'inventory-dispensing' => [
                'tab' => 'inventory',
                'tab_label' => 'Procurement & Stores',
                'icon' => '🍸',
                'title' => 'Bar Liquor Dispensing & Milliliter (ml) Accuracy',
                'summary' => 'Tracking bar inventory down to exact milliliters (30ml, 60ml, 750ml) including poured promotional bonus servings to eliminate dispensing variance.',
                'target_role' => 'Bar Staff & Stock Auditors',
                'estimated_time' => '2 mins read',
                'tags' => ['bar', 'liquor', 'ml', 'peg', '30ml', '60ml', '750ml', 'bottle', 'dispensing', 'cogs', 'stock'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => '30ml Small Peg Dispensing',
                        'instruction' => 'When a 30ml small peg is selected (key 1), exactly 30 ml deduct from physical inventory.',
                        'result' => 'Debits Liquor COGS (5010) and credits Bar Stores (1030) for 30ml at current moving average cost.',
                        'tip' => 'One standard 750ml bottle equals 25 small pegs of 30ml.',
                    ],
                    [
                        'number' => 2,
                        'title' => '60ml Large Peg Dispensing',
                        'instruction' => 'When a 60ml large peg is selected (key 2), exactly 60 ml deduct from physical inventory.',
                        'result' => 'Logs 60ml COGS and updates remaining bottle-equivalent stock counts.',
                        'tip' => 'One 750ml bottle equals 12.5 large pegs.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Full Bottles (750ml) & Promotional Free Units',
                        'instruction' => 'Sealed bottle sales deduct 750ml. When a Buy X Get Y Free bonus peg is claimed on POS, that bonus ml is also deducted automatically.',
                        'result' => 'Guarantees book stock matches actual physical pour volume even on promotional nights.',
                        'tip' => 'Any accidental spillage or breakage must be logged under Stock Adjustments—never concealed.',
                    ],
                ],
                'formula' => 'Stock Deduction = 30ml (Small) • 60ml (Large) • 750ml (Bottle) • Promo Free ml Deducted Identically',
                'policy_note' => 'Spillages and breakages require authorized adjustment logging so bar shrinkage audits remain accurate.',
            ],

            // =========================================================================
            // 5. EXECUTIVE GOVERNANCE & ADMIN
            // =========================================================================
            'executive-staff' => [
                'tab' => 'executive',
                'tab_label' => 'Executive & Admin',
                'icon' => '🛡️',
                'title' => 'Staff User Management, Granular Form Access & Login Gateway',
                'summary' => 'Managing staff users under Staff & User Access, controlling form-by-form permissions in Roles & Form Access (/admin/roles), deactivating accounts safely, and role-based login routing.',
                'target_role' => 'Club Secretary, General Manager & System Admins',
                'estimated_time' => '3 mins read',
                'tags' => ['staff', 'users', 'rbac', 'roles', 'permissions', 'form access', 'admin', 'manager', 'cashier', 'reception', 'accountant', 'deactivate', 'login'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Register Staff Users & Manage Overrides',
                        'instruction' => 'Go to System & Access Control -> Staff & User Access and click "Register New Staff User". Enter name, employee ID, designation, email, mobile, password, roles, and any direct per-user permission overrides.',
                        'result' => 'Creates an active staff login while obeying strict role segregation (e.g. non-admin staff cannot access restricted settings).',
                        'tip' => 'Always verify Employee ID matches HR payroll records for clean audit logs.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Configure Granular Role & Form-Access Matrix (/admin/roles)',
                        'instruction' => 'Open System & Access Control -> Roles & Form Access. Edit built-in roles (Manager, Accountant, Auditor, Reception, Cashier) across 53 form, view, and approval permissions, or click "New Custom Role" to build specialized roles.',
                        'result' => 'Sidebars, global search results, action buttons, and server controllers enforce updated permissions immediately. Built-in roles offer a 1-click "Restore Role Defaults" reset inside Edit Role.',
                        'tip' => 'Built-in Admin safeguards prevent locking out the sole administrator or disabling your own admin account.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Unified Login Tabs & Role-Based Landing Routing',
                        'instruction' => 'Staff and Members share the consolidated gateway at /login ("Staff Terminal | Member Portal" tabs). Upon sign-in, staff land automatically on their primary workspace (/admin for management/finance, /admin/room-rack for Reception, or /pos for Cashiers).',
                        'result' => 'Prevents unauthorized cross-panel access while letting every role start work in one click.',
                        'tip' => 'Members sign into /member via the "Member Portal" tab on the same login screen.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Deactivating Staff & Audit Preservation',
                        'instruction' => 'When an employee leaves, toggle their status to Inactive/Deactivated rather than deleting. Users with shifts, bills, receipts, or journals are delete-protected.',
                        'result' => 'Blocks login immediately while preserving every historical bill, shift count, and GL audit signature.',
                        'tip' => 'Always close open cashier shifts before deactivating a cashier account.',
                    ],
                ],
                'formula' => 'Access Control = Roles & Form Access (53 Permissions) + Optional User Overrides + Active Status Flag',
                'policy_note' => 'No role or permission—including Administrator—can override Zero-Credit rules, post unbalanced journals, or edit closed fiscal periods.',
            ],

            'executive-settings' => [
                'tab' => 'executive',
                'tab_label' => 'Executive & Admin',
                'icon' => '⚙️',
                'title' => 'System Settings, Fiscal Periods & Tally Integration',
                'summary' => 'Configuring club GSTIN/identity, POS recharge limits, Zero-Stock mode, room/court timings, Tally Prime LAN sync, thermal printer width, and closing Fiscal Periods.',
                'target_role' => 'System Admins, Club Secretary & Accountants',
                'estimated_time' => '3 mins read',
                'tags' => ['system settings', 'gstin', 'tally', 'fiscal periods', 'locks', 'integrations', 'zero stock', 'printer', '80mm'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Configure Club Policies in System Settings',
                        'instruction' => 'Open Club Master Setup -> System Settings across 5 tabs: Club Identity & Statutory (GSTIN, PAN, footer text), Billing & Cashier Policies (opening float, min/max recharge, lost card fee, Zero-Stock toggle), Facilities & Timings, Tally Prime LAN connection, and 80mm Receipt Printing.',
                        'result' => 'Saved settings dynamically update receipts, POS limits, schedules, and hardware options club-wide.',
                        'tip' => 'Only users granted settings.manage permission can edit System Settings.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Lock Closed Accounting Periods',
                        'instruction' => 'Under Finance & Audit Ledger -> Fiscal Periods & Locks, close audited past months or fiscal years to lock historical postings.',
                        'result' => 'Blocks backdated bills, expenses, contra deposits, stock receipts, and manual journals inside closed dates.',
                        'tip' => 'Always verify Bank Reconciliation and Contractor Settlement batches before locking a period.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Monitor Integrations & Tally Outbox',
                        'instruction' => 'Visit Finance & Audit Ledger -> Integrations & Hardware to test LAN connectivity to Tally Prime and review queued outbox messages or simulator mode warnings.',
                        'result' => 'Even if Tally is temporarily offline, club billing continues uninterrupted with retryable outbox synchronization.',
                        'tip' => 'Never expose Tally or biometric door controller ports directly to the public internet.',
                    ],
                ],
                'formula' => 'System Settings (5 Policy Tabs) • Fiscal Period Locks • Non-Blocking Outbox Queue',
                'policy_note' => 'Tally or hardware downtime never blocks confirmed paid sales at the counter.',
            ],

            'executive-reports' => [
                'tab' => 'executive',
                'tab_label' => 'Executive & Admin',
                'icon' => '📊',
                'title' => 'Executive Reports Hub (8 Statutory Reports)',
                'summary' => 'Generating, exporting to CSV, and printing the 8 core management and statutory audit statements from Finance & Audit Ledger -> Executive Reports Hub.',
                'target_role' => 'Management Committee, Secretary & Auditors',
                'estimated_time' => '3 mins read',
                'tags' => ['reports', 'executive reports', 'p&l', 'income expenditure', 'till recon', 'statutory', 'audit', 'csv'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Sales & Collections Report',
                        'instruction' => 'Segregates external Cash and Digital UPI receipts from internal Prepaid Wallet redemptions across every department and counter.',
                        'result' => 'Guarantees prepaid redemptions are never double-counted as new cash receipts.',
                        'tip' => 'Use 1-click presets (Today, Yesterday, This Week, This Month, Last Month, Fiscal Year).',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Income & Expenditure Statement',
                        'instruction' => 'Operational P&L comparing earned departmental revenues against consumption COGS and approved expenses over your selected range.',
                        'result' => 'Computes Net Operating Surplus or Deficit cleanly with white-paper printable banner.',
                        'tip' => 'Export to UTF-8 CSV or click Print Report for committee packets.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Daily Till Drawer Reconciliation',
                        'instruction' => 'Shift-by-shift audit comparing Opening Float + Cash collections against Counted Closing Cash and variance notes.',
                        'result' => 'Highlights cashier shortages or surpluses immediately.',
                        'tip' => 'Reviewed daily by Accounts prior to filing cash deposit slips.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Food Contractor Settlement Audit',
                        'instruction' => 'Audits daily kitchen turnover, refund adjustments, bank payouts (UTR), and residual contractor payables.',
                        'result' => 'Verifies pass-through accuracy excluding unspent member wallet recharges.',
                        'tip' => 'Cross-check directly against Contractor Settlements batches.',
                    ],
                    [
                        'number' => 5,
                        'title' => 'Bar Stock & Consumption Report',
                        'instruction' => 'Lists opening/closing ml stock, moving weighted-average valuation, sales + promo consumption COGS, wastage, and reorder alerts.',
                        'result' => 'Flags low-stock bottles and any temporary negative balances.',
                        'tip' => 'Compare closing ml book balance against monthly bar stocktakes.',
                    ],
                    [
                        'number' => 6,
                        'title' => 'Sports Facility & Plan Income',
                        'instruction' => 'Breaks down Monthly/Annual Badminton & Gym subscription collections, court utilization metrics, and casual hourly earnings.',
                        'result' => 'Measures sports department performance without double-counting guest passes.',
                        'tip' => 'Tracks active plans and renewal trends.',
                    ],
                    [
                        'number' => 7,
                        'title' => 'Zero-Credit & Payables Audit',
                        'instruction' => 'Audits every member ledger to confirm 0 overdrafts, 0 member bad debts, contractor payables, and Trial Balance equality.',
                        'result' => 'Statutory confirmation of Zero-Credit compliance across the club.',
                        'tip' => 'Essential appendix for annual external audit packs.',
                    ],
                    [
                        'number' => 8,
                        'title' => 'Departmental Profitability (P&L)',
                        'instruction' => 'Separates net contribution across Bar & Lounge, Dining, Guest Rooms, Sports & Badminton, and Guest Entry Passes.',
                        'result' => 'Shows true department-wise margins using consumption costing rather than raw cash receipts.',
                        'tip' => 'Account 2010 subledger reconciliation confirms unspent wallet backing at all times.',
                    ],
                ],
                'formula' => '8 Reports: Sales • Income/Exp • Till Recon • Contractor • Bar Stock • Sports • Zero-Credit • Dept P&L',
                'policy_note' => 'Reports read immutable double-entry ledger journals and match Accounting Period Locks automatically.',
            ],
        ];
    }
}
