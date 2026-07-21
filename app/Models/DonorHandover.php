<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonorHandover extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'from_volunteer_id',
        'initiated_by',
        'mode',
        'donors_moved',
        'reassign_interactions',
        'interactions_moved',
        'notes',
        'to_volunteer_ids',
        'donor_ids',
    ];

    protected function casts(): array
    {
        return [
            'reassign_interactions' => 'boolean',
            'to_volunteer_ids' => 'array',
            'donor_ids' => 'array',
        ];
    }

    public function fromVolunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_volunteer_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
