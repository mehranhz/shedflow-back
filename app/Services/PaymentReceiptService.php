<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentReceiptStatus;
use App\Models\Booking;
use App\Models\BookingPaymentReceipt;
use App\Models\User;
use App\Services\Interface\BookingServiceInterface;
use App\Services\Interface\PaymentReceiptServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentReceiptService implements PaymentReceiptServiceInterface
{
    public const DISK = 'local';

    public const MAX_BYTES = 5 * 1024 * 1024;

    /** @var list<string> */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    public function __construct(
        private readonly BookingServiceInterface $bookings,
        private readonly PaymentReceiptNotificationService $notifications,
        private readonly BookingNotificationService $bookingNotifications,
    ) {}

    public function getForStaff(User $user, string $organizationId, string $bookingId): array
    {
        $booking = $this->bookings->get($user, $organizationId, $bookingId);
        $receipt = $this->latestReceipt($booking);

        if (! $receipt) {
            throw new NotFoundHttpException('Not found');
        }

        return [
            'receipt' => $receipt,
            'downloadUrl' => $this->temporaryDownloadUrl($receipt),
        ];
    }

    public function storeForStaff(User $user, string $organizationId, string $bookingId, UploadedFile $file): BookingPaymentReceipt
    {
        $booking = $this->bookings->get($user, $organizationId, $bookingId);
        $receipt = $this->storeForBooking($user, $booking, $file);
        $this->notifications->notifyStaffOfUpload($booking->refresh());

        return $receipt;
    }

    public function storePublic(string $uid, string $manageToken, UploadedFile $file, ?User $uploader = null): array
    {
        $booking = $this->bookings->getByUid($uid);
        $this->bookings->verifyManageToken($booking, $manageToken);
        $this->bookings->expireStalePaymentHolds($booking->host_user_id);
        $booking->refresh();

        $eventType = $booking->eventType;
        if (! $eventType || ! $eventType->requires_payment_receipt) {
            throw new ConflictHttpException('Payment receipt is not required for this booking');
        }

        if (! in_array($booking->status, [
            BookingStatus::PendingPayment,
            BookingStatus::PendingConfirmation,
        ], true)) {
            if ($booking->status === BookingStatus::Expired) {
                throw ValidationException::withMessages([
                    'booking' => ['Payment hold expired'],
                ]);
            }

            throw new ConflictHttpException('Booking cannot accept a payment receipt');
        }

        if ($booking->status === BookingStatus::PendingPayment
            && $booking->hold_expires_at
            && $booking->hold_expires_at->isPast()) {
            $booking->status = BookingStatus::Expired;
            $booking->save();
            throw ValidationException::withMessages([
                'booking' => ['Payment hold expired'],
            ]);
        }

        $receipt = $this->storeForBooking($uploader, $booking, $file);
        $booking = $this->bookings->markPendingConfirmationAfterReceipt($booking);
        $this->notifications->notifyStaffOfUpload($booking);

        return [
            'receipt' => $receipt,
            'booking' => $booking->load(['customer', 'eventType']),
        ];
    }

    public function storeForBooking(?User $uploader, Booking $booking, UploadedFile $file): BookingPaymentReceipt
    {
        $this->assertValidUpload($file);

        $extension = $this->extensionForMime((string) $file->getMimeType());
        $directory = sprintf(
            'payment-receipts/%s/%s',
            $booking->organization_id,
            $booking->id,
        );
        $filename = (string) Str::uuid().'.'.$extension;

        // Never log file contents — persist path metadata only.
        $stored = $file->storeAs($directory, $filename, ['disk' => self::DISK]);

        if ($stored === false) {
            throw ValidationException::withMessages([
                'file' => ['Unable to store payment receipt'],
            ]);
        }

        $this->retireReplaceableReceipts($booking);

        return BookingPaymentReceipt::query()->create([
            'booking_id' => $booking->id,
            'organization_id' => $booking->organization_id,
            'uploaded_by_user_id' => $uploader?->id,
            'disk' => self::DISK,
            'path' => $stored,
            'original_filename' => $file->getClientOriginalName() ?: 'receipt.'.$extension,
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'status' => PaymentReceiptStatus::Uploaded,
        ]);
    }

    public function streamDownload(string $organizationId, string $bookingId, string $receiptId): StreamedResponse
    {
        $receipt = BookingPaymentReceipt::query()
            ->where('organization_id', $organizationId)
            ->where('booking_id', $bookingId)
            ->whereKey($receiptId)
            ->first();

        if (! $receipt) {
            throw new NotFoundHttpException('Not found');
        }

        $disk = Storage::disk($receipt->disk);
        if (! $disk->exists($receipt->path)) {
            throw new NotFoundHttpException('Not found');
        }

        return $disk->response(
            $receipt->path,
            $receipt->original_filename,
            [
                'Content-Type' => $receipt->mime_type,
            ],
        );
    }

    public function temporaryDownloadUrl(BookingPaymentReceipt $receipt): string
    {
        return URL::temporarySignedRoute(
            'v1.bookings.payment-receipt.download',
            now()->addMinutes(15),
            [
                'organization' => $receipt->organization_id,
                'booking' => $receipt->booking_id,
                'receipt' => $receipt->id,
            ],
        );
    }

    public function latestReceipt(Booking $booking): ?BookingPaymentReceipt
    {
        return BookingPaymentReceipt::query()
            ->where('booking_id', $booking->id)
            ->where('organization_id', $booking->organization_id)
            ->latest('created_at')
            ->first();
    }

    public function approveForStaff(User $user, string $organizationId, string $bookingId): array
    {
        $booking = $this->bookings->get($user, $organizationId, $bookingId);
        $receipt = $this->latestReceipt($booking);

        if (! $receipt) {
            throw new NotFoundHttpException('Not found');
        }

        if ($booking->status === BookingStatus::Confirmed
            && $receipt->status === PaymentReceiptStatus::Approved) {
            return [
                'receipt' => $receipt,
                'booking' => $booking->load(['customer', 'eventType']),
            ];
        }

        if ($receipt->status !== PaymentReceiptStatus::Uploaded) {
            throw new ConflictHttpException('Receipt cannot be approved');
        }

        if ($booking->status !== BookingStatus::PendingConfirmation) {
            throw new ConflictHttpException('Booking cannot be confirmed');
        }

        return DB::transaction(function () use ($user, $booking, $receipt) {
            $receipt->status = PaymentReceiptStatus::Approved;
            $receipt->reviewer_user_id = $user->id;
            $receipt->reviewed_at = now();
            $receipt->rejection_reason = null;
            $receipt->save();

            $booking->status = BookingStatus::Confirmed;
            $booking->hold_expires_at = null;
            $booking->save();

            $booking = $booking->refresh()->load(['customer', 'eventType', 'host', 'organization']);
            $this->notifications->notifyInviteeApproved($booking);
            $tokens = ['manage' => $this->bookings->issueManageToken($booking)];
            DB::afterCommit(fn () => $this->bookingNotifications->sendConfirmed(
                $booking->fresh(['customer', 'eventType', 'host', 'organization']),
                $tokens,
            ));

            return [
                'receipt' => $receipt->refresh(),
                'booking' => $booking,
            ];
        });
    }

    public function rejectForStaff(User $user, string $organizationId, string $bookingId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['A rejection reason is required'],
            ]);
        }

        $booking = $this->bookings->get($user, $organizationId, $bookingId);
        $receipt = $this->latestReceipt($booking);

        if (! $receipt) {
            throw new NotFoundHttpException('Not found');
        }

        if ($receipt->status !== PaymentReceiptStatus::Uploaded) {
            throw new ConflictHttpException('Receipt cannot be rejected');
        }

        if ($booking->status !== BookingStatus::PendingConfirmation) {
            throw new ConflictHttpException('Booking is not awaiting receipt review');
        }

        // Prefer re-upload: return to PENDING_PAYMENT with a fresh hold window.
        return DB::transaction(function () use ($user, $booking, $receipt, $reason) {
            $receipt->status = PaymentReceiptStatus::Rejected;
            $receipt->reviewer_user_id = $user->id;
            $receipt->reviewed_at = now();
            $receipt->rejection_reason = $reason;
            $receipt->save();

            $booking->status = BookingStatus::PendingPayment;
            $booking->hold_expires_at = now()->addMinutes(BookingService::PAYMENT_HOLD_MINUTES);
            $booking->save();

            $booking = $booking->refresh()->load(['customer', 'eventType']);
            $this->notifications->notifyInviteeRejected($booking, $reason);

            return [
                'receipt' => $receipt->refresh(),
                'booking' => $booking,
            ];
        });
    }

    private function retireReplaceableReceipts(Booking $booking): void
    {
        $replaceable = BookingPaymentReceipt::query()
            ->where('booking_id', $booking->id)
            ->where('organization_id', $booking->organization_id)
            ->whereIn('status', [
                PaymentReceiptStatus::Uploaded->value,
                PaymentReceiptStatus::Rejected->value,
            ])
            ->get();

        foreach ($replaceable as $receipt) {
            Storage::disk($receipt->disk)->delete($receipt->path);
            $receipt->delete();
        }
    }

    private function assertValidUpload(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => ['Invalid upload'],
            ]);
        }

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'file' => ['File type not allowed. Use JPEG, PNG, WebP, or PDF.'],
            ]);
        }

        if ((int) $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'file' => ['File must be 5 MB or smaller.'],
            ]);
        }
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
