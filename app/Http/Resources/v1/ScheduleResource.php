<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'isPublic' => $this->is_public,
            'isDefault' => $this->is_default,
            'organizationId' => $this->organization_id,
            'timezone' => $this->timezone,
            'hostUserId' => $this->user_id,
            'rules' => AvailabilityRuleResource::collection($this->whenLoaded('availabilityRules')),
            'overrides' => AvailabilityOverrideResource::collection($this->whenLoaded('availabilityOverrides')),
            'createdAt' => optional($this->created_at)?->toIso8601String(),
            'updatedAt' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
