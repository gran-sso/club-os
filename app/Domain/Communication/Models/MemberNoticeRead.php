<?php

namespace App\Domain\Communication\Models;

use App\Domain\Membership\Models\Member;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberNoticeRead extends Model
{
    use HasFactory;

    protected $table = 'member_notice_reads';

    protected $fillable = [
        'notice_id',
        'member_id',
        'read_at',
        'is_dismissed',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'is_dismissed' => 'boolean',
    ];

    public function notice(): BelongsTo
    {
        return $this->belongsTo(MemberNotice::class, 'notice_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}

