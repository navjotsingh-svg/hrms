<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\Employee;
use App\Models\PerformanceCalibrationEntry;
use App\Models\PerformanceCalibrationSession;
use App\Services\PerformanceCalibrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PerformanceCalibrationController extends Controller
{
    use ApiResponse;

    public function __construct(private PerformanceCalibrationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'in_progress', 'finalized'])],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $sessions = $this->service->listSessions($request->user(), $validated);

        return $this->success([
            'sessions' => $sessions->getCollection()->map(fn ($session) => $this->formatSession($session))->values(),
            'pagination' => $this->pagination($sessions),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cycle_id' => ['nullable', 'integer', 'exists:performance_review_cycles,id'],
        ]);

        $session = $this->service->storeSession($request->user(), $validated);

        return $this->success(
            ['session' => $this->formatSession($session, true)],
            'Calibration session created successfully.',
            201
        );
    }

    public function show(Request $request, PerformanceCalibrationSession $calibrationSession): JsonResponse
    {
        $session = $this->service->resolveSession($request->user(), $calibrationSession);

        return $this->success([
            'session' => $this->formatSession($session, true),
        ]);
    }

    public function updateEntry(Request $request, PerformanceCalibrationSession $calibrationSession, PerformanceCalibrationEntry $entry): JsonResponse
    {
        if ((int) $entry->session_id !== (int) $calibrationSession->id) {
            abort(404);
        }

        $validated = $request->validate([
            'calibrated_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'notes' => ['nullable', 'string'],
        ]);

        $entry = $this->service->updateEntry($request->user(), $entry, $validated);

        return $this->success(
            ['entry' => $this->formatEntry($entry)],
            'Calibration entry updated successfully.'
        );
    }

    public function finalize(Request $request, PerformanceCalibrationSession $calibrationSession): JsonResponse
    {
        $session = $this->service->finalizeSession($request->user(), $calibrationSession);

        return $this->success(
            ['session' => $this->formatSession($session, true)],
            'Calibration session finalized successfully.'
        );
    }

    private function formatSession(PerformanceCalibrationSession $session, bool $detailed = false): array
    {
        $data = [
            'id' => $session->id,
            'name' => $session->name,
            'description' => $session->description,
            'status' => $session->status,
            'finalized_at' => $session->finalized_at?->toIso8601String(),
            'entries_count' => $session->entries_count ?? $session->entries?->count(),
            'cycle' => $session->cycle ? [
                'id' => $session->cycle->id,
                'name' => $session->cycle->name,
            ] : null,
            'created_at' => $session->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['entries'] = $session->entries->map(fn ($entry) => $this->formatEntry($entry))->values();
        }

        return $data;
    }

    private function formatEntry(PerformanceCalibrationEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'employee' => $this->employeeBrief($entry->employee),
            'original_rating' => $entry->original_rating !== null ? (float) $entry->original_rating : null,
            'calibrated_rating' => $entry->calibrated_rating !== null ? (float) $entry->calibrated_rating : null,
            'notes' => $entry->notes,
            'status' => $entry->status,
        ];
    }

    private function employeeBrief(?Employee $employee): ?array
    {
        if (! $employee) {
            return null;
        }

        return [
            'id' => $employee->id,
            'full_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
        ];
    }

    private function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
