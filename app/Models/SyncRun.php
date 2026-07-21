<?php

namespace App\Models;

use App\Enums\SyncStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRun extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'organization_api_connection_id',
        'status',
        'donors_imported',
        'donors_updated',
        'donations_imported',
        'donations_updated',
        'error_details',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SyncStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(OrganizationApiConnection::class, 'organization_api_connection_id');
    }
}
