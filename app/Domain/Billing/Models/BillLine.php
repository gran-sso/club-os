<?php

namespace App\Domain\Billing\Models;

use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_id',
        'department_code',
        'item_type',
        'item_id',
        'item_name',
        'quantity',
        'unit_price_paise',
        'tax_percent',
        'tax_amount_paise',
        'line_total_paise',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price_paise' => 'integer',
        'tax_percent' => 'decimal:2',
        'tax_amount_paise' => 'integer',
        'line_total_paise' => 'integer',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function getUnitPriceAttribute(): float
    {
        return round(($this->unit_price_paise ?? 0) / 100, 2);
    }

    public function setUnitPriceAttribute(float $value): void
    {
        $this->attributes['unit_price_paise'] = (int) round($value * 100);
    }

    public function getLineTotalAttribute(): float
    {
        return round(($this->line_total_paise ?? 0) / 100, 2);
    }

    public function getLineTotalMoney(): Money
    {
        return Money::fromPaise($this->line_total_paise);
    }

    public function getTotalPricePaiseAttribute(): int
    {
        return (int) $this->line_total_paise;
    }

    public function contractorSettlementAllocation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Domain\Contractor\Models\ContractorSettlementAllocation::class, 'bill_line_id');
    }
}
