<?php

namespace App\Domain\Billing\Models;

use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\ValueObjects\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'refund_number',
        'bill_id',
        'payment_receipt_id',
        'amount_paise',
        'tender_type',
        'reason',
        'approved_by_user_id',
        'idempotency_key',
    ];

    protected $casts = [
        'amount_paise' => 'integer',
        'tender_type' => TenderType::class,
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class, 'payment_receipt_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function getAmountMoney(): Money
    {
        return Money::fromPaise($this->amount_paise);
    }
}
