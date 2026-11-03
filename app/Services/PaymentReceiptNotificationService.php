<?php

namespace App\Services;

use App\DTOs\ContactDTO;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\PaymentReceiptApproved;
use App\Notifications\PaymentReceiptRejected;
use App\Notifications\PaymentReceiptUploaded;
use App\Services\Interface\BookingServiceInterface;
use App\Support\ManageBookingUrl;
use Illuminate\Support\Collection;

class PaymentReceiptNotificationService
{
    public function __construct(private readonly BookingServiceInterface $bookings) {}

    public function notifyStaffOfUpload(Booking $booking): void
    {
        $booking->loadMissing(['organization', 'host', 'customer', 'eventType']);

        foreach ($this->staffRecipients($booking) as $user) {
            $user->notify(new PaymentReceiptUploaded($booking));
        }
    }

    public function notifyInviteeApproved(Booking $booking): void
    {
        $this->notifyInvitee($booking, new PaymentReceiptApproved(
            $booking,
            $this->manageUrl($booking),
        ));
    }

    public function notifyInviteeRejected(Booking $booking, string $reason): void
    {
        $this->notifyInvitee($booking, new PaymentReceiptRejected(
            $booking,
            $this->manageUrl($booking),
            $reason,
        ));
    }

    private function manageUrl(Booking $booking): string
    {
        $token = $this->bookings->issueManageToken($booking);

        return ManageBookingUrl::forBooking($booking, $token);
    }

    private function notifyInvitee(Booking $booking, PaymentReceiptApproved|PaymentReceiptRejected $notification): void
    {
        $booking->loadMissing('customer');
        $phone = trim((string) ($booking->customer?->phone ?? ''));
        if ($phone === '') {
            return;
        }

        (new ContactDTO($phone))->notify($notification);
    }

    /**
     * @return Collection<int, User>
     */
    private function staffRecipients(Booking $booking): Collection
    {
        $host = $booking->host;
        $staffRoles = [
            OrganizationRole::Owner->value,
            OrganizationRole::Admin->value,
        ];

        $fromOrg = $booking->organization
            ->users()
            ->wherePivot('status', MembershipStatus::Active->value)
            ->wherePivotIn('type', $staffRoles)
            ->get();

        $recipients = $fromOrg->keyBy('id');
        if ($host && filled($host->phone)) {
            $recipients->put($host->id, $host);
        }

        return $recipients
            ->filter(fn (User $user) => filled($user->phone))
            ->values();
    }
}
