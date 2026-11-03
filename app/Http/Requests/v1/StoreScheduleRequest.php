<?php

namespace App\Http\Requests\v1;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:64'],
            'hostUserId' => ['nullable', 'uuid'],
            'isDefault' => ['sometimes', 'boolean'],
            'isPublic' => ['sometimes', 'boolean'],
        ];
    }
}
