<?php

namespace App\Domain\Membership\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dependent extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'person_id',
        'relationship',
        'can_spend_wallet',
    ];

    protected $casts = [
        'can_spend_wallet' => 'boolean',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Resolve display name for billing/access views consistently with Member and Guest.
     */
    public function getNameAttribute(): string
    {
        return $this->person ? $this->person->full_name : 'Dependent #' . $this->id;
    }

    public function getPhotoPathAttribute(): ?string
    {
        return $this->person?->photo_path;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (empty($this->photo_path)) {
            return null;
        }

        return route('dependents.photo.show', [
            'dependent' => $this->id,
            'v' => substr(md5((string) $this->photo_path), 0, 8),
        ]);
    }

    public function getPortalPhotoUrlAttribute(): ?string
    {
        if (empty($this->photo_path)) {
            return null;
        }

        return route('member.dependents.photo', [
            'dependent' => $this->id,
            'v' => substr(md5((string) $this->photo_path), 0, 8),
        ]);
    }
}
