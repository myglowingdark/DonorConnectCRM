<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('organization')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('donors_limit') && $this->input('donors_limit') === '') {
            $this->merge(['donors_limit' => null]);
        }
        if ($this->has('attribution_window_days') && $this->input('attribution_window_days') === '') {
            $this->merge(['attribution_window_days' => 3]);
        }
    }

    public function rules(): array
    {
        $organization = $this->route('organization');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('organizations', 'slug')->ignore($organization?->id),
            ],
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
            'donors_limit' => ['nullable', 'integer', 'min:1'],
            'attribution_window_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
