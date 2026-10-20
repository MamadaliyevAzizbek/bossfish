<?php

namespace App\Support;

use App\Models\Farm;
use App\Models\Pond;
use App\Models\User;

class FarmAccess
{
    public static function authorizeDirectorOrOwner(User $user, Farm $farm): void
    {
        if ($user->isOwner()) {
            OwnerAccess::authorizeFarmForOwner($user, $farm);

            return;
        }

        if ($user->isDirector() && (int) $farm->director_id === (int) $user->id) {
            return;
        }

        abort(403, 'Bu hojalik sizniki emas');
    }

    public static function authorizeWorker(User $user, Farm $farm): void
    {
        if (! $user->isWorker()) {
            abort(403);
        }

        if ($user->assignedFarms()->where('farms.id', $farm->id)->exists()) {
            return;
        }

        $director = WorkerAccess::linkedDirector($user);
        if ($director && (int) $farm->director_id === (int) $director->id) {
            return;
        }

        abort(403, 'Bu hojalikka biriktirilmagansiz');
    }

    public static function workerFarm(User $user): ?Farm
    {
        $assigned = $user->assignedFarms()->orderBy('farms.id')->first();
        if ($assigned) {
            return $assigned;
        }

        $director = WorkerAccess::linkedDirector($user);
        if (! $director) {
            return null;
        }

        return Farm::query()
            ->where('director_id', $director->id)
            ->orderBy('id')
            ->first();
    }

    public static function farmForPond(Pond $pond): Farm
    {
        $farm = $pond->farm;

        if (! $farm) {
            abort(422, 'Havza hojalikka biriktirilmagan');
        }

        return $farm;
    }
}
