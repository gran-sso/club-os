<?php

namespace Tests\Feature;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Filament\Resources\MembershipCategories\Pages\CreateMembershipCategory;
use App\Filament\Resources\MembershipCategories\Pages\EditMembershipCategory;
use App\Filament\Resources\MembershipCategories\Pages\ListMembershipCategories;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MembershipCategoryManagementTest extends TestCase
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

    public function test_admin_can_access_membership_categories_resource(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/membership-categories');
        $response->assertStatus(200);
        $response->assertSee('Membership Categories');
        $response->assertSee('Ordinary Member');
        $response->assertSee('Life Member');
        $response->assertSee('Corporate Member');
    }

    public function test_manager_can_access_membership_categories(): void
    {
        $response = $this->actingAs($this->manager)->get('/admin/membership-categories');
        $response->assertStatus(200);
        $response->assertSee('Ordinary Member');
    }

    public function test_cashier_cannot_access_membership_categories(): void
    {
        $response = $this->actingAs($this->cashier)->get('/admin/membership-categories');
        $response->assertStatus(403);
    }

    public function test_admin_can_create_new_membership_category(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateMembershipCategory::class)
            ->fillForm([
                'code' => 'HON',
                'name' => 'Honorary Member',
                'annual_fee_paise' => 0,
                'has_voting_rights' => false,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('membership_categories', [
            'code' => 'HON',
            'name' => 'Honorary Member',
            'annual_fee_paise' => 0,
            'has_voting_rights' => 0,
            'is_active' => 1,
        ]);
    }

    public function test_admin_can_edit_membership_category(): void
    {
        $category = MembershipCategory::where('code', 'ORD')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(EditMembershipCategory::class, ['record' => $category->id])
            ->fillForm([
                'name' => 'Ordinary Resident Member',
                'annual_fee_paise' => 15000, // ₹15,000 entered in form (1,500,000 paise dehydrated)
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('membership_categories', [
            'id' => $category->id,
            'name' => 'Ordinary Resident Member',
            'annual_fee_paise' => 1500000,
        ]);
    }

    public function test_cannot_delete_membership_category_with_assigned_members(): void
    {
        $category = MembershipCategory::where('code', 'ORD')->firstOrFail();
        $this->assertTrue($category->members()->count() > 0);

        Livewire::actingAs($this->admin)
            ->test(EditMembershipCategory::class, ['record' => $category->id])
            ->callAction('delete')
            ->assertNotDispatched('close-modal');

        // Category must still exist in database
        $this->assertDatabaseHas('membership_categories', ['id' => $category->id]);
    }

    public function test_can_delete_unused_membership_category(): void
    {
        $unusedCategory = MembershipCategory::create([
            'code' => 'TEMP',
            'name' => 'Temporary Visitor',
            'annual_fee_paise' => 500000,
            'has_voting_rights' => false,
            'is_active' => true,
        ]);

        $this->assertEquals(0, $unusedCategory->members()->count());

        Livewire::actingAs($this->admin)
            ->test(EditMembershipCategory::class, ['record' => $unusedCategory->id])
            ->callAction('delete');

        $this->assertDatabaseMissing('membership_categories', ['id' => $unusedCategory->id]);
    }
}

