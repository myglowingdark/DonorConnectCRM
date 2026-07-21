<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;

class OrganizationContext
{
    public static function id(): ?int
    {
        $id = session('current_organization_id');

        return $id ? (int) $id : null;
    }

    public static function organization(): ?Organization
    {
        $id = self::id();

        return $id ? Organization::query()->find($id) : null;
    }

    public static function set(?int $organizationId): void
    {
        if ($organizationId === null) {
            session()->forget('current_organization_id');

            return;
        }

        session(['current_organization_id' => $organizationId]);
    }

    public static function ensureFor(User $user): ?Organization
    {
        $currentId = self::id();

        if ($currentId && $user->belongsToOrganization($currentId)) {
            return Organization::query()->find($currentId);
        }

        if ($user->isSuperAdmin()) {
            $org = Organization::query()->where('is_active', true)->orderBy('name')->first();
            self::set($org?->id);

            return $org;
        }

        $org = $user->activeOrganizations()->orderBy('name')->first();
        self::set($org?->id);

        return $org;
    }
}
