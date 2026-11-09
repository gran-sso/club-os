<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Bill;
use App\Domain\Membership\Models\Member;
use App\Domain\Shared\Enums\MemberStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MemberPortalTest extends TestCase
{
    use RefreshDatabase;

    protected Member $activeMember;
    protected Member $otherMember;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->activeMember = Member::where('membership_number', 'MC-001')->firstOrFail();
        $this->otherMember = Member::where('membership_number', 'MC-002')->firstOrFail();
        $this->adminUser = User::firstOrFail();
    }

    public function test_member_login_screen_renders_successfully(): void
    {
        $response = $this->get(route('member.login'));
        $response->assertStatus(200);
        $response->assertSee('Member Portal');
        $response->assertSee('Membership Number or Mobile');
        $response->assertSee('Activate your account');
    }

    public function test_member_can_login_with_membership_number_and_pin(): void
    {
        $response = $this->post(route('member.login.submit'), [
            'login' => 'MC-001',
            'password' => 'Member@123',
        ]);

        $response->assertRedirect(route('member.dashboard'));
        $this->assertAuthenticatedAs($this->activeMember, 'member');
    }

    public function test_member_can_login_with_registered_phone(): void
    {
        $response = $this->post(route('member.login.submit'), [
            'login' => '9895011111',
            'password' => 'Member@123',
        ]);

        $response->assertRedirect(route('member.dashboard'));
        $this->assertAuthenticatedAs($this->activeMember, 'member');
    }

    public function test_member_login_fails_with_invalid_credentials(): void
    {
        $response = $this->from(route('member.login'))->post(route('member.login.submit'), [
            'login' => 'MC-001',
            'password' => 'WrongPassword',
        ]);

        $response->assertRedirect(route('member.login'));
        $response->assertSessionHasErrors(['password']);
        $this->assertGuest('member');
    }

    public function test_suspended_or_inactive_member_cannot_login(): void
    {
        $this->activeMember->update(['status' => MemberStatus::SUSPENDED]);

        $response = $this->from(route('member.login'))->post(route('member.login.submit'), [
            'login' => 'MC-001',
            'password' => 'Member@123',
        ]);

        $response->assertRedirect(route('member.login'));
        $response->assertSessionHasErrors(['login']);
        $this->assertGuest('member');
    }

    public function test_member_activation_with_membership_number_and_registered_phone(): void
    {
        // Set member without password
        $this->activeMember->update(['password' => null]);

        $response = $this->post(route('member.activate.submit'), [
            'membership_number' => 'MC-001',
            'phone' => '9895011111',
            'pin' => '456789',
            'pin_confirmation' => '456789',
        ]);

        $response->assertRedirect(route('member.dashboard'));
        $this->assertAuthenticatedAs($this->activeMember->fresh(), 'member');
        $this->assertTrue(Hash::check('456789', $this->activeMember->fresh()->password));
    }

    public function test_member_activation_fails_with_mismatched_phone(): void
    {
        $response = $this->from(route('member.activate'))->post(route('member.activate.submit'), [
            'membership_number' => 'MC-001',
            'phone' => '9999999999', // wrong phone
            'pin' => '456789',
            'pin_confirmation' => '456789',
        ]);

        $response->assertRedirect(route('member.activate'));
        $response->assertSessionHasErrors(['phone']);
        $this->assertGuest('member');
    }

    public function test_unauthenticated_user_redirected_to_member_login_on_protected_routes(): void
    {
        $response = $this->get(route('member.dashboard'));
        $response->assertRedirect(route('member.login'));
    }

    public function test_member_cannot_access_staff_pos_or_admin_panel(): void
    {
        // Authenticate as member only
        $this->actingAs($this->activeMember, 'member');

        // Accessing POS should redirect to staff login
        $posResponse = $this->get(route('pos'));
        $posResponse->assertRedirect(route('login'));

        // Accessing Filament admin panel should redirect to filament login or 403
        $adminResponse = $this->get('/admin');
        $this->assertTrue(in_array($adminResponse->getStatusCode(), [302, 403]));
    }

    public function test_member_can_view_dashboard_with_tabs_and_digital_card(): void
    {
        $this->actingAs($this->activeMember, 'member');

        $response = $this->get(route('member.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('MC-001');
        $response->assertSee('Venu Gopal');
        $response->assertSee('Sports Plans');
        $response->assertSee('Bookings');
        $response->assertSee('Itemized Bills');
        $response->assertSee('Family');
    }

    public function test_member_cannot_view_another_members_itemized_bill(): void
    {
        $this->actingAs($this->activeMember, 'member');

        // Create a bill belonging to other member
        $otherBill = Bill::forceCreate([
            'bill_number' => 'BILL-TEST-999',
            'shift_id' => null,
            'customer_type' => Member::class,
            'customer_id' => $this->otherMember->id,
            'department_code' => 'bar',
            'status' => 'paid',
            'gross_amount_paise' => 17700,
            'discount_amount_paise' => 0,
            'tax_amount_paise' => 2700,
            'net_amount_paise' => 17700,
            'paid_amount_paise' => 17700,
            'idempotency_key' => 'IDEM-TEST-999',
            'created_by_user_id' => $this->adminUser->id,
            'notes' => 'Confidential bill',
        ]);

        // Attempt to inspect other member's bill via Livewire
        Livewire::test(\App\Livewire\Member\MemberDashboard::class)
            ->call('openBillModal', $otherBill->id)
            ->assertSet('selectedBill', null)
            ->assertHasErrors(['bill']);
    }

    public function test_member_can_logout(): void
    {
        $this->actingAs($this->activeMember, 'member');

        $response = $this->post(route('member.logout'));
        $response->assertRedirect(route('member.login'));
        $this->assertGuest('member');
    }
}
