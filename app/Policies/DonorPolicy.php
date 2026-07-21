<?php

namespace App\Policies;

use App\Models\Donor;
use App\Models\User;

class DonorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Donor $donor): bool
    {
        if (! $user->belongsToOrganization($donor->organization_id)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $donor->assignments()
            ->where('volunteer_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Donor $donor): bool
    {
        return $this->view($user, $donor);
    }

    public function logCall(User $user, Donor $donor): bool
    {
        return $this->view($user, $donor) && ! $donor->do_not_call;
    }

    public function clearDoNotCall(User $user, Donor $donor): bool
    {
        return $user->isAdmin() && $user->belongsToOrganization($donor->organization_id);
    }

    public function export(User $user): bool
    {
        return $user->isAdmin();
    }
}
