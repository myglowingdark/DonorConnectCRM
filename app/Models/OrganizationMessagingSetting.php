<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMessagingSetting extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'email_enabled',
        'whatsapp_enabled',
        'sms_enabled',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'from_email',
        'from_name',
        'whatsapp_provider',
        'whatsapp_api_key',
        'whatsapp_from_number',
        'sms_provider',
        'sms_api_key',
        'sms_from_number',
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'smtp_password' => 'encrypted',
            'whatsapp_api_key' => 'encrypted',
            'sms_api_key' => 'encrypted',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function usesCustomSmtp(): bool
    {
        return filled($this->smtp_host) && filled($this->from_email);
    }
}
