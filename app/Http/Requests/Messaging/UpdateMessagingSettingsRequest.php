<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessagingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'email_enabled' => ['sometimes', 'boolean'],
            'whatsapp_enabled' => ['sometimes', 'boolean'],
            'sms_enabled' => ['sometimes', 'boolean'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', 'string', Rule::in(['tls', 'ssl'])],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'from_email' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'whatsapp_provider' => ['nullable', 'string', 'max:100'],
            'whatsapp_api_key' => ['nullable', 'string', 'max:500'],
            'whatsapp_from_number' => ['nullable', 'string', 'max:40'],
            'sms_provider' => ['nullable', 'string', 'max:100'],
            'sms_api_key' => ['nullable', 'string', 'max:500'],
            'sms_from_number' => ['nullable', 'string', 'max:40'],
        ];
    }
}
