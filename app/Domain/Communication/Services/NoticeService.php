<?php

namespace App\Domain\Communication\Services;

use App\Domain\Communication\Models\MemberNotice;
use App\Domain\Communication\Models\MemberNoticeRead;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Membership\Models\Member;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NoticeService
{
    /**
     * Create a new club member notice/bulletin.
     */
    public function createNotice(array $data, ?int $userId = null): MemberNotice
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['created_by'] = $userId ?? auth()->id();
            $data['published_at'] = $data['published_at'] ?? now();
            $data['is_active'] = $data['is_active'] ?? true;

            $notice = MemberNotice::create($data);

            if ($notice->is_active && ($notice->send_whatsapp || $notice->send_sms)) {
                $this->queueNoticeOutbox($notice);
            }

            return $notice;
        });
    }

    /**
     * 1-Click Broadcast: Generate a prominent dues reminder for all members with pending dues.
     */
    public function broadcastDuesReminders(
        ?int $userId = null,
        ?string $customNotes = null,
        bool $sendWhatsApp = false,
        bool $sendSms = false
    ): MemberNotice {
        $body = "Dear Member,\n\nOur records indicate that your annual club subscription or admission fee dues are currently outstanding. " .
            "In accordance with Malabar Club bylaws, prompt settlement of statutory dues is required to maintain full club privileges and access.\n\n" .
            ($customNotes ? "Special Note from Accounts: {$customNotes}\n\n" : "") .
            "Please remit via the Front Desk (Cash / UPI / Card) or contact the Club Accounts Office.";

        return $this->createNotice([
            'title' => 'Annual Membership Dues Pending Settlement',
            'content' => $body,
            'department' => 'accounts',
            'priority' => 'reminder',
            'target_type' => 'dues_pending',
            'action_label' => 'Review Dues & Pay',
            'action_url' => '/member/dashboard?tab=wallet',
            'is_banner' => true,
            'published_at' => now(),
            'expires_at' => now()->addMonths(3),
            'is_active' => true,
            'send_whatsapp' => $sendWhatsApp,
            'send_sms' => $sendSms,
        ], $userId);
    }

    /**
     * Mark a notice as read by a member.
     */
    public function markAsRead(Member $member, int $noticeId): void
    {
        MemberNoticeRead::updateOrCreate(
            ['notice_id' => $noticeId, 'member_id' => $member->id],
            ['read_at' => now()]
        );
    }

    /**
     * Dismiss a prominent banner notice so it is no longer sticky on dashboard for this member.
     */
    public function dismissNotice(Member $member, int $noticeId): void
    {
        MemberNoticeRead::updateOrCreate(
            ['notice_id' => $noticeId, 'member_id' => $member->id],
            ['read_at' => now(), 'is_dismissed' => true]
        );
    }

    /**
     * Mark all active notices as read for a member.
     */
    public function markAllAsRead(Member $member): void
    {
        $notices = MemberNotice::active()->forMember($member)->get();
        foreach ($notices as $notice) {
            $this->markAsRead($member, $notice->id);
        }
    }

    /**
     * Get the count of unread notices for a member.
     */
    public function getUnreadCount(Member $member): int
    {
        $activeNoticeIds = MemberNotice::active()->forMember($member)->pluck('id');
        $readNoticeIds = MemberNoticeRead::where('member_id', $member->id)
            ->whereIn('notice_id', $activeNoticeIds)
            ->pluck('notice_id');

        return $activeNoticeIds->diff($readNoticeIds)->count();
    }

    /**
     * Get active notices for a member, enriched with read status.
     */
    public function getActiveNoticesForMember(Member $member, int $limit = 30): Collection
    {
        return MemberNotice::active()
            ->forMember($member)
            ->with(['reads' => function ($q) use ($member) {
                $q->where('member_id', $member->id);
            }])
            ->orderBy('priority', 'desc')
            ->orderBy('published_at', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Get active prominent banners for member that have not been dismissed.
     */
    public function getProminentBannersForMember(Member $member): Collection
    {
        $dismissedNoticeIds = MemberNoticeRead::where('member_id', $member->id)
            ->where('is_dismissed', true)
            ->pluck('notice_id');

        return MemberNotice::active()
            ->forMember($member)
            ->where('is_banner', true)
            ->whereNotIn('id', $dismissedNoticeIds)
            ->orderBy('priority', 'desc')
            ->orderBy('published_at', 'desc')
            ->take(3)
            ->get();
    }

    /**
     * Queue transactional outbox messages for multichannel delivery (WhatsApp & SMS).
     */
    protected function queueNoticeOutbox(MemberNotice $notice): void
    {
        // Resolve target members
        $query = Member::query()->where('status', 'active');

        if ($notice->target_type === 'category' && $notice->target_category_id) {
            $query->where('membership_category_id', $notice->target_category_id);
        } elseif ($notice->target_type === 'dues_pending') {
            $query->where('fee_status', 'pending');
        } elseif ($notice->target_type === 'individual' && $notice->target_member_id) {
            $query->where('id', $notice->target_member_id);
        }

        $members = $query->with('person')->get();

        foreach ($members as $mem) {
            $phone = $mem->person?->phone;
            if (!$phone) {
                continue;
            }

            if ($notice->send_whatsapp) {
                OutboxMessage::create([
                    'event_type' => 'MEMBER_NOTICE_WHATSAPP',
                    'aggregate_type' => MemberNotice::class,
                    'aggregate_id' => $notice->id,
                    'payload' => [
                        'notice_id' => $notice->id,
                        'member_id' => $mem->id,
                        'membership_number' => $mem->membership_number,
                        'phone' => $phone,
                        'channel' => 'whatsapp',
                        'title' => $notice->title,
                        'content' => $notice->content,
                    ],
                    'status' => 'pending',
                    'idempotency_key' => 'NOTICE-WA-' . $notice->id . '-' . $mem->id . '-' . Str::random(6),
                ]);
            }

            if ($notice->send_sms) {
                OutboxMessage::create([
                    'event_type' => 'MEMBER_NOTICE_SMS',
                    'aggregate_type' => MemberNotice::class,
                    'aggregate_id' => $notice->id,
                    'payload' => [
                        'notice_id' => $notice->id,
                        'member_id' => $mem->id,
                        'membership_number' => $mem->membership_number,
                        'phone' => $phone,
                        'channel' => 'sms',
                        'title' => $notice->title,
                        'content' => $notice->content,
                    ],
                    'status' => 'pending',
                    'idempotency_key' => 'NOTICE-SMS-' . $notice->id . '-' . $mem->id . '-' . Str::random(6),
                ]);
            }
        }
    }
}
