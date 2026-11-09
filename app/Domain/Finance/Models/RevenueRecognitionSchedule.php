<?php

namespace App\Domain\Finance\Models;

use App\Domain\Facility\Models\FacilitySubscription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RevenueRecognitionSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'total_amount_paise',
        'recognised_amount_paise',
        'service_start_date',
        'service_end_date',
        'total_days',
        'daily_rate_paise',
        'status',
    ];

    protected $casts = [
        'total_amount_paise' => 'integer',
        'recognised_amount_paise' => 'integer',
        'service_start_date' => 'date',
        'service_end_date' => 'date',
        'total_days' => 'integer',
        'daily_rate_paise' => 'integer',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(FacilitySubscription::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RevenueRecognitionEntry::class, 'schedule_id');
    }
}
