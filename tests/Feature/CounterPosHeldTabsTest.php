<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PosHeldTab;
use App\Domain\Billing\Models\Shift;
use App\Domain\Membership\Models\Member;
use App\Domain\Tariff\Models\Tariff;
use App\Livewire\CounterPos;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CounterPosHeldTabsTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected Member $member1;
    protected Member $member2;
    protected Counter $counter;
    protected Tariff $tariff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->member1 = Member::where('membership_number', 'MC-001')->firstOrFail();
        $this->member2 = Member::where('membership_number', 'MC-002')->firstOrFail();
        $this->counter = Counter::where('code', 'COUNTER-1')->firstOrFail();
        $this->tariff = Tariff::where('code', 'GUEST-BAR-ENTRY')->firstOrFail();
    }

    public function test_cannot_hold_tab_with_empty_cart(): void
    {
        Livewire::actingAs($this->cashier)
            ->test(CounterPos::class)
            ->set('selectedCounterId', $this->counter->id)
            ->set('openingFloat', 2000.00)
            ->call('openShift')
            ->set('searchQuery', 'MC-001')
            ->call('searchMember')
            ->call('openHoldTabModal')
            ->assertSee('Cannot park an empty bill');
    }

    public function test_cannot_hold_tab_without_member_selected(): void
    {
        Livewire::actingAs($this->cashier)
            ->test(CounterPos::class)
            ->set('selectedCounterId', $this->counter->id)
            ->set('openingFloat', 2000.00)
            ->call('openShift')
            ->call('addToCart', $this->tariff->id)
            ->call('openHoldTabModal')
            ->assertSee('Member-First Policy');
    }

    public function test_can_park_cart_as_running_tab(): void
    {
        Livewire::actingAs($this->cashier)
            ->test(CounterPos::class)
            ->set('selectedCounterId', $this->counter->id)
            ->set('openingFloat', 2000.00)
            ->call('openShift')
            // Select member 1 and add tariff (₹200 + 18% GST = ₹236)
            ->set('searchQuery', 'MC-001')
            ->call('searchMember')
            ->call('addToCart', $this->tariff->id)
            // Open hold tab modal & save
            ->call('openHoldTabModal')
            ->assertSet('showHoldTabModal', true)
            ->set('tabTableIdentifier', 'Table 4')
            ->set('tabNotes', 'Round 1 drinks')
            ->call('holdCurrentCart')
            ->assertSet('showHoldTabModal', false)
            ->assertSet('activeHeldTabId', null)
            ->assertSet('cart', [])
            ->assertSee('parked for');

        $this->assertDatabaseHas('pos_held_tabs', [
            'counter_id' => $this->counter->id,
            'member_id' => $this->member1->id,
            'table_identifier' => 'Table 4',
            'status' => 'open',
            'net_amount_paise' => 23600,
        ]);
    }

    public function test_can_park_multiple_tabs_and_switch_between_them(): void
    {
        $component = Livewire::actingAs($this->cashier)
            ->test(CounterPos::class)
            ->set('selectedCounterId', $this->counter->id)
            ->set('openingFloat', 2000.00)
            ->call('openShift');

        // Park Tab 1 for Member 1
        $component->set('searchQuery', 'MC-001')
            ->call('searchMember')
            ->call('addToCart', $this->tariff->id)
            ->set('tabTableIdentifier', 'Bar Stool 1')
            ->call('holdCurrentCart');

        $tab1 = PosHeldTab::where('member_id', $this->member1->id)->firstOrFail();

        // Park Tab 2 for Member 2
        $component->set('searchQuery', 'MC-002')
            ->call('searchMember')
            ->call('addToCart', $this->tariff->id)
            ->set('tabTableIdentifier', 'Table 8')
            ->call('holdCurrentCart');

        $tab2 = PosHeldTab::where('member_id', $this->member2->id)->firstOrFail();

        $this->assertEquals(2, PosHeldTab::open()->count());

        // Switch back to Tab 1
        $component->call('loadHeldTab', $tab1->id)
            ->assertSet('activeHeldTabId', $tab1->id)
            ->assertSet('selectedMember.id', $this->member1->id)
            ->assertSet('tabTableIdentifier', 'Bar Stool 1')
            ->assertCount('cart', 1);

        // Add another round to Tab 1
        $component->call('addToCart', $this->tariff->id)
            ->assertCount('cart', 1) // same tariff code increments qty
            ->assertSet('cartNet', 472.00) // ₹236 * 2
            ->call('holdCurrentCart');

        $tab1->refresh();
        $this->assertEquals(47200, $tab1->total_paise);
    }

    public function test_settling_held_tab_updates_status_and_links_bill(): void
    {
        // Give Member 1 sufficient wallet balance
        $component = Livewire::actingAs($this->cashier)
            ->test(CounterPos::class)
            ->set('selectedCounterId', $this->counter->id)
            ->set('openingFloat', 2000.00)
            ->call('openShift')
            ->set('searchQuery', 'MC-001')
            ->call('searchMember')
            ->set('rechargeAmount', 1000.00)
            ->set('rechargeTender', 'cash')
            ->call('rechargeWallet')
            ->call('closeReceiptModal');

        // Park Tab for Member 1
        $component->call('addToCart', $this->tariff->id)
            ->set('tabTableIdentifier', 'Table 10')
            ->call('holdCurrentCart');

        $tab = PosHeldTab::where('member_id', $this->member1->id)->open()->firstOrFail();

        // Load and settle via prepaid
        $component->call('loadHeldTab', $tab->id)
            ->call('initiateCheckout', 'prepaid')
            ->call('confirmCheckout')
            ->assertSet('errorMessage', null)
            ->assertSet('activeHeldTabId', null)
            ->assertSet('cart', []);

        $tab->refresh();
        $this->assertEquals('settled', $tab->status);
        $this->assertNotNull($tab->settled_at);
        $this->assertNotNull($tab->settled_bill_id);
    }

    public function test_shift_close_is_blocked_if_open_tabs_remain(): void
    {
        $component = Livewire::actingAs($this->cashier)
            ->test(CounterPos::class)
            ->set('selectedCounterId', $this->counter->id)
            ->set('openingFloat', 2000.00)
            ->call('openShift')
            ->set('searchQuery', 'MC-001')
            ->call('searchMember')
            ->call('addToCart', $this->tariff->id)
            ->set('tabTableIdentifier', 'Bar Stool 5')
            ->call('holdCurrentCart');

        // Attempt to close shift while 1 open tab exists
        $component->set('closingCashCounted', 2000.00)
            ->call('closeShift')
            ->assertSee('unsettled running bar tab');

        $this->assertNotNull($component->get('activeShift'));

        // Void the tab
        $tab = PosHeldTab::where('member_id', $this->member1->id)->open()->firstOrFail();
        $component->call('loadHeldTab', $tab->id)
            ->call('voidActiveHeldTab', 'Member cancelled')
            ->assertSet('activeHeldTabId', null);

        $this->assertEquals('voided', $tab->fresh()->status);

        // Now shift can close smoothly
        $component->set('closingCashCounted', 2000.00)
            ->call('closeShift')
            ->assertSet('errorMessage', null)
            ->assertSet('activeShift', null);
    }

    public function test_max_held_tabs_limit_is_enforced(): void
    {
        config(['club.pos.max_held_tabs' => 2]);

        $component = Livewire::actingAs($this->cashier)
            ->test(CounterPos::class)
            ->set('selectedCounterId', $this->counter->id)
            ->set('openingFloat', 2000.00)
            ->call('openShift');

        // Tab 1
        $component->set('searchQuery', 'MC-001')
            ->call('searchMember')
            ->call('addToCart', $this->tariff->id)
            ->set('tabTableIdentifier', 'T1')
            ->call('holdCurrentCart');

        // Tab 2
        $component->set('searchQuery', 'MC-002')
            ->call('searchMember')
            ->call('addToCart', $this->tariff->id)
            ->set('tabTableIdentifier', 'T2')
            ->call('holdCurrentCart');

        // Attempt Tab 3
        $component->set('searchQuery', 'MC-001')
            ->call('searchMember')
            ->call('addToCart', $this->tariff->id)
            ->set('tabTableIdentifier', 'T3')
            ->call('holdCurrentCart')
            ->assertSee('concurrent active tabs reached');
    }
}
