<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case OrganizationAdmin = 'organization_admin';
    case Volunteer = 'volunteer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::OrganizationAdmin => 'Organization Admin',
            self::Volunteer => 'Telecalling Volunteer',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::SuperAdmin || $this === self::OrganizationAdmin;
    }
}
