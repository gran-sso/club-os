<?php

namespace App\Domain\Contractor\Models;

use App\Domain\Billing\Models\BillLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractorSettlementAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_batch_id',
        'bill_line_id',
        'line_net_paise',
        'tender_type',
        'status',
    ];

    protected $casts = [
        'line_net_paise' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ContractorSettlementBatch::class, 'settlement_batch_id');
    }

    public function billLine(): BelongsTo
    {
        return $this->belongsTo(BillLine::class, 'bill_line_id');
    }
}
