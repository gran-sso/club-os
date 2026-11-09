<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\Shift;
use App\Domain\Membership\Models\Member;
use App\Domain\Tariff\Models\Tariff;
use App\Livewire\CounterPos;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CounterPosTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected Member $member;
    protected Counter $counter;
    protected Tariff $tariff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $this->counter = Counter::where('code', 'COUNTER-1')->firstOrFail();
        $this->tariff = Tariff::where('code', 'GUEST-BAR-ENTRY')->firstOrFail();
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/pos');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_cashier_can_access_pos(): void
    {
        $response = $this->actingAs($this->cashier)->get('/pos');
        $response->assertStatus(200);
        $response->assertSeeLivewire(CounterPos::class);
    }

    public function test_distraction_free_open_shift_gateway_hides_billing_workspace_until_opened(): void
    {
        $test = Livewire::actingAs($this->cashier)
            ->test(CounterPos::class)
            // Shift is closed: only Open Shift form is visible
            ->assertSeeHtml('Open Counter Shift')
            ->assertSeeHtml('Opening Cash (₹)')
            ->assertDontSeeHtml('id="customer-search-input"')
            ->assertDontSeeHtml('id="item-search-input"')
            ->assertSeeHtml('SHIFT CLOSED');

        // Open shift
        $test->set('selectedCounterId', $this->counter->id)
            ->set('openingFloat', 2000.00)
            ->call('openShift')
            ->assertSet('errorMessage', null)
            // Now billing workspace is visible
            ->assertSeeHtml('id="customer-search-input"')
            ->assertSeeHtml('id="item-search-input"')
            ->assertSeeHtml('ONLINE')
            ->assertSeeHtml('Expected Cash');

        // Close shift
        $test->set('closingCashCounted', 2000.00)
            ->call('closeShift')
            ->assertSet('errorMessage', null)
            ->assertSet('activeShift', null)
            // Reverts to clean Open Shift screen
            ->assertSeeHtml('Open Counter Shift')
            ->assertDontSeeHtml('id="customer-search-input"')
            ->assertDontSeeHtml('id="item-search-input"');
    }

    public function test_cashier_counter_pos_full_workflow(): void
    {
        Livewire::actingAs($this->cashier)
            ->test(CounterPos::class)
            // 1. Open shift with ₹2,000 float
            ->set('selectedCounterId', $this->counter->id)
            ->set('openingFloat', 2000.00)
            ->call('openShift')
            ->assertSet('errorMessage', null)
            ->assertSee('opened successfully')

            // 2. Search member by membership number
            ->set('searchQuery', 'MC-001')
            ->call('searchMember')
            ->assertSet('selectedMember.id', $this->member->id)
            ->assertSet('memberWalletBalancePaise', 0)

            // 3. Recharge wallet with ₹1,000 cash
            ->set('rechargeAmount', 1000.00)
            ->set('rechargeTender', 'cash')
            ->call('rechargeWallet')
            ->assertSet('errorMessage', null)
            ->assertSet('memberWalletBalancePaise', 100000)
            ->assertSet('showReceiptModal', true)
            ->call('closeReceiptModal')
            ->assertSet('showReceiptModal', false)

            // 4. Add guest entry tariff to cart (₹200 + 18% GST = ₹236)
            ->call('addToCart', $this->tariff->id)
            ->assertSet('cartNet', 236.00)

            // 5. Checkout via Prepaid Wallet
            ->call('checkout', 'prepaid')
            ->assertSet('errorMessage', null)
            ->assertSet('memberWalletBalancePaise', 76400) // ₹1,000 - ₹236 = ₹764.00
            ->assertSet('cart', [])
            ->assertSet('showReceiptModal', true)
            ->call('closeReceiptModal')

            // 6. Close Shift
            ->set('closingCashCounted', 3000.00) // Opening 2000 + Recharge 1000 = 3000
            ->call('closeShift')
            ->assertSet('errorMessage', null)
            ->assertSet('activeShift', null);
    }

    public function test_insufficient_prepaid_balance_blocks_checkout(): void
    {
        Livewire::actingAs($this->cashier)
            ->test(CounterPos::class)
            ->set('selectedCounterId', $this->counter->id)
            ->set('openingFloat', 1000.00)
            ->call('openShift')
            ->set('searchQuery', 'MC-001')
            ->call('searchMember')
            ->assertSet('memberWalletBalancePaise', 0)
            ->call('addToCart', $this->tariff->id)
            ->call('checkout', 'prepaid')
            ->assertSee('Insufficient prepaid balance')
            ->assertSet('memberWalletBalancePaise', 0);
    }
}
