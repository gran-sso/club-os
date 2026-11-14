<?php

namespace Tests\Feature;

use App\Domain\Communication\Models\MemberNotice;
use App\Domain\Communication\Models\MemberNoticeRead;
use App\Domain\Communication\Services\NoticeService;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Membership\Models\Person;
use App\Domain\Shared\Enums\MemberStatus;
use App\Filament\Resources\MemberNotices\Pages\ListMemberNotices;
use App\Livewire\Member\MemberDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $accountant;
    protected MembershipCategory $ordCategory;
    protected Member $memberPendingDues;
    protected Member $memberPaidDues;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ProductionBootstrapSeeder::class);

        $adminRole = Role::findByName('admin');
        $accountantRole = Role::findByName('accountant');

        $this->admin = User::factory()->create();
        $this->admin->assignRole($adminRole);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole($accountantRole);

        $this->ordCategory = MembershipCategory::where('code', 'ORD')->first();

        // 1. Member with Pending Dues
        $person1 = Person::create([
            'first_name' => 'Kiran',
            'last_name' => 'Kumar',
            'phone' => '9847111222',
            'email' => 'kiran.pending@example.com',
        ]);
        $this->memberPendingDues = Member::create([
            'membership_number' => 'MC-TEST-001',
            'person_id' => $person1->id,
            'membership_category_id' => $this->ordCategory->id,
            'status' => MemberStatus::ACTIVE,
            'fee_status' => 'pending',
            'joined_at' => now(),
            'valid_until' => now()->addYear(),
            'password' => bcrypt('password'),
        ]);

        // 2. Member with Paid Dues
        $person2 = Person::create([
            'first_name' => 'Sanjay',
            'last_name' => 'Nambiar',
            'phone' => '9847222333',
            'email' => 'sanjay.paid@example.com',
        ]);
        $this->memberPaidDues = Member::create([
            'membership_number' => 'MC-TEST-002',
            'person_id' => $person2->id,
            'membership_category_id' => $this->ordCategory->id,
            'status' => MemberStatus::ACTIVE,
            'fee_status' => 'paid',
            'joined_at' => now(),
            'valid_until' => now()->addYear(),
            'password' => bcrypt('password'),
        ]);
    }

    public function test_staff_can_create_club_wide_notice(): void
    {
        $service = app(NoticeService::class);

        $notice = $service->createNotice([
            'title' => 'Club Annual General Meeting Notice',
            'content' => 'The 42nd AGM will be convened at the Main Auditorium on Sunday at 10:00 AM.',
            'department' => 'admin',
            'priority' => 'info',
            'target_type' => 'all',
            'is_banner' => true,
        ], $this->admin->id);

        $this->assertDatabaseHas('member_notices', [
            'id' => $notice->id,
            'title' => 'Club Annual General Meeting Notice',
            'target_type' => 'all',
            'is_banner' => true,
        ]);

        $this->assertTrue($notice->is_active);
        $this->assertCount(1, $service->getActiveNoticesForMember($this->memberPendingDues));
        $this->assertCount(1, $service->getActiveNoticesForMember($this->memberPaidDues));
    }

    public function test_notice_service_targets_members_with_pending_dues_correctly(): void
    {
        $service = app(NoticeService::class);

        $notice = $service->broadcastDuesReminders(
            userId: $this->accountant->id,
            customNotes: 'Please remit before fiscal year end.'
        );

        $this->assertEquals('dues_pending', $notice->target_type);
        $this->assertEquals('reminder', $notice->priority);
        $this->assertTrue($notice->is_banner);

        // Member 1 (Pending Dues) SHOULD receive and see banner
        $pendingNotices = $service->getActiveNoticesForMember($this->memberPendingDues);
        $this->assertCount(1, $pendingNotices);
        $this->assertEquals($notice->id, $pendingNotices->first()->id);

        $pendingBanners = $service->getProminentBannersForMember($this->memberPendingDues);
        $this->assertCount(1, $pendingBanners);

        // Member 2 (Paid Dues) SHOULD NOT receive or see banner
        $paidNotices = $service->getActiveNoticesForMember($this->memberPaidDues);
        $this->assertCount(0, $paidNotices);

        $paidBanners = $service->getProminentBannersForMember($this->memberPaidDues);
        $this->assertCount(0, $paidBanners);
    }

    public function test_member_pwa_dashboard_renders_prominent_dues_banner(): void
    {
        $service = app(NoticeService::class);
        $service->broadcastDuesReminders(userId: $this->accountant->id);

        Livewire::actingAs($this->memberPendingDues, 'member')
            ->test(MemberDashboard::class)
            ->assertSeeHtml('Annual Membership Dues Pending Settlement')
            ->assertSeeHtml('Review Dues &amp; Pay')
            ->assertSeeHtml('Reminder');
    }

    public function test_member_can_mark_notice_as_read_and_dismiss_banner(): void
    {
        $service = app(NoticeService::class);
        $notice = $service->broadcastDuesReminders(userId: $this->accountant->id);

        $component = Livewire::actingAs($this->memberPendingDues, 'member')
            ->test(MemberDashboard::class)
            ->assertSeeHtml('Annual Membership Dues Pending Settlement');

        // Dismiss banner
        $component->call('dismissBanner', $notice->id);

        $this->assertDatabaseHas('member_notice_reads', [
            'notice_id' => $notice->id,
            'member_id' => $this->memberPendingDues->id,
            'is_dismissed' => true,
        ]);

        // Banner no longer rendered in prominent header
        $banners = $service->getProminentBannersForMember($this->memberPendingDues);
        $this->assertCount(0, $banners);

        // But still accessible in Notices tab
        $component->call('setTab', 'notices')
            ->assertSet('activeTab', 'notices')
            ->assertSeeHtml('Annual Membership Dues Pending Settlement');
    }

    public function test_outbox_messages_queued_when_whatsapp_or_sms_enabled(): void
    {
        $service = app(NoticeService::class);

        $service->broadcastDuesReminders(
            userId: $this->accountant->id,
            sendWhatsApp: true,
            sendSms: true
        );

        $this->assertDatabaseHas('outbox_messages', [
            'event_type' => 'MEMBER_NOTICE_WHATSAPP',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('outbox_messages', [
            'event_type' => 'MEMBER_NOTICE_SMS',
            'status' => 'pending',
        ]);
    }

    public function test_filament_admin_can_access_and_broadcast_dues_notices(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListMemberNotices::class)
            ->assertSuccessful()
            ->assertActionExists('broadcastDuesReminder')
            ->callAction('broadcastDuesReminder', [
                'custom_notes' => 'Urgent audit reminder.',
                'send_whatsapp' => false,
                'send_sms' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('member_notices', [
            'target_type' => 'dues_pending',
            'priority' => 'reminder',
        ]);
    }
}
