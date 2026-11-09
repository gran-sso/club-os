<?php

namespace App\Domain\Access\Models;

use App\Domain\Membership\Models\Person;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'sponsor_type',
        'sponsor_id',
        'visit_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'visit_date' => 'date',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function sponsor(): MorphTo
    {
        return $this->morphTo();
    }

    public function passes(): HasMany
    {
        return $this->hasMany(GuestPass::class);
    }

    public function getNameAttribute(): string
    {
        return $this->person ? $this->person->full_name : 'Guest #' . $this->id;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->person?->phone;
    }
}
