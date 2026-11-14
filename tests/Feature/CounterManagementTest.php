<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Counter;
use App\Domain\Tariff\Models\Department;
use App\Filament\Resources\Counters\Pages\ListCounters;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CounterManagementTest extends TestCase
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

    public function test_admin_and_manager_can_access_counters_master(): void
    {
        $this->actingAs($this->admin)->get('/admin/counters')->assertSuccessful();
        $this->actingAs($this->manager)->get('/admin/counters')->assertSuccessful();
        $this->actingAs($this->cashier)->get('/admin/counters')->assertForbidden();
    }

    public function test_admin_can_view_existing_counters_in_table(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListCounters::class)
            ->assertCanSeeTableRecords(Counter::all())
            ->assertSee('Main Reception Counter')
            ->assertSee('Bar POS Counter')
            ->assertSee('Restaurant & Sports Counter');
    }

    public function test_admin_can_create_new_billing_counter(): void
    {
        $dept = Department::where('code', 'bar')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(ListCounters::class)
            ->callAction('create', [
                'code' => 'COUNTER-4',
                'name' => 'Poolside Bar Counter',
                'department_id' => $dept->id,
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('counters', [
            'code' => 'COUNTER-4',
            'name' => 'Poolside Bar Counter',
            'department_id' => $dept->id,
            'is_active' => 1,
        ]);
    }

    public function test_admin_can_edit_and_deactivate_counter(): void
    {
        $counter = Counter::where('code', 'COUNTER-2')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(ListCounters::class)
            ->callTableAction('edit', $counter, [
                'code' => 'COUNTER-2',
                'name' => 'Renamed Bar Counter',
                'department_id' => $counter->department_id,
                'is_active' => false,
            ])
            ->assertHasNoTableActionErrors();

        $counter->refresh();
        $this->assertEquals('Renamed Bar Counter', $counter->name);
        $this->assertFalse($counter->is_active);

        // Deactivated counter is hidden from Counter POS station selection
        Livewire::actingAs($this->cashier)
            ->test(\App\Livewire\CounterPos::class)
            ->assertDontSeeHtml('<option value="' . $counter->id . '">Renamed Bar Counter (COUNTER-2)</option>');
    }
}
