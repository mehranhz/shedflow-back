<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CustomerBookingQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $user, string $organizationId, array $filters = []): LengthAwarePaginator
    {
        if (! empty($filters['organizationId']) && (string) $filters['organizationId'] !== $organizationId) {
            throw new NotFoundHttpException('Not found');
        }

        $query = Booking::query()
            ->where('organization_id', $organizationId)
            ->whereHas('customer', fn (Builder $customers) => $customers->where('user_id', $user->id))
            ->with(['customer', 'eventType', 'organization'])
            ->orderByDesc('start_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->where('start_at', '>=', CarbonImmutable::parse((string) $filters['from'])->utc());
        }

        if (! empty($filters['to'])) {
            $query->where('start_at', '<=', CarbonImmutable::parse((string) $filters['to'])->utc());
        }

        $limit = min(100, max(1, (int) ($filters['limit'] ?? 25)));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $query->paginate(perPage: $limit, page: $page);
    }
}
