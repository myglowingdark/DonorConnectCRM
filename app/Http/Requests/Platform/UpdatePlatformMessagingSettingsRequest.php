<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformMessagingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'email_enabled' => ['sometimes', 'boolean'],
            'whatsapp_enabled' => ['sometimes', 'boolean'],
            'whatsapp_module_enabled' => ['sometimes', 'boolean'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', 'string', 'max:16'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'from_email' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'meta_access_token' => ['nullable', 'string', 'max:2000'],
            'meta_phone_number_id' => ['nullable', 'string', 'max:64'],
            'meta_waba_id' => ['nullable', 'string', 'max:64'],
            'meta_app_id' => ['nullable', 'string', 'max:64'],
            'meta_app_secret' => ['nullable', 'string', 'max:255'],
            'meta_api_version' => ['nullable', 'string', 'max:20'],
            'meta_embedded_signup_config_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
