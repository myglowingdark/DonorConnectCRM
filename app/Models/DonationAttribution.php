<?php

namespace App\Models;

use App\Enums\AttributionSource;
use App\Enums\AttributionStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonationAttribution extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'donation_id',
        'donor_id',
        'volunteer_id',
        'source',
        'tracking_link_id',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => AttributionSource::class,
            'status' => AttributionStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function trackingLink(): BelongsTo
    {
        return $this->belongsTo(TrackingLink::class);
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'volunteer_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
