<?php

namespace Tests\Feature;

use App\Domain\Tariff\Models\Tariff;
use App\Domain\Tariff\Models\Facility;
use App\Domain\Inventory\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPhase1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_access_tariff_create_page(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/admin/tariffs/create');
        $response->assertStatus(200);
        $response->assertSee('Tariff / Item Description');
        $response->assertSee('Indian Pricing &amp; GST Slabs', false);
    }

    public function test_admin_can_create_tariff_via_livewire(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Tariffs\Pages\CreateTariff::class)
            ->fillForm([
                'code' => 'TEST-TRF-01',
                'name' => 'Badminton Shuttlecock Yonex Mavis 350',
                'department_code' => 'sports',
                'rate_paise' => 350.00, // Entering ₹350.00
                'tax_percent' => '18.00',
                'effective_from' => '2026-11-08',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tariff = Tariff::where('code', 'TEST-TRF-01')->first();
        $this->assertNotNull($tariff);
        $this->assertEquals('Badminton Shuttlecock Yonex Mavis 350', $tariff->name);
        $this->assertEquals(35000, $tariff->rate_paise, '₹350.00 must be stored as 35000 paise');
        $this->assertEquals('18.00', $tariff->tax_percent);
    }

    public function test_admin_can_create_facility_modal_action(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Facilities\Pages\ListFacilities::class)
            ->callAction('create', [
                'code' => 'COURT-03',
                'name' => 'Badminton Court 3 (Synthetic)',
                'type' => 'court',
                'capacity' => 4,
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $facility = Facility::where('code', 'COURT-03')->first();
        $this->assertNotNull($facility);
        $this->assertEquals('court', $facility->type);
        $this->assertEquals(4, $facility->capacity);
    }

    public function test_admin_can_create_inventory_item_modal_action(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Inventory\Pages\ListInventoryItems::class)
            ->callAction('create', [
                'sku' => 'LIQ-AMRUT-FUSION',
                'name' => 'Amrut Fusion Single Malt 750ml',
                'department' => 'bar',
                'category' => 'liquor',
                'base_unit' => 'ml',
                'current_stock_qty' => 750,
                'current_avg_cost_paise' => 4500.00, // ₹4500.00 for bottle -> 450000 paise
                'reorder_level' => 1500,
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $item = InventoryItem::where('sku', 'LIQ-AMRUT-FUSION')->first();
        $this->assertNotNull($item);
        $this->assertEquals('ml', $item->base_unit);
        $this->assertEquals(450000, $item->current_avg_cost_paise, '₹4500.00 must be stored as 450000 paise');
    }
}
