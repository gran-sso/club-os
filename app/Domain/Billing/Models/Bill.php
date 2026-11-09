<?php

namespace App\Domain\Billing\Models;

use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Shared\ValueObjects\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_number',
        'shift_id',
        'customer_type',
        'customer_id',
        'department_code',
        'status',
        'business_date',
        'gross_amount_paise',
        'discount_amount_paise',
        'tax_amount_paise',
        'net_amount_paise',
        'paid_amount_paise',
        'idempotency_key',
        'idempotency_hash',
        'notes',
        'created_by_user_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'status' => BillStatus::class,
        'business_date' => 'date',
        'gross_amount_paise' => 'integer',
        'discount_amount_paise' => 'integer',
        'tax_amount_paise' => 'integer',
        'net_amount_paise' => 'integer',
        'paid_amount_paise' => 'integer',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function customer(): MorphTo
    {
        return $this->morphTo();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillLine::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillLine::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class);
    }

    public function paymentReceipts(): HasMany
    {
        return $this->receipts();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getGrossAmountAttribute(): float
    {
        return round(($this->gross_amount_paise ?? 0) / 100, 2);
    }

    public function getTaxAmountAttribute(): float
    {
        return round(($this->tax_amount_paise ?? 0) / 100, 2);
    }

    public function getNetAmountAttribute(): float
    {
        return round(($this->net_amount_paise ?? 0) / 100, 2);
    }

    public function getNetAmountMoney(): Money
    {
        return Money::fromPaise($this->net_amount_paise);
    }

    public function getPaidAmountMoney(): Money
    {
        return Money::fromPaise($this->paid_amount_paise);
    }

    public function isPaid(): bool
    {
        return $this->status === BillStatus::PAID;
    }

    public function resolveBusinessDate(?string $overrideCutoff = null): string
    {
        return app(\App\Domain\Shared\Services\BusinessDateService::class)->getBusinessDate($this->created_at ?? now(), $overrideCutoff);
    }

    protected static function booted(): void
    {
        static::creating(function (Bill $bill) {
            if (!$bill->business_date) {
                $bill->business_date = app(\App\Domain\Shared\Services\BusinessDateService::class)->getBusinessDate($bill->created_at ?? now());
            }
        });

        static::updating(function (Bill $bill) {
            if ($bill->isDirty('created_at') && !$bill->isDirty('business_date')) {
                $bill->business_date = app(\App\Domain\Shared\Services\BusinessDateService::class)->getBusinessDate($bill->created_at);
            }

            if ($bill->getOriginal('status') === BillStatus::PAID && $bill->status === BillStatus::PAID) {
                if ($bill->isDirty(['gross_amount_paise', 'net_amount_paise', 'tax_amount_paise', 'customer_id', 'customer_type', 'department_code'])) {
                    throw new \App\Domain\Shared\Exceptions\UnauthorizedActionException("Posted bills are immutable and cannot be edited. Authorised adjustments require linked reversals.");
                }
            }
        });

        static::deleting(function (Bill $bill) {
            if ($bill->status === BillStatus::PAID || $bill->status === BillStatus::REFUNDED) {
                throw new \App\Domain\Shared\Exceptions\UnauthorizedActionException("Posted bills cannot be deleted. Authorised corrections require linked reversals.");
            }
        });
    }
}
