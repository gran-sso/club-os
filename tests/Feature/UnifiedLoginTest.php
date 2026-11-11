<?php

namespace Tests\Feature;

use App\Domain\Membership\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnifiedLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_unified_login_screen_renders_segmented_tabs_for_staff_and_members(): void
    {
        $this->get('/')->assertRedirect('/login');

        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('role="tablist"', false);
        $response->assertSee('id="tab-staff"', false);
        $response->assertSee('id="tab-member"', false);
        $response->assertSee('Staff Terminal');
        $response->assertSee('Member Portal');
        $response->assertSee('action="'.route('login').'"', false);
        $response->assertSee('action="'.route('member.login.submit').'"', false);
        $response->assertDontSee('aria-controls=', false);
    }

    public function test_tab_query_parameter_selects_member_tab_server_side(): void
    {
        $response = $this->get('/login?tab=member');
        $response->assertStatus(200);
        $response->assertSee('data-initial-tab="member"', false);
        $response->assertSee('data-force-tab="1"', false);
        $response->assertSee('id="tab-member"', false);
        $this->assertMatchesRegularExpression('/id="tab-member"[\s\S]*?aria-selected="true"/', $response->getContent());
        $this->assertMatchesRegularExpression('/id="panel-staff"[\s\S]*?hidden="until-found"/', $response->getContent());
    }

    public function test_staff_roles_redirect_to_role_specific_landing_pages_after_login(): void
    {
        $auditor = User::create([
            'name' => 'Test Auditor',
            'email' => 'auditor@malabarclub.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $auditor->assignRole(Role::findByName('auditor'));

        $cases = [
            ['email' => 'admin@malabarclub.local', 'expected' => url('/admin')],
            ['email' => 'manager@malabarclub.local', 'expected' => url('/admin')],
            ['email' => 'accountant@malabarclub.local', 'expected' => url('/admin')],
            ['email' => 'auditor@malabarclub.local', 'expected' => url('/admin')],
            ['email' => 'reception@malabarclub.local', 'expected' => url('/admin/room-rack')],
            ['email' => 'cashier1@malabarclub.local', 'expected' => route('pos')],
        ];

        foreach ($cases as $case) {
            $response = $this->post('/login', [
                'email' => $case['email'],
                'password' => 'password',
                '_portal' => 'staff',
            ]);

            $response->assertRedirect($case['expected']);
            $this->assertAuthenticated('web');

            $this->post(route('logout'));
            $this->assertGuest('web');
        }
    }

    public function test_authenticated_root_and_login_redirect_to_respective_dashboards(): void
    {
        $cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $reception = User::where('email', 'reception@malabarclub.local')->firstOrFail();
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $member = Member::where('membership_number', 'MC-001')->firstOrFail();

        $this->actingAs($cashier, 'web')->get('/')->assertRedirect(route('pos'));
        $this->actingAs($cashier, 'web')->get('/login')->assertRedirect(route('pos'));

        $this->actingAs($reception, 'web')->get('/')->assertRedirect(url('/admin/room-rack'));
        $this->actingAs($reception, 'web')->get('/login')->assertRedirect(url('/admin/room-rack'));

        $this->actingAs($admin, 'web')->get('/')->assertRedirect(url('/admin'));
        $this->actingAs($admin, 'web')->get('/login')->assertRedirect(url('/admin'));

        Auth::guard('web')->logout();
        $this->flushSession();

        $this->actingAs($member, 'member')->get('/')->assertRedirect(route('member.dashboard'));
        $this->actingAs($member, 'member')->get('/login')->assertRedirect(route('member.dashboard'));
    }

    public function test_admin_login_redirect_preserves_intended_admin_destination(): void
    {
        $protectedResponse = $this->get('/admin/help');
        $protectedResponse->assertRedirect('/admin/login');
        $this->assertEquals(url('/admin/help'), session('url.intended'));

        $adminLoginRedirect = $this->get('/admin/login');
        $adminLoginRedirect->assertRedirect(route('login', ['tab' => 'staff']));
        $this->assertEquals(url('/admin/help'), session('url.intended'));

        $loginResponse = $this->post('/login', [
            'email' => 'admin@malabarclub.local',
            'password' => 'password',
            '_portal' => 'staff',
        ]);

        $loginResponse->assertRedirect(url('/admin/help'));
    }

    public function test_member_login_error_from_unified_screen_preserves_member_tab(): void
    {
        $response = $this->from('/login')->post(route('member.login.submit'), [
            'login' => 'MC-001',
            'password' => 'WrongPin99',
            '_portal' => 'member',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['password']);
        $this->assertGuest('member');

        $this->flushSession();

        // Simulate real browser following 302 back to /login immediately after validation failure
        $followUp = $this->followingRedirects()->from('/login')->post(route('member.login.submit'), [
            'login' => 'MC-001',
            'password' => 'WrongPin99',
            '_portal' => 'member',
        ]);
        $followUp->assertStatus(200);
        $followUp->assertSee('data-initial-tab="member"', false);
        $followUp->assertSee('data-force-tab="1"', false);
        $followUp->assertSee('id="member-form-error"', false);
        $followUp->assertSee('The security PIN or password entered is incorrect.');

        // Valid member login from unified tab still lands on member dashboard
        $member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $this->from('/login')->post(route('member.login.submit'), [
            'login' => 'MC-001',
            'password' => 'Member@123',
            '_portal' => 'member',
        ])->assertRedirect(route('member.dashboard'));
        $this->assertAuthenticatedAs($member, 'member');
    }

    public function test_member_pwa_standalone_login_remains_functional_and_links_to_staff_tab(): void
    {
        $response = $this->get(route('member.login'));
        $response->assertStatus(200);
        $response->assertSee(route('login', ['tab' => 'staff']), false);
    }

    public function test_deactivated_staff_account_cannot_sign_in_via_unified_login(): void
    {
        $cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $cashier->update(['is_active' => false]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'cashier1@malabarclub.local',
            'password' => 'password',
            '_portal' => 'staff',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest('web');
    }

    public function test_production_environment_blocks_hardcoded_demo_password_fallbacks(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $admin->update(['password' => Hash::make('RealProdSecret#987')]);

        $member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $member->update(['password' => Hash::make('847291')]);

        $originalEnv = $this->app['env'];

        try {
            $this->app['env'] = 'production';

            // Demo staff fallback rejected in production
            $staffFail = $this->withSession(['_token' => 'prod-csrf'])
                ->from('/login')
                ->post('/login', [
                    '_token' => 'prod-csrf',
                    'email' => 'admin@malabarclub.local',
                    'password' => 'Password@123',
                ]);
            $staffFail->assertRedirect('/login');
            $staffFail->assertSessionHasErrors(['email']);
            $this->assertGuest('web');

            // Real staff credential works in production
            $staffOk = $this->withSession(['_token' => 'prod-csrf'])
                ->post('/login', [
                    '_token' => 'prod-csrf',
                    'email' => 'admin@malabarclub.local',
                    'password' => 'RealProdSecret#987',
                ]);
            $staffOk->assertRedirect(url('/admin'));
            $this->assertAuthenticatedAs($admin, 'web');

            $this->withSession(['_token' => 'prod-csrf'])
                ->post(route('logout'), ['_token' => 'prod-csrf']);

            // Demo member fallback ('1234' / 'Member@123') rejected in production
            $memberFail = $this->withSession(['_token' => 'prod-csrf'])
                ->from('/login')
                ->post(route('member.login.submit'), [
                    '_token' => 'prod-csrf',
                    'login' => 'MC-001',
                    'password' => 'Member@123',
                    '_portal' => 'member',
                ]);
            $memberFail->assertRedirect('/login');
            $memberFail->assertSessionHasErrors(['password']);
            $this->assertGuest('member');

            // Real member PIN works in production
            $memberOk = $this->withSession(['_token' => 'prod-csrf'])
                ->post(route('member.login.submit'), [
                    '_token' => 'prod-csrf',
                    'login' => 'MC-001',
                    'password' => '847291',
                    '_portal' => 'member',
                ]);
            $memberOk->assertRedirect(route('member.dashboard'));
            $this->assertAuthenticatedAs($member, 'member');
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }
}
