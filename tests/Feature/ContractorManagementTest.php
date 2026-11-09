<?php

namespace Tests\Feature;

use App\Domain\Contractor\Models\Contractor;
use App\Domain\Contractor\Models\ContractorSettlementBatch;
use App\Filament\Resources\Contractors\Pages\CreateContractor;
use App\Filament\Resources\Contractors\Pages\EditContractor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContractorManagementTest extends TestCase
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

    public function test_admin_can_access_contractors_list(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/contractors');
        $response->assertStatus(200);
        $response->assertSee('Contractors');
        $response->assertSee('Malabar Spices Catering & Hospitality');
        $response->assertSee('CONT-FOOD-01');
    }

    public function test_manager_can_access_contractors_list(): void
    {
        $responseManager = $this->actingAs($this->manager)->get('/admin/contractors');
        $responseManager->assertStatus(200);
        $responseManager->assertSee('Malabar Spices Catering & Hospitality');
    }

    public function test_cashier_cannot_access_contractors_resource(): void
    {
        $response = $this->actingAs($this->cashier)->get('/admin/contractors');
        $response->assertStatus(403);
    }

    public function test_admin_can_register_new_contractor(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateContractor::class)
            ->fillForm([
                'code' => 'CONT-BANQUET-02',
                'name' => 'Royal Heritage Outdoor Banquets',
                'department_code' => 'food',
                'contact_person' => 'Ibrahim V. P.',
                'phone' => '+91 98472 88888',
                'email' => 'events@royalheritage.local',
                'bank_name' => 'Federal Bank',
                'bank_account_number' => '104820019284',
                'bank_ifsc' => 'FDRL0001048',
                'commission_rate_percent' => 5.00,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('contractors', [
            'code' => 'CONT-BANQUET-02',
            'name' => 'Royal Heritage Outdoor Banquets',
            'contact_person' => 'Ibrahim V. P.',
            'bank_account_number' => '104820019284',
            'commission_rate_percent' => 5.00,
            'is_active' => 1,
        ]);
    }

    public function test_admin_can_edit_contractor_bank_and_contact_details(): void
    {
        $contractor = Contractor::where('code', 'CONT-FOOD-01')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(EditContractor::class, ['record' => $contractor->id])
            ->fillForm([
                'phone' => '+91 98471 99999',
                'bank_name' => 'HDFC Bank',
                'bank_account_number' => '50100234567890',
                'bank_ifsc' => 'HDFC0000492',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('contractors', [
            'id' => $contractor->id,
            'phone' => '+91 98471 99999',
            'bank_name' => 'HDFC Bank',
            'bank_account_number' => '50100234567890',
            'bank_ifsc' => 'HDFC0000492',
        ]);
    }

    public function test_cannot_delete_contractor_with_existing_settlement_batches(): void
    {
        $contractor = Contractor::where('code', 'CONT-FOOD-01')->firstOrFail();

        // Create a settlement batch linked to this contractor
        ContractorSettlementBatch::create([
            'batch_number' => 'BATCH-TEST-001',
            'contractor_id' => $contractor->id,
            'business_date' => now()->toDateString(),
            'cutoff_at' => now(),
            'total_food_sales_gross_paise' => 100000,
            'total_food_sales_net_paise' => 95238,
            'commission_rate_percent' => 0.00,
            'commission_amount_paise' => 0,
            'total_adjustments_paise' => 0,
            'net_payable_paise' => 95238,
            'paid_amount_paise' => 0,
            'residual_payable_paise' => 95238,
            'status' => 'draft',
        ]);

        $this->assertTrue($contractor->settlementBatches()->exists());

        Livewire::actingAs($this->admin)
            ->test(EditContractor::class, ['record' => $contractor->id])
            ->callAction('delete')
            ->assertNotDispatched('close-modal');

        $this->assertDatabaseHas('contractors', [
            'id' => $contractor->id,
            'code' => 'CONT-FOOD-01',
        ]);
    }

    public function test_can_delete_unused_contractor_profile(): void
    {
        $unused = Contractor::create([
            'code' => 'CONT-UNUSED-99',
            'name' => 'Temporary Trial Vendor',
            'department_code' => 'food',
            'is_active' => true,
        ]);

        $this->assertEquals(0, $unused->settlementBatches()->count());

        Livewire::actingAs($this->admin)
            ->test(EditContractor::class, ['record' => $unused->id])
            ->callAction('delete');

        $this->assertDatabaseMissing('contractors', [
            'id' => $unused->id,
        ]);
    }
}
