<?php

namespace App\Http\Requests\Donors;

use App\Enums\CallOutcome;
use App\Support\Languages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::enum(CallOutcome::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'follow_up_at' => ['nullable', 'date'],
            'pledged_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'preferred_language' => ['nullable', 'string', Rule::in(Languages::codes())],
            'attribute_donation' => ['sometimes', 'boolean'],
            'go_next' => ['sometimes', 'boolean'],
        ];
    }
}
