<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'organization_webhook_id',
        'event',
        'status_code',
        'success',
        'payload',
        'response_body',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'success' => 'boolean',
            'payload' => 'array',
            'delivered_at' => 'datetime',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(OrganizationWebhook::class, 'organization_webhook_id');
    }
}
