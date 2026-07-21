<?php

namespace App\Models;

use App\Enums\ApiAuthType;
use App\Enums\SyncStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationApiConnection extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'base_url',
        'auth_type',
        'credentials',
        'field_mappings',
        'sync_settings',
        'last_synced_at',
        'sync_status',
        'last_error',
        'is_active',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'auth_type' => ApiAuthType::class,
            'credentials' => 'encrypted:array',
            'field_mappings' => 'array',
            'sync_settings' => 'array',
            'last_synced_at' => 'datetime',
            'sync_status' => SyncStatus::class,
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    public function toSafeArray(): array
    {
        $credentials = $this->credentials ?? [];

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'base_url' => $this->base_url,
            'auth_type' => $this->auth_type?->value,
            'has_credentials' => ! empty($credentials['api_key'] ?? $credentials['key'] ?? $credentials['token'] ?? $credentials['hmac_secret'] ?? null),
            'site_id' => $credentials['site_id'] ?? null,
            'field_mappings' => $this->field_mappings,
            'sync_settings' => $this->sync_settings,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'sync_status' => $this->sync_status?->value,
            'last_error' => $this->last_error,
            'is_active' => $this->is_active,
        ];
    }
}
