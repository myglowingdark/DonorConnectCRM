<?php

namespace App\Http\Requests\Messaging;

use App\Enums\MessageChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'body' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $channel = $this->input('channel');

            if ($channel === MessageChannel::WhatsApp->value) {
                if (blank($this->input('message_template_id'))) {
                    $validator->errors()->add('message_template_id', 'An approved WhatsApp template is required.');
                }

                return;
            }

            if (blank($this->input('body'))) {
                $validator->errors()->add('body', 'Message body is required.');
            }
        });
    }
}
