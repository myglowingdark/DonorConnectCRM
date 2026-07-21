<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformCommissionSetting extends Model
{
    protected $fillable = [
        'individual_enabled',
        'individual_default_percent',
        'shared_enabled',
        'shared_percent',
        'shared_eligibility',
        'internal_individual_enabled',
        'internal_individual_default_percent',
        'internal_shared_enabled',
        'internal_shared_percent',
    ];

    protected function casts(): array
    {
        return [
            'individual_enabled' => 'boolean',
            'shared_enabled' => 'boolean',
            'internal_individual_enabled' => 'boolean',
            'internal_shared_enabled' => 'boolean',
            'individual_default_percent' => 'decimal:2',
            'shared_percent' => 'decimal:2',
            'internal_individual_default_percent' => 'decimal:2',
            'internal_shared_percent' => 'decimal:2',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'individual_enabled' => true,
            'individual_default_percent' => 5,
            'shared_enabled' => false,
            'shared_percent' => 0,
            'shared_eligibility' => 'active_contributors',
            'internal_individual_enabled' => true,
            'internal_individual_default_percent' => 5,
            'internal_shared_enabled' => true,
            'internal_shared_percent' => 0,
        ]);
    }

    /** @return array<string, mixed> */
    public function defaultsForOrganization(): array
    {
        return [
            'individual_enabled' => $this->individual_enabled,
            'individual_default_percent' => $this->individual_default_percent,
            'shared_enabled' => $this->shared_enabled,
            'shared_percent' => $this->shared_percent,
            'shared_eligibility' => $this->shared_eligibility ?: 'active_contributors',
            'volunteer_overrides' => [],
            'internal_individual_enabled' => $this->internal_individual_enabled,
            'internal_individual_default_percent' => $this->internal_individual_default_percent,
            'internal_shared_enabled' => $this->internal_shared_enabled,
            'internal_shared_percent' => $this->internal_shared_percent,
            'internal_volunteer_overrides' => [],
        ];
    }
}
