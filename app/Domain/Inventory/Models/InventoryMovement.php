<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Billing\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'inventory_item_id',
        'shift_id',
        'type',
        'quantity',
        'unit_cost_paise',
        'total_cost_paise',
        'balance_after_qty',
        'avg_cost_after_paise',
        'reference_type',
        'reference_id',
        'notes',
        'created_by_user_id',
        'created_at',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_cost_paise' => 'integer',
        'total_cost_paise' => 'integer',
        'balance_after_qty' => 'float',
        'avg_cost_after_paise' => 'integer',
        'created_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
