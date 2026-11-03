<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\v1\StoreBookingPaymentReceiptRequest;
use App\Http\Requests\v1\StorePublicBookingRequest;
use App\Http\Resources\v1\BookingPaymentReceiptResource;
use App\Http\Resources\v1\BookingResource;
use App\Services\Interface\BookingServiceInterface;
use App\Services\Interface\PaymentReceiptServiceInterface;
use Illuminate\Http\Request;

class PublicBookingController extends Controller
{
    public function __construct(
        protected BookingServiceInterface $bookingService,
        protected PaymentReceiptServiceInterface $receipts,
    ) {}

    public function store(StorePublicBookingRequest $request)
    {
        $result = $this->bookingService->createPublic($request->validated());
        $payload = (new BookingResource($result['booking']))->resolve();
        $payload['actionTokens'] = $result['actionTokens'];

        return response()->json($payload, 201);
    }

    public function show(string $uid)
    {
        $booking = $this->bookingService->getByUid($uid);
        $payload = (new BookingResource($booking))->resolve();
        $receipt = $this->receipts->latestReceipt($booking);
        if ($receipt) {
            $payload['paymentReceipt'] = [
                'status' => $receipt->status?->value,
                'rejectionReason' => $receipt->rejection_reason,
                'reviewedAt' => optional($receipt->reviewed_at)?->toIso8601String(),
                'createdAt' => optional($receipt->created_at)?->toIso8601String(),
            ];
        }

        return response()->json($payload);
    }

    public function cancel(Request $request, string $uid)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'reason' => ['nullable', 'string'],
        ]);

        $booking = $this->bookingService->cancelPublic(
            $uid,
            $request->input('token'),
            $request->input('reason'),
        );

        return response()->json((new BookingResource($booking))->resolve());
    }

    public function reschedule(Request $request, string $uid)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'startAt' => ['required', 'date'],
            'timezone' => ['required', 'string'],
        ]);

        $booking = $this->bookingService->reschedulePublic(
            $uid,
            $request->input('token'),
            $request->input('startAt'),
            $request->input('timezone'),
        );

        return response()->json((new BookingResource($booking))->resolve());
    }

    public function storePaymentReceipt(StoreBookingPaymentReceiptRequest $request, string $uid)
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $result = $this->receipts->storePublic(
            $uid,
            (string) $request->input('token'),
            $request->file('file'),
            $request->user(),
        );

        $payload = (new BookingResource($result['booking']))->resolve();
        $payload['paymentReceipt'] = (new BookingPaymentReceiptResource($result['receipt']))->resolve();

        return response()->json($payload, 201);
    }
}
