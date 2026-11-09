<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Tariff\Models\Tariff;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServingMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'tariff_id',
        'inventory_item_id',
        'serving_name',
        'serving_qty',
    ];

    protected $casts = [
        'serving_qty' => 'float',
    ];

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
