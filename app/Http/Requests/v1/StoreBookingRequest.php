<?php

namespace App\Http\Requests\v1;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'eventTypeId' => ['required', 'uuid'],
            'startAt' => ['required', 'date'],
            'timezone' => ['required', 'string'],
            'invitee' => ['required', 'array'],
            'invitee.name' => ['required', 'string', 'max:255'],
            'invitee.email' => ['required', 'email'],
            'invitee.phone' => ['nullable', 'string', 'max:50'],
            'answers' => ['sometimes', 'array'],
        ];
    }
}
