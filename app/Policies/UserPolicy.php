<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $actor, User $model): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ($actor->id === $model->id) {
            return true;
        }

        if (! $actor->isOrganizationAdmin()) {
            return false;
        }

        $orgIds = $actor->accessibleOrganizationIds();

        return $model->organizations()->whereIn('organizations.id', $orgIds)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $actor, User $model): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ($actor->id === $model->id) {
            return true;
        }

        return $actor->isOrganizationAdmin()
            && $model->role !== UserRole::SuperAdmin
            && $this->view($actor, $model);
    }

    public function delete(User $actor, User $model): bool
    {
        return $actor->isSuperAdmin() && $actor->id !== $model->id;
    }
}
