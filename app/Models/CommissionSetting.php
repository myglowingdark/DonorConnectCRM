<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionSetting extends Model
{
    protected $fillable = [
        'organization_id',
        'individual_enabled',
        'individual_default_percent',
        'shared_enabled',
        'shared_percent',
        'shared_eligibility',
        'volunteer_overrides',
        'internal_individual_enabled',
        'internal_individual_default_percent',
        'internal_shared_enabled',
        'internal_shared_percent',
        'internal_volunteer_overrides',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'individual_enabled' => 'boolean',
            'shared_enabled' => 'boolean',
            'individual_default_percent' => 'decimal:2',
            'shared_percent' => 'decimal:2',
            'volunteer_overrides' => 'array',
            'internal_individual_enabled' => 'boolean',
            'internal_shared_enabled' => 'boolean',
            'internal_individual_default_percent' => 'decimal:2',
            'internal_shared_percent' => 'decimal:2',
            'internal_volunteer_overrides' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function rateForVolunteer(int $volunteerId, bool $internal = false): float
    {
        $overrides = $internal
            ? ($this->internal_volunteer_overrides ?? [])
            : ($this->volunteer_overrides ?? []);
        $key = (string) $volunteerId;

        if (array_key_exists($key, $overrides) && is_numeric($overrides[$key])) {
            return (float) $overrides[$key];
        }

        return (float) ($internal
            ? $this->internal_individual_default_percent
            : $this->individual_default_percent);
    }
}
