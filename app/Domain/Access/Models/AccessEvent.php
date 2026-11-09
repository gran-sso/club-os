<?php

namespace App\Domain\Access\Models;

use App\Domain\Membership\Models\Person;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'guest_pass_id',
        'card_number',
        'facility_code',
        'event_type',
        'reason',
        'device_id',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function guestPass(): BelongsTo
    {
        return $this->belongsTo(GuestPass::class);
    }
}
