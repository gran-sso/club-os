<?php

namespace App\Domain\Communication\Models;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberNotice extends Model
{
    use HasFactory;

    protected $table = 'member_notices';

    protected $fillable = [
        'title',
        'content',
        'department',
        'priority',
        'target_type',
        'target_category_id',
        'target_member_id',
        'action_label',
        'action_url',
        'is_banner',
        'published_at',
        'expires_at',
        'is_active',
        'send_whatsapp',
        'send_sms',
        'dispatched_whatsapp_at',
        'dispatched_sms_at',
        'created_by',
    ];

    protected $casts = [
        'is_banner' => 'boolean',
        'is_active' => 'boolean',
        'send_whatsapp' => 'boolean',
        'send_sms' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'dispatched_whatsapp_at' => 'datetime',
        'dispatched_sms_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'department' => 'admin',
        'priority' => 'info',
        'target_type' => 'all',
        'is_banner' => false,
        'send_whatsapp' => false,
        'send_sms' => false,
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targetCategory(): BelongsTo
    {
        return $this->belongsTo(MembershipCategory::class, 'target_category_id');
    }

    public function targetMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'target_member_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MemberNoticeRead::class, 'notice_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('published_at', '<=', now())
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    public function scopeForMember(Builder $query, Member $member): Builder
    {
        return $query->where(function (Builder $q) use ($member) {
            // 1. Broadcast to all
            $q->where('target_type', 'all')
                // 2. Specific membership category
                ->orWhere(function (Builder $sub) use ($member) {
                    $sub->where('target_type', 'category')
                        ->where('target_category_id', $member->membership_category_id);
                })
                // 3. Dynamic target: Pending dues
                ->orWhere(function (Builder $sub) use ($member) {
                    if ($member->fee_status === 'pending') {
                        $sub->where('target_type', 'dues_pending');
                    } else {
                        $sub->whereRaw('1 = 0');
                    }
                })
                // 4. Individual specific member
                ->orWhere(function (Builder $sub) use ($member) {
                    $sub->where('target_type', 'individual')
                        ->where('target_member_id', $member->id);
                });
        });
    }

    public function isReadBy(Member $member): bool
    {
        return $this->reads()->where('member_id', $member->id)->exists();
    }

    public function isDismissedBy(Member $member): bool
    {
        return $this->reads()
            ->where('member_id', $member->id)
            ->where('is_dismissed', true)
            ->exists();
    }
}
