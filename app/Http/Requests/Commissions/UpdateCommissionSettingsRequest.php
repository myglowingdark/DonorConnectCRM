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
        $rules = [
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

        if ($this->user()?->isSuperAdmin()) {
            $rules = array_merge($rules, [
                'internal_individual_enabled' => ['sometimes', 'boolean'],
                'internal_individual_default_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'internal_shared_enabled' => ['sometimes', 'boolean'],
                'internal_shared_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'internal_volunteer_overrides' => ['nullable', 'array'],
                'internal_volunteer_overrides.*.volunteer_id' => ['required', 'integer'],
                'internal_volunteer_overrides.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            ]);
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $orgId = OrganizationContext::id();
            if (! $orgId) {
                $validator->errors()->add('volunteer_overrides', 'No organization selected.');

                return;
            }

            $this->assertVolunteers($validator, 'volunteer_overrides', $orgId, internal: false);

            if ($this->user()?->isSuperAdmin()) {
                $this->assertVolunteers($validator, 'internal_volunteer_overrides', $orgId, internal: true);
            }
        });
    }

    protected function assertVolunteers(Validator $validator, string $field, int $orgId, bool $internal): void
    {
        $ids = collect($this->input($field, []))
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
            ->where('is_internal_telecaller', $internal)
            ->whereIn('id', $ids)
            ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $orgId))
            ->count();

        if ($validCount !== $ids->count()) {
            $validator->errors()->add($field, 'One or more volunteers are invalid for this pool.');
        }
    }
}
