<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\v1\ReplaceScheduleRulesRequest;
use App\Http\Requests\v1\StoreScheduleRequest;
use App\Http\Requests\v1\UpdateScheduleRequest;
use App\Http\Requests\v1\UpsertAvailabilityOverrideRequest;
use App\Http\Resources\v1\ScheduleResource;
use App\Services\Interface\ScheduleServiceInterface;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(protected ScheduleServiceInterface $scheduleService) {}

    public function index(Request $request, string $organization)
    {
        $schedules = $this->scheduleService->listForOrganization($request->user(), $organization);

        return response()->json(ScheduleResource::collection(collect($schedules))->resolve());
    }

    public function store(StoreScheduleRequest $request, string $organization)
    {
        $schedule = $this->scheduleService->create($request->user(), $organization, $request->validated());

        return response()->json((new ScheduleResource($schedule))->resolve(), 201);
    }

    public function storeDefault(Request $request, string $organization)
    {
        $schedule = $this->scheduleService->createDefaultSchedule($request->user(), $organization);

        return response()->json((new ScheduleResource($schedule))->resolve(), 201);
    }

    public function show(Request $request, string $organization, string $schedule)
    {
        $model = $this->scheduleService->getForOrganization($request->user(), $organization, $schedule);

        return response()->json((new ScheduleResource($model))->resolve());
    }

    public function update(UpdateScheduleRequest $request, string $organization, string $schedule)
    {
        $model = $this->scheduleService->update($request->user(), $organization, $schedule, $request->validated());

        return response()->json((new ScheduleResource($model))->resolve());
    }

    public function destroy(Request $request, string $organization, string $schedule)
    {
        $this->scheduleService->delete($request->user(), $organization, $schedule);

        return response()->noContent();
    }

    public function replaceRules(ReplaceScheduleRulesRequest $request, string $organization, string $schedule)
    {
        $model = $this->scheduleService->replaceRules(
            $request->user(),
            $organization,
            $schedule,
            $request->validated('rules'),
        );

        return response()->json((new ScheduleResource($model))->resolve());
    }

    public function upsertOverride(UpsertAvailabilityOverrideRequest $request, string $organization, string $schedule, string $date)
    {
        $model = $this->scheduleService->upsertOverride(
            $request->user(),
            $organization,
            $schedule,
            $date,
            $request->validated(),
        );

        return response()->json((new ScheduleResource($model))->resolve());
    }

    public function destroyOverride(Request $request, string $organization, string $schedule, string $date)
    {
        $this->scheduleService->deleteOverride($request->user(), $organization, $schedule, $date);

        return response()->noContent();
    }
}
