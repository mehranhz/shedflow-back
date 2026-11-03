<?php

namespace App\Services\Interface;

use App\Models\Booking;
use App\Models\BookingPaymentReceipt;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface PaymentReceiptServiceInterface
{
    /**
     * @return array{receipt: BookingPaymentReceipt, downloadUrl: string}
     */
    public function getForStaff(User $user, string $organizationId, string $bookingId): array;

    public function storeForStaff(User $user, string $organizationId, string $bookingId, UploadedFile $file): BookingPaymentReceipt;

    /**
     * @return array{receipt: BookingPaymentReceipt, booking: Booking}
     */
    public function storePublic(string $uid, string $manageToken, UploadedFile $file, ?User $uploader = null): array;

    public function storeForBooking(?User $uploader, Booking $booking, UploadedFile $file): BookingPaymentReceipt;

    public function streamDownload(string $organizationId, string $bookingId, string $receiptId): StreamedResponse;

    public function temporaryDownloadUrl(BookingPaymentReceipt $receipt): string;

    public function latestReceipt(Booking $booking): ?BookingPaymentReceipt;

    /**
     * @return array{receipt: BookingPaymentReceipt, booking: Booking}
     */
    public function approveForStaff(User $user, string $organizationId, string $bookingId): array;

    /**
     * @return array{receipt: BookingPaymentReceipt, booking: Booking}
     */
    public function rejectForStaff(User $user, string $organizationId, string $bookingId, string $reason): array;
}
