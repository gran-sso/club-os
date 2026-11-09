<?php

namespace Tests\Feature;

use App\Domain\Inventory\Models\Supplier;
use App\Filament\Resources\Purchases\Pages\CreatePurchaseReceipt;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->manager = User::where('email', 'manager@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
    }

    public function test_default_core_wholesale_suppliers_are_seeded(): void
    {
        $this->assertDatabaseHas('suppliers', [
            'code' => 'KSBC-FL4',
            'name' => 'Kerala State Beverages Corporation (KSBC Wholesale Depot)',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('suppliers', [
            'code' => 'MALABAR-BEV',
            'name' => 'Malabar Beverage & FMCG Distributors',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('suppliers', [
            'code' => 'APEX-HOSP',
            'name' => 'Apex Club & Hospitality Supplies',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_access_supplier_pages(): void
    {
        $this->actingAs($this->admin);
        $this->get('/admin/suppliers')->assertSuccessful();
        $this->get('/admin/suppliers/create')->assertSuccessful();
    }

    public function test_manager_can_access_supplier_pages(): void
    {
        $this->actingAs($this->manager);
        $this->get('/admin/suppliers')->assertSuccessful();
        $this->get('/admin/suppliers/create')->assertSuccessful();
    }

    public function test_cashier_cannot_access_supplier_resource(): void
    {
        $this->actingAs($this->cashier);
        $this->get('/admin/suppliers')->assertForbidden();
        $this->get('/admin/suppliers/create')->assertForbidden();
    }

    public function test_manager_can_create_new_wholesale_supplier_via_form(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(CreateSupplier::class)
            ->fillForm([
                'name' => 'Highland Spirits & Breweries Ltd',
                'code' => 'HIGHLAND-01',
                'gstin' => '32AAACH9988P1Z4',
                'contact_person' => 'Rajeev Nambiar',
                'phone' => '+91 98471 88990',
                'email' => 'distribution@highlandspirits.in',
                'address' => 'Plot 12, Excise Zone, Palakkad - 678007',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('suppliers', [
            'code' => 'HIGHLAND-01',
            'name' => 'Highland Spirits & Breweries Ltd',
            'gstin' => '32AAACH9988P1Z4',
        ]);
    }

    public function test_supplier_resource_global_search(): void
    {
        $this->actingAs($this->admin);

        // Search by name
        $results = SupplierResource::getGlobalSearchResults('Beverages Corporation');
        $this->assertNotEmpty($results);
        $this->assertStringContainsString('KSBC', $results->first()->title);
        $this->assertArrayHasKey('GSTIN', $results->first()->details);
        $this->assertArrayHasKey('Code', $results->first()->details);

        // Search by Code
        $codeResults = SupplierResource::getGlobalSearchResults('MALABAR-BEV');
        $this->assertNotEmpty($codeResults);
        $this->assertStringContainsString('Malabar Beverage', $codeResults->first()->title);
    }

    public function test_purchase_receipt_form_has_supplier_dropdown(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/purchases/purchase-receipts/create');
        $response->assertSuccessful();
        $response->assertSee('Wholesale Supplier / Distributor');

        Livewire::actingAs($this->admin)
            ->test(CreatePurchaseReceipt::class)
            ->assertFormFieldExists('supplier_id');
    }
}
