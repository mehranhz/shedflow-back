<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailabilityRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dayOfWeek' => $this->day_of_week,
            'startMinute' => $this->start_minute,
            'endMinute' => $this->end_minute,
        ];
    }
}
