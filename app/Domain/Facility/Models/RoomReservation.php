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
}
