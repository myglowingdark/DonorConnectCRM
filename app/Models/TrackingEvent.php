<?php

namespace App\Models;

use App\Enums\TrackingEventType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingEvent extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'tracking_link_id',
        'volunteer_id',
        'event_type',
        'page_url',
        'project_id',
        'amount',
        'meta',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => TrackingEventType::class,
            'amount' => 'decimal:2',
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function trackingLink(): BelongsTo
    {
        return $this->belongsTo(TrackingLink::class);
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'volunteer_id');
    }
}
