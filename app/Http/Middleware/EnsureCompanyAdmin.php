<?php

namespace App\Http\Middleware;

use App\Services\CompanyOrganizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAdmin
{
    public function __construct(private CompanyOrganizationService $companyOrganizationService) {}

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

        if (! $this->companyOrganizationService->hasActiveAdministrator((int) $user->company_id)) {
            abort(403, $this->companyOrganizationService->organizationUnavailableMessage());
        }

        if (! $user->hasFullAccess()) {
            abort(403, 'Only the company administrator has access to this area.');
        }

        return $next($request);
    }
}
