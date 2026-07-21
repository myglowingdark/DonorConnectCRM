<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'code',
        'name',
        'price_monthly',
        'seats_limit',
        'donors_limit',
        'campaigns_limit',
        'whatsapp_monthly_limit',
        'telecaller_hours_monthly',
        'imports_monthly_limit',
        'features',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'integer',
            'seats_limit' => 'integer',
            'donors_limit' => 'integer',
            'campaigns_limit' => 'integer',
            'whatsapp_monthly_limit' => 'integer',
            'telecaller_hours_monthly' => 'integer',
            'imports_monthly_limit' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PlanInvoice::class);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }
}
