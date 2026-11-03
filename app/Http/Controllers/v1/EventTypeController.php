<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\v1\StoreEventTypeRequest;
use App\Http\Requests\v1\UpdateEventTypeRequest;
use App\Http\Resources\v1\EventTypeResource;
use App\Services\Interface\EventTypeServiceInterface;
use Illuminate\Http\Request;

class EventTypeController extends Controller
{
    public function __construct(protected EventTypeServiceInterface $eventTypeService) {}

    public function index(Request $request, string $organization)
    {
        $items = $this->eventTypeService->list($request->user(), $organization);

        return response()->json(EventTypeResource::collection($items)->resolve());
    }

    public function store(StoreEventTypeRequest $request, string $organization)
    {
        $eventType = $this->eventTypeService->create($request->user(), $organization, $request->validated());

        return response()->json((new EventTypeResource($eventType))->resolve(), 201);
    }

    public function show(Request $request, string $organization, string $eventType)
    {
        $model = $this->eventTypeService->get($request->user(), $organization, $eventType);

        return response()->json((new EventTypeResource($model))->resolve());
    }

    public function update(UpdateEventTypeRequest $request, string $organization, string $eventType)
    {
        $model = $this->eventTypeService->update($request->user(), $organization, $eventType, $request->validated());

        return response()->json((new EventTypeResource($model))->resolve());
    }

    public function destroy(Request $request, string $organization, string $eventType)
    {
        $this->eventTypeService->delete($request->user(), $organization, $eventType);

        return response()->noContent();
    }
}
