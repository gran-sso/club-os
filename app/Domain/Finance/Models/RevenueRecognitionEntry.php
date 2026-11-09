<?php

namespace App\Domain\Finance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueRecognitionEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'recognition_date',
        'amount_paise',
        'journal_header_id',
        'status',
    ];

    protected $casts = [
        'amount_paise' => 'integer',
        'recognition_date' => 'date',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RevenueRecognitionSchedule::class, 'schedule_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(JournalHeader::class, 'journal_header_id');
    }
}
