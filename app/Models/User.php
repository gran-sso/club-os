<?php

namespace App\Models;

use App\Domain\Shared\Security\ClubPermissionRegistry;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'employee_id',
        'designation',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Deactivated staff accounts are blocked immediately
        if (! $this->is_active) {
            return false;
        }

        if ($this->hasAnyRole(ClubPermissionRegistry::SUPER_ROLES)) {
            return true;
        }

        return $this->hasAnyClubPermission(ClubPermissionRegistry::adminPanelPermissions());
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_active && $this->hasAnyRole(ClubPermissionRegistry::SUPER_ROLES);
    }

    public function hasClubPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->hasAnyRole(ClubPermissionRegistry::SUPER_ROLES)) {
            return true;
        }

        return $this->checkPermissionTo($permission);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function hasAnyClubPermission(array $permissions): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->hasAnyRole(ClubPermissionRegistry::SUPER_ROLES)) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($this->checkPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has immutable historical operational or financial transactions linked.
     */
    public function hasHistoricalRecords(): bool
    {
        return \App\Domain\Billing\Models\Shift::where('user_id', $this->id)->exists()
            || \App\Domain\Billing\Models\Bill::where('user_id', $this->id)->orWhere('created_by_user_id', $this->id)->exists()
            || \App\Domain\Accounting\Models\JournalHeader::where('created_by_user_id', $this->id)->exists()
            || \App\Domain\Accounting\Models\Expense::where('created_by_user_id', $this->id)->orWhere('approved_by_user_id', $this->id)->exists()
            || \App\Domain\Inventory\Models\PurchaseReceipt::where('created_by_user_id', $this->id)->exists();
    }
}
