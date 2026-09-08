<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\AssistantChatRequest;
use App\Services\EmployeeAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class EmployeeAssistantController extends Controller
{
    use ApiResponse;

    public function __construct(
        private EmployeeAssistantService $assistantService,
    ) {}

    public function meta(Request $request): JsonResponse
    {
        $this->assertAssistantAccess($request);

        return $this->success($this->assistantService->meta($request->user()));
    }

    public function chat(AssistantChatRequest $request): JsonResponse
    {
        $this->assertAssistantAccess($request);
        $this->assertRateLimit($request);

        $validated = $request->validated();
        $history = array_slice($validated['history'] ?? [], -12);

        $result = $this->assistantService->chat(
            $request->user(),
            $validated['message'],
            $history,
        );

        return $this->success($result);
    }

    private function assertAssistantAccess(Request $request): void
    {
        abort_unless(config('hrms.assistant.enabled', true), 404);

        $user = $request->user();

        abort_unless($user && $user->company_id, 403);
    }

    private function assertRateLimit(Request $request): void
    {
        $key = 'assistant-chat:'.$request->user()->id;
        $maxAttempts = max(1, (int) config('hrms.assistant.rate_limit_per_hour', 30));

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            throw new TooManyRequestsHttpException(
                $seconds,
                'Too many assistant requests. Please try again later.',
            );
        }

        RateLimiter::hit($key, 3600);
    }
}
