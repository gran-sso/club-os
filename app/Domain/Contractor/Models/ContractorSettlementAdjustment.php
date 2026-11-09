<?php

namespace App\Domain\Contractor\Models;

use App\Domain\Billing\Models\BillLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractorSettlementAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'contractor_id',
        'original_batch_id',
        'applied_batch_id',
        'bill_line_id',
        'type',
        'amount_paise',
        'reason',
        'is_applied',
        'created_by_user_id',
    ];

    protected $casts = [
        'amount_paise' => 'integer',
        'is_applied' => 'boolean',
    ];

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function originalBatch(): BelongsTo
    {
        return $this->belongsTo(ContractorSettlementBatch::class, 'original_batch_id');
    }

    public function appliedBatch(): BelongsTo
    {
        return $this->belongsTo(ContractorSettlementBatch::class, 'applied_batch_id');
    }

    public function billLine(): BelongsTo
    {
        return $this->belongsTo(BillLine::class, 'bill_line_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
