<?php

namespace App\Domain\Billing\Models;

use App\Domain\Shared\Enums\BillStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Waiter extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'phone',
        'department_code',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->code})";
    }

    public function tipsTotalPaise(): int
    {
        return (int) $this->bills()
            ->where('status', BillStatus::PAID)
            ->sum('tip_amount_paise');
    }
}

