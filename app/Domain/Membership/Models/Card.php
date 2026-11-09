<?php

namespace App\Domain\Membership\Models;

use App\Domain\Shared\Enums\CardStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Card extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_number',
        'cardable_type',
        'cardable_id',
        'status',
        'issued_at',
        'blocked_at',
        'replaced_by_card_id',
        'notes',
    ];

    protected $casts = [
        'status' => CardStatus::class,
        'issued_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    public function cardable(): MorphTo
    {
        return $this->morphTo();
    }

    public function replacedByCard(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'replaced_by_card_id');
    }

    public function isActive(): bool
    {
        return $this->status === CardStatus::ACTIVE;
    }
}
