<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWebUser
{
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

        $request->user()->loadMissing('role', 'company');

        return $next($request);
    }
}
