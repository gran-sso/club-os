<?php

namespace Tests\Feature;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Membership\Models\Person;
use App\Domain\Membership\Services\MemberService;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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
}

