<?php

namespace App\Http\Requests\SiteSettings;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformCommissionDefaultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'individual_enabled' => ['required', 'boolean'],
            'individual_default_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'shared_enabled' => ['required', 'boolean'],
            'shared_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'shared_eligibility' => ['required', 'string', 'max:64'],
            'internal_individual_enabled' => ['required', 'boolean'],
            'internal_individual_default_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'internal_shared_enabled' => ['required', 'boolean'],
            'internal_shared_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
