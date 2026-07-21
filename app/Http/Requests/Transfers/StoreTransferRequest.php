<?php

namespace App\Http\Requests\Transfers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'to_volunteer_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'volunteer')->where('is_active', true)),
            ],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
