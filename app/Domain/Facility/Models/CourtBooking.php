<?php

namespace App\Domain\Facility\Models;

use App\Domain\Billing\Models\BillLine;
use App\Domain\Membership\Models\Member;
use App\Domain\Tariff\Models\Facility;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_number',
        'facility_id',
        'booking_date',
        'start_time',
        'end_time',
        'member_id',
        'is_plan_covered',
        'bill_line_id',
        'status',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'is_plan_covered' => 'boolean',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function billLine(): BelongsTo
    {
        return $this->belongsTo(BillLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
