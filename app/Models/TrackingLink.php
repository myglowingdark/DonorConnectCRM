<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackingLink extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'donor_id',
        'volunteer_id',
        'code',
        'target_url',
        'channel',
        'outbound_message_id',
        'open_count',
        'last_opened_at',
        'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'open_count' => 'integer',
            'last_opened_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'volunteer_id');
    }

    public function outboundMessage(): BelongsTo
    {
        return $this->belongsTo(OutboundMessage::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }

    public function publicUrl(): string
    {
        return url('/t/'.$this->code);
    }
}
