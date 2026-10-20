<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVenger
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isVenger()) {
            abort(403, 'Faqat Venger Carp hodimi kira oladi');
        }

        if (! $user->is_active) {
            abort(403, 'Hisobingiz faol emas.');
        }

        return $next($request);
    }
}
