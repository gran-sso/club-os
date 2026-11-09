<?php

namespace App\Domain\Finance\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_code',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by_user_id',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function isDateClosed(CarbonInterface $date): bool
    {
        if ($this->status !== 'closed') {
            return false;
        }

        $d = $date->toDateString();

        return $this->start_date->toDateString() <= $d && $this->end_date->toDateString() >= $d;
    }
}
