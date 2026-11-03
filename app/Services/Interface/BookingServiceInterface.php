<?php

namespace App\Services\Interface;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BookingServiceInterface
{
    public function list(User $user, string $organizationId, array $filters = []): LengthAwarePaginator;

    public function get(User $user, string $organizationId, string $bookingId): Booking;

    public function getByUid(string $uid): Booking;

    /**
     * @return array{booking:Booking,actionTokens:array<string,string>}
     */
    public function createPublic(array $input): array;

    /**
     * @return array{booking:Booking,actionTokens:array<string,string>}
     */
    public function createHost(User $user, string $organizationId, array $input): array;

    public function cancelPublic(string $uid, string $token, ?string $reason = null): Booking;

    public function cancelHost(User $user, string $organizationId, string $bookingId, ?string $reason = null): Booking;

    public function cancelBooking(Booking $booking, ?string $reason, bool $hostOverride = false): Booking;

    public function cancelCustomer(User $user, string $organizationId, string $uid, ?string $reason = null): Booking;

    public function reschedulePublic(string $uid, string $token, string $startAt, string $timezone): Booking;

    public function rescheduleHost(User $user, string $organizationId, string $bookingId, string $startAt, string $timezone): Booking;

    public function rescheduleCustomer(User $user, string $organizationId, string $uid, string $startAt, string $timezone): Booking;

    /**
     * @return array{booking:Booking,actionTokens:array<string,string>}
     */
    public function createCustomer(User $user, string $organizationId, array $input): array;

    public function getCustomerBooking(User $user, string $organizationId, string $uid): Booking;

    public function confirmHost(User $user, string $organizationId, string $bookingId): Booking;

    public function markNoShow(User $user, string $organizationId, string $bookingId): Booking;

    public function expireStalePaymentHolds(?string $hostUserId = null): int;

    public function markPendingConfirmationAfterReceipt(Booking $booking): Booking;

    public function verifyManageToken(Booking $booking, string $token): void;

    public function issueManageToken(Booking $booking): string;
}
