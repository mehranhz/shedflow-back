<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\v1\BookingResource;
use App\Models\Booking;
use App\Services\Interface\BookingServiceInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MeBookingController extends Controller
{
    public function __construct(protected BookingServiceInterface $bookingService) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $phone = trim((string) $user->phone);

        $bookings = Booking::query()
            ->whereHas('customer', function (Builder $customers) use ($user, $phone) {
                $customers->where(function (Builder $query) use ($user, $phone) {
                    $query->where('user_id', $user->id);
                    if ($phone !== '') {
                        $query->orWhere('phone', $phone);
                    }
                });
            })
            ->orderByDesc('start_at')
            ->get();

        return response()->json(BookingResource::collection($bookings)->resolve());
    }

    public function cancel(Request $request, Booking $booking)
    {
        $user = $request->user();
        $phone = trim((string) $user->phone);

        $owned = Booking::query()
            ->whereKey($booking->getKey())
            ->whereHas('customer', function (Builder $customers) use ($user, $phone) {
                $customers->where(function (Builder $query) use ($user, $phone) {
                    $query->where('user_id', $user->id);
                    if ($phone !== '') {
                        $query->orWhere('phone', $phone);
                    }
                });
            })
            ->exists();

        if (! $owned) {
            throw new NotFoundHttpException('Not found');
        }

        $reason = $request->input('reason');
        $updated = $this->bookingService->cancelBooking(
            $booking,
            is_string($reason) ? $reason : null,
        );

        return response()->json((new BookingResource($updated))->resolve());
    }
}
