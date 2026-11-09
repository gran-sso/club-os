<?php

namespace App\Domain\Prepaid\Models;

use App\Domain\Shared\ValueObjects\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_account_id',
        'type',
        'amount_paise',
        'balance_after_paise',
        'reference_type',
        'reference_id',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'amount_paise' => 'integer',
        'balance_after_paise' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class, 'wallet_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getAmountMoney(): Money
    {
        return Money::fromPaise($this->amount_paise);
    }

    public function getBalanceAfterMoney(): Money
    {
        return Money::fromPaise($this->balance_after_paise);
    }
}
