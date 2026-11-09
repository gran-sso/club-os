<?php

namespace App\Domain\Contractor\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractorSettlementBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_number',
        'contractor_id',
        'business_date',
        'cutoff_at',
        'total_food_sales_gross_paise',
        'total_food_sales_tax_paise',
        'total_food_sales_net_paise',
        'total_adjustments_paise',
        'net_payable_paise',
        'paid_amount_paise',
        'residual_payable_paise',
        'status',
        'payment_reference',
        'approved_by_user_id',
        'approved_at',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'business_date' => 'date',
        'cutoff_at' => 'datetime',
        'total_food_sales_gross_paise' => 'integer',
        'total_food_sales_tax_paise' => 'integer',
        'total_food_sales_net_paise' => 'integer',
        'total_adjustments_paise' => 'integer',
        'net_payable_paise' => 'integer',
        'paid_amount_paise' => 'integer',
        'residual_payable_paise' => 'integer',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ContractorSettlementAllocation::class, 'settlement_batch_id');
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ['paid', 'partially_paid', 'locked']);
    }
}
