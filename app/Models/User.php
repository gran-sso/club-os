<?php

namespace App\Models;

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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Administrative, management, front-desk, and counter staff can access the panel.
        // Sensitive financial ledgers, reports, tariffs, and contractor payouts are scoped per resource.
        return $this->hasAnyRole([
            'admin',
            'manager',
            'accountant',
            'auditor',
            'reception',
            'cashier',
            'Administrator',
            'Manager',
            'Accountant',
            'Auditor',
            'Reception',
            'Cashier',
        ]) || $this->hasAnyPermission([
            'reports.view',
            'rooms.view_rack',
            'courts.view_schedule',
            'counter.operate',
        ]);
    }
}
