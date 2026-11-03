<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\v1\StoreBookingPaymentReceiptRequest;
use App\Http\Resources\v1\BookingPaymentReceiptResource;
use App\Http\Resources\v1\BookingResource;
use App\Services\Interface\PaymentReceiptServiceInterface;
use Illuminate\Http\Request;

class BookingPaymentReceiptController extends Controller
{
    public function __construct(protected PaymentReceiptServiceInterface $receipts) {}

    public function store(StoreBookingPaymentReceiptRequest $request, string $organization, string $booking)
    {
        $receipt = $this->receipts->storeForStaff(
            $request->user(),
            $organization,
            $booking,
            $request->file('file'),
        );

        $payload = (new BookingPaymentReceiptResource($receipt))->resolve();
        $payload['downloadUrl'] = $this->receipts->temporaryDownloadUrl($receipt);

        return response()->json($payload, 201);
    }

    public function show(Request $request, string $organization, string $booking)
    {
        $result = $this->receipts->getForStaff($request->user(), $organization, $booking);
        $payload = (new BookingPaymentReceiptResource($result['receipt']))->resolve();
        $payload['downloadUrl'] = $result['downloadUrl'];

        return response()->json($payload);
    }

    public function download(string $organization, string $booking, string $receipt)
    {
        return $this->receipts->streamDownload($organization, $booking, $receipt);
    }

    public function approve(Request $request, string $organization, string $booking)
    {
        $result = $this->receipts->approveForStaff($request->user(), $organization, $booking);
        $payload = (new BookingResource($result['booking']))->resolve();
        $payload['paymentReceipt'] = (new BookingPaymentReceiptResource($result['receipt']))->resolve();
        $payload['paymentReceipt']['downloadUrl'] = $this->receipts->temporaryDownloadUrl($result['receipt']);

        return response()->json($payload);
    }

    public function reject(Request $request, string $organization, string $booking)
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $result = $this->receipts->rejectForStaff(
            $request->user(),
            $organization,
            $booking,
            (string) $request->input('reason'),
        );
        $payload = (new BookingResource($result['booking']))->resolve();
        $payload['paymentReceipt'] = (new BookingPaymentReceiptResource($result['receipt']))->resolve();

        return response()->json($payload);
    }
}
