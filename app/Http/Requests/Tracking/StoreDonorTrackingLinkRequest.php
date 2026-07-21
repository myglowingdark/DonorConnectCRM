<?php

namespace App\Http\Requests\Tracking;

use App\Enums\MessageChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDonorTrackingLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'target_url' => ['required', 'url', 'max:2048'],
            'channel' => ['required', Rule::in(['copy', 'email', 'whatsapp'])],
            'message_template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $channel = $this->input('channel');

            if ($channel === 'copy') {
                return;
            }

            if ($channel === MessageChannel::WhatsApp->value) {
                if (blank($this->input('message_template_id')) && blank($this->input('body'))) {
                    // WhatsApp still requires approved template via MessageService;
                    // require template id here.
                    $validator->errors()->add('message_template_id', 'An approved WhatsApp template is required.');
                }

                return;
            }

            if ($channel === MessageChannel::Email->value && blank($this->input('body'))) {
                // Default body is applied in controller when empty for email with donation link.
            }
        });
    }
}
