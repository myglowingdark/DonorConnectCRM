<?php

namespace App\Http\Requests\Users;

use App\Enums\UserRole;
use App\Support\Languages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $roles = [UserRole::Volunteer->value, UserRole::OrganizationAdmin->value];

        if ($this->user()?->isSuperAdmin()) {
            $roles[] = UserRole::SuperAdmin->value;
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', Rule::in(Languages::codes())],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in($roles)],
            'organization_ids' => ['required', 'array', 'min:1'],
            'organization_ids.*' => ['integer', 'exists:organizations,id'],
            'is_active' => ['sometimes', 'boolean'],
            'is_internal_telecaller' => ['sometimes', 'boolean'],
        ];
    }
}
