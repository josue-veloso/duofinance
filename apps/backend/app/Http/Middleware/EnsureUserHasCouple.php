<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasCouple
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        $couple = $user->currentCouple();

        if (!$couple) {
            abort(403, 'User is not part of a couple');
        }

        // Attach couple instance to request attributes for controllers
        $request->attributes->set('couple', $couple);

        return $next($request);
    }
}

