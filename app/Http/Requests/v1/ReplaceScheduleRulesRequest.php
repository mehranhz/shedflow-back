<?php

namespace App\Http\Requests\v1;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceScheduleRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rules' => ['required', 'array'],
            'rules.*.dayOfWeek' => ['required', 'integer', 'between:0,6'],
            'rules.*.startMinute' => ['required', 'integer', 'min:0', 'max:1440'],
            'rules.*.endMinute' => ['required', 'integer', 'min:0', 'max:1440'],
        ];
    }
}
