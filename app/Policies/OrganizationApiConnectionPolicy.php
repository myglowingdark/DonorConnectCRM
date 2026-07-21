<?php

namespace App\Policies;

use App\Models\OrganizationApiConnection;
use App\Models\User;

class OrganizationApiConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isOrganizationAdmin();
    }

    public function view(User $user, OrganizationApiConnection $connection): bool
    {
        return $user->can('manageSync', $connection->organization);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isOrganizationAdmin();
    }

    public function update(User $user, OrganizationApiConnection $connection): bool
    {
        return $this->view($user, $connection);
    }

    public function sync(User $user, OrganizationApiConnection $connection): bool
    {
        return $this->view($user, $connection);
    }
}
