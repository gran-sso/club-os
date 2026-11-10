<?php

namespace App\Domain\Billing\Models;

use App\Domain\Access\Models\Guest;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Membership\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosHeldTab extends Model
{
    use HasFactory;

    protected $table = 'pos_held_tabs';

    protected $fillable = [
        'shift_id',
        'counter_id',
        'tab_number',
        'customer_type',
        'member_id',
        'room_reservation_id',
        'guest_id',
        'customer_name',
        'customer_identifier',
        'table_identifier',
        'notes',
        'cart_items',
        'gross_amount_paise',
        'tax_amount_paise',
        'net_amount_paise',
        'status',
        'settled_at',
        'settled_bill_id',
        'created_by',
    ];

    protected $casts = [
        'cart_items' => 'array',
        'gross_amount_paise' => 'integer',
        'tax_amount_paise' => 'integer',
        'net_amount_paise' => 'integer',
        'settled_at' => 'datetime',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class, 'counter_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function roomReservation(): BelongsTo
    {
        return $this->belongsTo(RoomReservation::class, 'room_reservation_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function settledBill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'settled_bill_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeForShift(Builder $query, int $shiftId): Builder
    {
        return $query->where('shift_id', $shiftId);
    }

    public function scopeForCounter(Builder $query, int $counterId): Builder
    {
        return $query->where('counter_id', $counterId);
    }

    public function getFormattedNetAmountAttribute(): string
    {
        return '₹' . number_format($this->net_amount_paise / 100, 2);
    }

    public function getTotalPaiseAttribute(): int
    {
        return (int) $this->net_amount_paise;
    }

    public function getTotalAmountAttribute(): float
    {
        return $this->net_amount_paise / 100.0;
    }

    public function getCustomerDisplayNameAttribute(): string
    {
        return $this->customer_name;
    }

    public function getHeldAtAttribute()
    {
        return $this->created_at;
    }

    public function getItemsCountAttribute(): int
    {
        return $this->item_count;
    }

    public function getItemCountAttribute(): int
    {
        if (empty($this->cart_items) || !is_array($this->cart_items)) {
            return 0;
        }

        return (int) array_sum(array_column($this->cart_items, 'quantity'));
    }
}
