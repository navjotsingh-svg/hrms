<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ValidatesReviewNotes;
use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\RejectAttendanceRegularizationRequest;
use App\Http\Requests\StoreAttendanceRegularizationRequest;
use App\Http\Resources\AttendanceRegularizationResource;
use App\Models\AttendanceRegularizationRequest;
use App\Services\AttendanceRegularizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceRegularizationController extends Controller
{
    use ApiResponse, ValidatesReviewNotes;

    public function __construct(private AttendanceRegularizationService $regularizationService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
            'scope' => ['nullable', Rule::in(['mine', 'history'])],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $requests = $this->regularizationService->listForUser($request->user(), $validated);

        return $this->success([
            'regularization_requests' => AttendanceRegularizationResource::collection($requests->items()),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
                'from' => $requests->firstItem(),
                'to' => $requests->lastItem(),
            ],
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        $groups = $this->regularizationService->pendingGroupsForReviewer($request->user());

        return $this->success([
            'pending_groups' => $groups,
            'regularization_requests' => AttendanceRegularizationResource::collection(
                $this->regularizationService->pendingForReviewer($request->user()),
            ),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'scope' => ['nullable', Rule::in(['mine', 'history'])],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        return $this->success(
            $this->regularizationService->summaryForUser($request->user(), $validated),
        );
    }

    public function eligibleDates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        return $this->success(
            $this->regularizationService->eligibleDatesForUser(
                $request->user(),
                null,
                $validated['date'] ?? null,
            ),
        );
    }

    public function store(StoreAttendanceRegularizationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (! empty($validated['dates'])) {
            $requests = $this->regularizationService->createBulk(
                $request->user(),
                $validated,
            );

            $count = count($requests);

            return $this->success(
                ['regularization_requests' => AttendanceRegularizationResource::collection($requests)],
                "Attendance regularization submitted for {$count} day(s).",
                201,
            );
        }

        $regularization = $this->regularizationService->create(
            $request->user(),
            $validated,
        );

        return $this->success(
            ['regularization_request' => new AttendanceRegularizationResource($regularization)],
            'Attendance regularization request submitted successfully.',
            201,
        );
    }

    public function show(Request $request, AttendanceRegularizationRequest $attendance_regularization): JsonResponse
    {
        $this->ensureAccessible($request, $attendance_regularization);
        $attendance_regularization->load(['employee', 'appliedBy', 'reviewedBy']);

        return $this->success([
            'regularization_request' => new AttendanceRegularizationResource($attendance_regularization),
        ]);
    }

    public function showBatch(Request $request, string $batchId): JsonResponse
    {
        $group = $this->regularizationService->groupForBatch($request->user(), $batchId);

        if (! $group) {
            abort(404);
        }

        return $this->success([
            'group' => $group,
        ]);
    }

    public function approve(Request $request, AttendanceRegularizationRequest $attendance_regularization): JsonResponse
    {
        $this->ensureCompanyRequest($request, $attendance_regularization);
        $attendance_regularization = $this->regularizationService->approve(
            $request->user(),
            $attendance_regularization,
            $this->optionalReviewNotes($request),
        );

        return $this->success(
            ['regularization_request' => new AttendanceRegularizationResource($attendance_regularization)],
            'Attendance regularization approved successfully.',
        );
    }

    public function approveBatch(Request $request, string $batchId): JsonResponse
    {
        $requests = $this->regularizationService->approveBatch(
            $request->user(),
            $batchId,
            $this->optionalReviewNotes($request),
        );
        $count = count($requests);

        return $this->success(
            ['regularization_requests' => AttendanceRegularizationResource::collection($requests)],
            "Attendance regularization approved for {$count} day(s).",
        );
    }

    public function reject(
        RejectAttendanceRegularizationRequest $request,
        AttendanceRegularizationRequest $attendance_regularization,
    ): JsonResponse {
        $this->ensureCompanyRequest($request, $attendance_regularization);
        $attendance_regularization = $this->regularizationService->reject(
            $request->user(),
            $attendance_regularization,
            $request->validated()['notes'],
        );

        return $this->success(
            ['regularization_request' => new AttendanceRegularizationResource($attendance_regularization)],
            'Attendance regularization rejected.',
        );
    }

    public function rejectBatch(
        RejectAttendanceRegularizationRequest $request,
        string $batchId,
    ): JsonResponse {
        $requests = $this->regularizationService->rejectBatch(
            $request->user(),
            $batchId,
            $request->validated()['notes'],
        );
        $count = count($requests);

        return $this->success(
            ['regularization_requests' => AttendanceRegularizationResource::collection($requests)],
            "Attendance regularization rejected for {$count} day(s).",
        );
    }

    public function cancel(Request $request, AttendanceRegularizationRequest $attendance_regularization): JsonResponse
    {
        $this->ensureAccessible($request, $attendance_regularization);
        $attendance_regularization = $this->regularizationService->cancel($request->user(), $attendance_regularization);

        return $this->success(
            ['regularization_request' => new AttendanceRegularizationResource($attendance_regularization)],
            'Attendance regularization cancelled.',
        );
    }

    private function ensureCompanyRequest(Request $request, AttendanceRegularizationRequest $regularization): void
    {
        if ((int) $regularization->company_id !== (int) $request->user()->company_id) {
            abort(404);
        }
    }

    private function ensureAccessible(Request $request, AttendanceRegularizationRequest $regularization): void
    {
        $this->ensureCompanyRequest($request, $regularization);

        if (! $this->regularizationService->canViewRequest($request->user(), $regularization)) {
            abort(403);
        }
    }

}
