<?php

namespace App\Domain\Membership\Models;

use App\Domain\Billing\Models\Bill;
use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Shared\Enums\MemberStatus;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Member extends Model implements AuthenticatableContract
{
    use HasFactory, Authenticatable;

    protected $fillable = [
        'membership_number',
        'person_id',
        'membership_category_id',
        'status',
        'password',
        'last_login_at',
        'joined_at',
        'valid_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'status' => MemberStatus::class,
        'joined_at' => 'date',
        'valid_until' => 'date',
        'last_login_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Member $member) {
            // 1. Guard against non-zero prepaid wallet balance
            if ($member->wallet && $member->wallet->balance_paise > 0) {
                $formatted = number_format($member->wallet->balance_paise / 100, 2);
                throw new \DomainException("Cannot delete Member #{$member->membership_number}: Account holds an unrefunded prepaid balance of ₹{$formatted}. Unused prepaid deposits must be refunded or liquidated before account removal.");
            }

            // 2. Guard against financial transaction history in wallet
            if ($member->wallet && $member->wallet->transactions()->exists()) {
                throw new \DomainException("Cannot delete Member #{$member->membership_number}: Member has an immutable financial transaction history on the prepaid ledger. Member master records with financial history cannot be deleted; transition status to 'Resigned' or 'Terminated'.");
            }

            // 3. Guard against historical bills and tax invoices
            if ($member->bills()->exists()) {
                throw new \DomainException("Cannot delete Member #{$member->membership_number}: Member is associated with historical club bills. Audited financial sales cannot be orphaned; transition status to 'Resigned' or 'Suspended'.");
            }

            // 4. Guard against court bookings, room reservations, or facility subscriptions
            if ($member->courtBookings()->exists()) {
                throw new \DomainException("Cannot delete Member #{$member->membership_number}: Active or historical badminton court bookings exist.");
            }
            if ($member->subscriptions()->exists()) {
                throw new \DomainException("Cannot delete Member #{$member->membership_number}: Active or historical facility subscriptions exist.");
            }
            if ($member->roomReservations()->exists()) {
                throw new \DomainException("Cannot delete Member #{$member->membership_number}: Accommodation room reservations exist.");
            }

            // 5. Clean up polymorphic entities if member is pristine (zero history)
            if ($member->wallet) {
                $member->wallet->delete();
            }
            $member->cards()->delete();
        });
    }

    /**
     * Transition member lifecycle status safely, blocking access cards when departing.
     */
    public function transitionStatus(MemberStatus|string $status, ?string $reason = null): void
    {
        $newStatus = $status instanceof MemberStatus ? $status : MemberStatus::from($status);
        $this->update(['status' => $newStatus]);

        if (!$newStatus->canAccess()) {
            $this->cards()->where('status', 'active')->update([
                'status' => 'blocked',
                'blocked_at' => now(),
                'notes' => $reason ?: "Blocked automatically due to member status transition to '{$newStatus->value}'",
            ]);
        }
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MembershipCategory::class, 'membership_category_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(Dependent::class);
    }

    public function committeeNotes(): HasMany
    {
        return $this->hasMany(MemberNote::class)->latest();
    }

    public function lockers(): HasMany
    {
        return $this->hasMany(\App\Domain\Facility\Models\Locker::class, 'assigned_member_id');
    }

    public function cards(): MorphMany
    {
        return $this->morphMany(Card::class, 'cardable');
    }

    public function activeCard(): MorphOne
    {
        return $this->morphOne(Card::class, 'cardable')->where('status', 'active');
    }

    public function wallet(): MorphOne
    {
        return $this->morphOne(WalletAccount::class, 'walletable');
    }

    public function getWalletBalancePaiseAttribute(): int
    {
        return (int) ($this->wallet?->balance_paise ?? 0);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(FacilitySubscription::class);
    }

    public function courtBookings(): HasMany
    {
        return $this->hasMany(CourtBooking::class, 'member_id');
    }

    public function roomReservations(): HasMany
    {
        return $this->hasMany(RoomReservation::class, 'sponsor_member_id');
    }

    public function bills(): MorphMany
    {
        return $this->morphMany(Bill::class, 'customer');
    }

    public function sponsoredGuests(): MorphMany
    {
        return $this->morphMany(\App\Domain\Access\Models\Guest::class, 'sponsor');
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return false;
    }

    public function getNameAttribute(): string
    {
        return $this->person ? $this->person->full_name : $this->membership_number;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->person?->phone;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->person?->email;
    }

    public function getPhotoPathAttribute(): ?string
    {
        return $this->person?->photo_path;
    }

    public function isActive(?\Carbon\CarbonInterface $onDate = null): bool
    {
        if ($this->status !== MemberStatus::ACTIVE) {
            return false;
        }

        $date = $onDate ?? now();

        if ($this->valid_until !== null && $this->valid_until->endOfDay()->lt($date)) {
            return false;
        }

        return true;
    }
}
