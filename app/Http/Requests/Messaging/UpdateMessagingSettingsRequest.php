<?php

namespace App\Http\Requests\Messaging;

use App\Enums\MessageChannel;
use App\Support\OrganizationContext;
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
        $orgId = OrganizationContext::id();

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
            'whatsapp_api_key' => ['nullable', 'string', 'max:2000'],
            'whatsapp_from_number' => ['nullable', 'string', 'max:40'],
            'whatsapp_use_platform' => ['sometimes', 'boolean'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:64'],
            'whatsapp_waba_id' => ['nullable', 'string', 'max:64'],
            'sms_provider' => ['nullable', 'string', 'max:100'],
            'sms_api_key' => ['nullable', 'string', 'max:500'],
            'sms_from_number' => ['nullable', 'string', 'max:40'],
            'bulk_whatsapp_enabled' => ['sometimes', 'boolean'],
            'auto_donation_thankyou_enabled' => ['sometimes', 'boolean'],
            'auto_donation_thankyou_template_id' => [
                'nullable',
                'integer',
                Rule::exists('message_templates', 'id')->where(
                    fn ($q) => $orgId
                        ? $q->where('organization_id', $orgId)->where('channel', MessageChannel::WhatsApp->value)
                        : $q->whereRaw('1 = 0')
                ),
            ],
        ];
    }
}
