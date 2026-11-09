<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'barcode',
        'name',
        'department_code',
        'category',
        'base_unit',
        'current_stock_qty',
        'current_avg_cost_paise',
        'reorder_level',
        'is_active',
    ];

    protected $casts = [
        'current_stock_qty' => 'float',
        'current_avg_cost_paise' => 'integer',
        'reorder_level' => 'float',
        'is_active' => 'boolean',
    ];

    public function servingMappings(): HasMany
    {
        return $this->hasMany(ServingMapping::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function hasSufficientStock(float $requiredQty): bool
    {
        return $this->current_stock_qty >= $requiredQty;
    }
}
