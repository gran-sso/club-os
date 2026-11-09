<?php

namespace App\Domain\Finance\Models;

use App\Domain\Shared\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'is_system',
        'category',
        'description',
        'is_active',
    ];

    protected $casts = [
        'type' => AccountType::class,
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function hasHistoricalLines(): bool
    {
        return $this->lines()->exists() || $this->expenses()->exists();
    }
}
