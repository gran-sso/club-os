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
     * Match query across topic metadata, tags, formulas, procedural steps, and field references.
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

        foreach ($topic['field_sections'] ?? [] as $section) {
            foreach (['section_title', 'section_note'] as $sectionKey) {
                $text = (string) ($section[$sectionKey] ?? '');
                if ($text !== '' && str_contains(strtolower($text), $normalizedQuery)) {
                    return true;
                }
            }

            foreach ($section['fields'] ?? [] as $field) {
                foreach (['name', 'type', 'meaning', 'effect'] as $fieldKey) {
                    $text = (string) ($field[$fieldKey] ?? '');
                    if ($text !== '' && str_contains(strtolower($text), $normalizedQuery)) {
                        return true;
                    }
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
                'field_sections' => [
                    [
                        'section_title' => 'Counter Shift Opening & Back-Office Shift Form Fields',
                        'section_note' => 'Configured when opening a POS counter shift or auditing shift records under Commercial & Counters -> Cashier Shifts.',
                        'fields' => [
                            [
                                'name' => 'Billing Counter Station',
                                'type' => 'Required Select',
                                'meaning' => 'Identifies the physical POS counter terminal (for example, Bar Main Counter or Reception Counter) taking orders during this shift.',
                                'effect' => 'Binds all bills, cash drawer movements, and terminal receipts created during the shift to that station.',
                            ],
                            [
                                'name' => 'Station Cashier / Staff',
                                'type' => 'Required Staff User',
                                'meaning' => 'The signed-in cashier legally accountable for drawer cash and customer collections during this shift.',
                                'effect' => 'Recorded on every shift audit log and Daily Till Drawer Reconciliation report.',
                            ],
                            [
                                'name' => 'Fiscal Business Date',
                                'type' => 'Required Date',
                                'meaning' => 'The official accounting day to which this shift’s sales and cash collections belong.',
                                'effect' => 'Determines which daily Z-report, contractor settlement cutoff, and GL posting date receive the shift’s transactions. Must fall inside an open Fiscal Period.',
                            ],
                            [
                                'name' => 'Opening Cash Float (₹)',
                                'type' => 'Rupee Amount (>= ₹0)',
                                'meaning' => 'Starting petty cash and loose change already counted in the drawer before the first sale (guided by System Settings -> Standard Drawer Float).',
                                'effect' => 'Added to Cash Sales and Cash Recharges when calculating Expected Drawer Cash at shift close.',
                            ],
                            [
                                'name' => 'Shift Operational Status (Open / Closed)',
                                'type' => 'Status',
                                'meaning' => 'Open allows active billing on POS; Closed seals the shift after final drawer count and variance sign-off.',
                                'effect' => 'Closed shifts are permanent audit records and cannot be reopened or edited.',
                            ],
                            [
                                'name' => 'Physical Counted Cash & Handover Notes (POS Close Modal)',
                                'type' => 'Closing Audit Inputs',
                                'meaning' => 'Actual currency notes/coins counted in the drawer at handover plus explanation for any excess or shortage.',
                                'effect' => 'Calculates Closing Cash Variance (Counted minus Expected); all Running Tabs must be 0 before the system permits closing.',
                            ],
                        ],
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
                'field_sections' => [
                    [
                        'section_title' => 'Promotions & Offers Form Fields (Commercial & Counters -> Promotions & Offers)',
                        'section_note' => 'Defines automated "Buy X Get Y" POS promotional rules that cashiers claim via the 1-click bonus button without manual discounting.',
                        'fields' => [
                            [
                                'name' => 'Promotion Code & Offer Name',
                                'type' => 'Required Text',
                                'meaning' => 'Short unique internal campaign code and human-readable offer label shown on POS promo badges and bills.',
                                'effect' => 'Appears on the Counter POS search card ("Buy 2 Get 1 Free") and on printed thermal receipts when bonus units are claimed.',
                            ],
                            [
                                'name' => 'Active Status',
                                'type' => 'Toggle (On / Off)',
                                'meaning' => 'Master switch enabling or pausing the promotion on Counter POS.',
                                'effect' => 'Even within valid dates, toggling OFF immediately hides the offer from all POS cashiers.',
                            ],
                            [
                                'name' => 'Buy Paid Units (buy_qty) & Get Bonus Units (get_qty)',
                                'type' => 'Required Integers',
                                'meaning' => 'How many full-price units a patron must purchase ("Buy X") to unlock bonus units ("Get Y") — e.g., Buy 2, Get 1.',
                                'effect' => 'POS unlocks the claim button once cart quantity reaches Paid Units; every qualifying multiple triggers another bonus eligibility.',
                            ],
                            [
                                'name' => 'Bonus Discount Percentage (%)',
                                'type' => 'Percentage (Default: 100%)',
                                'meaning' => 'Price reduction applied to the bonus unit(s). Leave at 100% for completely free units, or set 50% for half-price bonus servings.',
                                'effect' => 'GST is calculated only on the net taxable price charged; inventory still deducts full milliliters for poured free units.',
                            ],
                            [
                                'name' => 'Eligible Tariffs',
                                'type' => 'Multi-Select Tariffs',
                                'meaning' => 'Specific priced bar or counter selling tariffs included in the offer. Zero-rate items and visiting guest pass tariffs (GUEST-*) are excluded.',
                                'effect' => 'Keep distinct serving sizes (such as 30ml small pegs vs 60ml large pegs) in separate offers so free units match the exact pour size bought.',
                            ],
                            [
                                'name' => 'Customer Scope (Members Only / All Customers)',
                                'type' => 'Select (Default: Members Only)',
                                'meaning' => 'Restricts promotional pricing strictly to club members/dependents, or allows eligible resident/visiting guests.',
                                'effect' => 'When set to Members Only, POS suppresses the free-unit claim button whenever a non-member guest is selected.',
                            ],
                            [
                                'name' => 'Max Free Units Quota Cap',
                                'type' => 'Optional Cap',
                                'meaning' => 'Maximum cumulative bonus units this promotion may issue across its entire lifetime.',
                                'effect' => 'Automatically stops further bonus claims once total issued free units hit the cap.',
                            ],
                            [
                                'name' => 'Effective From & Effective To Dates',
                                'type' => 'Date Window',
                                'meaning' => 'Start and end calendar dates during which Counter POS honors this offer.',
                                'effect' => 'POS ignores offers outside these dates so expired weekend specials never leak into normal shifts.',
                            ],
                        ],
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
                'field_sections' => [
                    [
                        'section_title' => 'Guest Registration & KYC Form (Membership & Guest Desk -> Guests)',
                        'section_note' => 'Guest identity, sponsoring member, occupant, and payer remain strictly separate so every gate entry and day pass is auditable.',
                        'fields' => [
                            [
                                'name' => 'Guest First Name, Last Name & Mobile Number (+91)',
                                'type' => 'Required Personal Details',
                                'meaning' => 'Legal name and reachable Indian/international mobile phone of the visiting non-member.',
                                'effect' => 'Printed on the Guest Entry Permit and verified by gate/lounge security.',
                            ],
                            [
                                'name' => 'Statutory Identity Proof (KYC) & Identity Document Number',
                                'type' => 'Required KYC (Aadhaar / Passport / DL / Voter ID / PAN)',
                                'meaning' => 'Government-issued photo ID type and exact document number inspected at reception.',
                                'effect' => 'Required under club safety policy before granting entry to the Bar or Card Room.',
                            ],
                            [
                                'name' => 'Sponsor Classification (Primary vs Affiliated Reciprocal Member)',
                                'type' => 'Required Select',
                                'meaning' => 'Indicates whether the escorting host is a Malabar Club primary member or a verified visitor from a reciprocal affiliated club.',
                                'effect' => 'Filters the Sponsoring Member dropdown to eligible active sponsors only.',
                            ],
                            [
                                'name' => 'Sponsoring Member',
                                'type' => 'Required Sponsor Lookup',
                                'meaning' => 'Specific active club member (or verified affiliated member) vouching for the visitor’s conduct.',
                                'effect' => 'Blocks suspended or dues-pending members from sponsoring guests; links the sponsor to the guest visit record.',
                            ],
                            [
                                'name' => 'Date of Visit',
                                'type' => 'Required Date (Default: Today)',
                                'meaning' => 'Single calendar day on which the visitor’s day pass and entry permission apply.',
                                'effect' => 'Scopes active visitor lists and printed gate slips to that business date.',
                            ],
                            [
                                'name' => 'Visit / Admission Status',
                                'type' => 'Registered / Checked In / Departed',
                                'meaning' => 'Tracks whether the visitor has only registered (pass unpaid/pending), paid and entered premises (Checked In), or exited (Departed).',
                                'effect' => 'Gate admission requires a paid Day Pass receipt alongside Active/Checked-In clearance; zero-rupee free passes are never permitted.',
                            ],
                            [
                                'name' => 'Visit Remarks & Security Notes',
                                'type' => 'Optional Notes',
                                'meaning' => 'Visitor city, purpose of visit, or front-desk remarks recorded by reception.',
                                'effect' => 'Visible to reception, security gate checks, and post-visit audit logs.',
                            ],
                        ],
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
                'field_sections' => [
                    [
                        'section_title' => 'Stock & Sports Entitlement Controls Highlighted Here',
                        'section_note' => 'Explains how System Settings stock policy interacts with POS billing and automatic Sports Plan activation.',
                        'fields' => [
                            [
                                'name' => 'Allow Selling Zero / Negative Stock Items on POS (System Settings -> Tab 2)',
                                'type' => 'Admin Override Toggle (Default: OFF)',
                                'meaning' => 'Controls whether bar items at 0 ml system stock can be sold temporarily while waiting for a storekeeper to post an inward Supplier Receipt.',
                                'effect' => 'When OFF, POS blocks the item at 0 stock. When ON, POS allows paid checkout and tracks negative ml variance until reconciled by purchase inward; payment collection rules never relax.',
                            ],
                            [
                                'name' => 'Current On-Hand Stock vs Base Serving Volume (30ml / 60ml / 750ml)',
                                'type' => 'Real-Time Milliliter Check',
                                'meaning' => 'Available ml left in Bar Inventory compared against the selected peg or bottle size.',
                                'effect' => 'Prevents silent shrinkage and alerts bar managers via Low Stock Reorder Thresholds.',
                            ],
                            [
                                'name' => 'Sports Subscription Tariffs on POS (e.g., BADMINTON-MONTHLY / GYM-MONTHLY)',
                                'type' => 'Auto-Entitled Tariffs',
                                'meaning' => 'Selling a sports plan tariff on Counter POS links the paid bill directly to the member’s Sports Facility Entitlement.',
                                'effect' => 'Activates Badminton or Gym validity immediately upon confirmed payment and credits 4040 Sports Revenue.',
                            ],
                        ],
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
                'field_sections' => [
                    [
                        'section_title' => 'Room Booking & Stay Form Fields (Operations & Front Desk -> Room Bookings / Accommodation Deck)',
                        'section_note' => 'Guest identity, sponsoring member, stay dates, upfront payment, and housekeeping turnover status are tracked separately.',
                        'fields' => [
                            [
                                'name' => 'Room / Facility (Rooms 101–105)',
                                'type' => 'Required Room Select',
                                'meaning' => 'Selects which guest suite (101–105) is reserved for the stay.',
                                'effect' => 'Concurrent database locking checks this room over `[check_in, check_out)` to prevent double bookings.',
                            ],
                            [
                                'name' => 'Sponsoring Member & Visiting Guest',
                                'type' => 'Linked Identity Fields',
                                'meaning' => 'Sponsor Member identifies who vouches for/hosts the stay; Guest identifies the registered visitor checking in (with KYC verified).',
                                'effect' => 'Separates who is staying in the room from who sponsors or pays for it in audit logs.',
                            ],
                            [
                                'name' => 'Check-In Date & Check-Out Date',
                                'type' => 'Date Range [check_in, check_out)',
                                'meaning' => 'Arrival date and morning departure date. Treated as a half-open stay interval so one guest can check out the same morning another checks in.',
                                'effect' => 'Calculates total nights billed and blocks overlapping reservations or Maintenance windows.',
                            ],
                            [
                                'name' => 'Nightly Rate (₹) & Upfront Paid Amount (₹)',
                                'type' => 'Rupee Amounts',
                                'meaning' => 'Tariff per night plus confirmed advance/full payment collected via Cash, Digital UPI/Card, or Member Prepaid Wallet.',
                                'effect' => 'Zero-credit rule: full stay balance must show Paid before the deck allows Check-In and key handover.',
                            ],
                            [
                                'name' => 'Reservation Status & Room Housekeeping Status',
                                'type' => 'Clean / DIRTY / Maintenance',
                                'meaning' => 'Reservation tracks Reserved -> Checked In -> Checked Out; Housekeeping tracks Clean & Ready vs Needs Cleaning (DIRTY) vs Under Maintenance.',
                                'effect' => 'Check-Out automatically flips room readiness to DIRTY until staff mark it Clean & Ready.',
                            ],
                            [
                                'name' => 'Stay Notes',
                                'type' => 'Optional Notes',
                                'meaning' => 'Arrival ETA, special room requests, or maintenance details.',
                                'effect' => 'Visible to front desk shifts during check-in and check-out.',
                            ],
                        ],
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
                'field_sections' => [
                    [
                        'section_title' => 'Badminton Court Booking Form Fields (Operations & Front Desk -> Badminton Court Schedule / Court Bookings)',
                        'section_note' => 'Applies single-court locking rules for Malabar Club’s Indoor Wooden Badminton Court.',
                        'fields' => [
                            [
                                'name' => 'Sports Court',
                                'type' => 'Required Facility Select',
                                'meaning' => 'Selects the active Indoor Wooden Badminton Court resource.',
                                'effect' => 'Enforces exclusive 1-booking-per-hour capacity on that court slot (up to 4 players per slot).',
                            ],
                            [
                                'name' => 'Booking Club Member',
                                'type' => 'Required Active Member',
                                'meaning' => 'Primary active member in good standing responsible for the court reservation.',
                                'effect' => 'Checks whether the member holds an active Badminton subscription plan and prevents suspended members from booking.',
                            ],
                            [
                                'name' => 'Booking Date & Hourly Time Slot',
                                'type' => 'Date + 60-Min Interval (06:00 AM – 09:00 PM)',
                                'meaning' => 'Day of play (today or future within System Settings -> Advance Booking Window) and exact 60-minute hourly slot.',
                                'effect' => 'Rejects past time slots on today’s date and locks the hour against concurrent overlapping bookings.',
                            ],
                            [
                                'name' => 'Covered by Member Sports Subscription Plan',
                                'type' => 'Toggle (Default: ON)',
                                'meaning' => 'Indicates the booking member holds a paid Monthly/Annual Badminton Plan and qualifies for ₹0.00 hourly fee waiver.',
                                'effect' => 'When unchecked or for casual non-plan play, standard casual court hourly tariff must be collected upfront.',
                            ],
                            [
                                'name' => 'Booking Status (Confirmed / Completed / Cancelled / No Show)',
                                'type' => 'Status Select',
                                'meaning' => 'Tracks whether the reservation is active (Confirmed), played (Completed), released early (Cancelled), or missed (No Show).',
                                'effect' => 'Cancelling a booking frees that hourly slot immediately on the Badminton Court Schedule.',
                            ],
                            [
                                'name' => 'Booking Notes',
                                'type' => 'Optional Notes',
                                'meaning' => 'Opponent names, doubles roster, or club tournament note.',
                                'effect' => 'Shown on the booking slot detail for court stewards and reception.',
                            ],
                        ],
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
                'field_sections' => [
                    [
                        'section_title' => 'Sports Plan Enrollment Fields (Membership & Guest Desk -> Sports Subscriptions)',
                        'section_note' => 'Gym and Badminton subscriptions are separate paid entitlements layered on top of active club membership.',
                        'fields' => [
                            [
                                'name' => 'Club Member',
                                'type' => 'Required Active Member',
                                'meaning' => 'Active club member enrolling in the facility plan (suspended or inactive base memberships cannot enroll).',
                                'effect' => 'Binds plan access privileges and biometric/gate checks to this member’s profile.',
                            ],
                            [
                                'name' => 'Facility (`badminton` / `gym`) & Plan Type (`monthly` / `annual`)',
                                'type' => 'Selects',
                                'meaning' => 'Selects the indoor wooden Badminton Court or Fitness Gym and whether the pass runs Monthly or Annually.',
                                'effect' => 'Filters matching Sports Tariffs and calculates exact calendar end dates automatically.',
                            ],
                            [
                                'name' => 'Sports Plan Tariff (e.g. BADMINTON-MONTHLY)',
                                'type' => 'Linked Tariff',
                                'meaning' => 'Master commercial tariff governing the plan rate and GST slab.',
                                'effect' => 'Populates Total Plan Fee (Incl. GST) so GST 50:50 CGST/SGST posts accurately to 4040 Sports Revenue and 2030 GST Output Liability.',
                            ],
                            [
                                'name' => 'Start Date (`starts_at`) & Exclusive Expiry Date (`ends_at`)',
                                'type' => 'Plan Validity Dates',
                                'meaning' => 'First active day of the subscription and the exclusive expiration boundary date.',
                                'effect' => 'Plan privileges work up to the day before Expiry Date; overlapping plans are prevented on active slots.',
                            ],
                            [
                                'name' => 'Total Fee Incl. GST (₹) & Upfront Payment Tender',
                                'type' => 'Mandatory Payment (Cash / UPI / Bank / Cheque / Wallet)',
                                'meaning' => 'Full GST-inclusive plan charge collected upfront before activating the subscription (plus optional open Cashier Shift link for drawer cash).',
                                'effect' => 'Generates an immutable Bill, PaymentReceipt, and GL journal (DR Cash/Bank/Wallet Liability -> CR 4040 Sports Revenue + 2030 GST).',
                            ],
                            [
                                'name' => 'Subscription Status',
                                'type' => 'Status',
                                'meaning' => 'Active enables fee-waived court bookings and gym access; Expired or Cancelled ends entitlement.',
                                'effect' => 'Governs automated court waiver detection and Member 360° Profile Facilities badge.',
                            ],
                        ],
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
                'field_sections' => [
                    [
                        'section_title' => 'Personal Identity & Private KYC Vault (Member Form)',
                        'section_note' => 'Documents uploaded here are stored in protected private disk storage outside public URLs and require KYC view permission.',
                        'fields' => [
                            [
                                'name' => 'First Name, Last Name, Mobile Phone & Email',
                                'type' => 'Member Identity Fields',
                                'meaning' => 'Legal name and primary contact details used for POS member lookup, Member Portal sign-in, and receipts.',
                                'effect' => 'Indexed in Counter POS customer search (F2) and Member Bulletins delivery.',
                            ],
                            [
                                'name' => 'ID Proof Type & ID Proof Number',
                                'type' => 'Statutory KYC Identification',
                                'meaning' => 'Government identity card category (such as Aadhaar/Passport/DL/Voter ID/PAN) and document number.',
                                'effect' => 'Provides identity verification for admission audits and biometric device mapping.',
                            ],
                            [
                                'name' => 'KYC ID Proof File (Max 5 MB) & Application Form File (Max 10 MB)',
                                'type' => 'Private Vault Upload',
                                'meaning' => 'Scanned copy of the applicant’s photo ID (max 5 MB) and signed physical membership application form (max 10 MB).',
                                'effect' => 'Saved privately (never exposed on public web paths); downloadable only by staff with member KYC view permission.',
                            ],
                        ],
                    ],
                    [
                        'section_title' => 'Membership Category, Validity & Two-Tier Fee Settlement',
                        'section_note' => 'Supports onboarding brand-new applicants (collecting fees now) as well as recording already-paid legacy members.',
                        'fields' => [
                            [
                                'name' => 'Membership Number & Membership Category (+ Inline Create)',
                                'type' => 'Required Classification',
                                'meaning' => 'Unique club roll number and membership tier (Ordinary, Life, Corporate, etc.). Click "+" beside Category to create a new tier with its Admission & Annual fees.',
                                'effect' => 'Determines statutory fee defaults, voting eligibility, and permanent vs renewable expiry behaviour.',
                            ],
                            [
                                'name' => 'Status, Joined Date & Valid Until Date',
                                'type' => 'Lifecycle & Expiry Dates',
                                'meaning' => 'Membership standing (Active/Suspended/Expired), date of enrollment, and subscription renewal date (leave Valid Until blank for Life/Permanent categories).',
                                'effect' => 'Inactive/expired members are blocked from sponsoring guests, booking badminton courts, or activating new sports plans.',
                            ],
                            [
                                'name' => 'Historical Fees Already Paid (+ Paid Date & Receipt Reference)',
                                'type' => 'Legacy Member Option',
                                'meaning' => 'Marks an existing club member whose Admission & Annual dues were already paid prior to software rollout, storing their past receipt # and paid date for audit.',
                                'effect' => 'Sets fee status as paid without creating duplicate today cash receipts or inflating today’s shift cash drawer.',
                            ],
                            [
                                'name' => 'Collect Initial Fee Now (Admission ₹, Annual ₹, Tender & Reference)',
                                'type' => 'New Admission Payment Option',
                                'meaning' => 'Collects One-Time Admission Fee plus Year-1 Annual Subscription Dues immediately upon registration via Cash, UPI, Bank Transfer, Cheque, or Wallet.',
                                'effect' => 'Automatically issues a paid Fee Receipt and posts Admission/Annual revenue into the General Ledger.',
                            ],
                            [
                                'name' => 'RFID Smartcard Number',
                                'type' => 'Optional Card UID',
                                'meaning' => 'Physical RFID card tag serial linked to the member’s prepaid wallet account.',
                                'effect' => 'Identifies the member on POS tap; remember the server ledger—not the plastic card—owns the ₹ balance.',
                            ],
                        ],
                    ],
                    [
                        'section_title' => 'Membership Categories Setup (Membership Categories Form)',
                        'section_note' => 'Configured under Membership Categories; defines category fees used across registrations and renewals.',
                        'fields' => [
                            [
                                'name' => 'Category Code & Category Title / Designation',
                                'type' => 'e.g. ORD, LIFE, CORP',
                                'meaning' => 'Short immutable classification code and full formal tier title printed on cards and notices.',
                                'effect' => 'Matches CSV `category_code` during Bulk Member Import and groups Member Bulletin targeting.',
                            ],
                            [
                                'name' => 'One-Time Admission Fee (₹) & Annual Subscription Dues (₹)',
                                'type' => 'Two-Tier Rupee Schedule',
                                'meaning' => 'Capital entrance fee charged once at joining vs recurring yearly maintenance dues (leave Annual Dues ₹0 for Life/Permanent categories).',
                                'effect' => 'Auto-fills fee amounts during new registration and yearly renewals; renewals charge only Annual Dues.',
                            ],
                            [
                                'name' => 'Club Voting Rights & Admissions Active',
                                'type' => 'Policy Toggles',
                                'meaning' => 'Controls AGM/committee voting entitlement and whether reception can assign new applicants to this category.',
                                'effect' => 'Turning Admissions Active OFF hides the category for new applicants while keeping existing members unaffected.',
                            ],
                        ],
                    ],
                ],
                'formula' => 'Initial Registration = One-Time Admission + Year-1 Annual Dues • Renewal = Recurring Annual Subscription Only',
                'policy_note' => 'KYC document access requires explicit authorization; uncollected membership fees flag "Fee Pending Payment" prominently until settled.',
            ],

            'reception-bulk-import' => [
                'tab' => 'reception',
                'tab_label' => 'Reception & Membership',
                'icon' => '📥',
                'title' => 'Bulk Member CSV Import & Opening Wallet Migration',
                'summary' => 'How to onboard existing club members in bulk via CSV, handle already-paid fees without demanding payment again, migrate opening prepaid balances safely, and fix failed rows.',
                'target_role' => 'Club Administrators, Membership Clerks & Accountants',
                'estimated_time' => '5 mins read',
                'tags' => ['bulk import', 'csv', 'migration', 'existing members', 'opening balance', 'historical fees', 'errors csv', 'valid until', 'tally'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Download CSV Template & Prepare Existing Member Rows',
                        'instruction' => 'Open Membership & Guest Desk -> Members -> "Bulk Import CSV" and click "Download Sample CSV Template". Populate existing member records using one row per member (matching your configured Membership Category codes such as ORD or LIFE).',
                        'result' => 'Structured UTF-8 CSV file ready for validation preview without duplicating or corrupting existing active members.',
                        'tip' => 'Keep phone numbers and RFID Smartcard Numbers unique across rows; leave valid_until blank for Permanent/Life members if using a default validity fallback.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Configure Fee Mode, Validity Fallback & Tally Options Before Preview',
                        'instruction' => 'Choose whether imported existing members have Already Paid Historical Fees or Pending Dues, set how blank valid_until dates should behave, pick an open Fiscal Period date for any Opening Wallet Balances, and leave Tally Outbox Queuing unchecked unless you specifically want opening balances pushed to Tally.',
                        'result' => 'Prevents the system from asking already-paid existing members for admission fees again while cleanly backing any positive opening prepaid balances on Liability 2010.',
                        'tip' => 'If opening balances are already inside your Tally Trial Balance, keep "Queue Tally Outbox" UNCHECKED so Tally does not double-count opening equity/liability.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Run Dry-Run Preview & Inspect Validation Table',
                        'instruction' => 'Upload your CSV file and click Preview Import. The dry-run checks every row for duplicate membership numbers, invalid category codes, duplicate RFID tags, negative wallet balances, or missing dates before modifying the database.',
                        'result' => 'Shows an exact breakdown of Clean Ready Rows vs Errored Rows. You can download a Pre-Import Errors CSV to fix issues offline if needed.',
                        'tip' => 'Preview performs zero database writes—you can safely test and re-upload files as many times as needed.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Import Clean Rows & Download Failed Rows CSV',
                        'instruction' => 'Click "Import Valid Rows" to commit clean records transactionally. If any rows fail row-level validation, click "Download Errored Rows CSV" to retrieve only the unimported rows with exact error reasons, fix them in Excel/Calc, and re-upload just that corrected CSV.',
                        'result' => 'Clean rows become active immediately with optional historical fee status and opening wallet balance, while errored rows stay isolated for safe retry.',
                        'tip' => 'Every failed-rows export keeps original column headers plus the exact error_message column so re-import requires zero manual reformatting.',
                    ],
                ],
                'field_sections' => [
                    [
                        'section_title' => 'Bulk Import Batch Settings (Import Members Page)',
                        'section_note' => 'These controls apply across all clean rows committed in the current CSV upload batch.',
                        'fields' => [
                            [
                                'name' => 'Existing Member Fee Mode (`already_paid` vs `pending`)',
                                'type' => 'Required Migration Choice',
                                'meaning' => 'Already Paid marks legacy members as historically settled without creating new fee bills; Pending flags imported members as awaiting fee collection.',
                                'effect' => 'When set to Already Paid, the system will NOT ask migrated members for admission or current-year renewal fees.',
                            ],
                            [
                                'name' => 'Blank Valid Until Behaviour (`fy_end` / `one_year_from_joined` / `custom_date` / `require`)',
                                'type' => 'Validity Date Rule',
                                'meaning' => 'Determines what expiry date applies if a CSV row leaves `valid_until` empty: end of current financial year, 1 year after `joined_at`, a chosen Custom Date, or strictly reject blanks.',
                                'effect' => 'Saves manual typing across hundreds of annual members while still respecting explicit dates provided in the CSV.',
                            ],
                            [
                                'name' => 'Opening Balance Posting Date',
                                'type' => 'Required Date (When Wallet Balances > ₹0)',
                                'meaning' => 'Accounting journal date used to post legacy Prepaid Smartcard Opening Balances imported from the CSV.',
                                'effect' => 'Must fall inside an Open Fiscal Period. Posts DR Opening Balance Offset / CR Prepaid Wallet Advances (2010) for balances >= ₹0 (negative opening credit balances are rejected).',
                            ],
                            [
                                'name' => 'Queue Opening Balance Journals in Tally Outbox',
                                'type' => 'Checkbox (Default: OFF)',
                                'meaning' => 'Controls whether opening wallet migration journals generated during import are queued for Tally export.',
                                'effect' => 'Keep OFF if your legacy balances are already captured in Tally’s Opening Trial Balance; check ON only if starting a fresh Tally company from scratch.',
                            ],
                        ],
                    ],
                    [
                        'section_title' => 'CSV File Columns Reference',
                        'section_note' => 'Download the template directly from the Bulk Import page for exact header spelling.',
                        'fields' => [
                            [
                                'name' => '`membership_number`, `first_name`, `last_name`, `category_code`',
                                'type' => 'Core Required Columns',
                                'meaning' => 'Unique club ID, member legal name, and exact short Membership Category code (such as `ORD` or `LIFE`).',
                                'effect' => 'Missing or unknown category codes move that row to the Errored Rows list without blocking valid rows.',
                            ],
                            [
                                'name' => '`phone`, `email`, `status`, `joined_at`, `valid_until`',
                                'type' => 'Contact & Standing Columns',
                                'meaning' => 'Reachable phone/email, standing (`active`/`suspended`/`expired`), joining date, and optional membership expiry date (YYYY-MM-DD).',
                                'effect' => 'Powers Reception search, dues tracking, and portal access.',
                            ],
                            [
                                'name' => '`rfid_card_number` & `opening_balance_rupees`',
                                'type' => 'Prepaid Smartcard Migration Columns',
                                'meaning' => 'Existing card UID tag (must be unique) and carried-forward prepaid Rupee balance (>= ₹0; blank defaults to ₹0.00).',
                                'effect' => 'Credits the member’s server wallet ledger on import using integer paise precision.',
                            ],
                            [
                                'name' => '`historical_receipt_ref`, `historical_fee_paid_at`, `id_proof_type`, `id_proof_number`, `notes`',
                                'type' => 'Optional Audit & KYC Metadata Columns',
                                'meaning' => 'Paper receipt reference, historical paid date, statutory ID details, and remarks carried over from manual registers.',
                                'effect' => 'Preserves paper audit trail directly inside each imported Member Profile.',
                            ],
                        ],
                    ],
                ],
                'formula' => 'Preview CSV → Commit Clean Rows + Credit Opening Wallet (>= ₹0) → Export & Fix Remaining Errored Rows CSV',
                'policy_note' => 'Negative credit balances remain strictly forbidden even during legacy CSV migration; already-paid members never trigger duplicate fee demands.',
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
                'field_sections' => [
                    [
                        'section_title' => 'Member Bulletins & Notices Form Fields (Membership & Guest Desk -> Member Bulletins & Notices)',
                        'section_note' => 'Controls notice appearance, priority styling, Member Portal sticky banners, and audience filtering.',
                        'fields' => [
                            [
                                'name' => 'Notice Title & Department',
                                'type' => 'Required Headline & Scope',
                                'meaning' => 'Short headline shown in the Member Portal bulletin board and the issuing club section (e.g., Administration, Bar, Sports, Accounts).',
                                'effect' => 'Helps members quickly identify announcements relevant to sports schedules, AGM dates, or dues.',
                            ],
                            [
                                'name' => 'Priority Level (Info / Reminder / Urgent / Event)',
                                'type' => 'Priority Badge Select',
                                'meaning' => 'Visual urgency badge distinguishing routine circulars from fee reminders, urgent closures, or club events.',
                                'effect' => 'Changes badge color on the Member Portal notice list.',
                            ],
                            [
                                'name' => 'Sticky Portal Banner (`is_banner`) & Active (`is_active`)',
                                'type' => 'Visibility Toggles',
                                'meaning' => 'Sticky Banner pins the notice prominently across the top of Member Portal until dismissed; Active controls live publishing.',
                                'effect' => 'Use Sticky Banner sparingly for urgent closures or AGM notices; uncheck Active to archive old bulletins without deleting history.',
                            ],
                            [
                                'name' => 'Target Audience Scope (`all` / `dues_pending` / `category` / `individual`)',
                                'type' => 'Audience Delivery Filter',
                                'meaning' => 'Chooses whether all active members see the notice, only members with unpaid annual dues, a specific Membership Category (e.g., Life), or one chosen Member.',
                                'effect' => 'Generates targeted MemberPortal delivery records and read-receipt logs strictly for matching recipients.',
                            ],
                            [
                                'name' => 'Bulletin Content',
                                'type' => 'Notice Body Text',
                                'meaning' => 'Full message text displayed to targeted members when viewing the bulletin.',
                                'effect' => 'Delivered directly inside the Member Portal bulletin reader.',
                            ],
                        ],
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
                'field_sections' => [
                    [
                        'section_title' => 'Chart of Accounts Form Fields (Finance & Audit Ledger -> Chart of Accounts)',
                        'section_note' => 'Core automated accounts (`is_system`) lock Code, Type, and Active fields so financial reports and Tally ledgers never break.',
                        'fields' => [
                            [
                                'name' => 'Ledger Account Code',
                                'type' => 'Required Unique Code (4–32 Chars)',
                                'meaning' => 'Unique accounting code following the 1xxx Asset, 2xxx Liability, 3xxx Equity, 4xxx Revenue, 5xxx Expense convention (e.g., 5110 for Electricity).',
                                'effect' => 'Locked on system accounts (1010–5030). Used for sorting Trial Balance statements and matching Tally Master ledgers.',
                            ],
                            [
                                'name' => 'Account Name / Ledger Head',
                                'type' => 'Required Ledger Name',
                                'meaning' => 'Official title shown across Expense Vouchers, P&L Statements, General Ledger drill-downs, and Tally XML exports.',
                                'effect' => 'Should match or clearly correspond to the ledger name created in Tally Gateway -> Masters.',
                            ],
                            [
                                'name' => 'Accounting Classification (`type`)',
                                'type' => 'Asset / Liability / Equity / Revenue / Expense',
                                'meaning' => 'Determines whether balances sit on the Balance Sheet (Asset/Liability/Equity) or the Income & Expenditure P&L statement (Revenue/Expense).',
                                'effect' => 'Governs normal debit/credit sign rules and places the head under the matching Tally Primary Group.',
                            ],
                            [
                                'name' => 'Sub-Category / Statement Group',
                                'type' => 'Reporting Grouping Tag',
                                'meaning' => 'Organizes related accounts together on management reports (for example: Utilities & Power, Repairs & Maintenance, Current Assets).',
                                'effect' => 'Groups expense and revenue lines cleanly inside Income & Expenditure reports.',
                            ],
                            [
                                'name' => 'Active for New Vouchers & Postings',
                                'type' => 'Toggle (Default: ON)',
                                'meaning' => 'Turns OFF selection of retired accounts in new Expense Vouchers or Manual Journals without erasing historical postings.',
                                'effect' => 'Historical reports still show archived transactions accurately.',
                            ],
                            [
                                'name' => 'Description & Purpose',
                                'type' => 'Optional Scope Note',
                                'meaning' => 'Clarifies what bills, taxes, or departmental costs belong under this ledger head for audit consistency.',
                                'effect' => 'Guides accountants and external auditors reviewing trial balance schedules.',
                            ],
                        ],
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
                'field_sections' => [
                    [
                        'section_title' => 'Bank Account Master Fields (Finance & Audit Ledger -> Bank Accounts & Deposits)',
                        'section_note' => 'Configured when registering each official club current, savings, or fixed-deposit bank account.',
                        'fields' => [
                            [
                                'name' => 'Bank Name, Branch Name & Account Display Name',
                                'type' => 'Required Account Identity',
                                'meaning' => 'Official bank institution, branch location, and recognizable club display label (e.g., "SBI Operating Current A/c").',
                                'effect' => 'Displayed in all Cash Deposit, Operating Expense, and Contractor Payout bank selectors.',
                            ],
                            [
                                'name' => 'Account Number & Classification (Current / Savings / Fixed Deposit)',
                                'type' => 'Account Details',
                                'meaning' => 'Bank account number and whether it operates as a working Current Account, Savings Account, or Fixed Deposit / Corpus Escrow.',
                                'effect' => 'Printed on Passbook statements and helps distinguish operating liquidity from reserve deposits.',
                            ],
                            [
                                'name' => 'Branch IFSC Code & Club UPI VPA (Optional)',
                                'type' => 'Routing & Digital Clearing Info',
                                'meaning' => '11-character Indian Financial System Code (IFSC) of the branch and optional merchant UPI ID mapped to this account.',
                                'effect' => 'Used for bank reconciliation audits, vendor payouts, and UPI settlement tracking.',
                            ],
                            [
                                'name' => 'Linked General Ledger Asset Head (`gl_account_id`)',
                                'type' => 'Chart of Accounts Link (1020 Bank Series)',
                                'meaning' => 'Accounting asset ledger head debited whenever counter cash is deposited or banked receipts settle here.',
                                'effect' => 'Routes Contra Deposits, Expense Payments, and Tally Vouchers directly to the right GL/Tally bank ledger.',
                            ],
                            [
                                'name' => 'Opening Balance (₹) (Locked After Creation)',
                                'type' => 'Rupee Opening Balance',
                                'meaning' => 'Starting verified bank balance entered once when first onboarding the bank account into the software.',
                                'effect' => 'Disabled on subsequent edits so balances can only change via auditable Deposits, Transfers, or Disbursements.',
                            ],
                            [
                                'name' => 'Primary Bank Account & Active Status Toggles',
                                'type' => 'Default Routing Toggles',
                                'meaning' => 'Primary sets the default destination bank pre-selected on Deposit Counter Cash and Expense forms; Active enables or pauses selection.',
                                'effect' => 'Deactivating a closed bank account hides it from new payouts while preserving its Passbook audit trail.',
                            ],
                        ],
                    ],
                    [
                        'section_title' => 'Deposit Counter Cash (Contra Remittance Modal)',
                        'section_note' => 'Used whenever physical cash taken from counter tills/safe is deposited into the bank or CDM.',
                        'fields' => [
                            [
                                'name' => 'Destination Bank Account & Deposit Amount (₹)',
                                'type' => 'Required Remittance Inputs',
                                'meaning' => 'Bank account receiving the cash deposit and exact currency total handed over at the bank branch or Cash Deposit Machine.',
                                'effect' => 'Increases Bank Balance and reduces Cash in Hand (1010) via a balanced Contra Journal (DR Bank / CR 1010).',
                            ],
                            [
                                'name' => 'Deposit / Challan Date & Bank Pay-in Slip # / CDM Txn ID',
                                'type' => 'Audit & Period Check Fields',
                                'meaning' => 'Official date stamped on the bank pay-in slip plus the counterfoil/CDM transaction reference number.',
                                'effect' => 'Validated against Fiscal Period Locks (backdating into closed periods is blocked) and printed on Bank Passbooks.',
                            ],
                            [
                                'name' => 'Deposited By & Narration / Deposit Remarks',
                                'type' => 'Custody Trail Fields',
                                'meaning' => 'Staff member who carried cash to the bank plus summary remarks (such as weekend collections deposit).',
                                'effect' => 'Stored permanently on the Contra voucher for cash-custody verification.',
                            ],
                        ],
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
                'field_sections' => [
                    [
                        'section_title' => 'Operating Expense Voucher Fields (Procurement & Food Contractor -> Operating Expenses)',
                        'section_note' => 'Captures electricity bills, maintenance invoices, petty expenses, and overheads against 5xxx Expense accounts and Cost Centres.',
                        'fields' => [
                            [
                                'name' => 'Expense Account Head (`5xxx`) & Inline "+" Head Creator',
                                'type' => 'Required GL Expense Account',
                                'meaning' => 'Selects which operating expense head (utilities, repairs, staffing, stationary, etc.) absorbs the cost. Click "+" inside the field to create a new 5xxx Account (Code, Name, Category) without leaving the voucher.',
                                'effect' => 'Debits the chosen 5xxx ledger on the General Ledger, Income & Expenditure report, and Tally Payment/Journal export.',
                            ],
                            [
                                'name' => 'Cost-Center Department (Blank = General Clubhouse Infrastructure)',
                                'type' => 'Optional Department Cost Centre',
                                'meaning' => 'Attributes the expense to Bar, Restaurant/Kitchen, Sports, Accommodation, or Card Room. Leave blank if the bill covers general clubhouse overheads.',
                                'effect' => 'Feeds Departmental P&L Profitability reports and Tally Department Cost Centres when enabled.',
                            ],
                            [
                                'name' => 'Expense Date',
                                'type' => 'Required Posting Date',
                                'meaning' => 'Accounting voucher date matching the vendor bill or payout date.',
                                'effect' => 'Must belong to an Open Fiscal Period; postings dated inside Locked or Closed periods are rejected.',
                            ],
                            [
                                'name' => 'Payee Name & Voucher Amount (₹)',
                                'type' => 'Required Payee & Rupee Total',
                                'meaning' => 'Name of the vendor, utility board (e.g., KSEB), or contractor paid, and gross voucher total in Rupees.',
                                'effect' => 'Recorded on the printable Expense Voucher PDF and General Ledger journal entry.',
                            ],
                            [
                                'name' => 'Payment Method (`bank_transfer` / `cheque` / `cash`) & Bank Account',
                                'type' => 'Funding Source',
                                'meaning' => 'Selects Cash in Hand (1010) for petty cash payouts or Bank Transfer/Cheque paired with the specific Disbursing Club Bank Account.',
                                'effect' => 'Credits Cash in Hand (1010) for cash payouts or reduces the selected Bank Account balance + posts Bank Clearing credit.',
                            ],
                            [
                                'name' => 'Supporting Document Reference (Bill # / UTR / Cheque #)',
                                'type' => 'Audit Reference',
                                'meaning' => 'Vendor invoice serial, bank UTR, or cheque number tying the software voucher to physical paper/bank records.',
                                'effect' => 'Essential during statutory audits and Tally voucher cross-checking.',
                            ],
                        ],
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
                'field_sections' => [
                    [
                        'section_title' => 'Contractor Settlement Batch & Payout Modal Fields',
                        'section_note' => 'Only confirmed paid food bill lines (including prepaid wallet redemptions) enter daily batches; unused prepaid recharges never settle to contractors.',
                        'fields' => [
                            [
                                'name' => 'Cutoff Business Date (Generate Daily Settlement Batch)',
                                'type' => 'Required Business Date',
                                'meaning' => 'Selects the operating day whose paid, unsettled food bills are grouped into a unique settlement batch.',
                                'effect' => 'Marks included food bill lines with a unique batch ID so the same bill can never be settled twice.',
                            ],
                            [
                                'name' => 'Net Food Sales, Adjustments, Net Payable & Residual Payable',
                                'type' => 'Calculated Batch Columns',
                                'meaning' => 'Net Food Sales totals eligible kitchen sales (excl. GST); Adjustments captures post-settlement refund deductions from prior locked batches; Residual shows unpaid balance left.',
                                'effect' => 'Guarantees refunds issued later do not break closed historical batches and automatically net off the next payout.',
                            ],
                            [
                                'name' => 'Approve Batch Action',
                                'type' => 'Authorization Step (Draft -> Approved)',
                                'meaning' => 'Confirms audited batch totals before bank disbursement.',
                                'effect' => 'Posts Journal 1 (DR 5020 Food Contractor Expense / CR 2020 Food Contractor Payable) and prevents further draft edits.',
                            ],
                            [
                                'name' => 'Disbursing Club Bank Account, Payment Instrument & Bank UTR / Cheque #',
                                'type' => 'Record Payout Inputs (NEFT / RTGS / Cheque / IMPS)',
                                'meaning' => 'Which club bank account pays out the contractor settlement, transfer mode, and bank UTR or cheque reference.',
                                'effect' => 'Records bank disbursement, updates Bank Passbook, and posts Journal 2 (DR 2020 Contractor Payable / CR 1020 Bank Clearing).',
                            ],
                            [
                                'name' => 'Payout Amount (₹)',
                                'type' => 'Rupee Amount (Defaults to Residual Payable)',
                                'meaning' => 'Exact amount remitted to the contractor (allows partial or full settlement up to residual payable).',
                                'effect' => 'Moves batch to Partially Paid or Paid; once Paid with 0 residual, batch remains permanently locked.',
                            ],
                        ],
                    ],
                    [
                        'section_title' => 'Contractor Enterprise Master Fields (Contractor Directory)',
                        'section_note' => 'Configured per external vendor operating dining, catering, or concession facilities.',
                        'fields' => [
                            [
                                'name' => 'Contractor Reference Code, Commercial Name & Operating Department',
                                'type' => 'Contractor Identity & Mapping',
                                'meaning' => 'Unique code (e.g., CONT-FOOD-01), business name, and operating department (`food`, etc.) whose sales feed this vendor.',
                                'effect' => 'Routes department sales automatically into matching contractor settlement batches and Tally sub-ledgers.',
                            ],
                            [
                                'name' => 'Bank Name, Beneficiary Account Number & Bank IFSC Code',
                                'type' => 'Beneficiary Payout Details',
                                'meaning' => 'Caterer’s bank coordinates verified against signed contract.',
                                'effect' => 'Displayed on settlement payout audit slips to verify NEFT/RTGS destinations.',
                            ],
                            [
                                'name' => 'Club Concession / Commission Rate (%) & Active Toggle',
                                'type' => 'Default: 0.00% • Active Toggle',
                                'meaning' => 'Optional club commission share percentage (standard pass-through contract is 0.00%, paying 100% of net food sales).',
                                'effect' => 'Applies configured percentage during daily batch net-payable calculations when Active is ON.',
                            ],
                        ],
                    ],
                ],
                'formula' => 'Net Payout = Gross Food Sales - Deductions • Journal 1 (Approve): Dr 5020 / Cr 2020 • Journal 2 (Payout): Dr 2020 / Cr 1020',
                'policy_note' => 'Strict Club Policy: Unused member prepaid wallet funds do NOT feed contractor settlements. Once approved and paid, settlement batches remain permanently locked.',
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
                'field_sections' => [
                    [
                        'section_title' => 'Supplier Receipts Form Header (Procurement & Food Contractor -> Supplier Receipts)',
                        'section_note' => 'Captures wholesale excise invoices / delivery bills into Inventory Stores (1030) and Vendor Payables.',
                        'fields' => [
                            [
                                'name' => 'Wholesale Supplier (+ Inline Quick-Create)',
                                'type' => 'Required Vendor Select',
                                'meaning' => 'Distributor supplying the liquor or store consignment (such as Kerala State Beverages Corp). Click "+" to register a new vendor with their 15-digit GSTIN inline.',
                                'effect' => 'Binds accounts payable balances and GST Input Tax Credit (ITC) details to that supplier.',
                            ],
                            [
                                'name' => 'Supplier Invoice Number & Invoice Date',
                                'type' => 'Required Invoice Audit Fields',
                                'meaning' => 'Exact vendor tax/excise invoice reference number and issue date on the physical bill.',
                                'effect' => 'Invoice Date must fall inside an Open Fiscal Period; duplicates or closed-period dates are rejected.',
                            ],
                            [
                                'name' => 'Receipt Notes',
                                'type' => 'Optional Remarks',
                                'meaning' => 'Excise permit number, transport vehicle details, or short-delivery observations.',
                                'effect' => 'Printed on the viewable Supplier Receipt PDF voucher.',
                            ],
                        ],
                    ],
                    [
                        'section_title' => 'Inward Line Item & Valuation Calculator Fields',
                        'section_note' => 'Each line updates perpetual moving weighted-average cost per ml / base unit.',
                        'fields' => [
                            [
                                'name' => 'Inventory Item',
                                'type' => 'Required Stock Item Select',
                                'meaning' => 'Tracked liquor bottle or store item receiving physical stock inward.',
                                'effect' => 'Links the purchase line directly to that SKU’s base unit (ml / bottles / cans / kg).',
                            ],
                            [
                                'name' => 'Bottle / Case Count & Pack Size Qty (Auto-Calculator Helpers)',
                                'type' => 'Optional Helper Inputs',
                                'meaning' => 'Convenient counter helpers (e.g., 12 bottles × 750 ml) that compute total milliliters for you without needing a manual calculator.',
                                'effect' => 'Populates Total Base Quantity cleanly; always include free/scheme bottles in Total Base Qty received.',
                            ],
                            [
                                'name' => 'Total Base Quantity (`quantity` incl. scheme/free bottles)',
                                'type' => 'Required Inward Stock Qty',
                                'meaning' => 'Exact total milliliters or base units physically placed into cellar/store stock from this invoice line (paid + free scheme units combined).',
                                'effect' => 'Added directly to On-Hand Stock so POS pours never go artificially negative.',
                            ],
                            [
                                'name' => 'Line Gross Cost (`line_gross_paise`) vs Unit Cost (`unit_cost_paise`)',
                                'type' => 'Gross Invoice Value (Before Discount)',
                                'meaning' => 'Total line bill amount before applying any cash or wholesale trade discount, or cost per base unit.',
                                'effect' => 'Establishes gross batch value before discounts and GST split.',
                            ],
                            [
                                'name' => 'Cash / Trade Discount (`discount_paise`) & Scheme Notes',
                                'type' => 'Deduction + Description',
                                'meaning' => 'Rupee trade/cash rebate deducted from the invoice value, plus text notes recording supplier schemes (e.g., "12 + 1 Free Case Scheme").',
                                'effect' => 'Reduces net inventory asset valuation directly without cutting Counter POS customer selling prices.',
                            ],
                            [
                                'name' => 'GST % & Live Effective Cost Preview (`effective_cost_preview`)',
                                'type' => 'Tax Rate & Readout',
                                'meaning' => 'Applicable tax slab on the inward line plus real-time calculated net cost per ml after discounts and free bottles.',
                                'effect' => 'Helps storekeepers verify realistic cost per peg before saving the receipt.',
                            ],
                        ],
                    ],
                    [
                        'section_title' => 'Wholesale Supplier Master Fields (Wholesale Suppliers)',
                        'section_note' => 'Master vendor directory for beverage depots and FMCG suppliers.',
                        'fields' => [
                            [
                                'name' => 'Supplier Reference Code, Commercial Entity Name & Statutory GSTIN (15 Digits)',
                                'type' => 'Vendor Identity & Tax Registration',
                                'meaning' => 'Unique short code (e.g., KSBC-PKD), official company name, and 15-character GSTIN.',
                                'effect' => 'Ensures GST returns and Tally purchase ledgers carry valid distributor tax IDs.',
                            ],
                            [
                                'name' => 'Depot / Billing Address & Active Supplier Status',
                                'type' => 'Address & Active Toggle',
                                'meaning' => 'Physical warehouse address and switch controlling whether the vendor appears in Supplier Receipts dropdowns.',
                                'effect' => 'Turn Active OFF to retire an old vendor cleanly while keeping historical bills intact.',
                            ],
                        ],
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
                'field_sections' => [
                    [
                        'section_title' => 'Inventory Stock Item Fields (Commercial & Counters -> Inventory Items)',
                        'section_note' => 'Defines physical stock units held in stores/cellar and deducted automatically on bar sales and promo pours.',
                        'fields' => [
                            [
                                'name' => 'SKU / Inventory Item Code & Bottle Barcode (EAN)',
                                'type' => 'Stock Code & Scanner Barcode',
                                'meaning' => 'Unique SKU identifier (e.g., LIQ-SIG-750ML) and bottle EAN barcode scanned at purchase inward or POS.',
                                'effect' => 'Allows storekeepers and cashiers to match exact bottle SKUs without mispicking.',
                            ],
                            [
                                'name' => 'Item Name / Brand, Department Attribution & Item Category',
                                'type' => 'Classification Selects',
                                'meaning' => 'Brand/bottle name plus department (`bar` / `food` / `consumables`) and category (`liquor` / `beer_wine` / `beverages` / `tobacco` / `food_raw` / `consumables`).',
                                'effect' => 'Controls stock filters and ensures Bar Stock & Consumption reports isolate spirits vs soft drinks cleanly.',
                            ],
                            [
                                'name' => 'Base Unit of Measurement (`ml` / `bottle` / `can` / `pcs` / `kg` / `portion`)',
                                'type' => 'Required Measurement Unit (Default: ml)',
                                'meaning' => 'Smallest unit tracked in perpetual inventory (`ml` is standard for spirits so 30ml, 60ml, and 750ml servings deduct precisely).',
                                'effect' => 'Every purchase receipt and POS sale converts into this base unit when updating on-hand balances.',
                            ],
                            [
                                'name' => 'Low Stock Reorder Alert Level',
                                'type' => 'Numeric Threshold (Default: 1500 ml = 2 Bottles)',
                                'meaning' => 'Minimum comfortable stock buffer expressed in Base Units.',
                                'effect' => 'Inventory table and Bar Stock Report highlight the item in red whenever on-hand stock falls at or below this level.',
                            ],
                            [
                                'name' => 'Current On-Hand Stock & Moving Avg Unit Cost (₹)',
                                'type' => 'Stock Valuation Readouts',
                                'meaning' => 'Physical base units currently in bar/cellar plus rolling weighted-average purchase cost per unit.',
                                'effect' => 'Used on every POS sale to compute exact perpetual Cost of Goods Sold (DR 5010 COGS / CR 1030 Bar Stores).',
                            ],
                            [
                                'name' => 'Active for Purchasing and Serving Issue',
                                'type' => 'Toggle (Default: ON)',
                                'meaning' => 'Allows the stock SKU to receive inward purchases and link to POS selling tariffs.',
                                'effect' => 'Turning OFF retires discontinued brands cleanly without deleting historical stock ledger movements.',
                            ],
                        ],
                    ],
                    [
                        'section_title' => 'POS Tariffs & Selling Prices (Tariffs Form)',
                        'section_note' => 'Controls menu pricing, customer GST slabs, and effective dates charged on Counter POS and Front Desk.',
                        'fields' => [
                            [
                                'name' => 'Tariff Code (`code`), Barcode (`barcode`) & Display Name (`name`)',
                                'type' => 'POS Menu Lookup Fields',
                                'meaning' => 'Unique billing SKU, optional scanner EAN, and item name printed on POS search and thermal receipts.',
                                'effect' => 'Matched immediately when scanning barcodes or pressing F3 in Counter POS.',
                            ],
                            [
                                'name' => 'Department (`department_code`)',
                                'type' => 'Income Attribution Department',
                                'meaning' => 'Operating section earning the revenue (bar, food, accommodation, sports, card_room, general).',
                                'effect' => 'Routes bill lines to matching GL revenue accounts (4010–4040), food contractor cutoffs, and Tally Cost Centres.',
                            ],
                            [
                                'name' => 'Selling Rate Excl. GST (`rate_paise`) & GST Tax Rate (`0% / 5% / 12% / 18% / 28%`)',
                                'type' => 'Base Price + Split CGST/SGST Tax',
                                'meaning' => 'Pre-tax customer selling price in Rupees and statutory GST percentage applied on top (split 50:50 into CGST and SGST on tax invoices).',
                                'effect' => 'Separates net revenue from 2030 GST Output Liability cleanly on every bill.',
                            ],
                            [
                                'name' => 'Effective From / To Dates & Active Toggle',
                                'type' => 'Pricing Schedule Controls',
                                'meaning' => 'Optional validity date window and active status switch for seasonal or updated price tariffs.',
                                'effect' => 'Deactivate old rate revisions rather than overwriting them so historical invoice snapshots stay unchanged.',
                            ],
                        ],
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
                'field_sections' => [
                    [
                        'section_title' => 'Staff User Access Fields (System & Access Control -> Staff & User Access)',
                        'section_note' => 'Controls login credentials, employee identification, assigned roles, and safety guards preventing self-lockout.',
                        'fields' => [
                            [
                                'name' => 'Full Name, Employee ID & Designation',
                                'type' => 'Staff Identity Fields',
                                'meaning' => 'Employee’s legal name, internal club staff number, and job role title (e.g., Head Cashier, Reception Executive).',
                                'effect' => 'Printed on Cashier Shift logs, Expense approvals, Contra deposits, and audit history.',
                            ],
                            [
                                'name' => 'Login Email & Mobile Phone',
                                'type' => 'Required Sign-In & Contact',
                                'meaning' => 'Unique email address entered at the /login Staff Terminal tab and staff phone number.',
                                'effect' => 'Determines login identity and routes users automatically to /admin, /admin/room-rack, or /pos based on role.',
                            ],
                            [
                                'name' => 'Active Status Toggle (`is_active`)',
                                'type' => 'Lockout-Protected Switch (Default: ON)',
                                'meaning' => 'Grants or revokes sign-in access without deleting historical financial accountability. Self-deactivation is blocked by the server.',
                                'effect' => 'Use this when staff resign or go on long leave; users with past bills/shifts can never be hard-deleted.',
                            ],
                            [
                                'name' => 'Password (Min 8 Characters — Leave Blank on Edit)',
                                'type' => 'Credential Input',
                                'meaning' => 'Sets initial login passphrase when registering staff (minimum 8 characters); when editing an existing user, leave empty to keep their current password.',
                                'effect' => 'Hashed securely on save; never stored or displayed in plain text.',
                            ],
                            [
                                'name' => 'Assigned Roles & Direct Permission Overrides',
                                'type' => 'Checklist (Self / Last-Admin Protected)',
                                'meaning' => 'Assigns one or more built-in or custom Roles (Admin, Manager, Accountant, Auditor, Reception, Cashier) plus optional per-user overrides.',
                                'effect' => 'The server prevents removing Administrator access from yourself or from the last active club Admin.',
                            ],
                        ],
                    ],
                    [
                        'section_title' => 'Roles & Form Access Matrix Fields (System & Access Control -> Roles & Form Access)',
                        'section_note' => 'Granular form, view, and approval control across all 53 club permissions.',
                        'fields' => [
                            [
                                'name' => 'Role Identifier Slug (`name`)',
                                'type' => 'Unique Role Code (Locked on System Roles)',
                                'meaning' => 'Machine identifier for the role (editable only on newly created Custom Roles; locked on built-in roles like `manager` or `cashier`).',
                                'effect' => 'Determines landing-page routing defaults and system permission baseline.',
                            ],
                            [
                                'name' => 'Grouped Permission Checkboxes (`ClubPermissionRegistry`)',
                                'type' => '53 Granular Permissions',
                                'meaning' => 'Individual toggles grouped by Counter POS, Membership & KYC, Sports & Rooms, Finance & Audit, Procurement, and Admin Settings.',
                                'effect' => 'Enforced on both Filament UI menus and server endpoints/services; built-in Admin always keeps full system permissions, and Restore Role Defaults resets built-ins cleanly.',
                            ],
                        ],
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
                'summary' => 'Plain-English guide to every club setting across all 5 System Settings tabs, accounting Period Locks, and both Tally XML Export / Pull-Bridge options.',
                'target_role' => 'System Admins, Club Secretary & Accountants',
                'estimated_time' => '6 mins read',
                'tags' => ['system settings', 'gstin', 'pan', 'float', 'recharge', 'split tender', 'zero stock', 'check-in', 'booking window', 'guest pass', 'tally', 'cost centres', 'pull bridge', 'xml', 'fiscal periods', 'locks', 'integrations', 'printer', '80mm', '58mm'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Configure Club Policies Across the 5 System Settings Tabs',
                        'instruction' => 'Open Club Master Setup -> System Settings and review each of the 5 tabs: Club Identity & Statutory, Billing & Cashier Policies, Facilities & Timings, Tally Prime Integration, and Receipt Printing. Refer to the Field Reference below to see what every toggle and limit controls before saving.',
                        'result' => 'Saved settings immediately govern receipt headers, counter top-up limits, POS zero-stock rules, room/court schedules, and Tally/printer behaviour club-wide.',
                        'tip' => 'Only users granted the settings.manage permission can edit System Settings. Keep GSTIN, PAN, and Tally Target Company Name aligned with your statutory books.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Manage & Lock Accounting Periods (Fiscal Periods & Locks)',
                        'instruction' => 'Open Finance & Audit Ledger -> Fiscal Periods & Locks. Create periods matching your accounting months or financial year, keep active periods Open, move closing periods to Locked during review, and switch finalized periods to Closed.',
                        'result' => 'Blocks backdated POS bills, vendor purchases, expenses, bank deposits, stock adjustments, or manual journals inside locked/closed date ranges.',
                        'tip' => 'Before closing a month, verify Bank Reconciliation, Contractor Settlement batches, and Tally voucher exports for that period.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Sync Financials with Tally Prime (LAN Push, Pull-Bridge, or Manual XML)',
                        'instruction' => 'Open Finance & Audit Ledger -> Integrations & Hardware. Use Direct LAN Push when the app shares a local network with Tally Prime, generate a Pull-Bridge Token when the app runs on cloud hosting and a club PC runs the Python Tally Bridge, or download Masters XML and Date-Range / Pending Vouchers XML for manual import into Tally Gateway.',
                        'result' => 'Tally downtime never blocks counter billing: every paid bill, recharge, expense, deposit, or settlement queues safely in the retryable Outbox until delivered or downloaded.',
                        'tip' => 'Never expose Tally XML port 9000 or biometric device ports directly to the public internet; use Pull-Bridge or manual XML download for cloud deployments.',
                    ],
                ],
                'field_sections' => [
                    [
                        'section_title' => 'Tab 1: Club Identity & Statutory Details',
                        'section_note' => 'Controls the official clubhouse header, contact details, tax registrations, and statutory declaration printed on every bill, recharge slip, and member statement.',
                        'fields' => [
                            [
                                'name' => 'Club Legal Name',
                                'type' => 'Required Text',
                                'meaning' => 'Official registered name of the club (for example, Malabar Club).',
                                'effect' => 'Printed at the top of thermal counter receipts, A4 tax invoices, member ledger statements, and PDF financial reports.',
                            ],
                            [
                                'name' => 'Tagline / Designation',
                                'type' => 'Optional Text',
                                'meaning' => 'Short subtitle printed directly below the club name (such as a club motto, registration number, or establishment classification).',
                                'effect' => 'Appears on thermal receipts, printable tax invoices, and statement headers to give formal branding.',
                            ],
                            [
                                'name' => 'Registered Clubhouse Physical Address',
                                'type' => 'Required Multi-line Text',
                                'meaning' => 'Full street address, town/city, PIN code, and state of the clubhouse premises.',
                                'effect' => 'Shown in the supplier/issuer block of GST tax invoices and receipts as required under GST invoicing rules.',
                            ],
                            [
                                'name' => 'Office Telephone / Mobile',
                                'type' => 'Optional Text',
                                'meaning' => 'Primary reception or administrative phone number for member inquiries and billing clarifications.',
                                'effect' => 'Displayed below the clubhouse address on printed receipts, invoices, and Desk Reference sheets.',
                            ],
                            [
                                'name' => 'Official Communications Email',
                                'type' => 'Optional Email',
                                'meaning' => 'Club office email address shown for member correspondence and invoice support.',
                                'effect' => 'Printed in the contact header of A4 tax invoices and member statements.',
                            ],
                            [
                                'name' => 'Statutory GSTIN (Goods & Services Tax #)',
                                'type' => '15-Character Tax ID (Optional)',
                                'meaning' => 'The club’s 15-character Goods and Services Tax Identification Number (Kerala GSTINs begin with state code 32).',
                                'effect' => 'Printed prominently on GST tax invoices and receipts when set. Verify every character carefully before filing returns.',
                            ],
                            [
                                'name' => 'Permanent Account Number (PAN)',
                                'type' => '10-Character Tax ID (Optional)',
                                'meaning' => 'Club Income Tax PAN card number.',
                                'effect' => 'Displayed on formal statements and statutory invoices for vendor/audit cross-reference.',
                            ],
                            [
                                'name' => 'Bill & Receipt Footer Statutory Notation',
                                'type' => 'Optional Footer Text',
                                'meaning' => 'Custom closing note printed at the very bottom of counter receipts and tax invoices (for example: "Subject to local jurisdiction • Thank you for visiting").',
                                'effect' => 'Automatically appended to every new thermal bill, recharge receipt, and tax invoice.',
                            ],
                        ],
                    ],
                    [
                        'section_title' => 'Tab 2: Billing & Cashier Policies',
                        'section_note' => 'Sets hard cash-control boundaries and checkout rules enforced across Counter POS and Smartcard Recharges.',
                        'fields' => [
                            [
                                'name' => 'Standard Drawer Float (₹)',
                                'type' => 'Rupee Amount (Default Reference)',
                                'meaning' => 'Recommended opening petty-cash change kept inside the cashier drawer at the start of a shift (e.g., ₹2,000 for giving ₹10/₹20/₹50 change).',
                                'effect' => 'Pre-fills or guides the Opening Float during Cashier Shift Open and clarifies expected drawer cash in shift close variance checks.',
                            ],
                            [
                                'name' => 'Minimum Recharge (₹)',
                                'type' => 'Rupee Minimum Limit',
                                'meaning' => 'Smallest top-up amount accepted when reloading a member’s RFID Smartcard.',
                                'effect' => 'Blocks tiny ad-hoc top-ups below this rupee threshold at Counter POS and Reception Desk.',
                            ],
                            [
                                'name' => 'Maximum Single Top-Up (₹)',
                                'type' => 'Rupee Maximum Limit',
                                'meaning' => 'Largest single recharge permitted in one wallet top-up transaction.',
                                'effect' => 'Prevents accidental extra-zero typing mistakes (like entering ₹50,000 instead of ₹5,000) and limits single cash/UPI exposure.',
                            ],
                            [
                                'name' => 'Lost RFID Smartcard Replacement Fee (₹)',
                                'type' => 'Rupee Fee Amount',
                                'meaning' => 'Standard administrative charge collected when re-issuing a lost or damaged member RFID smartcard.',
                                'effect' => 'Used as the club reference fee when replacing a member card while keeping their existing wallet balance intact.',
                            ],
                            [
                                'name' => 'Allow Split Tender Checkout',
                                'type' => 'Checkbox (On / Off)',
                                'meaning' => 'Lets the cashier settle one bill using two payment methods together — for example, draining the remaining Prepaid Smartcard balance and paying the difference by Cash or UPI.',
                                'effect' => 'When ON, POS shows Split Tender fields at checkout. When OFF, the entire bill must be paid by a single tender mode; credit/unpaid checkout remains strictly forbidden either way.',
                            ],
                            [
                                'name' => 'Allow Selling Zero / Negative Stock Items on POS',
                                'type' => 'Checkbox (Default: OFF)',
                                'meaning' => 'Emergency override allowing bar/pantry/store items to be billed even when recorded system stock is 0.',
                                'effect' => 'When OFF (recommended), out-of-stock tracked items are blocked at POS until Vendor Purchase is entered. When ON, POS allows billing while recording negative stock variance for later purchase reconciliation; does not bypass payment rules.',
                            ],
                        ],
                    ],
                    [
                        'section_title' => 'Tab 3: Facilities, Schedules & Guest Pass Fees',
                        'section_note' => 'Defines operational hours for rooms and courts, advance booking limits, and standard guest entry pass fees.',
                        'fields' => [
                            [
                                'name' => 'Standard Check-In Time',
                                'type' => 'Time of Day',
                                'meaning' => 'Normal afternoon time from which booked overnight guest rooms become ready for handover.',
                                'effect' => 'Shown on Guest Desk room reservations and booking confirmations as the standard arrival time.',
                            ],
                            [
                                'name' => 'Standard Check-Out Time',
                                'type' => 'Time of Day',
                                'meaning' => 'Normal time by which departing guests must vacate rooms before housekeeping turnover.',
                                'effect' => 'Displayed on reservation slips and Desk checklists to calculate departure readiness.',
                            ],
                            [
                                'name' => 'Court Daily Opening',
                                'type' => 'Time of Day (e.g., 6:00 AM)',
                                'meaning' => 'Earliest slot time at which indoor badminton court bookings can start each day.',
                                'effect' => 'Controls the first available booking hour shown on Reception Deck Court Reservations (6:00 AM – 9:00 PM schedule).',
                            ],
                            [
                                'name' => 'Court Daily Closing',
                                'type' => 'Time of Day (e.g., 9:00 PM)',
                                'meaning' => 'Latest time by which all indoor badminton court sessions must finish.',
                                'effect' => 'Sets the closing cutoff for court slot generation so late-night bookings cannot be scheduled past club hours.',
                            ],
                            [
                                'name' => 'Advance Booking Window (Days)',
                                'type' => 'Whole Number (1–30 Days)',
                                'meaning' => 'How many calendar days ahead Reception can accept court or facility advance reservations.',
                                'effect' => 'Prevents staff from booking slots further ahead than club committee policy allows.',
                            ],
                            [
                                'name' => 'Bar & Card Room Pass (₹)',
                                'type' => 'Rupee Guest Pass Rate',
                                'meaning' => 'Default guest entry fee for an external guest escorted into the Bar or Card Room by an eligible sponsoring member.',
                                'effect' => 'Guides Reception Desk pass billing; guest entry remains blocked unless registered with an eligible sponsor and paid in full.',
                            ],
                            [
                                'name' => 'Badminton Court Pass (₹)',
                                'type' => 'Rupee Guest Pass Rate',
                                'meaning' => 'Default guest entry fee for a visitor playing alongside a sponsoring member on the Indoor Wooden Badminton Court.',
                                'effect' => 'Applies as the default guest pass tariff on Guest & Badminton Desk workflows.',
                            ],
                            [
                                'name' => 'Gym Daily Pass (₹)',
                                'type' => 'Rupee Guest Pass Rate',
                                'meaning' => 'Default single-day visitor charge for using gymnasium equipment with member sponsorship.',
                                'effect' => 'Sets the reference daily visitor fee when issuing a temporary gym pass.',
                            ],
                        ],
                    ],
                    [
                        'section_title' => 'Tab 4: Tally Prime Integration Settings',
                        'section_note' => 'Configures how Malabar Club connects to Tally Prime and what ledger/department detail goes into accounting vouchers.',
                        'fields' => [
                            [
                                'name' => 'Integration Driver Mode',
                                'type' => 'Select (simulator / live)',
                                'meaning' => 'Switches Tally communication between safe Simulator mode (logs/downloads XML without hitting a real Tally company) and Live mode (transmits or serves live XML vouchers).',
                                'effect' => 'Keep Simulator while testing; switch to Live only when pointing to the club’s real or designated test Tally company.',
                            ],
                            [
                                'name' => 'Tally Target Company Name',
                                'type' => 'Exact Text Match',
                                'meaning' => 'Exact company name opened inside Tally Prime (case, spaces, and punctuation must match Tally F11/Company Info, e.g., "Malabar Club").',
                                'effect' => 'Written into the XML SVCURRENTCOMPANY envelope; if it differs even by one space or year suffix, Tally rejects the voucher import.',
                            ],
                            [
                                'name' => 'Tally Computer LAN IP / Hostname',
                                'type' => 'Host / IP (Direct LAN mode)',
                                'meaning' => 'Local network address of the Windows computer running Tally Prime (e.g., 192.168.1.50 or localhost if on the same machine).',
                                'effect' => 'Used only for Direct LAN Push / Test Connection when the server is inside the same private LAN. Cloud hosting uses Pull-Bridge or XML download instead.',
                            ],
                            [
                                'name' => 'XML Server Port',
                                'type' => 'TCP Port (Default: 9000)',
                                'meaning' => 'Port configured inside Tally Prime -> F1 Help -> Settings -> Connectivity -> Client/Server (Client/Server = Both or Server, Port 9000).',
                                'effect' => 'Used by Direct LAN Push and the local Pull-Bridge script to post XML over HTTP to Tally.',
                            ],
                            [
                                'name' => 'Include Department Cost Centres in Tally XML',
                                'type' => 'Checkbox (On / Off)',
                                'meaning' => 'Tags income and expense ledger lines inside exported XML with Department Cost Centres (Bar, Kitchen/Canteen, Card Room, Sports, Rooms, General).',
                                'effect' => 'Turn ON only after enabling "Maintain Cost Centres" in Tally F11 Features and creating matching Cost Centres in Masters XML. If Tally has Cost Centres OFF, keep unchecked or imports will complain about missing cost centres.',
                            ],
                            [
                                'name' => 'Enable Automatic 15-Minute Outbox Sync',
                                'type' => 'Checkbox (On / Off)',
                                'meaning' => 'Allows the background scheduler to automatically push pending Tally outbox events every 15 minutes in Direct LAN Live mode.',
                                'effect' => 'Turn OFF if running Tally on cloud hosting via Pull-Bridge or manual XML import so LAN push jobs do not repeatedly time out.',
                            ],
                        ],
                    ],
                    [
                        'section_title' => 'Tab 5: Receipt Printing & Hardware Preferences',
                        'section_note' => 'Controls thermal printer paper layout and which financial lines print on customer receipts.',
                        'fields' => [
                            [
                                'name' => 'Thermal Receipt Paper Width',
                                'type' => 'Select (80mm Standard / 58mm Compact)',
                                'meaning' => 'Matches the physical roll width loaded in your counter thermal printer.',
                                'effect' => 'Adjusts columns, font sizing, and line wrapping on POS checkout slips and recharge receipts.',
                            ],
                            [
                                'name' => 'Auto-Print Receipt Dialog on Checkout',
                                'type' => 'Checkbox (On / Off)',
                                'meaning' => 'Automatically triggers the browser print popup immediately after a POS bill is paid.',
                                'effect' => 'Speeds up counter checkout when a dedicated thermal printer is attached; turn OFF at desks where receipts are printed selectively.',
                            ],
                            [
                                'name' => 'Print Remaining Prepaid Balance on Member Receipts',
                                'type' => 'Checkbox (On / Off)',
                                'meaning' => 'Shows the member’s updated Smartcard wallet balance at the bottom of prepaid purchase or recharge slips.',
                                'effect' => 'Gives members immediate confirmation of remaining prepaid funds and reduces balance-check inquiries.',
                            ],
                            [
                                'name' => 'Print Detailed CGST & SGST Split Breakdown',
                                'type' => 'Checkbox (On / Off)',
                                'meaning' => 'Prints separate 50% CGST and 50% SGST tax rows per rate band rather than only a single combined GST line.',
                                'effect' => 'Required when members or corporate guests request explicit intra-state Kerala CGST/SGST tax breakup on receipts.',
                            ],
                        ],
                    ],
                    [
                        'section_title' => 'Fiscal Periods & Locks + Integrations & Tally Bridge Controls',
                        'section_note' => 'Found under Finance & Audit Ledger -> Fiscal Periods & Locks and Finance & Audit Ledger -> Integrations & Hardware.',
                        'fields' => [
                            [
                                'name' => 'Fiscal Period Status (Open / Locked / Closed)',
                                'type' => 'Period Lock Dropdown',
                                'meaning' => 'Open accepts daily transactions; Locked freezes routine edits while accountants finalize month-end; Closed permanently seals an audited period.',
                                'effect' => 'Any transaction dated inside a Locked or Closed period is rejected on save across POS, Purchases, Expenses, Deposits, and Journals.',
                            ],
                            [
                                'name' => 'Download Masters XML (Ledgers)',
                                'type' => 'One-Time Setup Download',
                                'meaning' => 'Exports the club’s complete Chart of Accounts, GST duties, Department Cost Centres, and Bank/Supplier ledgers in Tally XML format.',
                                'effect' => 'Import Masters XML once into Tally Gateway -> Import Data -> Masters (or let Tally Pull-Bridge sync it) before importing daily vouchers so no "Ledger does not exist" errors occur.',
                            ],
                            [
                                'name' => 'Date Range XML (From Date / To Date -> Download Range XML)',
                                'type' => 'On-Demand Export',
                                'meaning' => 'Re-generates clean Tally Voucher XML for all posted journals inside the chosen date window (for initial backfill or accountant audit).',
                                'effect' => 'Downloads an XML file ready for Tally Gateway -> Import Data -> Vouchers without affecting live POS operations.',
                            ],
                            [
                                'name' => 'Download Pending Vouchers XML & Mark Exported as Synced',
                                'type' => 'Manual Batch Workflow',
                                'meaning' => 'Downloads only un-synced Outbox vouchers, then marks them Delivered/Ack once you verify they imported cleanly in Tally.',
                                'effect' => 'Prevents duplicate voucher imports on future exports while giving cloud-hosted clubs zero-LAN-setup Tally accounting.',
                            ],
                            [
                                'name' => 'Pull-Bridge Token (Generate / Rotate / Revoke)',
                                'type' => 'Cloud-to-PC Bridge Credential',
                                'meaning' => 'Generates a secret bearer token used by the club’s Windows Tally PC script to securely pull pending XML over HTTPS and report back acknowledgements.',
                                'effect' => 'Avoids opening office firewall ports to the public internet. Rotate immediately if a staff PC is replaced, or Revoke to stop bridge sync.',
                            ],
                        ],
                    ],
                ],
                'formula' => 'System Settings (5 Policy Tabs) • Fiscal Period Locks • Non-Blocking Outbox & Tally Pull-Bridge',
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
