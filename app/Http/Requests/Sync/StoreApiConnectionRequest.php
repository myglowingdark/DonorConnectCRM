<?php

namespace App\Http\Requests\Sync;

use App\Enums\ApiAuthType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'max:500'],
            'auth_type' => ['required', Rule::enum(ApiAuthType::class)],
            'token' => ['nullable', 'string', 'max:2000'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:2000'],
            'api_key_header' => ['nullable', 'string', 'max:100'],
            'field_mappings' => ['nullable', 'array'],
            'sync_settings' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
