<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->company_id || $user->isSuperAdmin()) {
            abort(403, 'This area is only available to company users.');
        }

        if ($user->company?->status === 'inactive') {
            abort(403, 'Your company account is inactive. Please contact support.');
        }

        if (! $user->canSignIn()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'Your portal access has been disabled. Contact your administrator.');
        }

        return $next($request);
    }
}
