<?php

namespace Tests\Feature;

use App\Domain\Access\Models\Guest;
use App\Domain\Membership\Models\Card;
use App\Domain\Membership\Models\Dependent;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPhase2MembershipAndGuestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_access_member_create_page(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/admin/members/create');
        $response->assertStatus(200);
        $response->assertSee('Personal Details &amp; Statutory KYC', false);
        $response->assertSee('Aadhaar Card (UIDAI 12-digit)');
        $response->assertSee('Club Membership Classification');
    }

    public function test_admin_can_create_member_with_kyc_and_rfid(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $category = MembershipCategory::firstOrFail();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Members\Pages\CreateMember::class)
            ->fillForm([
                'first_name' => 'Ananthan',
                'last_name' => 'Nair',
                'phone' => '+91 98470 99887',
                'email' => 'ananthan@malabarclub.local',
                'id_proof_type' => 'Aadhaar Card',
                'id_proof_number' => '9876 5432 1098',
                'notes' => 'Palakkad Road, Vadakkencherry',
                'membership_number' => 'MC-TEST-99',
                'membership_category_id' => $category->id,
                'status' => 'active',
                'joined_at' => '2026-11-08',
                'rfid_card_number' => 'CARD-ANANTHAN-99',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = Member::where('membership_number', 'MC-TEST-99')->first();
        $this->assertNotNull($member);
        $this->assertEquals('Ananthan', $member->person->first_name);
        $this->assertEquals('Nair', $member->person->last_name);
        $this->assertEquals('Aadhaar Card', $member->person->id_proof_type);
        $this->assertEquals('9876 5432 1098', $member->person->id_proof_number);

        // Wallet must be initialized with 0 balance
        $wallet = WalletAccount::where('walletable_type', Member::class)
            ->where('walletable_id', $member->id)
            ->first();
        $this->assertNotNull($wallet);
        $this->assertEquals(0, $wallet->balance_paise);

        // Active RFID Card must be issued
        $card = Card::where('card_number', 'CARD-ANANTHAN-99')->first();
        $this->assertNotNull($card);
        $this->assertEquals('active', $card->status->value);
        $this->assertEquals($member->id, $card->cardable_id);
    }

    public function test_admin_can_edit_member_details(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $member = Member::where('membership_number', 'MC-001')->firstOrFail();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Members\Pages\EditMember::class, ['record' => $member->getKey()])
            ->fillForm([
                'first_name' => 'Venu Updated',
                'phone' => '+91 98470 00000',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $member->refresh();
        $this->assertEquals('Venu Updated', $member->person->first_name);
        $this->assertEquals('+91 98470 00000', $member->person->phone);
    }

    public function test_admin_can_register_dependent_for_member(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $member = Member::where('membership_number', 'MC-001')->firstOrFail();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Members\Pages\ListMembers::class)
            ->callTableAction('dependents', $member, [
                'dep_first_name' => 'Lakshmi',
                'dep_last_name' => 'Gopal',
                'dep_relationship' => 'Spouse',
                'dep_phone' => '+91 98470 88888',
                'dep_can_spend_wallet' => false, // Non-negotiable policy
            ])
            ->assertHasNoTableActionErrors();

        $dependent = Dependent::where('member_id', $member->id)->first();
        $this->assertNotNull($dependent);
        $this->assertEquals('Lakshmi', $dependent->person->first_name);
        $this->assertEquals('Spouse', $dependent->relationship);
        $this->assertFalse($dependent->can_spend_wallet);
    }

    public function test_admin_can_access_guest_create_page(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/admin/guests/create');
        $response->assertStatus(200);
        $response->assertSee('Guest Personal Identification &amp; KYC', false);
        $response->assertSee('Sponsoring Member &amp; Visit Authorization', false);
    }

    public function test_admin_can_create_guest_with_sponsor(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $sponsor = Member::where('membership_number', 'MC-001')->firstOrFail();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Guests\Pages\CreateGuest::class)
            ->fillForm([
                'first_name' => 'Vijay',
                'last_name' => 'Menon',
                'phone' => '+91 94470 11223',
                'email' => 'vijay.menon@example.com',
                'id_proof_type' => 'Aadhaar Card',
                'id_proof_number' => '1122 3344 5566',
                'sponsor_type' => Member::class,
                'sponsor_id' => $sponsor->id,
                'visit_date' => '2026-11-08',
                'status' => 'registered',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $guest = Guest::whereHas('person', function ($q) {
            $q->where('first_name', 'Vijay')->where('last_name', 'Menon');
        })->first();

        $this->assertNotNull($guest);
        $this->assertEquals(Member::class, $guest->sponsor_type);
        $this->assertEquals($sponsor->id, $guest->sponsor_id);
        $this->assertEquals('Aadhaar Card', $guest->person->id_proof_type);
        $this->assertEquals('1122 3344 5566', $guest->person->id_proof_number);
        $this->assertEquals('registered', $guest->status);
    }
}
