<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\AvailabilityService;
use App\Services\EventTypeService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicSchedulingController extends Controller
{
    public function __construct(
        protected EventTypeService $eventTypeService,
        protected AvailabilityService $availabilityService,
    ) {}

    public function showOrganization(string $orgSlug)
    {
        $org = $this->requireOrg($orgSlug);

        return response()->json([
            'name' => $org->name,
            'slug' => $org->slug,
            'logoUrl' => $org->logo_url,
            'brandColor' => $org->brand_color,
            'locale' => $org->locale,
            'timezone' => $org->timezone,
            'hideSchedflowBadge' => (bool) data_get($org->settings, 'branding.hideSchedflowBadge', false),
        ]);
    }

    public function listEventTypes(string $orgSlug)
    {
        $org = $this->requireOrg($orgSlug);
        $items = $this->eventTypeService->listPublic($org->id);

        return response()->json($items->map(fn ($item) => $this->eventTypeService->toPublic($item))->values());
    }

    public function showEventType(string $orgSlug, string $eventSlug)
    {
        $org = $this->requireOrg($orgSlug);
        $eventType = $this->eventTypeService->getPublicBySlug($org->id, $eventSlug);

        return response()->json(array_merge($this->eventTypeService->toPublic($eventType), [
            'organization' => [
                'name' => $org->name,
                'slug' => $org->slug,
                'logoUrl' => $org->logo_url,
                'brandColor' => $org->brand_color,
                'locale' => $org->locale,
                'timezone' => $org->timezone,
            ],
        ]));
    }

    public function listSlots(string $orgSlug, string $eventSlug)
    {
        return response()->json($this->availabilityService->listSlotsForOrgSlug(
            $orgSlug,
            $eventSlug,
            request('from'),
            request('to'),
            request('tz'),
        ));
    }

    private function requireOrg(string $orgSlug): Organization
    {
        $org = Organization::query()->where('slug', $orgSlug)->first();
        if (! $org) {
            throw new NotFoundHttpException('Not found');
        }

        return $org;
    }
}
