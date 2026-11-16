<?php

namespace App\Domain\Membership\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{
    use HasFactory;

    protected $table = 'persons';

    protected $fillable = [
        'title',
        'first_name',
        'last_name',
        'phone',
        'email',
        'date_of_birth',
        'gender',
        'blood_group',
        'profession',
        'designation',
        'organization',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'emergency_contact_name',
        'emergency_contact_phone',
        'id_proof_type',
        'id_proof_number',
        'id_proof_path',
        'application_form_path',
        'photo_path',
        'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getFullNameWithTitleAttribute(): string
    {
        $prefix = $this->title ? trim($this->title) . ' ' : '';

        return trim("{$prefix}{$this->first_name} {$this->last_name}");
    }

    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
    }

    public function getFormattedAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
        ], fn ($p) => ! empty(trim((string) $p)));

        return implode(', ', $parts);
    }

    public function getProfessionalHeadlineAttribute(): ?string
    {
        if ($this->designation && $this->organization) {
            return "{$this->designation}, {$this->organization}";
        }

        if ($this->designation) {
            return $this->designation;
        }

        if ($this->profession && $this->organization) {
            return "{$this->profession} at {$this->organization}";
        }

        return $this->profession ?: null;
    }
}
