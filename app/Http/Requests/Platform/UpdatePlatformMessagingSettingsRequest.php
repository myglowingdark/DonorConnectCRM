<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'meta_phone_number_id' => ['nullable', 'string', 'max:64', 'regex:/^\d*$/'],
            'meta_waba_id' => ['nullable', 'string', 'max:64', 'regex:/^\d*$/'],
            'meta_app_id' => ['nullable', 'string', 'max:64'],
            'meta_app_secret' => ['nullable', 'string', 'max:255'],
            'meta_api_version' => ['nullable', 'string', 'max:20'],
            'meta_embedded_signup_config_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'meta_phone_number_id.regex' => 'Phone Number ID must be digits only (copy from Meta → WhatsApp → API Setup). Do not paste the display number like +1 555-…',
            'meta_waba_id.regex' => 'WABA ID must be digits only (copy WhatsApp Business Account ID from Meta).',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $phone = (string) ($this->input('meta_phone_number_id') ?? '');
            if ($phone !== '' && str_contains($phone, '+')) {
                $validator->errors()->add(
                    'meta_phone_number_id',
                    'That looks like a display phone number. Paste the numeric Phone Number ID from Meta instead.',
                );
            }
        });
    }
}
