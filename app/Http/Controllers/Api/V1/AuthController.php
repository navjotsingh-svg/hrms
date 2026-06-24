<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private ActivityLogService $activityLogService) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->ensureIsNotRateLimited($credentials['email'], $request->ip());
        } catch (ValidationException $exception) {
            $this->activityLogService->logAuthAttempt(
                null,
                $request,
                false,
                collect($exception->errors())->flatten()->first() ?: 'Too many login attempts.',
                $credentials['email'],
            );

            throw $exception;
        }

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($this->throttleKey($credentials['email'], $request->ip()));

            $this->activityLogService->logAuthAttempt(
                $user,
                $request,
                false,
                trans('auth.failed'),
                $credentials['email'],
            );

            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        $user->load('company');

        if ($user->company_id && $user->company?->status === 'inactive') {
            $this->activityLogService->logAuthAttempt(
                $user,
                $request,
                false,
                'Company account is inactive.',
                $credentials['email'],
            );

            throw ValidationException::withMessages([
                'email' => ['Your company account is inactive. Please contact support.'],
            ]);
        }

        RateLimiter::clear($this->throttleKey($credentials['email'], $request->ip()));

        $deviceName = $credentials['device_name'] ?? ($request->userAgent() ?: 'api-client');
        $token = $user->createToken($deviceName)->plainTextToken;

        $user->load(['company', 'role']);

        $this->activityLogService->logAuthAttempt($user, $request, true);

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 'Login successful.');
    }
 
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['company', 'role']);

        return $this->success([
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->currentAccessToken()?->delete();

        $this->activityLogService->logLogout($user, $request);

        return $this->success(null, 'Logged out successfully.');
    }

    private function ensureIsNotRateLimited(string $email, string $ip): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($email, $ip), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($email, $ip));

        throw ValidationException::withMessages([
            'email' => [trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ]);
    }

    private function throttleKey(string $email, string $ip): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ip);
    }
}
