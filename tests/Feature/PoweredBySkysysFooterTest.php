<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoweredBySkysysFooterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_unified_login_page_renders_powered_by_skysys_footer(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Exclusively for Members &amp; Authorised Staff', false);
        $response->assertSee('Club OS, Club Management System, powered by', false);
        $response->assertSee('href="https://www.skysysonline.com/"', false);
        $response->assertSee('target="_blank"', false);
        $response->assertSee('rel="noopener noreferrer"', false);
        $response->assertSee('Skysys</a>', false);
    }

    public function test_member_login_page_renders_powered_by_skysys_footer(): void
    {
        $response = $this->get('/member/login');
        $response->assertStatus(200);
        $response->assertSee('Powered by', false);
        $response->assertSee('https://www.skysysonline.com/', false);
        $response->assertSee('Skysys</a>', false);
    }

    public function test_password_change_page_renders_powered_by_skysys_footer(): void
    {
        $user = User::where('username', 'manager')->first() ?? User::factory()->create();
        $response = $this->actingAs($user, 'web')->get('/password/change');
        $response->assertStatus(200);
        $response->assertSee('Powered by', false);
        $response->assertSee('https://www.skysysonline.com/', false);
        $response->assertSee('Skysys</a>', false);
    }

    public function test_counter_pos_renders_powered_by_skysys_attribution(): void
    {
        $cashier = User::where('email', 'cashier1@malabarclub.local')->first()
            ?? User::where('username', 'cashier1')->first()
            ?? User::factory()->create();
        $response = $this->actingAs($cashier, 'web')->get('/pos');
        $response->assertStatus(200);
        $response->assertSee('Powered by', false);
        $response->assertSee('https://www.skysysonline.com/', false);
        $response->assertSee('Skysys</a>', false);
    }

    public function test_filament_admin_panel_renders_powered_by_skysys_footer_via_hook(): void
    {
        $admin = User::where('username', 'admin')->first() ?? User::factory()->create();
        $response = $this->actingAs($admin, 'web')->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('Powered by', false);
        $response->assertSee('https://www.skysysonline.com/', false);
        $response->assertSee('Skysys</a>', false);
    }
}
