<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ActivityLogController extends Controller
{
    use ApiResponse;

    public function __construct(private ActivityLogService $activityLogService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeViewer($request);

        $validated = $request->validate([
            'range' => ['nullable', 'string', 'in:today,yesterday,this_week,this_month,custom'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'module' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:success,failure'],
            'search' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
        ]);

        $range = $this->activityLogService->resolveViewerDateRange($validated);
        $result = $this->activityLogService->listForViewer($request->user(), $validated);

        return $this->success([
            'date_range' => [
                'preset' => $range['preset'],
                'from_date' => $range['from_date'],
                'to_date' => $range['to_date'],
            ],
            'entries' => $result['entries'],
            'pagination' => [
                'current_page' => $result['page'],
                'last_page' => $result['last_page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
            ],
        ]);
    }

    public function dates(Request $request): JsonResponse
    {
        $this->authorizeViewer($request);

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);

        return $this->success([
            'dates' => $this->activityLogService->availableDates(
                $request->user(),
                $validated['month'] ?? null,
                $validated['year'] ?? null,
                $validated['company_id'] ?? null,
            ),
        ]);
    }

    public function timeline(Request $request, int $employee): JsonResponse
    {
        $viewer = $request->user();

        if (! $viewer?->canViewEmployees() && ! $viewer?->canViewActivityLogs()) {
            throw new AccessDeniedHttpException('You do not have permission to view this timeline.');
        }

        $validated = $request->validate([
            'module' => ['nullable', 'string', 'max:50'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
            'limit' => ['nullable', 'integer', 'min:10', 'max:200'],
        ]);

        return $this->success([
            'entries' => $this->activityLogService->timelineForEmployee($viewer, $employee, $validated),
        ]);
    }

    private function authorizeViewer(Request $request): void
    {
        if (! $request->user()?->canViewActivityLogs()) {
            throw new AccessDeniedHttpException('You do not have permission to view activity logs.');
        }
    }
}
