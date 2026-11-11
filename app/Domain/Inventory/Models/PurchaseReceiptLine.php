<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReceiptLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_receipt_id',
        'inventory_item_id',
        'quantity',
        'unit_cost_paise',
        'gross_unit_cost_paise',
        'discount_paise',
        'tax_percent',
        'line_total_paise',
        'scheme_notes',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_cost_paise' => 'integer',
        'gross_unit_cost_paise' => 'integer',
        'discount_paise' => 'integer',
        'tax_percent' => 'float',
        'line_total_paise' => 'integer',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
