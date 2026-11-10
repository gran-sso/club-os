<?php

namespace App\Domain\Membership\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'admission_fee_paise',
        'annual_fee_paise',
        'has_voting_rights',
        'is_active',
    ];

    protected $casts = [
        'admission_fee_paise' => 'integer',
        'annual_fee_paise' => 'integer',
        'has_voting_rights' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getAdmissionFeeRupeesAttribute(): float
    {
        return round(($this->admission_fee_paise ?? 0) / 100, 2);
    }

    public function getAnnualFeeRupeesAttribute(): float
    {
        return round(($this->annual_fee_paise ?? 0) / 100, 2);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
