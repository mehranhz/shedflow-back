<?php

namespace App\Services\Interface;

use App\Models\EventType;
use App\Models\User;
use Illuminate\Support\Collection;

interface EventTypeServiceInterface
{
    public function list(User $user, string $organizationId): Collection;

    public function get(User $user, string $organizationId, string $eventTypeId): EventType;

    public function create(User $user, string $organizationId, array $input): EventType;

    public function update(User $user, string $organizationId, string $eventTypeId, array $input): EventType;

    public function delete(User $user, string $organizationId, string $eventTypeId): void;

    public function listPublic(string $organizationId): Collection;

    public function getPublicBySlug(string $organizationId, string $slug): EventType;
}
