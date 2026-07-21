<?php

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\MetaTemplateStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class MessageTemplate extends Model
{
    use BelongsToOrganization, HasFactory;

    /** @var list<string> Placeholders that map to a document header/attachment, not body text. */
    public const DOCUMENT_VARIABLES = ['receipt'];

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
        'header_format',
        'attachment_path',
        'attachment_filename',
        'attachment_mime',
    ];

    protected $appends = [
        'has_attachment',
        'attachment_url',
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

    public function getHasAttachmentAttribute(): bool
    {
        return filled($this->attachment_path);
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! filled($this->attachment_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->attachment_path);
    }

    public function usesDocumentHeader(): bool
    {
        return ($this->header_format ?? 'none') === 'document'
            || $this->bodyContainsDocumentVariable();
    }

    public function bodyContainsDocumentVariable(): bool
    {
        return (bool) preg_match('/\{\{\s*('.implode('|', self::DOCUMENT_VARIABLES).')\s*\}\}/i', $this->body ?? '');
    }

    /**
     * Ordered placeholder keys used for Meta body parameters, e.g. ['name', 'org'].
     * Document variables such as {{receipt}} are excluded.
     *
     * @return list<string>
     */
    public function orderedVariableKeys(): array
    {
        $keys = [];

        if (is_array($this->variable_schema) && $this->variable_schema !== []) {
            $keys = array_values(array_map('strval', $this->variable_schema));
        } else {
            preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $this->body ?? '', $matches);
            $keys = array_values(array_unique($matches[1] ?? []));
        }

        return array_values(array_filter(
            $keys,
            fn (string $key) => ! in_array(strtolower($key), self::DOCUMENT_VARIABLES, true),
        ));
    }

    public function absoluteAttachmentPath(): ?string
    {
        if (! filled($this->attachment_path)) {
            return null;
        }

        return Storage::disk('public')->path($this->attachment_path);
    }
}
