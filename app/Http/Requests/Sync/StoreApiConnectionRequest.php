<?php

namespace App\Http\Requests\Sync;

use App\Enums\ApiAuthType;
use App\Models\Organization;
use App\Models\OrganizationApiConnection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $organization = $this->route('organization');
        if ($organization instanceof Organization) {
            return $user->can('manageSync', $organization);
        }

        $connection = $this->route('connection');
        if ($connection instanceof OrganizationApiConnection) {
            return $user->can('update', $connection);
        }

        return $user->isSuperAdmin() || $user->isOrganizationAdmin();
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
            'hmac_secret' => ['nullable', 'string', 'max:2000'],
            'site_id' => ['nullable', 'string', 'max:64'],
            'field_mappings' => ['nullable', 'array'],
            'sync_settings' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('auth_type') !== ApiAuthType::Hmac->value) {
                return;
            }

            $connection = $this->route('connection');
            $existing = $connection instanceof OrganizationApiConnection
                ? ($connection->safeCredentials() ?? [])
                : [];
            $hasExisting = filled($existing['api_key'] ?? null) && filled($existing['hmac_secret'] ?? null);

            if (! $hasExisting && blank($this->input('api_key'))) {
                $validator->errors()->add('api_key', 'API key is required for HMAC (DonorConnect Bridge).');
            }
            if (! $hasExisting && blank($this->input('hmac_secret'))) {
                $validator->errors()->add('hmac_secret', 'HMAC secret is required for HMAC (DonorConnect Bridge).');
            }
            if (! $hasExisting && blank($this->input('site_id'))) {
                $validator->errors()->add('site_id', 'Site ID is required for HMAC (DonorConnect Bridge).');
            }
        });
    }
}
