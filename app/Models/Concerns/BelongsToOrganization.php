<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToOrganization
{
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where($this->getTable().'.organization_id', $organizationId);
    }

    public function scopeInOrganizations(Builder $query, array $organizationIds): Builder
    {
        return $query->whereIn($this->getTable().'.organization_id', $organizationIds);
    }
}
