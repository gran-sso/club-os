<?php

namespace App\Domain\Access\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_identifier',
        'device_type',
        'facility_code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
