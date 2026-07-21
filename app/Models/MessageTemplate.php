<?php

namespace App\Models;

use App\Enums\MessageChannel;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageTemplate extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'channel',
        'subject',
        'body',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function outboundMessages(): HasMany
    {
        return $this->hasMany(OutboundMessage::class);
    }
}
