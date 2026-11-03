<?php

namespace App\Http\Requests\v1;

use App\Enums\LocationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'durationMinutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'locationType' => ['required', Rule::enum(LocationType::class)],
            'locationValue' => ['nullable', 'string', 'max:255'],
            'hostUserId' => ['nullable', 'uuid'],
            'scheduleId' => ['nullable', 'uuid'],
            'bufferBeforeMinutes' => ['sometimes', 'integer', 'min:0'],
            'bufferAfterMinutes' => ['sometimes', 'integer', 'min:0'],
            'minNoticeMinutes' => ['sometimes', 'integer', 'min:0'],
            'maxDaysAhead' => ['sometimes', 'integer', 'min:1'],
            'slotIntervalMinutes' => ['sometimes', 'integer', 'min:0'],
            'dailyCap' => ['nullable', 'integer', 'min:1'],
            'requiresConfirmation' => ['sometimes', 'boolean'],
            'requiresPaymentReceipt' => ['sometimes', 'boolean'],
            'paymentInstructions' => ['nullable', 'string', 'max:5000'],
            'cancellationNoticeHours' => ['sometimes', 'integer', 'min:0'],
            'rescheduleNoticeHours' => ['sometimes', 'integer', 'min:0'],
            'questions' => ['sometimes', 'array'],
            'isActive' => ['sometimes', 'boolean'],
            'isHidden' => ['sometimes', 'boolean'],
        ];
    }
}
