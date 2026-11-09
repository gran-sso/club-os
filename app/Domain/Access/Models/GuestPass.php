<?php

namespace App\Domain\Access\Models;

use App\Domain\Shared\Enums\PassStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestPass extends Model
{
    use HasFactory;

    protected $fillable = [
        'guest_id',
        'pass_code',
        'facility_code',
        'bill_line_id',
        'status',
        'valid_from',
        'valid_until',
        'entry_count',
        'max_entries',
    ];

    protected $casts = [
        'status' => PassStatus::class,
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'entry_count' => 'integer',
        'max_entries' => 'integer',
    ];

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function isValidForEntry(): bool
    {
        if ($this->status !== PassStatus::ACTIVE) {
            return false;
        }

        $now = now();
        if ($now->lt($this->valid_from) || $now->gt($this->valid_until)) {
            return false;
        }

        if ($this->entry_count >= $this->max_entries) {
            return false;
        }

        return true;
    }
}
