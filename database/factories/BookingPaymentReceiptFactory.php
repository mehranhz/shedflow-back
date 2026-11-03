<?php

namespace Database\Factories;

use App\Enums\PaymentReceiptStatus;
use App\Models\Booking;
use App\Models\BookingPaymentReceipt;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingPaymentReceipt>
 */
class BookingPaymentReceiptFactory extends Factory
{
    protected $model = BookingPaymentReceipt::class;

    public function definition(): array
    {
        $organization = Organization::factory();

        return [
            'booking_id' => Booking::factory(),
            'organization_id' => $organization,
            'uploaded_by_user_id' => User::factory(),
            'disk' => 'local',
            'path' => 'payment-receipts/'.Str::uuid().'.jpg',
            'original_filename' => 'receipt.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12_345,
            'status' => PaymentReceiptStatus::Uploaded,
            'reviewer_user_id' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ];
    }
}
