<?php

namespace App\Models;

use App\Enums\CallOutcome;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonorInteraction extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'donor_id',
        'volunteer_id',
        'interaction_type',
        'outcome',
        'notes',
        'contacted_at',
        'follow_up_at',
        'pledged_amount',
        'campaign_id',
        'attribute_donation',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => CallOutcome::class,
            'contacted_at' => 'datetime',
            'follow_up_at' => 'datetime',
            'pledged_amount' => 'decimal:2',
            'attribute_donation' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'volunteer_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
