<?php

namespace Tests\Feature;

use App\Domain\Shared\Services\ClubGlobalSearchProvider;
use App\Filament\Pages\HelpPage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $cashier;
    protected User $reception;
    protected User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->manager = User::where('email', 'manager@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->reception = User::where('email', 'reception@malabarclub.local')->firstOrFail();
        $this->accountant = User::where('email', 'accountant@malabarclub.local')->firstOrFail();
    }

    public function test_admin_can_access_help_page(): void
    {
        $this->actingAs($this->admin);
        $response = $this->get('/admin/help');
        $response->assertSuccessful();
        $response->assertSee('Malabar Club Operations Help &amp; Manual', false);
    }

    public function test_cashier_can_access_help_page(): void
    {
        $this->actingAs($this->cashier);
        $response = $this->get('/admin/help');
        $response->assertSuccessful();
        $response->assertSee('Counter &amp; POS', false);
        $response->assertSee('F2');
        $response->assertSee('F3');
        $response->assertSee('F8');
        $response->assertSee('F9');
        $response->assertSee('F10');
    }

    public function test_reception_can_access_help_page(): void
    {
        $this->actingAs($this->reception);
        $response = $this->get('/admin/help');
        $response->assertSuccessful();
    }

    public function test_accountant_can_access_help_page(): void
    {
        $this->actingAs($this->accountant);
        $response = $this->get('/admin/help');
        $response->assertSuccessful();
    }

    public function test_unauthenticated_user_redirected_from_help_page(): void
    {
        $response = $this->get('/admin/help');
        $response->assertRedirect('/admin/login');
    }

    public function test_help_page_is_not_registered_in_sidebar_navigation(): void
    {
        $this->assertFalse(
            HelpPage::shouldRegisterNavigation(),
            'Help page must not be registered in the sidebar navigation.'
        );
    }

    public function test_help_page_interactive_tabs(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(HelpPage::class)
            ->assertSet('activeTab', 'cashier')
            ->assertSee('Shift Lifecycle')
            ->call('setTab', 'reception')
            ->assertSet('activeTab', 'reception')
            ->assertSee('Accommodation Deck')
            ->call('setTab', 'finance')
            ->assertSet('activeTab', 'finance')
            ->assertSee('General Ledger &amp; 5-Digit Numbering Standard', false)
            ->call('setTab', 'inventory')
            ->assertSet('activeTab', 'inventory')
            ->assertSee('Inward Stock Purchases')
            ->call('setTab', 'executive')
            ->assertSet('activeTab', 'executive')
            ->assertSee('Staff User Management')
            ->assertDontSee('Club Bylaws');
    }

    public function test_help_page_live_search_filters_articles(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(HelpPage::class)
            ->set('searchQuery', 'f8')
            ->assertSee('High-Speed Keyboard Shortcuts')
            ->assertSee('Search Results for:')
            ->assertCount('filteredArticles', 2) // cashier-shortcuts and cashier-recharge have f8 tag
            ->call('clearSearch')
            ->assertSet('searchQuery', '')
            ->assertCount('filteredArticles', 0);
    }

    public function test_help_page_jump_to_navigation(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(HelpPage::class)
            ->assertSet('activeTab', 'cashier')
            ->call('jumpTo', 'finance', 'coa')
            ->assertSet('activeTab', 'finance')
            ->assertSet('searchQuery', 'coa');
    }

    public function test_help_page_select_topic_displays_step_by_step_procedure(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(HelpPage::class)
            ->call('selectTopic', 'cashier-recharge')
            ->assertSet('activeTopic', 'cashier-recharge')
            ->assertSet('activeTab', 'cashier')
            ->assertSee('Standard Step-by-Step Procedure')
            ->assertSee('Identify Member at POS')
            ->assertSee('Click Quick Top-Up')
            ->assertSee('Prepaid Member Liability (2010)');
    }

    public function test_help_page_sidebar_grouped_topics(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(HelpPage::class);
        $groups = $component->get('groupedTopics');

        $this->assertArrayHasKey('cashier', $groups);
        $this->assertArrayHasKey('reception', $groups);
        $this->assertArrayHasKey('finance', $groups);
        $this->assertArrayHasKey('inventory', $groups);
        $this->assertArrayHasKey('executive', $groups);
        $this->assertArrayNotHasKey('bylaws', $groups);
        $this->assertNotEmpty($groups['cashier']['topics']);
    }

    public function test_user_menu_contains_help_link(): void
    {
        $this->actingAs($this->admin);

        $panel = Filament::getPanel('admin');
        $userMenuItems = $panel->getUserMenuItems();

        $this->assertNotEmpty($userMenuItems);
        $foundHelp = false;
        foreach ($userMenuItems as $item) {
            if ($item->getLabel() === 'Help') {
                $foundHelp = true;
                $this->assertStringContainsString('/admin/help', $item->getUrl());
            }
        }

        $this->assertTrue($foundHelp, 'Help item must exist in panel user menu.');
    }

    public function test_global_search_returns_help_page(): void
    {
        $this->actingAs($this->admin);

        $provider = app(ClubGlobalSearchProvider::class);
        $results = $provider->getResults('help');

        $this->assertNotNull($results);
        $categories = $results->getCategories();
        $this->assertTrue($categories->has('Operational Screens & Quick Links'));

        $items = collect($categories->get('Operational Screens & Quick Links'));
        $this->assertTrue($items->contains(fn ($res) => str_contains($res->title, 'Help')));
    }

    public function test_help_page_contractor_settlement_procedure_details(): void
    {
        $this->actingAs($this->accountant);

        Livewire::test(HelpPage::class)
            ->call('selectTopic', 'finance-contractor')
            ->assertSet('activeTopic', 'finance-contractor')
            ->assertSet('activeTab', 'finance')
            ->assertSee('External Food Contractor Daily Settlement &amp; Bank Payout Cycle', false)
            ->assertSee('Daily Counter Billing at POS (Cashiers)')
            ->assertSee('Generate Yesterday\'s Settlement Batch')
            ->assertSee('Audit &amp; Approve Settlement Batch', false)
            ->assertSee('Execute &amp; Record Bank Payout (NEFT / RTGS / Cheque)', false)
            ->assertSee('Automated Refund Handling (Post-Settlement Adjustments)')
            ->assertSee('Contractor Food Expense')
            ->assertSee('Food Contractor Payable')
            ->assertSee('Bank Clearing');
    }

    public function test_help_page_covers_recent_workflows_and_corrects_outdated_guidance(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(HelpPage::class)
            ->assertSee('24 operational guides')
            ->assertSeeHtml('aria-label="Help Topics"')
            ->assertSeeHtml('<label for="help-topic-search"')
            ->assertSeeHtml('[F6] HOLD RUNNING BAR TAB')
            ->assertSeeHtml('[P] PRINT RECEIPT')
            ->call('selectTopic', 'cashier-recharge')
            ->assertDontSee('Alt+R')
            ->call('selectTopic', 'cashier-running-tabs')
            ->assertSee('Bar / Lounge Running Tabs')
            ->assertSee('Up to 50 Concurrent Open Tabs per Shift')
            ->call('selectTopic', 'cashier-promotions')
            ->assertSee('Promotional Offers')
            ->assertSee('Buy X Get Y Free')
            ->call('selectTopic', 'cashier-room-guest')
            ->assertSee('Resident Room Guest Counter Sales')
            ->call('selectTopic', 'cashier-zero-stock')
            ->assertSee('Allow Selling Zero / Negative Stock Items on POS')
            ->call('selectTopic', 'reception-courts')
            ->assertSee('6:00 AM – 9:00 PM')
            ->assertSee('Indoor Wooden Badminton Court')
            ->assertSee('4 players')
            ->assertDontSee('Court 1 or Court 2')
            ->call('selectTopic', 'reception-subscriptions')
            ->assertSee('Sports Plans Enrollment')
            ->assertSee('4040 Sports Revenue')
            ->call('selectTopic', 'reception-members-fees')
            ->assertSee('Two-Tier Membership Fee Structure')
            ->call('selectTopic', 'reception-notices')
            ->assertSee('Broadcast Dues Reminder')
            ->call('selectTopic', 'finance-banking')
            ->assertSee('Deposit Counter Cash')
            ->call('selectTopic', 'finance-printing')
            ->assertSee('Printing Receipts, Vouchers, Member Statements & Desk Reference')
            ->call('selectTopic', 'executive-staff')
            ->assertSee('Roles & Form Access')
            ->assertSee('Restore Role Defaults')
            ->call('selectTopic', 'executive-settings')
            ->assertSee('Fiscal Periods & Locks');
    }

    public function test_help_page_search_indexes_procedural_step_text_and_handles_empty_matches(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(HelpPage::class)
            // Text present inside step instructions/tips rather than only top-level tags
            ->set('searchQuery', 'Restore Role Defaults')
            ->assertSee('Staff User Management, Granular Form Access & Login Gateway')
            ->assertCount('filteredArticles', 1)
            ->set('searchQuery', 'nonexistent-query-xyz-123')
            ->assertSee('No operational guides matched')
            ->call('clearSearch')
            ->assertSet('searchQuery', '');
    }

    public function test_executive_settings_guide_explains_all_five_tabs_fiscal_locks_and_tally_bridge(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(HelpPage::class)
            ->call('selectTopic', 'executive-settings')
            ->assertSee('Form Fields')
            ->assertSee('Settings Reference')
            ->assertDontSee('(opening float, min/max recharge, lost card fee, Zero-Stock toggle)')
            ->assertSee('Tab 1: Club Identity')
            ->assertSee('Standard Drawer Float')
            ->assertSee('Allow Split Tender Checkout')
            ->assertSee('Allow Selling Zero / Negative Stock Items on POS')
            ->assertSee('Advance Booking Window (Days)')
            ->assertSee('Include Department Cost Centres in Tally XML')
            ->assertSee('Print Remaining Prepaid Balance on Member Receipts')
            ->assertSee('Fiscal Period Status (Open / Locked / Closed)')
            ->assertSee('Download Masters XML (Ledgers)')
            ->assertSee('Pull-Bridge Token (Generate / Rotate / Revoke)');
    }

    public function test_form_heavy_guides_render_field_by_field_explanations_and_bulk_import_sop(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(HelpPage::class)
            ->call('selectTopic', 'reception-bulk-import')
            ->assertSee('Bulk Member CSV Import')
            ->assertSee('Existing Member Fee Mode')
            ->assertSee('Blank Valid Until Behaviour')
            ->assertSee('Opening Balance Posting Date')
            ->assertSee('Queue Opening Balance Journals in Tally Outbox')
            ->call('selectTopic', 'reception-members-fees')
            ->assertSee('Historical Fees Already Paid')
            ->assertSee('One-Time Admission Fee')
            ->call('selectTopic', 'finance-banking')
            ->assertSee('Branch IFSC Code')
            ->assertSee('Linked General Ledger Asset Head')
            ->assertSee('Destination Bank Account')
            ->call('selectTopic', 'finance-expenses')
            ->assertSee('Cost-Center Department')
            ->assertSee('Expense Account Head')
            ->call('selectTopic', 'finance-contractor')
            ->assertSee('Cutoff Business Date')
            ->assertSee('Disbursing Club Bank Account')
            ->call('selectTopic', 'inventory-purchases')
            ->assertSee('Bottle / Case Count')
            ->assertSee('Cash / Trade Discount')
            ->call('selectTopic', 'inventory-dispensing')
            ->assertSee('Low Stock Reorder Alert Level')
            ->assertSee('Base Unit of Measurement')
            ->call('selectTopic', 'cashier-promotions')
            ->assertSee('Bonus Discount Percentage (%)')
            ->assertSee('Eligible Tariffs')
            ->call('selectTopic', 'executive-staff')
            ->assertSee('Active Status Toggle')
            ->assertSee('Grouped Permission Checkboxes');
    }

    public function test_help_page_search_indexes_field_level_reference_labels(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(HelpPage::class)
            ->set('searchQuery', 'Branch IFSC Code')
            ->assertSee('Club Bank Accounts, Cash Deposits')
            ->set('searchQuery', 'Include Department Cost Centres in Tally XML')
            ->assertSee('System Settings, Fiscal Periods')
            ->assertCount('filteredArticles', 1)
            ->set('searchQuery', 'Existing Member Fee Mode')
            ->assertSee('Bulk Member CSV Import')
            ->assertCount('filteredArticles', 1);
    }
}
