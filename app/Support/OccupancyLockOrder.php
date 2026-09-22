<?php

namespace App\Support;

use App\Models\{RentalContract, Tenant, Unit};
use Illuminate\Support\Collection;

/** Locks occupancy records in the shared unit -> contract -> tenant order. */
final class OccupancyLockOrder
{
    public static function units(iterable $ids, ?int $workspaceId = null): Collection
    {
        $ids = collect($ids)->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values();
        return Unit::query()->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    public static function contracts(iterable $ids, ?int $workspaceId = null, bool $withTrashed = false): Collection
    {
        $ids = collect($ids)->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values();
        return RentalContract::query()->when($withTrashed, fn ($q) => $q->withTrashed())->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    public static function tenants(iterable $ids, ?int $workspaceId = null): Collection
    {
        $ids = collect($ids)->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values();
        return Tenant::query()->withTrashed()->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }
}
