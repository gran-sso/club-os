<?php

namespace App\Domain\Prepaid\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_account_id',
        'amount_paise',
        'status',
        'reference_type',
        'reference_id',
        'expires_at',
    ];

    protected $casts = [
        'amount_paise' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class, 'wallet_account_id');
    }
}
