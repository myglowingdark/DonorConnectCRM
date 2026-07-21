<?php

namespace App\Http\Requests\Messaging;

use App\Enums\MessageChannel;
use App\Enums\MetaTemplateStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'channel' => ['required', Rule::enum(MessageChannel::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'meta_name' => ['nullable', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'meta_language' => ['nullable', 'string', 'max:20'],
            'meta_category' => ['nullable', 'string', Rule::in(['UTILITY', 'MARKETING', 'AUTHENTICATION', 'utility', 'marketing', 'authentication'])],
            'meta_status' => ['nullable', Rule::enum(MetaTemplateStatus::class)],
            'variable_schema' => ['nullable', 'array'],
            'variable_schema.*' => ['string', 'max:40'],
        ];
    }
}
