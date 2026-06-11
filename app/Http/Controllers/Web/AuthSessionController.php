<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AuthSessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication token is required.',
            ], 401);
        }

        $accessToken = PersonalAccessToken::findToken(urldecode($token));

        if (! $accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        Auth::login($accessToken->tokenable->load('role'));
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => 'Web session established.',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Web session ended.',
        ]);
    }
}
