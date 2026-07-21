<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelecallerCapacityBooking extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'campaign_id',
        'created_by',
        'seats',
        'starts_on',
        'ends_on',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
