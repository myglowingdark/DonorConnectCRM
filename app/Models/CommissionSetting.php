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
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function rateForVolunteer(int $volunteerId): float
    {
        $overrides = $this->volunteer_overrides ?? [];
        $key = (string) $volunteerId;

        if (array_key_exists($key, $overrides) && is_numeric($overrides[$key])) {
            return (float) $overrides[$key];
        }

        return (float) $this->individual_default_percent;
    }
}
