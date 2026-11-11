<?php

namespace Tests\Feature;

use App\Filament\Pages\SystemSettingsPage;
use App\Domain\Shared\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_access_system_settings_page(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/admin/settings');
        $response->assertStatus(200);
        $response->assertSee('System &amp; Clubhouse Settings', false);
        $response->assertSee('Club Identity &amp; GSTIN', false);
        $response->assertSee('Billing &amp; Prepaid Wallet', false);
        $response->assertSee('Facilities &amp; Timings', false);
        $response->assertSee('Tally Prime LAN', false);
        $response->assertSee('Receipt Printing', false);
    }

    public function test_manager_can_access_system_settings_page(): void
    {
        $manager = User::where('email', 'manager@malabarclub.local')->firstOrFail();

        $response = $this->actingAs($manager)->get('/admin/settings');
        $response->assertStatus(200);
        $response->assertSee('System &amp; Clubhouse Settings', false);
    }

    public function test_cashier_and_reception_staff_denied_access_to_settings(): void
    {
        $cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $reception = User::where('email', 'reception@malabarclub.local')->firstOrFail();

        $this->actingAs($cashier)->get('/admin/settings')->assertStatus(403);
        $this->actingAs($reception)->get('/admin/settings')->assertStatus(403);
    }

    public function test_can_switch_tabs_on_settings_page(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(SystemSettingsPage::class)
            ->assertSet('activeTab', 'identity')
            ->call('setTab', 'billing')
            ->assertSet('activeTab', 'billing')
            ->call('setTab', 'facilities')
            ->assertSet('activeTab', 'facilities')
            ->call('setTab', 'tally')
            ->assertSet('activeTab', 'tally')
            ->call('setTab', 'hardware')
            ->assertSet('activeTab', 'hardware');
    }

    public function test_can_save_club_identity_settings(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(SystemSettingsPage::class)
            ->set('clubName', 'Malabar Heritage Club')
            ->set('clubTagline', 'Excellence in Hospitality')
            ->set('clubAddress', 'High Street, Vadakkencherry, Palakkad - 678683')
            ->set('clubPhone', '+91 4922 999888')
            ->set('clubEmail', 'admin@malabarheritage.org')
            ->set('clubGstin', '32aabcm9999f1z9')
            ->set('clubPan', 'aabcm9999f')
            ->set('receiptFooterNote', 'Thank you for visiting! Members are requested to maintain decorum.')
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertEquals('Malabar Heritage Club', SystemSetting::get('club_name'));
        $this->assertEquals('Excellence in Hospitality', SystemSetting::get('club_tagline'));
        $this->assertEquals('High Street, Vadakkencherry, Palakkad - 678683', SystemSetting::get('club_address'));
        $this->assertEquals('32AABCM9999F1Z9', SystemSetting::get('club_gstin'));
        $this->assertEquals('AABCM9999F', SystemSetting::get('club_pan'));
        $this->assertEquals('Thank you for visiting! Members are requested to maintain decorum.', SystemSetting::get('receipt_footer_note'));
    }

    public function test_can_save_billing_and_cashier_policies(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(SystemSettingsPage::class)
            ->set('defaultOpeningFloat', 3500.00)
            ->set('minWalletRecharge', 1000.00)
            ->set('maxWalletRecharge', 100000.00)
            ->set('lostCardFee', 250.00)
            ->set('allowSplitTender', false)
            ->set('allowZeroStockSales', true)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertEquals('3500', SystemSetting::get('default_opening_float'));
        $this->assertEquals('1000', SystemSetting::get('min_wallet_recharge'));
        $this->assertEquals('100000', SystemSetting::get('max_wallet_recharge'));
        $this->assertEquals('250', SystemSetting::get('lost_card_fee'));
        $this->assertEquals('0', SystemSetting::get('allow_split_tender'));
        $this->assertEquals('1', SystemSetting::get('allow_zero_stock_sales'));
    }

    public function test_can_save_front_desk_and_facilities_timings(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(SystemSettingsPage::class)
            ->set('checkInTime', '13:00')
            ->set('checkOutTime', '11:00')
            ->set('courtOpeningTime', '05:30')
            ->set('courtClosingTime', '22:00')
            ->set('courtAdvanceBookingDays', 14)
            ->set('guestBarEntryFee', 300.00)
            ->set('guestBadmintonEntryFee', 150.00)
            ->set('guestGymEntryFee', 200.00)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertEquals('13:00', SystemSetting::get('check_in_time'));
        $this->assertEquals('11:00', SystemSetting::get('check_out_time'));
        $this->assertEquals('05:30', SystemSetting::get('court_opening_time'));
        $this->assertEquals('22:00', SystemSetting::get('court_closing_time'));
        $this->assertEquals('14', SystemSetting::get('court_advance_booking_days'));
        $this->assertEquals('300', SystemSetting::get('guest_bar_entry_fee'));
        $this->assertEquals('150', SystemSetting::get('guest_badminton_entry_fee'));
        $this->assertEquals('200', SystemSetting::get('guest_gym_entry_fee'));
    }

    public function test_can_save_and_test_tally_lan_configuration(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(SystemSettingsPage::class)
            ->set('tallyDriver', 'simulator')
            ->set('tallyHost', '192.168.1.150')
            ->set('tallyPort', 9005)
            ->set('tallyCompany', 'Malabar Club 2026')
            ->call('saveSettings')
            ->assertHasNoErrors()
            ->call('testTallyConnection');

        $this->assertEquals('simulator', SystemSetting::get('tally_driver'));
        $this->assertEquals('192.168.1.150', SystemSetting::get('tally_host'));
        $this->assertEquals('9005', SystemSetting::get('tally_port'));
        $this->assertEquals('Malabar Club 2026', SystemSetting::get('tally_company'));
    }

    public function test_can_save_receipt_printing_and_hardware_settings(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(SystemSettingsPage::class)
            ->set('receiptPaperWidth', '58mm')
            ->set('autoPrintReceipt', true)
            ->set('printWalletBalanceOnReceipt', false)
            ->set('printGstBreakdownOnReceipt', true)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertEquals('58mm', SystemSetting::get('receipt_paper_width'));
        $this->assertEquals('1', SystemSetting::get('auto_print_receipt'));
        $this->assertEquals('0', SystemSetting::get('print_wallet_balance_on_receipt'));
        $this->assertEquals('1', SystemSetting::get('print_gst_breakdown_on_receipt'));
    }

    public function test_can_reset_settings_to_system_defaults(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        // Alter a setting first
        SystemSetting::set('club_name', 'Temporary Altered Name');
        $this->assertEquals('Temporary Altered Name', SystemSetting::get('club_name'));

        Livewire::actingAs($admin)
            ->test(SystemSettingsPage::class)
            ->call('resetToDefaults')
            ->assertHasNoErrors();

        $this->assertEquals('Malabar Club, Vadakkencherry', SystemSetting::get('club_name'));
        $this->assertEquals('2000', SystemSetting::get('default_opening_float'));
    }

    public function test_validation_errors_on_invalid_settings(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(SystemSettingsPage::class)
            ->set('clubName', '') // Required
            ->set('tallyPort', 99999) // Max 65535
            ->set('defaultOpeningFloat', -100) // Min 0
            ->call('saveSettings')
            ->assertHasErrors(['clubName', 'tallyPort', 'defaultOpeningFloat']);
    }

    public function test_allow_zero_stock_sales_persists_across_database_cache_hits_and_self_heals_incomplete_class(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        // Simulate production/local cache configuration where Eloquent models fail unserialization
        config([
            'cache.default' => 'database',
            'cache.serializable_classes' => false,
        ]);
        app('cache')->forgetDriver('database');

        // 1. Save setting from SystemSettingsPage (billing tab)
        Livewire::actingAs($admin)
            ->test(SystemSettingsPage::class)
            ->call('setTab', 'billing')
            ->set('allowZeroStockSales', true)
            ->call('saveSettings')
            ->assertHasNoErrors();

        // 2. First read warms the database cache; second read MUST hit serialized cache without reverting to false
        $this->assertEquals('1', SystemSetting::get('allow_zero_stock_sales'));
        $this->assertEquals('1', SystemSetting::get('allow_zero_stock_sales'));
        $this->assertTrue(SystemSetting::getBool('allow_zero_stock_sales'));

        // 3. Re-mounting SystemSettingsPage must reflect the saved checked state across cache hits
        Livewire::actingAs($admin)
            ->test(SystemSettingsPage::class)
            ->assertSet('allowZeroStockSales', true);

        // 4. Seed a poisoned serialized Model (simulating pre-fix production DB cache entry)
        $rawModel = SystemSetting::where('key', 'allow_zero_stock_sales')->firstOrFail();
        \Illuminate\Support\Facades\Cache::store('database')->put('system_setting.allow_zero_stock_sales', $rawModel, 300);

        $this->assertEquals('1', SystemSetting::get('allow_zero_stock_sales'));
        $this->assertTrue(SystemSetting::getBool('allow_zero_stock_sales'));

        // 5. Restoring defaults disables zero-stock sales cleanly
        Livewire::actingAs($admin)
            ->test(SystemSettingsPage::class)
            ->call('resetToDefaults')
            ->assertHasNoErrors()
            ->assertSet('allowZeroStockSales', false);

        $this->assertFalse(SystemSetting::getBool('allow_zero_stock_sales'));
    }

    public function test_cross_tab_validation_error_switches_to_failing_tab(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(SystemSettingsPage::class)
            ->call('setTab', 'billing')
            ->set('clubName', '')
            ->call('saveSettings')
            ->assertHasErrors(['clubName'])
            ->assertSet('activeTab', 'identity');
    }
}
