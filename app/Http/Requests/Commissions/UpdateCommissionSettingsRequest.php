<?php

namespace App\Http\Requests\Commissions;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCommissionSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'individual_enabled' => ['sometimes', 'boolean'],
            'individual_default_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'shared_enabled' => ['sometimes', 'boolean'],
            'shared_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shared_eligibility' => ['nullable', 'string', 'max:64'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'volunteer_overrides' => ['nullable', 'array'],
            'volunteer_overrides.*.volunteer_id' => ['required', 'integer'],
            'volunteer_overrides.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $orgId = OrganizationContext::id();
            if (! $orgId) {
                $validator->errors()->add('volunteer_overrides', 'No organization selected.');

                return;
            }

            $ids = collect($this->input('volunteer_overrides', []))
                ->pluck('volunteer_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($ids->isEmpty()) {
                return;
            }

            $validCount = User::query()
                ->where('role', UserRole::Volunteer)
                ->whereIn('id', $ids)
                ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $orgId))
                ->count();

            if ($validCount !== $ids->count()) {
                $validator->errors()->add('volunteer_overrides', 'One or more volunteers are invalid for this organization.');
            }
        });
    }
}
