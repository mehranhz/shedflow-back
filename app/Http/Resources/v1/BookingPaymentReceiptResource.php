<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingPaymentReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bookingId' => $this->booking_id,
            'organizationId' => $this->organization_id,
            'uploadedByUserId' => $this->uploaded_by_user_id,
            'originalFilename' => $this->original_filename,
            'mimeType' => $this->mime_type,
            'sizeBytes' => $this->size_bytes,
            'status' => $this->status?->value,
            'reviewerUserId' => $this->reviewer_user_id,
            'reviewedAt' => optional($this->reviewed_at)?->toIso8601String(),
            'rejectionReason' => $this->rejection_reason,
            'createdAt' => optional($this->created_at)?->toIso8601String(),
            'updatedAt' => optional($this->updated_at)?->toIso8601String(),
            'downloadUrl' => $this->when(isset($this->downloadUrl), $this->downloadUrl),
        ];
    }
}
