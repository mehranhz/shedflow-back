<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\v1\StoreBookingRequest;
use App\Http\Resources\v1\BookingResource;
use App\Services\Interface\BookingServiceInterface;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(protected BookingServiceInterface $bookingService) {}

    public function index(Request $request, string $organization)
    {
        $page = $this->bookingService->list($request->user(), $organization, $request->query());

        return response()->json([
            'items' => BookingResource::collection($page->items())->resolve(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'limit' => $page->perPage(),
            'pageCount' => $page->lastPage(),
        ]);
    }

    public function store(StoreBookingRequest $request, string $organization)
    {
        $result = $this->bookingService->createHost($request->user(), $organization, $request->validated());
        $payload = (new BookingResource($result['booking']))->resolve();
        $payload['actionTokens'] = $result['actionTokens'];

        return response()->json($payload, 201);
    }

    public function show(Request $request, string $organization, string $booking)
    {
        $model = $this->bookingService->get($request->user(), $organization, $booking);

        return response()->json((new BookingResource($model))->resolve());
    }

    public function cancel(Request $request, string $organization, string $booking)
    {
        $model = $this->bookingService->cancelHost(
            $request->user(),
            $organization,
            $booking,
            $request->input('reason'),
        );

        return response()->json((new BookingResource($model))->resolve());
    }

    public function reschedule(Request $request, string $organization, string $booking)
    {
        $request->validate([
            'startAt' => ['required', 'date'],
            'timezone' => ['required', 'string'],
        ]);

        $model = $this->bookingService->rescheduleHost(
            $request->user(),
            $organization,
            $booking,
            $request->input('startAt'),
            $request->input('timezone'),
        );

        return response()->json((new BookingResource($model))->resolve());
    }

    public function confirm(Request $request, string $organization, string $booking)
    {
        $model = $this->bookingService->confirmHost($request->user(), $organization, $booking);

        return response()->json((new BookingResource($model))->resolve());
    }

    public function noShow(Request $request, string $organization, string $booking)
    {
        $model = $this->bookingService->markNoShow($request->user(), $organization, $booking);

        return response()->json((new BookingResource($model))->resolve());
    }
}
