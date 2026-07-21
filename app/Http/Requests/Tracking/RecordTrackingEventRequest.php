<?php

namespace App\Http\Requests\Tracking;

use App\Enums\TrackingEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordTrackingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dcr' => ['required', 'string', 'max:32'],
            'event_type' => ['required', Rule::enum(TrackingEventType::class)],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'project_id' => ['nullable', 'string', 'max:64'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
