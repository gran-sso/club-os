<?php

namespace App\Domain\Billing\Models;

use App\Domain\Prepaid\Models\WalletTransaction;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\ValueObjects\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'shift_id',
        'bill_id',
        'wallet_transaction_id',
        'customer_type',
        'customer_id',
        'tender_type',
        'amount_paise',
        'provider_name',
        'provider_reference',
        'provider_status',
        'idempotency_key',
        'confirmed_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'tender_type' => TenderType::class,
        'amount_paise' => 'integer',
        'confirmed_at' => 'datetime',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    public function customer(): MorphTo
    {
        return $this->morphTo();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getAmountMoney(): Money
    {
        return Money::fromPaise($this->amount_paise);
    }

    public function isConfirmed(): bool
    {
        return $this->provider_status === 'confirmed';
    }
}
