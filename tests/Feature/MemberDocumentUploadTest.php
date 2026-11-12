<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Membership\Models\Dependent;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Membership\Models\Person;
use App\Domain\Membership\Services\MemberService;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Livewire\CounterPos;
use App\Livewire\Member\MemberDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected MembershipCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->category = MembershipCategory::where('is_active', true)->firstOrFail();
        Storage::fake('local');
    }

    public function test_can_register_member_without_uploading_documents_optional(): void
    {
        $memberService = app(MemberService::class);

        $member = $memberService->registerMember(
            [
                'first_name' => 'Suresh',
                'last_name' => 'Gopi',
                'phone' => '+919847111222',
                'email' => 'suresh.gopi@example.com',
                'id_proof_type' => 'Passport',
                'id_proof_number' => 'M1234567',
                'id_proof_path' => null,
                'application_form_path' => null,
            ],
            $this->category->id,
            'MC-TEST-DOC-001'
        );

        $this->assertNotNull($member);
        $this->assertNull($member->id_proof_path);
        $this->assertNull($member->application_form_path);
        $this->assertEquals('Passport', $member->person->id_proof_type);
        $this->assertEquals('M1234567', $member->person->id_proof_number);
    }

    public function test_can_register_member_with_uploaded_documents(): void
    {
        $fakeIdProof = UploadedFile::fake()->create('aadhaar_card.pdf', 500, 'application/pdf');
        $idProofPath = $fakeIdProof->store('documents/members/kyc', 'local');

        $fakeAppForm = UploadedFile::fake()->create('application_signed.pdf', 800, 'application/pdf');
        $appFormPath = $fakeAppForm->store('documents/members/applications', 'local');

        $memberService = app(MemberService::class);

        $member = $memberService->registerMember(
            [
                'first_name' => 'Mohanlal',
                'last_name' => 'Viswanathan',
                'phone' => '+919847333444',
                'email' => 'mohanlal@example.com',
                'id_proof_type' => 'Aadhaar Card',
                'id_proof_number' => '999988887777',
                'id_proof_path' => $idProofPath,
                'application_form_path' => $appFormPath,
            ],
            $this->category->id,
            'MC-TEST-DOC-002'
        );

        $this->assertNotNull($member);
        $this->assertEquals($idProofPath, $member->id_proof_path);
        $this->assertEquals($appFormPath, $member->application_form_path);
        Storage::disk('local')->assertExists($idProofPath);
        Storage::disk('local')->assertExists($appFormPath);
    }

    public function test_member_profile_view_displays_status_and_actions(): void
    {
        // 1. Member without documents
        $memberWithoutDocs = app(MemberService::class)->registerMember(
            [
                'first_name' => 'Unni',
                'last_name' => 'Mukundan',
                'id_proof_type' => 'Driving Licence',
            ],
            $this->category->id,
            'MC-TEST-DOC-003'
        );

        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $memberWithoutDocs->id])
            ->assertSee('Not Uploaded')
            ->assertSee('Upload');

        // 2. Member with documents
        $idProofPath = UploadedFile::fake()->create('proof.jpg', 200, 'image/jpeg')->store('documents/members/kyc', 'local');
        $appFormPath = UploadedFile::fake()->create('form.pdf', 300, 'application/pdf')->store('documents/members/applications', 'local');

        $memberWithDocs = app(MemberService::class)->registerMember(
            [
                'first_name' => 'Mammootty',
                'last_name' => 'Panaparambil',
                'id_proof_type' => 'Passport',
                'id_proof_path' => $idProofPath,
                'application_form_path' => $appFormPath,
            ],
            $this->category->id,
            'MC-TEST-DOC-004'
        );

        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $memberWithDocs->id])
            ->assertSee('Uploaded')
            ->assertSee('View Proof')
            ->assertSee('View Form');
    }

    public function test_document_controller_serves_uploaded_file_to_authorized_staff(): void
    {
        $idProofPath = UploadedFile::fake()->create('proof.jpg', 200, 'image/jpeg')->store('documents/members/kyc', 'local');

        $member = app(MemberService::class)->registerMember(
            [
                'first_name' => 'Prithviraj',
                'last_name' => 'Sukumaran',
                'id_proof_path' => $idProofPath,
            ],
            $this->category->id,
            'MC-TEST-DOC-005'
        );

        // Cashier is denied
        $cashierRes = $this->actingAs($this->cashier)->get('/documents/members/' . $member->id . '/id_proof.jpg');
        $cashierRes->assertStatus(403);

        // Admin can view with private headers
        $adminRes = $this->actingAs($this->admin)->get('/documents/members/' . $member->id . '/id_proof.jpg');
        $adminRes->assertStatus(200);
        $adminRes->assertHeader('Pragma', 'no-cache');
    }

    public function test_member_photo_upload_edit_cleanup_and_display_across_forms_and_pos(): void
    {
        $initialPhoto = UploadedFile::fake()->image('ramesh_portrait.jpg', 300, 300);

        Livewire::actingAs($this->admin)
            ->test(CreateMember::class)
            ->fillForm([
                'first_name' => 'Ramesh',
                'last_name' => 'Kurup',
                'phone' => '+919847000111',
                'membership_number' => 'MC-PHOTO-001',
                'membership_category_id' => $this->category->id,
                'status' => 'active',
                'joined_at' => now()->toDateString(),
                'photo_path' => $initialPhoto,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = Member::with('person')->where('membership_number', 'MC-PHOTO-001')->firstOrFail();
        $firstPhotoPath = $member->photo_path;
        $this->assertNotNull($firstPhotoPath);
        $this->assertStringStartsWith('documents/members/photos/', $firstPhotoPath);
        Storage::disk('local')->assertExists($firstPhotoPath);

        // Replace member photo via EditMember and verify old file is cleaned up
        $replacementPhoto = UploadedFile::fake()->image('ramesh_updated.png', 300, 300);

        Livewire::actingAs($this->admin)
            ->test(EditMember::class, ['record' => $member->id])
            ->assertFormSet([
                'first_name' => 'Ramesh',
                'membership_number' => 'MC-PHOTO-001',
            ])
            ->set('data.photo_path', [])
            ->fillForm([
                'photo_path' => $replacementPhoto,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $member->refresh();
        $secondPhotoPath = $member->photo_path;
        $this->assertNotNull($secondPhotoPath);
        $this->assertNotEquals($firstPhotoPath, $secondPhotoPath);
        Storage::disk('local')->assertMissing($firstPhotoPath);
        Storage::disk('local')->assertExists($secondPhotoPath);

        // Member photo URL renders cleanly on Member View, ListMembers table, and Counter POS
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $member->id])
            ->assertSee("/documents/members/{$member->id}/photo", false);

        Livewire::actingAs($this->admin)
            ->test(ListMembers::class)
            ->assertSee("/documents/members/{$member->id}/photo", false);

        $counter = Counter::where('code', 'COUNTER-1')->firstOrFail();
        app(ShiftService::class)->openShift($counter->id, $this->cashier->id, 200000);

        Livewire::actingAs($this->cashier)
            ->test(CounterPos::class)
            ->set('searchQuery', 'MC-PHOTO-001')
            ->call('searchMember')
            ->assertSee("/documents/members/{$member->id}/photo", false);
    }

    public function test_dependent_optional_photo_upload_update_remove_and_table_action(): void
    {
        $member = app(MemberService::class)->registerMember(
            [
                'first_name' => 'Gokul',
                'last_name' => 'Menon',
                'phone' => '+919847000222',
            ],
            $this->category->id,
            'MC-PHOTO-002'
        );

        // 1. Register dependent with photo via ViewMember modal
        $depPhoto1 = UploadedFile::fake()->image('spouse.jpg', 240, 240);

        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $member->id])
            ->call('openDependentModal')
            ->set('depFirstName', 'Meenakshi')
            ->set('depLastName', 'Menon')
            ->set('depRelationship', 'Spouse')
            ->set('depPhoto', $depPhoto1)
            ->call('processDependentRegister')
            ->assertHasNoErrors();

        $dep1 = Dependent::with('person')->where('member_id', $member->id)->firstOrFail();
        $this->assertNotNull($dep1->photo_path);
        Storage::disk('local')->assertExists($dep1->photo_path);

        // 2. Replace dependent photo and remove dependent photo via ViewMember
        $depPhoto2 = UploadedFile::fake()->image('spouse_v2.webp', 240, 240);
        $oldPath = $dep1->photo_path;

        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $member->id])
            ->call('openDependentPhotoModal', $dep1->id)
            ->set('editingDependentPhoto', $depPhoto2)
            ->call('processDependentPhotoUpdate')
            ->assertHasNoErrors();

        $dep1->refresh();
        $newPath = $dep1->photo_path;
        $this->assertNotNull($newPath);
        $this->assertNotEquals($oldPath, $newPath);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);

        // Displays in Counter POS under Registered Dependents
        $counter = Counter::where('code', 'COUNTER-1')->firstOrFail();
        app(ShiftService::class)->openShift($counter->id, $this->cashier->id, 200000);

        Livewire::actingAs($this->cashier)
            ->test(CounterPos::class)
            ->set('searchQuery', 'MC-PHOTO-002')
            ->call('searchMember')
            ->assertSee("/documents/dependents/{$dep1->id}/photo", false);

        // Remove photo cleans up disk
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $member->id])
            ->call('removeDependentPhoto', $dep1->id)
            ->assertHasNoErrors();

        $dep1->refresh();
        $this->assertNull($dep1->photo_path);
        Storage::disk('local')->assertMissing($newPath);

        // 3. Register dependent via MembersTable row action (optional upload)
        $depPhoto3 = UploadedFile::fake()->image('child.png', 200, 200);
        Livewire::actingAs($this->admin)
            ->test(ListMembers::class)
            ->callTableAction('dependents', $member, [
                'dep_first_name' => 'Karthik',
                'dep_last_name' => 'Menon',
                'dep_relationship' => 'Child',
                'dep_can_spend_wallet' => false,
                'dep_photo_path' => $depPhoto3,
            ])
            ->assertHasNoTableActionErrors();

        $dep2 = Dependent::with('person')
            ->where('member_id', $member->id)
            ->where('relationship', 'Child')
            ->firstOrFail();
        $this->assertNotNull($dep2->photo_path);
        Storage::disk('local')->assertExists($dep2->photo_path);
    }

    public function test_photo_access_permissions_allow_cashier_verification_while_keeping_kyc_locked_and_scoping_portal(): void
    {
        $memberPhotoPath = UploadedFile::fake()->image('member.jpg', 200, 200)->store('documents/members/photos', 'local');
        $idProofPath = UploadedFile::fake()->create('aadhaar.pdf', 200, 'application/pdf')->store('documents/members/kyc', 'local');
        $depPhotoPath = UploadedFile::fake()->image('dep.jpg', 200, 200)->store('documents/dependents/photos', 'local');

        $memberService = app(MemberService::class);
        $member = $memberService->registerMember(
            [
                'first_name' => 'Sreejith',
                'last_name' => 'Nair',
                'phone' => '+919847000333',
                'photo_path' => $memberPhotoPath,
                'id_proof_path' => $idProofPath,
            ],
            $this->category->id,
            'MC-PHOTO-003'
        );
        $dependent = $memberService->registerDependent(
            $member,
            [
                'first_name' => 'Divya',
                'last_name' => 'Nair',
                'photo_path' => $depPhotoPath,
            ],
            'Spouse'
        );

        $otherMember = $memberService->registerMember(
            [
                'first_name' => 'Other',
                'last_name' => 'Member',
                'phone' => '+919847000444',
                'photo_path' => UploadedFile::fake()->image('other.jpg', 200, 200)->store('documents/members/photos', 'local'),
            ],
            $this->category->id,
            'MC-PHOTO-004'
        );
        $otherDependent = $memberService->registerDependent(
            $otherMember,
            [
                'first_name' => 'OtherDep',
                'photo_path' => UploadedFile::fake()->image('other_dep.jpg', 200, 200)->store('documents/dependents/photos', 'local'),
            ],
            'Child'
        );

        // 1. Unauthenticated access blocked
        $this->get("/documents/members/{$member->id}/photo")->assertRedirect('/login');
        $this->get("/documents/dependents/{$dependent->id}/photo")->assertRedirect('/login');

        // 2. Cashier (counter.operate) CAN view portraits for cardholder verification, but STILL 403 on KYC scans
        $this->actingAs($this->cashier)->get("/documents/members/{$member->id}/photo")
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->cashier)->get("/documents/dependents/{$dependent->id}/photo")
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache');
        $this->actingAs($this->cashier)->get("/documents/members/{$member->id}/id_proof.jpg")
            ->assertForbidden();

        // 3. Cashier cannot access admin Member View at all; Auditor (read-only members.view) cannot upload/modify dependent photos (requires members.manage)
        $this->actingAs($this->cashier)->get("/admin/members/{$member->id}")->assertForbidden();

        $auditor = User::create([
            'name' => 'ReadOnly Auditor',
            'email' => 'auditor-photo@malabarclub.local',
            'password' => bcrypt('Password@123'),
            'is_active' => true,
        ]);
        $auditor->assignRole('auditor');

        Livewire::actingAs($auditor)
            ->test(ViewMember::class, ['record' => $member->id])
            ->call('openDependentPhotoModal', $dependent->id)
            ->assertForbidden();

        // 4. Custom role without member/POS permissions gets 403 on photos
        $storekeeperRole = Role::create(['name' => 'photo_test_storekeeper', 'guard_name' => 'web']);
        $storekeeperRole->givePermissionTo('stock.view');
        $storekeeper = User::create([
            'name' => 'Storekeeper',
            'email' => 'storekeeper-photo@malabarclub.local',
            'password' => bcrypt('Password@123'),
            'is_active' => true,
        ]);
        $storekeeper->assignRole($storekeeperRole);

        $this->actingAs($storekeeper)->get("/documents/members/{$member->id}/photo")->assertForbidden();
        $this->actingAs($storekeeper)->get("/documents/dependents/{$dependent->id}/photo")->assertForbidden();

        // 5. Member portal routes serve own & own dependent photo, forbid other member's dependent photo
        $this->actingAs($member, 'member')->get('/member/photo')->assertOk();
        $this->actingAs($member, 'member')->get("/member/dependents/{$dependent->id}/photo")->assertOk();
        $this->actingAs($member, 'member')->get("/member/dependents/{$otherDependent->id}/photo")->assertForbidden();

        Livewire::actingAs($member, 'member')
            ->test(MemberDashboard::class)
            ->assertSee('/member/photo', false)
            ->call('setTab', 'family')
            ->assertSee("/member/dependents/{$dependent->id}/photo", false);
    }
}

