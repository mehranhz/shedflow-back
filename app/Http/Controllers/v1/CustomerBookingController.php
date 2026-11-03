<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\v1\StoreCustomerBookingRequest;
use App\Http\Resources\v1\BookingResource;
use App\Services\CustomerBookingQueryService;
use App\Services\Interface\BookingServiceInterface;
use Illuminate\Http\Request;

class CustomerBookingController extends Controller
{
    public function __construct(
        protected BookingServiceInterface $bookings,
        protected CustomerBookingQueryService $queries,
    ) {}

    public function index(Request $request)
    {
        $orgId = $this->requireCustomerOrg($request);

        $page = $this->queries->paginateForUser(
            $request->user(),
            $orgId,
            $request->query(),
        );

        return response()->json([
            'items' => BookingResource::collection($page->items())->resolve(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'limit' => $page->perPage(),
            'pageCount' => $page->lastPage(),
        ]);
    }

    public function show(Request $request, string $uid)
    {
        $orgId = $this->requireCustomerOrg($request);

        $booking = $this->bookings->getCustomerBooking($request->user(), $orgId, $uid);

        return response()->json((new BookingResource($booking))->resolve());
    }

    public function store(StoreCustomerBookingRequest $request)
    {
        $orgId = $this->requireCustomerOrg($request);

        $result = $this->bookings->createCustomer(
            $request->user(),
            $orgId,
            $request->validated(),
        );

        $payload = (new BookingResource($result['booking']))->resolve();
        $payload['actionTokens'] = $result['actionTokens'];

        return response()->json($payload, 201);
    }

    public function cancel(Request $request, string $uid)
    {
        $orgId = $this->requireCustomerOrg($request);

        $reason = $request->input('reason');
        $updated = $this->bookings->cancelCustomer(
            $request->user(),
            $orgId,
            $uid,
            is_string($reason) ? $reason : null,
        );

        return response()->json((new BookingResource($updated))->resolve());
    }

    public function reschedule(Request $request, string $uid)
    {
        $request->validate([
            'startAt' => ['required', 'date'],
            'timezone' => ['required', 'string'],
        ]);

        $orgId = $this->requireCustomerOrg($request);

        $updated = $this->bookings->rescheduleCustomer(
            $request->user(),
            $orgId,
            $uid,
            (string) $request->input('startAt'),
            (string) $request->input('timezone'),
        );

        return response()->json((new BookingResource($updated))->resolve());
    }

    private function requireCustomerOrg(Request $request): string
    {
        $orgId = $request->attributes->get('jwt_customer_org_id');

        if (! is_string($orgId) || $orgId === '') {
            abort(400, 'Active customer organization required');
        }

        return $orgId;
    }
}
