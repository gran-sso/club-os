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
            ->call('setTab', 'bylaws')
            ->assertSet('activeTab', 'bylaws')
            ->assertSee('Zero-Credit Policy');
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
}
