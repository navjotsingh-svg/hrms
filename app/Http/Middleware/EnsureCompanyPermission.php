<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $user?->loadMissing('company');

        if (! $user?->company_id || $user->isSuperAdmin()) {
            abort(403, 'This area is only available to company users.');
        }

        if ($user->company?->status === 'inactive') {
            abort(403, 'Your company account is inactive. Please contact support.');
        }

        if (str_contains($permission, 'employees.assign_admin') && $user->canAssignCompanyAdmin()) {
            return $next($request);
        }

        foreach (array_map('trim', explode(',', $permission)) as $slug) {
            if ($slug !== '' && $user->hasPermission($slug)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this area.');
    }
}
