<?php

namespace App\Domain\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UnappliedPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'bill_id',
        'customer_type',
        'customer_id',
        'tender_type',
        'amount_paise',
        'status',
        'idempotency_key',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'amount_paise' => 'integer',
    ];

    public function customer(): MorphTo
    {
        return $this->morphTo();
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
