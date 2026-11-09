<?php

namespace App\Domain\Billing\Models;

use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_receipt_id',
        'bill_id',
        'amount_paise',
    ];

    protected $casts = [
        'amount_paise' => 'integer',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class, 'payment_receipt_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function getAmountMoney(): Money
    {
        return Money::fromPaise($this->amount_paise);
    }
}
