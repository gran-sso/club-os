<?php

namespace App\Domain\Facility\Models;

use App\Domain\Access\Models\Guest;
use App\Domain\Membership\Models\Member;
use App\Domain\Tariff\Models\Facility;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RoomReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_number',
        'facility_id',
        'guest_id',
        'sponsor_member_id',
        'payer_type',
        'payer_id',
        'check_in_date',
        'check_out_date',
        'rate_per_night_paise',
        'total_charge_paise',
        'paid_amount_paise',
        'status',
        'housekeeping_status',
        'checked_in_at',
        'checked_out_at',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'rate_per_night_paise' => 'integer',
        'total_charge_paise' => 'integer',
        'paid_amount_paise' => 'integer',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function sponsorMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'sponsor_member_id');
    }

    public function payer(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isFullyPaid(): bool
    {
        return $this->paid_amount_paise >= $this->total_charge_paise;
    }

    public function getOccupantNameAttribute(): string
    {
        if ($this->guest) {
            return $this->guest->name;
        }

        if ($this->sponsorMember) {
            return $this->sponsorMember->name;
        }

        if ($this->payer instanceof Member) {
            return $this->payer->name;
        }

        return 'Guest #' . $this->id;
    }

    public function getOccupantPhoneAttribute(): ?string
    {
        return $this->guest?->phone 
            ?? $this->sponsorMember?->phone 
            ?? ($this->payer instanceof Member ? $this->payer->phone : null);
    }

    public function getOccupantTypeAttribute(): string
    {
        if ($this->guest) {
            return 'guest';
        }

        if ($this->sponsorMember || $this->payer instanceof Member) {
            return 'member';
        }

        return 'guest';
    }

    public function getOccupantBadgeAttribute(): string
    {
        if ($this->guest) {
            return 'Visiting Guest';
        }

        $member = $this->sponsorMember ?? ($this->payer instanceof Member ? $this->payer : null);
        if ($member) {
            return "Member ({$member->membership_number})";
        }

        return 'Guest';
    }
}
