<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponse;
use App\Http\Concerns\ValidatesReviewNotes;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectLeaveRequestRequest;
use App\Http\Requests\StoreResignationRequestRequest;
use App\Http\Resources\ResignationRequestResource;
use App\Models\ResignationRequest;
use App\Services\ResignationRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ResignationController extends Controller
{
    use ApiResponse, ValidatesReviewNotes;

    public function __construct(private ResignationRequestService $resignationRequestService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $requests = $this->resignationRequestService->listForUser($request->user(), $validated);

        return $this->success([
            'resignation_requests' => ResignationRequestResource::collection($requests->items()),
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
        $pending = $this->resignationRequestService->pendingForReviewer($request->user());

        return $this->success([
            'resignation_requests' => ResignationRequestResource::collection($pending),
        ]);
    }

    public function store(StoreResignationRequestRequest $request): JsonResponse
    {
        $resignation = $this->resignationRequestService->create(
            $request->user(),
            $request->validated(),
        );

        return $this->success(
            ['resignation_request' => new ResignationRequestResource($resignation)],
            'Resignation request submitted successfully.',
            201,
        );
    }

    public function show(Request $request, ResignationRequest $resignationRequest): JsonResponse
    {
        $this->ensureAccessible($request, $resignationRequest);
        $resignationRequest->load(['employee', 'appliedBy', 'reviewedBy', 'exitCase']);

        return $this->success(['resignation_request' => new ResignationRequestResource($resignationRequest)]);
    }

    public function approve(Request $request, ResignationRequest $resignationRequest): JsonResponse
    {
        $this->ensureCompanyRequest($request, $resignationRequest);

        $validated = $request->validate([
            'approved_last_working_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $resignationRequest = $this->resignationRequestService->approve(
            $request->user(),
            $resignationRequest,
            $validated['notes'] ?? null,
            $validated['approved_last_working_date'] ?? null,
        );

        return $this->success(
            ['resignation_request' => new ResignationRequestResource($resignationRequest)],
            'Resignation approved. Offboarding process started.',
        );
    }

    public function reject(RejectLeaveRequestRequest $request, ResignationRequest $resignationRequest): JsonResponse
    {
        $this->ensureCompanyRequest($request, $resignationRequest);

        $resignationRequest = $this->resignationRequestService->reject(
            $request->user(),
            $resignationRequest,
            $request->validated('notes'),
        );

        return $this->success(
            ['resignation_request' => new ResignationRequestResource($resignationRequest)],
            'Resignation request rejected.',
        );
    }

    public function cancel(Request $request, ResignationRequest $resignationRequest): JsonResponse
    {
        $this->ensureAccessible($request, $resignationRequest);

        $resignationRequest = $this->resignationRequestService->cancel(
            $request->user(),
            $resignationRequest,
        );

        return $this->success(
            ['resignation_request' => new ResignationRequestResource($resignationRequest)],
            'Resignation request cancelled.',
        );
    }

    private function ensureCompanyRequest(Request $request, ResignationRequest $resignationRequest): void
    {
        if ((int) $resignationRequest->company_id !== (int) $request->user()?->company_id) {
            abort(404);
        }
    }

    private function ensureAccessible(Request $request, ResignationRequest $resignationRequest): void
    {
        if (! $request->user()?->canViewResignationRequest($resignationRequest)) {
            throw new AccessDeniedHttpException('You are not allowed to view this request.');
        }
    }
}
