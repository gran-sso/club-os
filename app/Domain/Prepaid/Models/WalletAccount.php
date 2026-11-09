<?php

namespace App\Domain\Prepaid\Models;

use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'walletable_type',
        'walletable_id',
        'balance_paise',
        'reserved_paise',
        'currency',
        'status',
        'version',
    ];

    protected $casts = [
        'balance_paise' => 'integer',
        'reserved_paise' => 'integer',
        'version' => 'integer',
    ];

    public function walletable(): MorphTo
    {
        return $this->morphTo();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->orderBy('id', 'desc');
    }

    public function entries(): HasMany
    {
        return $this->transactions();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(WalletReservation::class);
    }

    public function getAvailablePaise(): int
    {
        return max(0, $this->balance_paise - $this->reserved_paise);
    }

    public function getAvailableMoney(): Money
    {
        return Money::fromPaise($this->getAvailablePaise());
    }

    public function getBalanceMoney(): Money
    {
        return Money::fromPaise($this->balance_paise);
    }
}
