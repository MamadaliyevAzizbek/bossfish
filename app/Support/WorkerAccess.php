<?php

namespace App\Support;

use App\Models\User;

class WorkerAccess
{
    public static function linkedDirector(User $worker): ?User
    {
        if (! $worker->isWorker()) {
            return null;
        }

        if ($worker->director_id) {
            $director = User::query()
                ->whereKey($worker->director_id)
                ->where('role', User::ROLE_DIRECTOR)
                ->first(['id', 'name', 'phone', 'access_expires_at', 'role', 'is_active']);

            if ($director) {
                return $director;
            }
        }

        $farm = $worker->assignedFarms()
            ->with('director:id,name,phone,access_expires_at,role,is_active')
            ->orderBy('farms.id')
            ->first();

        if ($farm?->director) {
            return $farm->director;
        }

        $pond = $worker->assignedPonds()
            ->with([
                'director:id,name,phone,access_expires_at,role,is_active',
                'farm.director:id,name,phone,access_expires_at,role,is_active',
            ])
            ->orderBy('ponds.id')
            ->first();

        if ($pond?->director) {
            return $pond->director;
        }

        return $pond?->farm?->director;
    }

    public static function isBlockedByDirectorAccess(User $worker): bool
    {
        return self::loginBlockMessage($worker) !== null;
    }

    public static function loginBlockMessage(User $worker): ?string
    {
        if (! $worker->isWorker()) {
            return null;
        }

        $director = self::linkedDirector($worker);

        if (! $director) {
            return 'Hodim direktorga biriktirilmagan. BOSS bilan bog‘laning.';
        }

        if (! $director->is_active) {
            return 'Direktor hisobi faol emas. Hodim kabineti vaqtincha yopilgan.';
        }

        if ($director->access_expired) {
            return 'Direktorning ish muddati tugagan. Hodim kabineti vaqtincha yopilgan.';
        }

        return null;
    }
}
