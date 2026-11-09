<?php

namespace App\Domain\Contractor\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contractor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'department_code',
        'contact_person',
        'phone',
        'email',
        'bank_name',
        'bank_account_number',
        'bank_ifsc',
        'commission_rate_percent',
        'is_active',
    ];

    protected $casts = [
        'commission_rate_percent' => 'float',
        'is_active' => 'boolean',
    ];

    public function settlementBatches(): HasMany
    {
        return $this->hasMany(ContractorSettlementBatch::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(ContractorSettlementAdjustment::class);
    }
}
