<?php

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\MetaTemplateStatus;
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
        'meta_name',
        'meta_language',
        'meta_category',
        'meta_status',
        'meta_template_id',
        'meta_rejection_reason',
        'variable_schema',
    ];

    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'is_active' => 'boolean',
            'meta_status' => MetaTemplateStatus::class,
            'variable_schema' => 'array',
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

    public function isWhatsApp(): bool
    {
        return $this->channel === MessageChannel::WhatsApp;
    }

    public function isMetaApproved(): bool
    {
        return $this->isWhatsApp()
            && $this->meta_status === MetaTemplateStatus::Approved
            && $this->is_active;
    }

    /**
     * Ordered placeholder keys used for Meta body parameters, e.g. ['name', 'org'].
     *
     * @return list<string>
     */
    public function orderedVariableKeys(): array
    {
        if (is_array($this->variable_schema) && $this->variable_schema !== []) {
            return array_values(array_map('strval', $this->variable_schema));
        }

        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $this->body, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
