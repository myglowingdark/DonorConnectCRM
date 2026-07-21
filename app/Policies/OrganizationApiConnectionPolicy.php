<?php

namespace App\Policies;

use App\Models\OrganizationApiConnection;
use App\Models\User;

class OrganizationApiConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, OrganizationApiConnection $connection): bool
    {
        return $user->belongsToOrganization($connection->organization_id) && $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
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
