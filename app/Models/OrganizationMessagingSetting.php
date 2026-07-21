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
        'whatsapp_use_platform',
        'whatsapp_phone_number_id',
        'whatsapp_waba_id',
        'sms_provider',
        'sms_api_key',
        'sms_from_number',
        'bulk_whatsapp_enabled',
        'auto_donation_thankyou_enabled',
        'auto_donation_thankyou_template_id',
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'whatsapp_use_platform' => 'boolean',
            'bulk_whatsapp_enabled' => 'boolean',
            'auto_donation_thankyou_enabled' => 'boolean',
            'smtp_password' => 'encrypted',
            'whatsapp_api_key' => 'encrypted',
            'sms_api_key' => 'encrypted',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function autoDonationThankYouTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'auto_donation_thankyou_template_id');
    }

    public function usesCustomSmtp(): bool
    {
        return filled($this->smtp_host) && filled($this->from_email);
    }

    public function hasOwnMetaCredentials(): bool
    {
        return filled($this->whatsapp_api_key)
            && filled($this->whatsapp_phone_number_id)
            && filled($this->whatsapp_waba_id);
    }
}
