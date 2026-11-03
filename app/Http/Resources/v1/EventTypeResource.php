<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'hostUserId' => $this->host_user_id,
            'scheduleId' => $this->schedule_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'durationMinutes' => $this->duration_minutes,
            'locationType' => $this->location_type?->value,
            'locationValue' => $this->location_value,
            'bufferBeforeMinutes' => $this->buffer_before_minutes,
            'bufferAfterMinutes' => $this->buffer_after_minutes,
            'minNoticeMinutes' => $this->min_notice_minutes,
            'maxDaysAhead' => $this->max_days_ahead,
            'slotIntervalMinutes' => $this->slot_interval_minutes,
            'dailyCap' => $this->daily_cap,
            'requiresConfirmation' => $this->requires_confirmation,
            'requiresPaymentReceipt' => $this->requires_payment_receipt,
            'paymentInstructions' => $this->payment_instructions,
            'cancellationNoticeHours' => $this->cancellation_notice_hours,
            'rescheduleNoticeHours' => $this->reschedule_notice_hours,
            'questions' => $this->questions ?? [],
            'isActive' => $this->is_active,
            'isHidden' => $this->is_hidden,
            'createdAt' => optional($this->created_at)?->toIso8601String(),
            'updatedAt' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
