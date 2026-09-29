<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventTypeRequest;
use App\Http\Requests\UpdateEventTypeRequest;
use App\Models\EventType;

class EventTypeController extends Controller
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
    public function store(StoreEventTypeRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(EventType $eventType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEventTypeRequest $request, EventType $eventType)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EventType $eventType)
    {
        //
    }
}
