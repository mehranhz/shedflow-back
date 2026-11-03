<?php

namespace App\Services;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\OrganizationRole;
use App\Enums\SignedActionPurpose;
use App\Exceptions\ApiDomainException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\EventType;
use App\Models\Organization;
use App\Models\SignedActionToken;
use App\Models\User;
use App\Services\Interface\BookingServiceInterface;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class BookingService implements BookingServiceInterface
{
    private const TOKEN_TTL_DAYS = 30;

    /** Payment-receipt hold window before the slot is released (T-020). */
    public const PAYMENT_HOLD_MINUTES = 30;

    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingNotificationService $bookingNotifications,
        private readonly BookingReminderService $bookingReminders,
        private readonly BookingInviteePolicy $inviteePolicy,
    ) {}

    public function list(User $user, string $organizationId, array $filters = []): LengthAwarePaginator
    {
        $role = $this->requireMembership($user, $organizationId);

        $query = Booking::query()
            ->where('organization_id', $organizationId)
            ->with(['customer', 'eventType'])
            ->orderByDesc('start_at');

        if (! $role->isStaff()) {
            $query->where('host_user_id', $user->id);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['eventTypeId'])) {
            $query->where('event_type_id', $filters['eventTypeId']);
        }

        return $query->paginate((int) ($filters['perPage'] ?? 20));
    }

    public function get(User $user, string $organizationId, string $bookingId): Booking
    {
        $role = $this->requireMembership($user, $organizationId);
        $booking = Booking::query()
            ->where('organization_id', $organizationId)
            ->whereKey($bookingId)
            ->with(['customer', 'eventType'])
            ->first();

        if (! $booking) {
            throw new NotFoundHttpException('Not found');
        }

        if (! $role->isStaff() && $booking->host_user_id !== $user->id) {
            throw new NotFoundHttpException('Not found');
        }

        return $booking;
    }

    public function getByUid(string $uid): Booking
    {
        $booking = Booking::query()->where('uid', $uid)->with(['customer', 'eventType', 'organization'])->first();
        if (! $booking) {
            throw new NotFoundHttpException('Not found');
        }

        return $booking;
    }

    public function createPublic(array $input): array
    {
        $org = Organization::query()->where('slug', $input['orgSlug'])->first();
        if (! $org) {
            throw new NotFoundHttpException('Not found');
        }

        $phone = trim((string) ($input['invitee']['phone'] ?? ''));
        if ($phone === '') {
            throw ValidationException::withMessages([
                'invitee.phone' => ['Phone is required.'],
            ]);
        }

        $invitee = User::query()->where('phone', $phone)->first();
        if (! $invitee || $invitee->phone_verified_at === null) {
            throw ValidationException::withMessages([
                'invitee.phone' => ['The phone must be verified.'],
            ]);
        }

        return $this->createBooking($org->id, [
            'eventTypeSlug' => $input['eventTypeSlug'],
            'startAt' => $input['startAt'],
            'timezone' => $input['timezone'],
            'invitee' => $input['invitee'],
            'answers' => $input['answers'] ?? [],
            'metadata' => $input['metadata'] ?? [],
            'source' => BookingSource::tryFrom((string) ($input['source'] ?? 'HOSTED')) ?? BookingSource::Hosted,
        ]);
    }

    public function createHost(User $user, string $organizationId, array $input): array
    {
        $role = $this->requireMembership($user, $organizationId);
        $eventType = EventType::query()
            ->where('organization_id', $organizationId)
            ->whereKey($input['eventTypeId'])
            ->where('is_active', true)
            ->first();

        if (! $eventType) {
            throw new NotFoundHttpException('Not found');
        }

        if (! $role->isStaff() && $eventType->host_user_id !== $user->id) {
            throw new AccessDeniedHttpException('Insufficient role');
        }

        return $this->createBooking($organizationId, [
            'eventTypeSlug' => $eventType->slug,
            'startAt' => $input['startAt'],
            'timezone' => $input['timezone'],
            'invitee' => $input['invitee'],
            'answers' => $input['answers'] ?? [],
            'metadata' => [],
            'source' => BookingSource::Dashboard,
        ]);
    }

    public function cancelPublic(string $uid, string $token, ?string $reason = null): Booking
    {
        $booking = $this->getByUid($uid);
        $this->consumeToken($booking, $token, SignedActionPurpose::Cancel);

        return $this->cancelBooking($booking, $reason);
    }

    public function cancelHost(User $user, string $organizationId, string $bookingId, ?string $reason = null): Booking
    {
        $booking = $this->get($user, $organizationId, $bookingId);

        return $this->cancelBooking($booking, $reason, hostOverride: true);
    }

    public function cancelCustomer(User $user, string $organizationId, string $uid, ?string $reason = null): Booking
    {
        $booking = $this->getCustomerBooking($user, $organizationId, $uid);

        return $this->cancelBooking($booking, $reason, hostOverride: false);
    }

    public function reschedulePublic(string $uid, string $token, string $startAt, string $timezone): Booking
    {
        $booking = $this->getByUid($uid);
        $this->consumeToken($booking, $token, SignedActionPurpose::Reschedule);

        return $this->rescheduleBooking($booking, $startAt, $timezone);
    }

    public function rescheduleHost(User $user, string $organizationId, string $bookingId, string $startAt, string $timezone): Booking
    {
        $booking = $this->get($user, $organizationId, $bookingId);

        return $this->rescheduleBooking($booking, $startAt, $timezone, hostOverride: true);
    }

    public function rescheduleCustomer(User $user, string $organizationId, string $uid, string $startAt, string $timezone): Booking
    {
        $booking = $this->getCustomerBooking($user, $organizationId, $uid);

        return $this->rescheduleBooking($booking, $startAt, $timezone, hostOverride: false);
    }

    /**
     * @return array{booking:Booking,actionTokens:array<string,string>}
     */
    public function createCustomer(User $user, string $organizationId, array $input): array
    {
        $customer = Customer::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->first();

        if (! $customer) {
            throw new NotFoundHttpException('Not found');
        }

        $email = strtolower(trim((string) ($user->email ?: $customer->email)));
        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => ['A verified email is required to book.'],
            ]);
        }

        return $this->createBooking($organizationId, [
            'eventTypeSlug' => $input['eventTypeSlug'],
            'startAt' => $input['startAt'],
            'timezone' => $input['timezone'],
            'invitee' => [
                'name' => $customer->name,
                'email' => $email,
                'phone' => $customer->phone ?? $user->phone,
            ],
            'answers' => $input['answers'] ?? [],
            'metadata' => array_merge($input['metadata'] ?? [], [
                'actorType' => 'customer',
                'userId' => $user->id,
            ]),
            'source' => BookingSource::Hosted,
        ]);
    }

    public function getCustomerBooking(User $user, string $organizationId, string $uid): Booking
    {
        $booking = Booking::query()
            ->where('uid', $uid)
            ->where('organization_id', $organizationId)
            ->whereHas('customer', fn ($q) => $q->where('user_id', $user->id))
            ->with(['customer', 'eventType', 'organization'])
            ->first();

        if (! $booking) {
            throw new NotFoundHttpException('Not found');
        }

        return $booking;
    }

    public function confirmHost(User $user, string $organizationId, string $bookingId): Booking
    {
        $booking = $this->get($user, $organizationId, $bookingId);
        if ($booking->status !== BookingStatus::PendingConfirmation) {
            throw new ConflictHttpException('Booking cannot be confirmed');
        }

        $booking->status = BookingStatus::Confirmed;
        $booking->hold_expires_at = null;
        $booking->save();

        $booking = $booking->refresh();
        $tokens = ['manage' => $this->issueManageToken($booking)];
        DB::afterCommit(fn () => $this->bookingNotifications->sendConfirmed(
            $booking->fresh(['customer', 'eventType', 'host', 'organization']),
            $tokens,
        ));

        return $booking;
    }

    public function markNoShow(User $user, string $organizationId, string $bookingId): Booking
    {
        $booking = $this->get($user, $organizationId, $bookingId);
        if ($booking->status !== BookingStatus::Confirmed) {
            throw new ConflictHttpException('Only confirmed bookings can be marked no-show');
        }

        $booking->status = BookingStatus::NoShow;
        $booking->save();

        return $booking->refresh();
    }

    /**
     * @param  array{eventTypeSlug:string,startAt:string,timezone:string,invitee:array{name:string,email:string,phone?:string},answers:array,metadata:array,source:BookingSource}  $input
     * @return array{booking:Booking,actionTokens:array<string,string>}
     */
    private function createBooking(string $organizationId, array $input): array
    {
        $this->requireTimeZone($input['timezone']);
        $startAt = CarbonImmutable::parse($input['startAt'])->utc();

        return DB::transaction(function () use ($organizationId, $input, $startAt) {
            $eventType = EventType::query()
                ->where('organization_id', $organizationId)
                ->where('slug', $input['eventTypeSlug'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $eventType) {
                throw new NotFoundHttpException('Not found');
            }

            $this->assertSlotAvailable($organizationId, $eventType, $startAt, $input['timezone']);

            $customer = $this->upsertCustomer($organizationId, $input['invitee'], $input['timezone']);
            $endAt = $startAt->addMinutes($eventType->duration_minutes);
            [$status, $holdExpiresAt] = $this->initialStatusAndHold($eventType);

            $booking = Booking::query()->create([
                'uid' => $this->generateUid(),
                'organization_id' => $organizationId,
                'event_type_id' => $eventType->id,
                'host_user_id' => $eventType->host_user_id,
                'customer_id' => $customer->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'buffer_before_minutes' => $eventType->buffer_before_minutes,
                'buffer_after_minutes' => $eventType->buffer_after_minutes,
                'timezone' => $input['timezone'],
                'status' => $status,
                'source' => $input['source'],
                'location_type' => $eventType->location_type,
                'location_value' => $eventType->location_value,
                'answers' => $input['answers'],
                'metadata' => $input['metadata'],
                'hold_expires_at' => $holdExpiresAt,
            ]);

            $tokens = $this->issueRawTokens($booking);
            $loaded = $booking->load(['customer', 'eventType', 'host', 'organization']);

            DB::afterCommit(function () use ($loaded, $tokens, $status) {
                try {
                    if ($status === BookingStatus::Confirmed) {
                        $this->bookingNotifications->sendConfirmed($loaded->fresh(['customer', 'eventType', 'host', 'organization']), $tokens);
                    } elseif ($status === BookingStatus::PendingConfirmation) {
                        $this->bookingNotifications->sendPendingHost($loaded->fresh(['customer', 'eventType', 'host', 'organization']), $tokens);
                    }
                } catch (\Throwable $exception) {
                    Log::error('booking notification failed after create', [
                        'booking_id' => $loaded->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            });

            return [
                'booking' => $loaded,
                'actionTokens' => $tokens,
            ];
        });
    }

    public function cancelBooking(Booking $booking, ?string $reason, bool $hostOverride = false): Booking
    {
        if (! in_array($booking->status, BookingStatus::active(), true)) {
            throw new ApiDomainException('CONFLICT', 'Booking cannot be cancelled', 409);
        }

        $this->inviteePolicy->assertCanCancel($booking, $hostOverride);

        $booking->status = BookingStatus::Cancelled;
        $booking->cancellation_reason = $reason;
        $booking->save();

        $booking = $booking->refresh();
        $tokens = ['manage' => $this->issueManageToken($booking)];
        DB::afterCommit(fn () => $this->bookingNotifications->sendCancelled(
            $booking->fresh(['customer', 'eventType', 'host', 'organization']),
            $tokens,
        ));

        return $booking;
    }

    private function rescheduleBooking(Booking $booking, string $startAt, string $timezone, bool $hostOverride = false): Booking
    {
        if (! in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::PendingConfirmation], true)) {
            throw new ApiDomainException('CONFLICT', 'Booking cannot be rescheduled', 409);
        }

        $this->inviteePolicy->assertCanReschedule($booking, $hostOverride);

        $this->requireTimeZone($timezone);
        $newStart = CarbonImmutable::parse($startAt)->utc();
        $eventType = $booking->eventType;

        $this->assertSlotAvailable($booking->organization_id, $eventType, $newStart, $timezone, $booking->id);

        return DB::transaction(function () use ($booking, $newStart, $timezone, $eventType) {
            $previousStatus = $booking->status;
            $booking->status = BookingStatus::Rescheduled;
            $booking->save();

            $replacementStatus = match ($previousStatus) {
                BookingStatus::PendingPayment => BookingStatus::PendingPayment,
                BookingStatus::PendingConfirmation => BookingStatus::PendingConfirmation,
                default => BookingStatus::Confirmed,
            };
            $holdExpiresAt = $previousStatus === BookingStatus::PendingPayment
                ? now()->addMinutes(self::PAYMENT_HOLD_MINUTES)
                : null;

            $replacement = Booking::query()->create([
                'uid' => $this->generateUid(),
                'organization_id' => $booking->organization_id,
                'event_type_id' => $booking->event_type_id,
                'host_user_id' => $booking->host_user_id,
                'customer_id' => $booking->customer_id,
                'start_at' => $newStart,
                'end_at' => $newStart->addMinutes($eventType->duration_minutes),
                'buffer_before_minutes' => $eventType->buffer_before_minutes,
                'buffer_after_minutes' => $eventType->buffer_after_minutes,
                'timezone' => $timezone,
                'status' => $replacementStatus,
                'source' => $booking->source,
                'location_type' => $eventType->location_type,
                'location_value' => $eventType->location_value,
                'answers' => $booking->answers,
                'rescheduled_from_id' => $booking->id,
                'metadata' => $booking->metadata,
                'hold_expires_at' => $holdExpiresAt,
            ]);

            $tokens = $this->issueRawTokens($replacement);
            $loaded = $replacement->load(['customer', 'eventType', 'host', 'organization']);

            DB::afterCommit(function () use ($loaded, $tokens, $replacementStatus) {
                $fresh = $loaded->fresh(['customer', 'eventType', 'host', 'organization']);
                $this->bookingNotifications->sendRescheduled($fresh, $tokens);
                if ($replacementStatus === BookingStatus::Confirmed) {
                    $this->bookingReminders->scheduleForBooking($fresh);
                }
            });

            return $loaded;
        });
    }

    private function assertSlotAvailable(
        string $organizationId,
        EventType $eventType,
        CarbonImmutable $startAt,
        string $timezone,
        ?string $ignoreBookingId = null,
    ): void {
        $endAt = $startAt->addMinutes($eventType->duration_minutes);
        $result = $this->availability->listSlots(
            $organizationId,
            $eventType->slug,
            $startAt->subMinute(),
            $endAt->addMinute(),
            $timezone,
        );

        $match = collect($result['slots'])->first(
            fn (array $slot) => CarbonImmutable::parse($slot['startAt'])->equalTo($startAt),
        );

        $this->expireStalePaymentHolds($eventType->host_user_id);

        $occupiedStart = $startAt->subMinutes($eventType->buffer_before_minutes);
        $occupiedEnd = $endAt->addMinutes($eventType->buffer_after_minutes);

        $overlap = Booking::query()
            ->where('host_user_id', $eventType->host_user_id)
            ->occupyingSlot()
            ->when($ignoreBookingId, fn ($q) => $q->where('id', '!=', $ignoreBookingId))
            ->where('start_at', '<', $occupiedEnd)
            ->where('end_at', '>', $occupiedStart)
            ->exists();

        if ($overlap || $match === null) {
            throw new ApiDomainException(
                'SLOT_UNAVAILABLE',
                'That time is no longer available.',
                409,
                ['startAt' => $startAt->toIso8601String()],
            );
        }
    }

    /**
     * @return array{0: BookingStatus, 1: CarbonImmutable|Carbon|null}
     */
    private function initialStatusAndHold(EventType $eventType): array
    {
        // Two-step receipt flow: PENDING_PAYMENT (hold) → upload → PENDING_CONFIRMATION → staff approve.
        if ($eventType->requires_payment_receipt) {
            return [
                BookingStatus::PendingPayment,
                now()->addMinutes(self::PAYMENT_HOLD_MINUTES),
            ];
        }

        if ($eventType->requires_confirmation) {
            return [BookingStatus::PendingConfirmation, null];
        }

        return [BookingStatus::Confirmed, null];
    }

    public function expireStalePaymentHolds(?string $hostUserId = null): int
    {
        $query = Booking::query()
            ->where('status', BookingStatus::PendingPayment)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now());

        if ($hostUserId) {
            $query->where('host_user_id', $hostUserId);
        }

        return $query->update([
            'status' => BookingStatus::Expired->value,
        ]);
    }

    public function markPendingConfirmationAfterReceipt(Booking $booking): Booking
    {
        if ($booking->status === BookingStatus::PendingPayment) {
            if ($booking->hold_expires_at && $booking->hold_expires_at->isPast()) {
                $booking->status = BookingStatus::Expired;
                $booking->save();
                throw ValidationException::withMessages([
                    'booking' => ['Payment hold expired'],
                ]);
            }

            $booking->status = BookingStatus::PendingConfirmation;
            $booking->hold_expires_at = null;
            $booking->save();
        }

        return $booking->refresh();
    }

    public function verifyManageToken(Booking $booking, string $token): void
    {
        $this->verifyToken($booking, $token, SignedActionPurpose::Manage, consume: false);
    }

    public function issueManageToken(Booking $booking): string
    {
        $raw = Str::random(48);
        SignedActionToken::query()->create([
            'booking_id' => $booking->id,
            'purpose' => SignedActionPurpose::Manage->value,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => now()->addDays(self::TOKEN_TTL_DAYS),
        ]);

        return $raw;
    }

    /**
     * @param  array{name:string,email:string,phone?:string|null}  $invitee
     */
    private function upsertCustomer(string $organizationId, array $invitee, string $timezone): Customer
    {
        $email = strtolower(trim((string) ($invitee['email'] ?? '')));
        if ($email === '') {
            throw ValidationException::withMessages([
                'invitee.email' => ['Email is required.'],
            ]);
        }

        $phone = trim((string) ($invitee['phone'] ?? ''));

        return Customer::query()->updateOrCreate(
            ['organization_id' => $organizationId, 'email' => $email],
            [
                'name' => trim($invitee['name']),
                'phone' => $phone !== '' ? $phone : null,
                'timezone' => $timezone,
            ],
        );
    }

    /**
     * @return array<string,string>
     */
    private function issueRawTokens(Booking $booking): array
    {
        $tokens = [];
        foreach ([
            SignedActionPurpose::Cancel,
            SignedActionPurpose::Reschedule,
            SignedActionPurpose::Manage,
        ] as $purpose) {
            $raw = Str::random(48);
            SignedActionToken::query()->create([
                'booking_id' => $booking->id,
                'purpose' => $purpose->value,
                'token_hash' => hash('sha256', $raw),
                'expires_at' => now()->addDays(self::TOKEN_TTL_DAYS),
            ]);
            $tokens[$purpose->value] = $raw;
        }

        return $tokens;
    }

    private function consumeToken(Booking $booking, string $token, SignedActionPurpose $purpose): void
    {
        $this->verifyToken($booking, $token, $purpose, consume: true);
    }

    private function verifyToken(
        Booking $booking,
        string $token,
        SignedActionPurpose $purpose,
        bool $consume,
    ): void {
        if ($token === '') {
            throw new UnauthorizedHttpException('', 'Missing action token');
        }

        $hash = hash('sha256', $token);
        $stored = SignedActionToken::query()
            ->where('booking_id', $booking->id)
            ->where('purpose', $purpose->value)
            ->where('token_hash', $hash)
            ->first();

        if (! $stored || $stored->expires_at->isPast()) {
            throw new UnauthorizedHttpException('', 'Invalid or expired action token');
        }

        if ($consume) {
            if ($stored->used_at !== null) {
                throw new UnauthorizedHttpException('', 'Invalid or expired action token');
            }
            $stored->used_at = now();
            $stored->save();
        }
    }

    private function generateUid(): string
    {
        return strtolower(Str::random(10));
    }

    private function requireTimeZone(string $timezone): void
    {
        try {
            new \DateTimeZone($timezone);
        } catch (\Exception) {
            throw ValidationException::withMessages([
                'timezone' => ['Invalid IANA timezone'],
            ]);
        }
    }

    private function requireMembership(User $user, string $organizationId): OrganizationRole
    {
        $membership = $user->organizations()
            ->where('organizations.id', $organizationId)
            ->first();

        if (! $membership) {
            throw new AccessDeniedHttpException('Not a member of this organization');
        }

        $type = (string) $membership->membership->type;

        return OrganizationRole::tryFrom($type) ?? OrganizationRole::Member;
    }
}
