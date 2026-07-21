<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case OrganizationAdmin = 'organization_admin';
    case TeamLead = 'team_lead';
    case Finance = 'finance';
    case Viewer = 'viewer';
    case Volunteer = 'volunteer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::OrganizationAdmin => 'Organization Admin',
            self::TeamLead => 'Team Lead',
            self::Finance => 'Finance',
            self::Viewer => 'Viewer',
            self::Volunteer => 'Telecalling Volunteer',
        };
    }

    public function isAdmin(): bool
    {
        return in_array($this, [
            self::SuperAdmin,
            self::OrganizationAdmin,
            self::TeamLead,
            self::Finance,
        ], true);
    }
}
