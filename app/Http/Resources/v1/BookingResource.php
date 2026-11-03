<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'organizationId' => $this->organization_id,
            'eventTypeId' => $this->event_type_id,
            'hostUserId' => $this->host_user_id,
            'customerId' => $this->customer_id,
            'startAt' => $this->start_at?->toIso8601String(),
            'endAt' => $this->end_at?->toIso8601String(),
            'bufferBeforeMinutes' => $this->buffer_before_minutes,
            'bufferAfterMinutes' => $this->buffer_after_minutes,
            'timezone' => $this->timezone,
            'status' => $this->status?->value,
            'source' => $this->source?->value,
            'locationType' => $this->location_type?->value,
            'locationValue' => $this->location_value,
            'answers' => $this->answers ?? [],
            'cancellationReason' => $this->cancellation_reason,
            'rescheduledFromId' => $this->rescheduled_from_id,
            'holdExpiresAt' => $this->hold_expires_at?->toIso8601String(),
            'metadata' => $this->metadata ?? [],
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'email' => $this->customer->email,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ]),
            'eventType' => $this->whenLoaded('eventType', fn () => [
                'id' => $this->eventType->id,
                'slug' => $this->eventType->slug,
                'title' => $this->eventType->title,
            ]),
            'organization' => $this->whenLoaded('organization', fn () => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'slug' => $this->organization->slug,
            ]),
        ];
    }
}
