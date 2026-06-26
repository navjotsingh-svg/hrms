<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ValidatesReviewNotes;
use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\JobRequisition;
use App\Services\HiringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobRequisitionController extends Controller
{
    use ApiResponse, ValidatesReviewNotes;

    public function __construct(private HiringService $hiringService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'pending', 'approved', 'rejected', 'cancelled'])],
            'search' => ['nullable', 'string', 'max:255'],
            'scope' => ['nullable', Rule::in(['all', 'mine'])],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
        ]);

        $paginator = $this->hiringService->listRequisitions($request->user(), $validated);

        return $this->paginated($paginator, 'requisitions', fn (JobRequisition $r) => $this->format($r));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $requisition = $this->hiringService->storeRequisition($request->user(), $validated);

        return $this->success(['requisition' => $this->format($requisition)], 'Requisition created.', 201);
    }

    public function update(Request $request, JobRequisition $jobRequisition): JsonResponse
    {
        $validated = $this->validatePayload($request, true);

        $requisition = $this->hiringService->updateRequisition($request->user(), $jobRequisition, $validated);

        return $this->success(['requisition' => $this->format($requisition)], 'Requisition updated.');
    }

    public function submit(Request $request, JobRequisition $jobRequisition): JsonResponse
    {
        $requisition = $this->hiringService->submitRequisition($request->user(), $jobRequisition);

        return $this->success(['requisition' => $this->format($requisition)], 'Requisition submitted for approval.');
    }

    public function approve(Request $request, JobRequisition $jobRequisition): JsonResponse
    {
        $requisition = $this->hiringService->approveRequisition(
            $request->user(),
            $jobRequisition,
            $this->optionalReviewNotes($request),
        );

        return $this->success(['requisition' => $this->format($requisition)], 'Requisition approved and job draft created.');
    }

    public function reject(Request $request, JobRequisition $jobRequisition): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $requisition = $this->hiringService->rejectRequisition(
            $request->user(),
            $jobRequisition,
            $validated['reason'],
        );

        return $this->success(['requisition' => $this->format($requisition)], 'Requisition rejected.');
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $rules = [
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'headcount' => ['nullable', 'integer', 'min:1', 'max:100'],
            'employment_type' => ['nullable', 'string', 'max:30'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0'],
            'urgency' => ['nullable', Rule::in(['low', 'normal', 'high', 'critical'])],
            'approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];

        return $request->validate($rules);
    }

    private function format(JobRequisition $requisition): array
    {
        $requisition->loadMissing(['department', 'requestedBy', 'approver', 'job']);

        return [
            'id' => $requisition->id,
            'title' => $requisition->title,
            'description' => $requisition->description,
            'headcount' => $requisition->headcount,
            'employment_type' => $requisition->employment_type,
            'budget_min' => $requisition->budget_min,
            'budget_max' => $requisition->budget_max,
            'urgency' => $requisition->urgency,
            'status' => $requisition->status,
            'department' => $requisition->department ? ['id' => $requisition->department->id, 'name' => $requisition->department->name] : null,
            'requested_by' => $requisition->requestedBy ? ['id' => $requisition->requestedBy->id, 'name' => $requisition->requestedBy->name] : null,
            'approver' => $requisition->approver ? ['id' => $requisition->approver->id, 'name' => $requisition->approver->name] : null,
            'approved_at' => $requisition->approved_at?->toIso8601String(),
            'rejection_reason' => $requisition->rejection_reason,
            'job_id' => $requisition->job_id,
            'job' => $requisition->job ? ['id' => $requisition->job->id, 'title' => $requisition->job->title, 'status' => $requisition->job->status] : null,
            'created_at' => $requisition->created_at?->toIso8601String(),
        ];
    }

    private function paginated($paginator, string $key, callable $formatter): JsonResponse
    {
        return $this->success([
            $key => collect($paginator->items())->map($formatter)->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }
}
