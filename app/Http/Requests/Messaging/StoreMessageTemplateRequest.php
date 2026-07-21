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

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->exists('is_active')) {
            $merge['is_active'] = $this->boolean('is_active');
        }

        if ($this->exists('remove_attachment')) {
            $merge['remove_attachment'] = $this->boolean('remove_attachment');
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
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
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx'],
            'remove_attachment' => ['sometimes', 'boolean'],
        ];
    }
}
