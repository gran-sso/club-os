<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CapturedPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_name',
        'provider_reference',
        'amount_paise',
        'currency',
        'status',
        'customer_type',
        'customer_id',
        'bill_id',
        'payment_receipt_id',
        'raw_payload',
        'signature',
        'captured_at',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'amount_paise' => 'integer',
        'raw_payload' => 'array',
        'captured_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function customer(): MorphTo
    {
        return $this->morphTo();
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function paymentReceipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class);
    }
}
