<?php

namespace App\Domain\Finance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_header_id',
        'account_id',
        'debit_paise',
        'credit_paise',
        'narration',
        'department_code',
    ];

    protected $casts = [
        'debit_paise' => 'integer',
        'credit_paise' => 'integer',
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(JournalHeader::class, 'journal_header_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected static function booted(): void
    {
        static::updating(function (JournalLine $line) {
            if ($line->isDirty(['debit_paise', 'credit_paise', 'account_id'])) {
                throw new \App\Domain\Shared\Exceptions\UnauthorizedActionException("Posted journal lines cannot be edited. Authorised adjustments require linked reversing journals.");
            }
        });

        static::deleting(function (JournalLine $line) {
            throw new \App\Domain\Shared\Exceptions\UnauthorizedActionException("Posted journal lines cannot be deleted.");
        });
    }
}
