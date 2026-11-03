<?php

namespace App\Http\Requests\v1;

use App\Enums\BookingSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'orgSlug' => ['required', 'string'],
            'eventTypeSlug' => ['required', 'string'],
            'startAt' => ['required', 'date'],
            'timezone' => ['required', 'string'],
            'invitee' => ['required', 'array'],
            'invitee.name' => ['required', 'string', 'max:255'],
            'invitee.email' => ['required', 'email', 'max:255'],
            'invitee.phone' => ['required', 'string', 'max:32'],
            'answers' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
            'source' => ['sometimes', Rule::enum(BookingSource::class)],
        ];
    }
}
