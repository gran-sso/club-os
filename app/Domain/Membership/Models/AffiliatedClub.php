<?php

namespace App\Domain\Membership\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliatedClub extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'city',
        'contact_email',
        'contact_phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(AffiliatedMember::class);
    }
}
