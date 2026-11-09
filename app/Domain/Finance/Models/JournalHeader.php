<?php

namespace App\Domain\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalHeader extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_number',
        'business_date',
        'source_type',
        'source_id',
        'description',
        'created_by_user_id',
    ];

    protected $casts = [
        'business_date' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function totalDebits(): int
    {
        return (int) $this->lines()->sum('debit_paise');
    }

    public function totalDebitsPaise(): int
    {
        return $this->totalDebits();
    }

    public function totalCredits(): int
    {
        return (int) $this->lines()->sum('credit_paise');
    }

    public function totalCreditsPaise(): int
    {
        return $this->totalCredits();
    }

    public function isBalanced(): bool
    {
        return $this->totalDebits() === $this->totalCredits();
    }

    protected static function booted(): void
    {
        static::updating(function (JournalHeader $header) {
            if ($header->isDirty(['journal_number', 'business_date', 'source_type', 'source_id'])) {
                throw new \App\Domain\Shared\Exceptions\UnauthorizedActionException("Posted journals are immutable. Authorised corrections require linked reversing journals.");
            }
        });

        static::deleting(function (JournalHeader $header) {
            throw new \App\Domain\Shared\Exceptions\UnauthorizedActionException("Posted journal entries cannot be deleted. Authorised corrections require linked reversing journals.");
        });
    }
}
