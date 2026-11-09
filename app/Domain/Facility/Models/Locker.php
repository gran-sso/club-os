<?php

namespace App\Domain\Facility\Models;

use App\Domain\Membership\Models\Member;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Locker extends Model
{
    use HasFactory;

    protected $fillable = [
        'locker_number',
        'zone',
        'status',
        'deposit_amount_paise',
        'assigned_member_id',
        'assigned_at',
        'allocated_until',
        'key_number',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'allocated_until' => 'date',
        'deposit_amount_paise' => 'integer',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'assigned_member_id');
    }

    public function getDepositRupeesAttribute(): float
    {
        return round($this->deposit_amount_paise / 100, 2);
    }
}

