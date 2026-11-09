<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_access_filament_dashboard(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('Dashboard');
        $response->assertSee('Billed Sales');
        $response->assertSee('Collections Today');
        $response->assertSee('Front-Desk &amp; Facilities Pulse', false);
        $response->assertSee('Contractor &amp; Counter Shifts', false);
        $response->assertSee('System Integrity &amp; Tally Bridge', false);
        $response->assertSee('Recent Club Billing &amp; Operational Activity', false);
    }

    public function test_admin_can_access_filament_resources(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $routes = [
            '/admin/members',
            '/admin/membership-categories',
            '/admin/guests',
            '/admin/tariffs',
            '/admin/facilities',
            '/admin/inventory/inventory-items',
            '/admin/purchases/purchase-receipts',
            '/admin/shifts',
            '/admin/bills',
            '/admin/contractors',
            '/admin/contractor-settlements/contractor-settlement-batches',
            '/admin/room-reservations',
            '/admin/court-bookings',
            '/admin/subscriptions/facility-subscriptions',
            '/admin/expenses',
            '/admin/financial-periods',
            '/admin/journal-headers',
            '/admin/financial-reports',
            '/admin/integration-status',
            '/admin/room-rack',
            '/admin/court-schedule',
            '/admin/settings',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($admin)->get($route);
            $response->assertStatus(200);
        }
    }

    public function test_counter_staff_can_access_room_rack_and_court_schedule(): void
    {
        $cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $reception = User::where('email', 'reception@malabarclub.local')->firstOrFail();

        foreach ([$cashier, $reception] as $staff) {
            $response = $this->actingAs($staff)->get('/admin/room-rack');
            $response->assertStatus(200);
            $response->assertSee('Accommodation Deck');

            $responseCourt = $this->actingAs($staff)->get('/admin/court-schedule');
            $responseCourt->assertStatus(200);
            $responseCourt->assertSee('Badminton Court');
        }
    }

    public function test_counter_staff_denied_access_to_financial_ledgers_and_tariffs(): void
    {
        $cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $reception = User::where('email', 'reception@malabarclub.local')->firstOrFail();

        $restrictedRoutes = [
            '/admin/tariffs',
            '/admin/financial-reports',
            '/admin/journal-headers',
            '/admin/financial-periods',
            '/admin/contractor-settlements/contractor-settlement-batches',
            '/admin/purchases/purchase-receipts',
            '/admin/expenses',
            '/admin/integration-status',
            '/admin/facilities',
            '/admin/settings',
        ];

        foreach ([$cashier, $reception] as $staff) {
            foreach ($restrictedRoutes as $route) {
                $response = $this->actingAs($staff)->get($route);
                $response->assertStatus(403);
            }
        }
    }

    public function test_cashier_sees_front_desk_links_on_pos_but_not_club_admin(): void
    {
        $cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();

        $response = $this->actingAs($cashier)->get('/pos');
        $response->assertStatus(200);
        $response->assertSee('Accommodation Deck');
        $response->assertSee('Court Schedule');
        $response->assertDontSee('Club Admin');
    }

    public function test_admin_sees_club_admin_link_on_pos(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/pos');
        $response->assertStatus(200);
        $response->assertSee('Club Admin');
    }
}
