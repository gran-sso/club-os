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
}
