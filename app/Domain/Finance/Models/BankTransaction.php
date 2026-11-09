<?php

namespace App\Domain\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'type',
        'bank_account_id',
        'transfer_to_bank_account_id',
        'amount_paise',
        'business_date',
        'reference_number',
        'deposited_by',
        'description',
        'journal_header_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'amount_paise' => 'integer',
        'business_date' => 'date',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function transferToAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'transfer_to_bank_account_id');
    }

    public function journalHeader(): BelongsTo
    {
        return $this->belongsTo(JournalHeader::class, 'journal_header_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getAmountAttribute(): float
    {
        return round($this->amount_paise / 100, 2);
    }
}

