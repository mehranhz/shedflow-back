<?php

namespace App\Http\Requests\v1;

use Illuminate\Foundation\Http\FormRequest;

class UpsertAvailabilityOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'isUnavailable' => ['sometimes', 'boolean'],
            'startMinute' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'endMinute' => ['nullable', 'integer', 'min:0', 'max:1440'],
        ];
    }
}
