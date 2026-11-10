<?php

namespace App\Domain\Membership\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{
    use HasFactory;

    protected $table = 'persons';

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'id_proof_type',
        'id_proof_number',
        'id_proof_path',
        'application_form_path',
        'photo_path',
        'notes',
    ];

    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getNameAttribute(): string
    {
        return $this->full_name;
    }
}
