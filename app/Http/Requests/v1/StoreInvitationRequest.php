<?php

namespace App\Http\Requests\v1;

use App\Enums\OrganizationRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "phone"=>["required","string"],
            "organization"=>["required","string"],
            "role" => [new Enum(OrganizationRole::class)],
        ];
    }
}
