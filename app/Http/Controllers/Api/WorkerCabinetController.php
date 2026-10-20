<?php

namespace App\Http\Controllers\Api;

use App\Models\Pond;
use App\Support\FarmAccess;
use App\Support\PondAccess;
use App\Support\WorkerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkerCabinetController extends DirectorCabinetController
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'assignedFarms:id,name,region',
            'assignedPonds:id,name,region,area_m2,current_stock,farm_id',
        ]);
        $pond = PondAccess::workerPond($user);
        $farm = FarmAccess::workerFarm($user);
        $director = WorkerAccess::linkedDirector($user);

        return response()->json([
            'user' => $user->only(['id', 'name', 'phone', 'role']),
            'farm' => $farm?->only(['id', 'name', 'region']),
            'pond' => $pond?->only(['id', 'name', 'region', 'area_m2', 'current_stock']),
            'ponds' => $farm
                ? $farm->ponds()->get(['id', 'name', 'region', 'area_m2', 'current_stock'])
                : $user->assignedPonds->map->only(['id', 'name', 'region', 'area_m2', 'current_stock']),
            'linked_director' => $director ? [
                'id' => $director->id,
                'name' => $director->name,
                'phone' => $director->phone,
                'access_expires_at' => optional($director->access_expires_at)->toIso8601String(),
                'access_days_left' => $director->access_days_left,
                'access_expired' => $director->access_expired,
            ] : null,
            'director_access_blocked' => WorkerAccess::isBlockedByDirectorAccess($user),
        ]);
    }

    protected function authorizePond(Request $request, Pond $pond): void
    {
        PondAccess::authorizeWorker($request->user(), $pond);
    }
}
