<?php

namespace App\Http\Requests\SiteSettings;

use App\Http\Controllers\SiteSettingsController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'price_monthly' => ['required', 'integer', 'min:0'],
            'seats_limit' => ['nullable', 'integer', 'min:0'],
            'donors_limit' => ['nullable', 'integer', 'min:0'],
            'campaigns_limit' => ['nullable', 'integer', 'min:0'],
            'whatsapp_monthly_limit' => ['nullable', 'integer', 'min:0'],
            'telecaller_hours_monthly' => ['nullable', 'integer', 'min:0'],
            'imports_monthly_limit' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', Rule::in(SiteSettingsController::FEATURE_KEYS)],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
