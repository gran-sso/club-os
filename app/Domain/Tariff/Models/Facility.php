<?php

namespace App\Domain\Tariff\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'capacity',
        'is_active',
        'housekeeping_status',
    ];

    protected $attributes = [
        'housekeeping_status' => 'clean',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];
}
