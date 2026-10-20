<?php

namespace App\Http\Middleware;

use App\Support\WorkerAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorker
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isWorker()) {
            abort(403, 'Faqat hodim kira oladi');
        }

        if (! $user->is_active) {
            abort(403, 'Hisobingiz faol emas. BOSS bilan bog‘laning.');
        }

        if ($blockMessage = WorkerAccess::loginBlockMessage($user)) {
            abort(403, $blockMessage);
        }

        return $next($request);
    }
}
