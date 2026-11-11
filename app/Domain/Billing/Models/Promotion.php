<?php

namespace App\Domain\Billing\Models;

use App\Domain\Tariff\Models\Tariff;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Promotion extends Model
{
    use HasFactory;

    public const TYPE_BUY_X_GET_Y = 'buy_x_get_y';

    public const SCOPE_MEMBERS_ONLY = 'members_only';

    public const SCOPE_ALL = 'all';

    protected $fillable = [
        'code',
        'name',
        'type',
        'buy_qty',
        'get_qty',
        'discount_percent',
        'customer_scope',
        'effective_from',
        'effective_to',
        'max_free_units',
        'redeemed_free_units',
        'notes',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'buy_qty' => 'integer',
        'get_qty' => 'integer',
        'discount_percent' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'max_free_units' => 'integer',
        'redeemed_free_units' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tariffs(): BelongsToMany
    {
        return $this->belongsToMany(Tariff::class, 'promotion_tariffs')->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Check whether the promotion is active and effective on the supplied business date.
     */
    public function isEffectiveOn(?CarbonInterface $date = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $targetDate = ($date ?? now())->format('Y-m-d');

        if ($this->effective_from && $this->effective_from->format('Y-m-d') > $targetDate) {
            return false;
        }

        if ($this->effective_to && $this->effective_to->format('Y-m-d') < $targetDate) {
            return false;
        }

        return true;
    }

    public function remainingFreeUnits(): ?int
    {
        if ($this->max_free_units === null) {
            return null;
        }

        return max(0, (int) $this->max_free_units - (int) $this->redeemed_free_units);
    }

    public function hasAvailableQuota(int $neededFreeUnits = 1): bool
    {
        $remaining = $this->remainingFreeUnits();

        return $remaining === null || $remaining >= $neededFreeUnits;
    }

    /**
     * Short display label suitable for POS chips and receipts (e.g. "Buy 2 Get 1 Free").
     */
    public function badgeLabel(): string
    {
        $discount = (float) $this->discount_percent;
        if ($discount >= 99.99) {
            return "Buy {$this->buy_qty} Get {$this->get_qty} Free";
        }

        return "Buy {$this->buy_qty} Get {$this->get_qty} @ " . number_format($discount, 0) . '% Off';
    }
}

