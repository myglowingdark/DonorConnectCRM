<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformMessagingSetting extends Model
{
    protected $fillable = [
        'email_enabled',
        'whatsapp_enabled',
        'whatsapp_module_enabled',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'from_email',
        'from_name',
        'meta_access_token',
        'meta_phone_number_id',
        'meta_waba_id',
        'meta_app_id',
        'meta_app_secret',
        'meta_api_version',
        'meta_embedded_signup_config_id',
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'whatsapp_module_enabled' => 'boolean',
            'smtp_password' => 'encrypted',
            'meta_access_token' => 'encrypted',
            'meta_app_secret' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'email_enabled' => true,
            'whatsapp_enabled' => false,
            'whatsapp_module_enabled' => false,
            'meta_api_version' => 'v21.0',
        ]);
    }

    public function usesCustomSmtp(): bool
    {
        return filled($this->smtp_host) && filled($this->from_email);
    }

    public function hasMetaCredentials(): bool
    {
        return filled($this->meta_access_token)
            && filled($this->meta_phone_number_id)
            && filled($this->meta_waba_id);
    }
}
