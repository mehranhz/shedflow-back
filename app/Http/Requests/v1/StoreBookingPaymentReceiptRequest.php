<?php

namespace App\Http\Requests\v1;

use App\Services\PaymentReceiptService;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingPaymentReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowed = implode(',', PaymentReceiptService::ALLOWED_MIMES);
        $maxKb = (int) (PaymentReceiptService::MAX_BYTES / 1024);

        return [
            'file' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimetypes:'.$allowed,
            ],
        ];
    }
}
