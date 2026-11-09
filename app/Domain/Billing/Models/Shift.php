<?php

namespace App\Domain\Billing\Models;

use App\Domain\Shared\Enums\ShiftStatus;
use App\Domain\Shared\ValueObjects\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_number',
        'counter_id',
        'user_id',
        'business_date',
        'opened_at',
        'closed_at',
        'opening_float_paise',
        'closing_cash_counted_paise',
        'expected_cash_paise',
        'discrepancy_paise',
        'status',
        'notes',
    ];

    protected $casts = [
        'status' => ShiftStatus::class,
        'business_date' => 'date',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_float_paise' => 'integer',
        'closing_cash_counted_paise' => 'integer',
        'expected_cash_paise' => 'integer',
        'discrepancy_paise' => 'integer',
    ];

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->user();
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(ShiftCashMovement::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function paymentReceipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class);
    }

    public function isOpen(): bool
    {
        return $this->status === ShiftStatus::OPEN;
    }

    public function calculateExpectedCashPaise(): int
    {
        // Expected cash = Opening float + Cash receipts - Cash transfers out
        $cashIn = (int) $this->paymentReceipts()
            ->where('tender_type', 'cash')
            ->where('provider_status', 'confirmed')
            ->sum('amount_paise');

        $transfersOut = (int) $this->cashMovements()
            ->where('type', 'transfer_out')
            ->sum('amount_paise');

        $drops = (int) $this->cashMovements()
            ->where('type', 'cash_drop')
            ->sum('amount_paise');

        $additionalIn = (int) $this->cashMovements()
            ->where('type', 'float_in')
            ->sum('amount_paise');

        return $this->opening_float_paise + $cashIn + $additionalIn - $transfersOut - $drops;
    }
}
