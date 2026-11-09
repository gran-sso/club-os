<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPwaTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
    }

    public function test_admin_panel_renders_pwa_head_meta_and_manifest(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('manifest-admin.json');
        $response->assertSee('apple-mobile-web-app-capable');
        $response->assertSee('apple-mobile-web-app-status-bar-style');
        $response->assertSee('Malabar Admin');
        $response->assertSee('apple-touch-icon');
        $response->assertSee('navigator.serviceWorker.register');
    }

    public function test_admin_panel_renders_mobile_navigation_dock_and_status(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Mobile Navigation Dock');
        $response->assertSee('Dashboard');
        $response->assertSee('Rooms');
        $response->assertSee('Badminton');
        $response->assertSee('POS');
        $response->assertSee('Menu');
    }

    public function test_admin_manifest_file_is_valid_json_with_standalone_display(): void
    {
        $manifestPath = public_path('manifest-admin.json');
        $this->assertFileExists($manifestPath);

        $json = json_decode(file_get_contents($manifestPath), true);
        $this->assertIsArray($json);
        $this->assertEquals('Malabar Club Administration', $json['name']);
        $this->assertEquals('Malabar Admin', $json['short_name']);
        $this->assertEquals('/admin', $json['start_url']);
        $this->assertEquals('standalone', $json['display']);
        $this->assertEquals('#0f172a', $json['background_color']);
        $this->assertNotEmpty($json['icons']);
    }

    public function test_pwa_icons_exist_on_disk(): void
    {
        $this->assertFileExists(public_path('icons/icon-192.png'));
        $this->assertFileExists(public_path('icons/icon-512.png'));
        $this->assertFileExists(public_path('icons/apple-touch-icon.png'));
        $this->assertFileExists(public_path('icons/icon-192.svg'));
        $this->assertFileExists(public_path('icons/icon-512.svg'));
    }
}

