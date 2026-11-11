<?php

namespace App\Domain\Facility\Models;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Membership\Models\Member;
use App\Domain\Tariff\Models\Tariff;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilitySubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_number',
        'member_id',
        'facility_code',
        'tariff_id',
        'plan_type',
        'starts_at',
        'ends_at',
        'total_fee_paise',
        'payment_receipt_id',
        'status',
        'created_by_user_id',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'total_fee_paise' => 'integer',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class);
    }

    public function paymentReceipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class);
    }

    public function getBillAttribute(): ?Bill
    {
        return $this->paymentReceipt?->bill;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getValidFromAttribute(): ?CarbonInterface
    {
        return $this->starts_at;
    }

    public function getValidUntilAttribute(): ?CarbonInterface
    {
        return $this->ends_at;
    }

    public function isValidOn(CarbonInterface $date): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $d = $date->toDateString();

        return $this->starts_at->toDateString() <= $d && $this->ends_at->toDateString() > $d;
    }
}
