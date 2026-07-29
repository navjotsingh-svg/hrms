<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($user->canSignIn()) {
            return $next($request);
        }

        $user->currentAccessToken()?->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your portal access has been disabled. Contact your administrator.',
            ], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        abort(403, 'Your portal access has been disabled. Contact your administrator.');
    }
}
