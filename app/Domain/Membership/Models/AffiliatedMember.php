<?php

namespace App\Domain\Membership\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliatedMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliated_club_id',
        'person_id',
        'home_membership_number',
        'valid_until',
        'is_verified',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'is_verified' => 'boolean',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(AffiliatedClub::class, 'affiliated_club_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function isValid(): bool
    {
        if (!$this->is_verified) {
            return false;
        }

        if ($this->valid_until !== null && $this->valid_until->isPast()) {
            return false;
        }

        return true;
    }
}
