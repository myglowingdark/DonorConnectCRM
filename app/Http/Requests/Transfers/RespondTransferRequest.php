<?php

namespace App\Http\Requests\Transfers;

use Illuminate\Foundation\Http\FormRequest;

class RespondTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'response_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
