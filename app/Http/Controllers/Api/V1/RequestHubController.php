<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Services\RequestHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RequestHubController extends Controller
{
    use ApiResponse;

    public function __construct(private RequestHubService $requestHubService) {}

    public function summary(Request $request): JsonResponse
    {
        return $this->success($this->requestHubService->summaryForUser($request->user()));
    }

    public function pending(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 5);

        return $this->success($this->requestHubService->pendingForUserPaginated(
            $request->user(),
            $page,
            $perPage,
        ));
    }

    public function bulkReview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.kind' => ['required', 'string'],
            'items.*.target' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->requestHubService->bulkReview(
            $request->user(),
            $validated['action'],
            $validated['items'],
            $validated['notes'] ?? null,
        );

        $message = $validated['action'] === 'approve'
            ? "{$result['succeeded']} request(s) approved."
            : "{$result['succeeded']} request(s) rejected.";

        return $this->success($result, $message);
    }

    public function mine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
        ]);

        return $this->success([
            'requests' => $this->requestHubService->mineForUser(
                $request->user(),
                $validated['status'] ?? null,
            ),
        ]);
    }

    public function team(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['approved', 'rejected', 'cancelled'])],
        ]);

        return $this->success([
            'requests' => $this->requestHubService->teamForUser(
                $request->user(),
                $validated['status'] ?? null,
            ),
        ]);
    }
}
