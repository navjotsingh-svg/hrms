<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\PreviewLeaveRequestRequest;
use App\Http\Requests\RejectLeaveRequestRequest;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Http\Requests\UploadLeaveAttachmentsRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Services\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveRequestController extends Controller
{
    use ApiResponse;

    public function __construct(private LeaveRequestService $leaveRequestService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
            'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $requests = $this->leaveRequestService->listForUser($request->user(), $validated);

        return $this->success([
            'leave_requests' => LeaveRequestResource::collection($requests->items()),
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
        $pending = $this->leaveRequestService->pendingForReviewer($request->user());

        return $this->success([
            'leave_requests' => LeaveRequestResource::collection($pending),
        ]);
    }

    public function preview(PreviewLeaveRequestRequest $request): JsonResponse
    {
        $preview = $this->leaveRequestService->previewApplication(
            $request->user(),
            $request->validated(),
        );

        return $this->success(['preview' => $preview]);
    }

    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        $leaveRequest = $this->leaveRequestService->create(
            $request->user(),
            $request->validated(),
            $request->file('proofs', []) ?? [],
        );

        return $this->success(
            ['leave_request' => new LeaveRequestResource($leaveRequest)],
            'Leave request submitted successfully.',
            201,
        );
    }

    public function show(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->ensureAccessible($request, $leaveRequest);
        $leaveRequest->load(['employee', 'leaveType', 'days', 'attachments', 'appliedBy', 'reviewedBy']);

        return $this->success(['leave_request' => new LeaveRequestResource($leaveRequest)]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->ensureCompanyRequest($request, $leaveRequest);
        $leaveRequest = $this->leaveRequestService->approve($request->user(), $leaveRequest);

        return $this->success(
            ['leave_request' => new LeaveRequestResource($leaveRequest)],
            'Leave request approved successfully.',
        );
    }

    public function reject(RejectLeaveRequestRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->ensureCompanyRequest($request, $leaveRequest);
        $leaveRequest = $this->leaveRequestService->reject(
            $request->user(),
            $leaveRequest,
            $request->validated()['notes'],
        );

        return $this->success(
            ['leave_request' => new LeaveRequestResource($leaveRequest)],
            'Leave request rejected.',
        );
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->ensureAccessible($request, $leaveRequest);
        $leaveRequest = $this->leaveRequestService->cancel($request->user(), $leaveRequest);

        return $this->success(
            ['leave_request' => new LeaveRequestResource($leaveRequest)],
            'Leave request cancelled.',
        );
    }

    public function uploadAttachments(UploadLeaveAttachmentsRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->ensureAccessible($request, $leaveRequest);
        $leaveRequest = $this->leaveRequestService->addAttachments(
            $request->user(),
            $leaveRequest,
            $request->file('proofs', []) ?? [],
        );

        return $this->success(
            ['leave_request' => new LeaveRequestResource($leaveRequest)],
            'Supporting documents uploaded successfully.',
        );
    }

    private function ensureCompanyRequest(Request $request, LeaveRequest $leaveRequest): void
    {
        if (! $this->leaveRequestService->belongsToCompany($leaveRequest, $request->user()->company_id)) {
            abort(404);
        }
    }

    private function ensureAccessible(Request $request, LeaveRequest $leaveRequest): void
    {
        $this->ensureCompanyRequest($request, $leaveRequest);

        if (! $request->user()->canViewLeaveRequest($leaveRequest)) {
            abort(403);
        }
    }
}
