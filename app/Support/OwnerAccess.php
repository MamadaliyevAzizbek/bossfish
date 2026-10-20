<?php

namespace App\Support;

use App\Models\Farm;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Http\Request;

class OwnerAccess
{
    public static function actingDirectorId(?Request $request = null): ?int
    {
        $request ??= request();

        if (! $request) {
            return null;
        }

        $raw = $request->header('X-Acting-Director-Id') ?: $request->query('director_id');

        if ($raw === null || $raw === '') {
            return null;
        }

        return (int) $raw;
    }

    public static function actingDirector(?Request $request = null): ?User
    {
        $directorId = self::actingDirectorId($request);

        if (! $directorId) {
            return null;
        }

        return User::query()
            ->whereKey($directorId)
            ->where('role', User::ROLE_DIRECTOR)
            ->first(['id', 'name', 'phone', 'access_expires_at', 'is_active']);
    }

    public static function requireActingDirector(?Request $request = null): User
    {
        $director = self::actingDirector($request);

        if (! $director) {
            abort(403, 'Direktor tanlanmagan. BOSS kabinetidan direktorni tanlang.');
        }

        return $director;
    }

    public static function farmBelongsToDirector(Farm $farm, int $directorId): bool
    {
        return (int) $farm->director_id === $directorId;
    }

    public static function pondBelongsToDirector(Pond $pond, int $directorId): bool
    {
        if ((int) $pond->director_id === $directorId) {
            return true;
        }

        $pond->loadMissing('farm');

        return $pond->farm && (int) $pond->farm->director_id === $directorId;
    }

    public static function authorizeFarmForOwner(User $user, Farm $farm, ?Request $request = null): void
    {
        if (! $user->isOwner()) {
            return;
        }

        $request ??= request();

        if ($request->is('api/owner/*')) {
            return;
        }

        $director = self::requireActingDirector($request);

        if (! self::farmBelongsToDirector($farm, $director->id)) {
            abort(403, 'Bu hojalik tanlangan direktorga tegishli emas.');
        }
    }

    public static function authorizePondForOwner(User $user, Pond $pond, ?Request $request = null): void
    {
        if (! $user->isOwner()) {
            return;
        }

        $request ??= request();

        if ($request->is('api/owner/*') || $request->is('api/director/ponds/*/close')) {
            return;
        }

        $director = self::requireActingDirector($request);

        if (! self::pondBelongsToDirector($pond, $director->id)) {
            abort(403, 'Bu havza tanlangan direktorga tegishli emas.');
        }
    }
}
