<?php

namespace App\Http\Middleware;

use App\Services\CompanyOrganizationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWebUser
{
    public function __construct(private CompanyOrganizationService $companyOrganizationService) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            $token = $request->bearerToken() ?? $request->cookie('api_token');

            if ($token) {
                $accessToken = PersonalAccessToken::findToken(urldecode($token));

                if ($accessToken) {
                    Auth::login($accessToken->tokenable);
                }
            }
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = $request->user();
        $user->loadMissing('role', 'company');

        if (
            $user->company_id
            && ! $user->isSuperAdmin()
            && ! $this->companyOrganizationService->hasActiveAdministrator((int) $user->company_id)
        ) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('login_error', $this->companyOrganizationService->organizationUnavailableMessage());
        }

        if (! $user->canSignIn()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('login_error', 'Your portal access has been disabled. Contact your administrator.');
        }

        return $next($request);
    }
}
