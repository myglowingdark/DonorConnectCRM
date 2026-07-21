<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'languages', 'is_active', 'is_internal_telecaller'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'languages' => 'array',
            'is_active' => 'boolean',
            'is_internal_telecaller' => 'boolean',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot(['is_active'])
            ->withTimestamps();
    }

    public function activeOrganizations(): BelongsToMany
    {
        return $this->organizations()->wherePivot('is_active', true)->where('organizations.is_active', true);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DonorAssignment::class, 'volunteer_id');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(DonorInteraction::class, 'volunteer_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isOrganizationAdmin(): bool
    {
        return $this->role === UserRole::OrganizationAdmin;
    }

    public function isVolunteer(): bool
    {
        return $this->role === UserRole::Volunteer;
    }

    public function isInternalTelecaller(): bool
    {
        return $this->isVolunteer() && $this->is_internal_telecaller;
    }

    public function isAdmin(): bool
    {
        return $this->role?->isAdmin() ?? false;
    }

    public function belongsToOrganization(int $organizationId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->organizations()
            ->where('organizations.id', $organizationId)
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function accessibleOrganizationIds(): array
    {
        if ($this->isSuperAdmin()) {
            return Organization::query()->pluck('id')->all();
        }

        return $this->activeOrganizations()->pluck('organizations.id')->all();
    }
}
