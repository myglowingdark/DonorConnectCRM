<?php

namespace App\Http\Requests\Messaging;

use App\Enums\MessageChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendDonorMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::enum(MessageChannel::class)],
            'message_template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
