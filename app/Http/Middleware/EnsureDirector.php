<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDirector
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (! $user->isDirector() && ! $user->isOwner())) {
            abort(403, 'Faqat direktor yoki ega kira oladi');
        }

        if ($user->isDirector() && ! $user->is_active) {
            abort(403, 'Hisobingiz faol emas. BOSS bilan bog‘laning.');
        }

        if ($user->isDirector() && $user->access_expired) {
            abort(403, 'Kirish muddati tugagan. BOSS bilan bog‘laning.');
        }

        return $next($request);
    }
}
