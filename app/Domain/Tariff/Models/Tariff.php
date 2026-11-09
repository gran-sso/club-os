<?php

namespace App\Domain\Tariff\Models;

use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'barcode',
        'department_code',
        'name',
        'rate_paise',
        'tax_percent',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'rate_paise' => 'integer',
        'tax_percent' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function servingMapping(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Domain\Inventory\Models\ServingMapping::class);
    }

    public function getRateMoney(): Money
    {
        return Money::fromPaise($this->rate_paise);
    }

    public function isEffectiveOn(\DateTimeInterface $date): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $formatted = $date->format('Y-m-d');
        if ($this->effective_from->format('Y-m-d') > $formatted) {
            return false;
        }

        if ($this->effective_to !== null && $this->effective_to->format('Y-m-d') < $formatted) {
            return false;
        }

        return true;
    }
}
