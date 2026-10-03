<?php

namespace App\Http\Controllers\v1;

use App\Http\Requests\v1\StoreAvailabilityOverrideRequest;
use App\Http\Requests\v1\UpdateAvailabilityOverrideRequest;
use App\Models\AvailabilityOverride;

class AvailabilityOverrideController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAvailabilityOverrideRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(AvailabilityOverride $availabilityOverride)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAvailabilityOverrideRequest $request, AvailabilityOverride $availabilityOverride)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AvailabilityOverride $availabilityOverride)
    {
        //
    }
}
