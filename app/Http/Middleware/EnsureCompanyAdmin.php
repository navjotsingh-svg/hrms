<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user?->loadMissing('company');

        if (! $user?->company_id || $user->isSuperAdmin()) {
            abort(403, 'This area is only available to company administrators.');
        }

        if ($user->company?->status === 'inactive') {
            abort(403, 'Your company account is inactive. Please contact support.');
        }

        if (! $user->hasFullAccess()) {
            abort(403, 'Only the company administrator has access to this area.');
        }

        return $next($request);
    }
}
