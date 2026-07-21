<?php

namespace App\Http\Requests\Assignments;

use Illuminate\Foundation\Http\FormRequest;

class AssignDonorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'volunteer_id' => ['required', 'integer', 'exists:users,id'],
            'donor_ids' => ['required', 'array', 'min:1'],
            'donor_ids.*' => ['integer', 'exists:donors,id'],
        ];
    }
}
