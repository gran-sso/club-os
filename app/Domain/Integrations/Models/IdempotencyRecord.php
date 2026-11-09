<?php

namespace App\Domain\Integrations\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdempotencyRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'idempotency_key',
        'request_hash',
        'route',
        'response_code',
        'response_body',
    ];

    protected $casts = [
        'response_code' => 'integer',
        'response_body' => 'array',
    ];
}
