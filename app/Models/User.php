<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Never pass raw request input to create()/update().
     * Controllers pick the exact fields, so "role" cannot be injected by a form.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'booking_restricted_at',
        'restriction_reason',
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
            'role' => Role::class,
            'is_active' => 'boolean',
            'booking_restricted_at' => 'datetime',
        ];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isBookingRestricted(): bool
    {
        return $this->booking_restricted_at !== null;
    }

    public function scopeWithRole(Builder $query, Role $role): Builder
    {
        return $query->where('role', $role->value);
    }

    /** Admins and Super Admins who can sign in. */
    public function scopeActiveStaff(Builder $query): Builder
    {
        return $query->whereIn('role', [Role::Admin->value, Role::SuperAdmin->value])
            ->where('is_active', true);
    }
}
