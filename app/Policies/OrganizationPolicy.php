<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isOrganizationAdmin();
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization->id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin()
            || ($user->isOrganizationAdmin() && $user->belongsToOrganization($organization->id));
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin();
    }

    public function manageUsers(User $user, Organization $organization): bool
    {
        return $this->update($user, $organization);
    }

    public function manageSync(User $user, Organization $organization): bool
    {
        return $this->update($user, $organization);
    }

    public function assignDonors(User $user, Organization $organization): bool
    {
        return $this->update($user, $organization);
    }

    public function viewReports(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization->id) && $user->isAdmin();
    }
}
