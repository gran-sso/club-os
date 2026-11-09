<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Models\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_name',
        'bank_name',
        'branch_name',
        'account_number',
        'account_type',
        'ifsc_code',
        'upi_id',
        'gl_account_id',
        'opening_balance_paise',
        'current_balance_paise',
        'is_primary',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'opening_balance_paise' => 'integer',
        'current_balance_paise' => 'integer',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'bank_account_id');
    }

    public function getCurrentBalanceAttribute(): float
    {
        return round($this->current_balance_paise / 100, 2);
    }

    public function getOpeningBalanceAttribute(): float
    {
        return round($this->opening_balance_paise / 100, 2);
    }

    public function getDisplayTitleAttribute(): string
    {
        return "{$this->account_name} — {$this->bank_name} (A/c: {$this->account_number})";
    }

    public function getMaskedAccountNumberAttribute(): string
    {
        $len = strlen($this->account_number);
        if ($len <= 4) {
            return $this->account_number;
        }

        return str_repeat('•', max(0, $len - 4)) . ' ' . substr($this->account_number, -4);
    }
}

