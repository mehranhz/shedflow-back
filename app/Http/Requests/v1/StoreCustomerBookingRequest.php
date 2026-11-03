<?php

namespace App\Http\Requests\v1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'eventTypeSlug' => ['required', 'string', 'max:255'],
            'startAt' => ['required', 'date'],
            'timezone' => ['required', 'string', 'max:64'],
            'answers' => ['sometimes', 'array'],
        ];
    }
}
