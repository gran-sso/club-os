<?php

namespace App\Domain\Tariff\Models;

use App\Domain\Billing\Models\Counter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function counters(): HasMany
    {
        return $this->hasMany(Counter::class);
    }
}
