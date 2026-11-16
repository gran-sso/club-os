<?php

namespace Tests\Feature;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Membership\Models\Person;
use App\Domain\Membership\Services\MemberService;
use App\Domain\Shared\Enums\MemberStatus;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberProfileExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_member_and_person_models_support_professional_and_profile_fields(): void
    {
        $category = MembershipCategory::firstOrFail();

        $proposerPerson = Person::create([
            'title' => 'Adv.',
            'first_name' => 'Govind',
            'last_name' => 'Menon',
            'phone' => '+91 98470 11111',
            'profession' => 'Senior Advocate',
        ]);

        $proposer = Member::create([
            'membership_number' => 'MC-P-001',
            'person_id' => $proposerPerson->id,
            'membership_category_id' => $category->id,
            'status' => MemberStatus::ACTIVE,
            'joined_at' => '2015-01-01',
        ]);

        $seconderPerson = Person::create([
            'title' => 'Er.',
            'first_name' => 'Madhavan',
            'last_name' => 'Nair',
            'phone' => '+91 98470 22222',
            'profession' => 'Chief Engineer (Retd.)',
        ]);

        $seconder = Member::create([
            'membership_number' => 'MC-S-002',
            'person_id' => $seconderPerson->id,
            'membership_category_id' => $category->id,
            'status' => MemberStatus::ACTIVE,
            'joined_at' => '2018-06-01',
        ]);

        $person = Person::create([
            'title' => 'Dr.',
            'first_name' => 'Kavitha',
            'last_name' => 'Warrier',
            'phone' => '+91 98470 33333',
            'email' => 'kavitha.warrier@example.com',
            'date_of_birth' => '1980-04-15',
            'gender' => 'female',
            'blood_group' => 'O+',
            'profession' => 'Cardiologist',
            'designation' => 'Chief of Cardiology',
            'organization' => 'Aster Medcity',
            'address_line1' => '"Sreevalsam", Door No. 14/220',
            'address_line2' => 'Club Road, Near Town Hall',
            'city' => 'Vadakkencherry',
            'state' => 'Kerala',
            'postal_code' => '678683',
            'emergency_contact_name' => 'Dr. Suresh Warrier (Spouse)',
            'emergency_contact_phone' => '+91 98470 44444',
            'id_proof_type' => 'Aadhaar Card',
            'id_proof_number' => '9876 5432 1234',
        ]);

        $member = Member::create([
            'membership_number' => 'MC-W-100',
            'person_id' => $person->id,
            'membership_category_id' => $category->id,
            'status' => MemberStatus::ACTIVE,
            'joined_at' => '2024-01-15',
            'valid_until' => '2027-01-15',
            'proposer_member_id' => $proposer->id,
            'seconder_member_id' => $seconder->id,
            'wedding_anniversary' => '2008-05-22',
            'vehicle_numbers' => 'KL-49-A-1234, KL-09-B-5678',
            'committee_role' => 'Managing Committee Member',
        ]);

        // Test Person Model Accessors
        $this->assertEquals('Dr. Kavitha Warrier', $person->full_name_with_title);
        $this->assertEquals('Chief of Cardiology, Aster Medcity', $person->professional_headline);
        $this->assertEquals('"Sreevalsam", Door No. 14/220, Club Road, Near Town Hall, Vadakkencherry, Kerala, 678683', $person->formatted_address);
        $this->assertNotNull($person->age);
        $this->assertGreaterThan(40, $person->age);

        // Test Member Model Relationships & Delegation
        $this->assertEquals('Dr. Kavitha Warrier', $member->display_name_with_title);
        $this->assertEquals('Dr.', $member->title);
        $this->assertEquals('Cardiologist', $member->profession);
        $this->assertEquals('Chief of Cardiology', $member->designation);
        $this->assertEquals('Aster Medcity', $member->organization);
        $this->assertEquals('O+', $member->blood_group);
        $this->assertEquals($proposer->id, $member->proposerMember->id);
        $this->assertEquals('Govind Menon', $member->proposerMember->name);
        $this->assertEquals($seconder->id, $member->seconderMember->id);
        $this->assertEquals('Madhavan Nair', $member->seconderMember->name);
        $this->assertEquals('Managing Committee Member', $member->committee_role);
        $this->assertEquals('KL-49-A-1234, KL-09-B-5678', $member->vehicle_numbers);
    }

    public function test_admin_can_create_member_with_full_profile_via_filament_form(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $category = MembershipCategory::firstOrFail();

        // Create a proposer member
        $proposer = app(MemberService::class)->registerMember(
            ['first_name' => 'Ramesh', 'last_name' => 'Varma', 'phone' => '+91 98470 55555'],
            $category->id,
            'MC-PROP-01'
        );

        Livewire::actingAs($admin)
            ->test(CreateMember::class)
            ->fillForm([
                'title' => 'Dr.',
                'first_name' => 'Sunil',
                'last_name' => 'Nambiar',
                'phone' => '+91 98470 66666',
                'email' => 'sunil.nambiar@example.com',
                'date_of_birth' => '1975-08-20',
                'gender' => 'male',
                'blood_group' => 'B+',
                'profession' => 'Senior Surgeon',
                'designation' => 'Chief Surgeon & Partner',
                'organization' => 'Malabar Hospital',
                'address_line1' => 'Palm Grove, Villa 4',
                'address_line2' => 'Civil Station Road',
                'city' => 'Palakkad',
                'state' => 'Kerala',
                'postal_code' => '678001',
                'emergency_contact_name' => 'Meera Nambiar',
                'emergency_contact_phone' => '+91 98470 77777',
                'wedding_anniversary' => '2002-11-14',
                'committee_role' => 'President',
                'proposer_member_id' => $proposer->id,
                'vehicle_numbers' => 'KL-09-AR-9999',
                'id_proof_type' => 'Passport',
                'id_proof_number' => 'M1234567',
                'notes' => 'Member introduced during charter expansion.',
                'membership_number' => 'MC-PRES-01',
                'membership_category_id' => $category->id,
                'status' => 'active',
                'joined_at' => '2026-11-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = Member::where('membership_number', 'MC-PRES-01')->firstOrFail();
        $this->assertEquals('MC-PRES-01', $member->membership_number);
        $this->assertEquals('President', $member->committee_role);
        $this->assertEquals('KL-09-AR-9999', $member->vehicle_numbers);
        $this->assertEquals('2002-11-14', $member->wedding_anniversary->toDateString());
        $this->assertEquals($proposer->id, $member->proposer_member_id);

        $person = $member->person;
        $this->assertNotNull($person);
        $this->assertEquals('Dr.', $person->title);
        $this->assertEquals('Sunil', $person->first_name);
        $this->assertEquals('Nambiar', $person->last_name);
        $this->assertEquals('B+', $person->blood_group);
        $this->assertEquals('Senior Surgeon', $person->profession);
        $this->assertEquals('Chief Surgeon & Partner', $person->designation);
        $this->assertEquals('Malabar Hospital', $person->organization);
        $this->assertEquals('Palm Grove, Villa 4', $person->address_line1);
        $this->assertEquals('Palakkad', $person->city);
        $this->assertEquals('678001', $person->postal_code);
        $this->assertEquals('Meera Nambiar', $person->emergency_contact_name);
    }

    public function test_admin_can_edit_member_profile_details(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $category = MembershipCategory::firstOrFail();

        $member = app(MemberService::class)->registerMember(
            [
                'first_name' => 'Unni',
                'last_name' => 'Kurup',
                'phone' => '+91 98470 88888',
                'profession' => 'Business',
            ],
            $category->id,
            'MC-UK-001'
        );

        Livewire::actingAs($admin)
            ->test(EditMember::class, ['record' => $member->id])
            ->assertFormSet([
                'first_name' => 'Unni',
                'last_name' => 'Kurup',
                'phone' => '+91 98470 88888',
                'profession' => 'Business',
            ])
            ->fillForm([
                'title' => 'Shri.',
                'profession' => 'Tea Plantation Owner & Exporter',
                'designation' => 'Managing Director',
                'organization' => 'Nelliyampathy Plantations Pvt Ltd',
                'city' => 'Nenmara',
                'blood_group' => 'A+',
                'committee_role' => 'Treasurer',
                'vehicle_numbers' => 'KL-49-T-7788',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $member->refresh();
        $this->assertEquals('Treasurer', $member->committee_role);
        $this->assertEquals('KL-49-T-7788', $member->vehicle_numbers);
        $this->assertEquals('Shri.', $member->person->title);
        $this->assertEquals('Tea Plantation Owner & Exporter', $member->person->profession);
        $this->assertEquals('Managing Director', $member->person->designation);
        $this->assertEquals('Nelliyampathy Plantations Pvt Ltd', $member->person->organization);
        $this->assertEquals('Nenmara', $member->person->city);
        $this->assertEquals('A+', $member->person->blood_group);
    }

    public function test_member_360_profile_view_renders_all_expanded_sections(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $category = MembershipCategory::firstOrFail();

        $proposer = app(MemberService::class)->registerMember(
            ['first_name' => 'Col. K.', 'last_name' => 'Unnithan', 'phone' => '+91 98470 99001'],
            $category->id,
            'MC-CHARTER-01'
        );

        $member = app(MemberService::class)->registerMember(
            [
                'title' => 'Justice',
                'first_name' => 'K. R.',
                'last_name' => 'Narayanan',
                'phone' => '+91 98470 99002',
                'email' => 'justice.narayanan@example.com',
                'date_of_birth' => '1955-03-10',
                'gender' => 'male',
                'blood_group' => 'AB+',
                'profession' => 'Former High Court Judge',
                'designation' => 'Chairman, Arbitral Tribunal',
                'organization' => 'Kerala State Judicial Commission',
                'address_line1' => '"Neethi Bhavan"',
                'address_line2' => 'High Court Colony, Ernakulam',
                'city' => 'Kochi',
                'state' => 'Kerala',
                'postal_code' => '682031',
                'emergency_contact_name' => 'Adv. Rohit Narayanan (Son)',
                'emergency_contact_phone' => '+91 98470 99003',
                'id_proof_type' => 'Indian Passport',
                'id_proof_number' => 'J8899001',
            ],
            $category->id,
            'MC-HON-007',
            '2020-01-01',
            null,
            [
                'committee_role' => 'Hon. Secretary',
                'proposer_member_id' => $proposer->id,
                'wedding_anniversary' => '1982-12-05',
                'vehicle_numbers' => 'KL-07-CJ-0001, KL-01-AB-9999',
            ]
        );

        $response = $this->actingAs($admin)->get("/admin/members/{$member->id}");

        $response->assertStatus(200);

        // Header Card Assertions
        $response->assertSee('Justice');
        $response->assertSee('K. R. Narayanan');
        $response->assertSee('Hon. Secretary');
        $response->assertSee('Chairman, Arbitral Tribunal, Kerala State Judicial Commission');
        $response->assertSee('AB+');

        // Overview Tab Cards Assertions
        $response->assertSee('Professional &amp; Social Standing', false);
        $response->assertSee('Former High Court Judge');
        $response->assertSee('Chairman, Arbitral Tribunal');
        $response->assertSee('Kerala State Judicial Commission');

        $response->assertSee('Registered Residential &amp; Postal Address', false);
        $response->assertSee('&quot;Neethi Bhavan&quot;', false);
        $response->assertSee('Kochi');
        $response->assertSee('682031');

        $response->assertSee('Demographics &amp; Emergency Contacts', false);
        $response->assertSee('Adv. Rohit Narayanan (Son)');
        $response->assertSee('+91 98470 99003');

        $response->assertSee('Club Heritage, Sponsors &amp; Assets', false);
        $response->assertSee('MC-CHARTER-01');
        $response->assertSee('Col. K. Unnithan');
        $response->assertSee('KL-07-CJ-0001');
        $response->assertSee('KL-01-AB-9999');

        $response->assertSee('ID Proof &amp; Personal Records', false);
        $response->assertSee('Indian Passport');
        $response->assertSee('J8899001');

        $response->assertSee('Membership Classification &amp; Access', false);
    }

    public function test_member_directory_dual_view_and_cards_rendering(): void
    {
        $admin = User::firstOrFail();
        $category = MembershipCategory::firstOrFail();

        $member = app(MemberService::class)->registerMember(
            [
                'title' => 'Dr.',
                'first_name' => 'Aravind',
                'last_name' => 'Swaminathan',
                'phone' => '+91 98471 23456',
                'email' => 'dr.aravind@example.com',
                'profession' => 'Senior Cardiologist',
                'designation' => 'Head of Cardiology',
                'organization' => 'Apollo Medical Centre',
                'city' => 'Kozhikode',
            ],
            $category->id,
            'MC-DIR-101',
            '2021-06-01',
            null,
            [
                'committee_role' => 'Vice President',
                'vehicle_numbers' => 'KL-11-BH-2020',
            ]
        );

        // 1. Default view renders table
        $tableResponse = $this->actingAs($admin)->get('/admin/members');
        $tableResponse->assertStatus(200);
        $tableResponse->assertSee('View Layout:');
        $tableResponse->assertSee('Table View');
        $tableResponse->assertSee('Directory Cards');
        $tableResponse->assertSee('MC-DIR-101');

        // 2. Cards view renders directory cards with photo avatar, headline, committee badge
        $cardsResponse = $this->actingAs($admin)->get('/admin/members?view=cards');
        $cardsResponse->assertStatus(200);
        $cardsResponse->assertSee('Directory Cards');
        $cardsResponse->assertSee('Dr.');
        $cardsResponse->assertSee('Aravind Swaminathan');
        $cardsResponse->assertSee('Head of Cardiology, Apollo Medical Centre');
        $cardsResponse->assertSee('Vice President');
        $cardsResponse->assertSee('MC-DIR-101');
        $cardsResponse->assertSee('Kozhikode');

        // 3. Livewire component reactive card search & filter
        Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Members\Pages\ListMembers::class)
            ->set('viewMode', 'cards')
            ->assertSee('Aravind Swaminathan')
            ->set('cardSearch', 'Apollo')
            ->assertSee('Aravind Swaminathan')
            ->set('cardSearch', 'NonExistentPersonName12345')
            ->assertSee('No members found');
    }
}
