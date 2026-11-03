<?php

namespace App\Services;

use App\Enums\LocationType;
use App\Enums\OrganizationRole;
use App\Models\EventType;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Interface\EventTypeServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventTypeService implements EventTypeServiceInterface
{
    private const FREE_ACTIVE_LIMIT = 3;

    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function list(User $user, string $organizationId): Collection
    {
        $role = $this->requireMembership($user, $organizationId);

        $query = EventType::query()->where('organization_id', $organizationId);

        if (! $role->isStaff()) {
            $query->where('host_user_id', $user->id);
        }

        return $query->orderBy('title')->get();
    }

    public function get(User $user, string $organizationId, string $eventTypeId): EventType
    {
        $role = $this->requireMembership($user, $organizationId);
        $eventType = EventType::query()
            ->where('organization_id', $organizationId)
            ->whereKey($eventTypeId)
            ->first();

        if (! $eventType) {
            throw new NotFoundHttpException('Not found');
        }

        if (! $role->isStaff() && $eventType->host_user_id !== $user->id) {
            throw new NotFoundHttpException('Not found');
        }

        return $eventType;
    }

    public function create(User $user, string $organizationId, array $input): EventType
    {
        $role = $this->requireMembership($user, $organizationId);
        $hostUserId = $this->resolveHost($user, $organizationId, $role, $input['hostUserId'] ?? null);
        $scheduleId = $this->resolveScheduleId($organizationId, $hostUserId, $input['scheduleId'] ?? null);
        $slug = $this->requireSlug((string) $input['slug']);
        $isActive = array_key_exists('isActive', $input) ? (bool) $input['isActive'] : true;

        if ($isActive) {
            $this->assertActiveQuota($organizationId, 1);
        }

        return EventType::query()->create([
            'organization_id' => $organizationId,
            'host_user_id' => $hostUserId,
            'schedule_id' => $scheduleId,
            'slug' => $slug,
            'title' => trim((string) $input['title']),
            'description' => trim((string) ($input['description'] ?? '')),
            'duration_minutes' => (int) $input['durationMinutes'],
            'location_type' => LocationType::from((string) $input['locationType']),
            'location_value' => $input['locationValue'] ?? null,
            'buffer_before_minutes' => (int) ($input['bufferBeforeMinutes'] ?? 0),
            'buffer_after_minutes' => (int) ($input['bufferAfterMinutes'] ?? 0),
            'min_notice_minutes' => (int) ($input['minNoticeMinutes'] ?? 60),
            'max_days_ahead' => (int) ($input['maxDaysAhead'] ?? 60),
            'slot_interval_minutes' => (int) ($input['slotIntervalMinutes'] ?? 0),
            'daily_cap' => $input['dailyCap'] ?? null,
            'requires_confirmation' => (bool) ($input['requiresConfirmation'] ?? false),
            'requires_payment_receipt' => (bool) ($input['requiresPaymentReceipt'] ?? false),
            'payment_instructions' => $this->normalizePaymentInstructions($input['paymentInstructions'] ?? null),
            'cancellation_notice_hours' => (int) ($input['cancellationNoticeHours'] ?? 24),
            'reschedule_notice_hours' => (int) ($input['rescheduleNoticeHours'] ?? 24),
            'questions' => $input['questions'] ?? [],
            'is_active' => $isActive,
            'is_hidden' => (bool) ($input['isHidden'] ?? false),
        ]);
    }

    public function update(User $user, string $organizationId, string $eventTypeId, array $input): EventType
    {
        $eventType = $this->get($user, $organizationId, $eventTypeId);
        $role = $this->requireMembership($user, $organizationId);

        if (! $role->isStaff() && $eventType->host_user_id !== $user->id) {
            throw new AccessDeniedHttpException('Insufficient role');
        }

        if (array_key_exists('isActive', $input) && $input['isActive'] && ! $eventType->is_active) {
            $this->assertActiveQuota($organizationId, 1);
        }

        if (array_key_exists('title', $input)) {
            $eventType->title = trim((string) $input['title']);
        }
        if (array_key_exists('description', $input)) {
            $eventType->description = trim((string) $input['description']);
        }
        if (array_key_exists('slug', $input)) {
            $eventType->slug = $this->requireSlug((string) $input['slug']);
        }
        if (array_key_exists('durationMinutes', $input)) {
            $eventType->duration_minutes = (int) $input['durationMinutes'];
        }
        if (array_key_exists('locationType', $input)) {
            $eventType->location_type = LocationType::from((string) $input['locationType']);
        }
        if (array_key_exists('locationValue', $input)) {
            $eventType->location_value = $input['locationValue'];
        }
        if (array_key_exists('bufferBeforeMinutes', $input)) {
            $eventType->buffer_before_minutes = (int) $input['bufferBeforeMinutes'];
        }
        if (array_key_exists('bufferAfterMinutes', $input)) {
            $eventType->buffer_after_minutes = (int) $input['bufferAfterMinutes'];
        }
        if (array_key_exists('minNoticeMinutes', $input)) {
            $eventType->min_notice_minutes = (int) $input['minNoticeMinutes'];
        }
        if (array_key_exists('maxDaysAhead', $input)) {
            $eventType->max_days_ahead = (int) $input['maxDaysAhead'];
        }
        if (array_key_exists('slotIntervalMinutes', $input)) {
            $eventType->slot_interval_minutes = (int) $input['slotIntervalMinutes'];
        }
        if (array_key_exists('dailyCap', $input)) {
            $eventType->daily_cap = $input['dailyCap'];
        }
        if (array_key_exists('requiresConfirmation', $input)) {
            $eventType->requires_confirmation = (bool) $input['requiresConfirmation'];
        }
        if (array_key_exists('requiresPaymentReceipt', $input)) {
            $eventType->requires_payment_receipt = (bool) $input['requiresPaymentReceipt'];
        }
        if (array_key_exists('paymentInstructions', $input)) {
            $eventType->payment_instructions = $this->normalizePaymentInstructions($input['paymentInstructions']);
        }
        if (array_key_exists('cancellationNoticeHours', $input)) {
            $eventType->cancellation_notice_hours = (int) $input['cancellationNoticeHours'];
        }
        if (array_key_exists('rescheduleNoticeHours', $input)) {
            $eventType->reschedule_notice_hours = (int) $input['rescheduleNoticeHours'];
        }
        if (array_key_exists('questions', $input)) {
            $eventType->questions = $input['questions'];
        }
        if (array_key_exists('isActive', $input)) {
            $eventType->is_active = (bool) $input['isActive'];
        }
        if (array_key_exists('isHidden', $input)) {
            $eventType->is_hidden = (bool) $input['isHidden'];
        }
        if (array_key_exists('hostUserId', $input)) {
            $eventType->host_user_id = $this->resolveHost($user, $organizationId, $role, $input['hostUserId']);
        }
        if (array_key_exists('scheduleId', $input)) {
            $eventType->schedule_id = $this->resolveScheduleId(
                $organizationId,
                $eventType->host_user_id,
                $input['scheduleId'],
            );
        }

        $eventType->save();

        return $eventType->refresh();
    }

    public function delete(User $user, string $organizationId, string $eventTypeId): void
    {
        $eventType = $this->get($user, $organizationId, $eventTypeId);
        $role = $this->requireMembership($user, $organizationId);

        if (! $role->isStaff() && $eventType->host_user_id !== $user->id) {
            throw new AccessDeniedHttpException('Insufficient role');
        }

        $eventType->delete();
    }

    public function listPublic(string $organizationId): Collection
    {
        return EventType::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->orderBy('title')
            ->get();
    }

    public function getPublicBySlug(string $organizationId, string $slug): EventType
    {
        $eventType = EventType::query()
            ->where('organization_id', $organizationId)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->first();

        if (! $eventType) {
            throw new NotFoundHttpException('Not found');
        }

        return $eventType;
    }

    public function toPublic(EventType $eventType): array
    {
        return [
            'slug' => $eventType->slug,
            'title' => $eventType->title,
            'description' => $eventType->description,
            'durationMinutes' => $eventType->duration_minutes,
            'locationType' => $eventType->location_type->value,
            'locationValue' => $eventType->location_value,
            'questions' => $eventType->questions ?? [],
            'requiresConfirmation' => (bool) $eventType->requires_confirmation,
            'requiresPaymentReceipt' => (bool) $eventType->requires_payment_receipt,
            'paymentInstructions' => $eventType->payment_instructions,
        ];
    }

    private function normalizePaymentInstructions(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function assertActiveQuota(string $organizationId, int $adding): void
    {
        $active = EventType::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->count();

        if ($active + $adding > self::FREE_ACTIVE_LIMIT) {
            throw ValidationException::withMessages([
                'isActive' => ['Active event type limit reached for this plan'],
            ]);
        }
    }

    private function requireSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        if (! preg_match(self::SLUG_PATTERN, $slug)) {
            throw ValidationException::withMessages([
                'slug' => ['Slug must be lowercase kebab-case'],
            ]);
        }

        return $slug;
    }

    private function resolveScheduleId(string $organizationId, string $hostUserId, ?string $scheduleId): string
    {
        if ($scheduleId) {
            $schedule = Schedule::query()
                ->where('organization_id', $organizationId)
                ->whereKey($scheduleId)
                ->first();

            if (! $schedule) {
                throw ValidationException::withMessages([
                    'scheduleId' => ['Schedule not found'],
                ]);
            }

            return $schedule->id;
        }

        $default = Schedule::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $hostUserId)
            ->where('is_default', true)
            ->first();

        if (! $default) {
            throw ValidationException::withMessages([
                'scheduleId' => ['Host has no default schedule'],
            ]);
        }

        return $default->id;
    }

    private function resolveHost(User $actor, string $organizationId, OrganizationRole $role, ?string $requested): string
    {
        if (! $role->isStaff()) {
            if ($requested && $requested !== $actor->id) {
                throw new AccessDeniedHttpException('Insufficient role');
            }

            return $actor->id;
        }

        $hostUserId = $requested ?? $actor->id;
        $exists = User::query()
            ->whereKey($hostUserId)
            ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $organizationId))
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'hostUserId' => ['Host must be an organization member'],
            ]);
        }

        return $hostUserId;
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
