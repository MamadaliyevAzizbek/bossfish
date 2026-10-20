<?php

namespace App\Support;

use App\Models\Pond;
use App\Models\User;

class PondAccess
{
    public static function authorizeDirectorOrOwner(User $user, Pond $pond): void
    {
        if ($user->isOwner()) {
            OwnerAccess::authorizePondForOwner($user, $pond);

            return;
        }

        if ($user->isDirector()) {
            if ((int) $pond->director_id === (int) $user->id) {
                return;
            }

            $pond->loadMissing('farm');
            if ($pond->farm && (int) $pond->farm->director_id === (int) $user->id) {
                return;
            }
        }

        abort(403, 'Bu havza sizniki emas');
    }

    public static function authorizeWorker(User $user, Pond $pond): void
    {
        if (! $user->isWorker()) {
            abort(403);
        }

        $pond->loadMissing('farm');

        if ($pond->farm && $user->assignedFarms()->where('farms.id', $pond->farm_id)->exists()) {
            return;
        }

        if ($user->assignedPonds()->where('ponds.id', $pond->id)->exists()) {
            return;
        }

        $director = WorkerAccess::linkedDirector($user);
        if ($director && $pond->farm && (int) $pond->farm->director_id === (int) $director->id) {
            return;
        }

        if ($director && (int) $pond->director_id === (int) $director->id) {
            return;
        }

        abort(403, 'Bu havzaga biriktirilmagansiz');
    }

    public static function workerPond(User $user): ?Pond
    {
        $farm = FarmAccess::workerFarm($user);
        if ($farm) {
            return $farm->ponds()->orderBy('id')->first();
        }

        return $user->assignedPonds()->first();
    }
}
