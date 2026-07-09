<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\MomentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformancePraiseController extends Controller
{
    use ApiResponse;

    public function __construct(private MomentService $momentService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->success($this->momentService->praiseFeedForUser(
            $request->user(),
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 15),
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $praise = $this->momentService->createPraise(
            $request->user(),
            (int) $validated['employee_id'],
            $validated['content'],
        );

        return $this->success(['praise' => $praise], 'Praise shared successfully.', 201);
    }
}
