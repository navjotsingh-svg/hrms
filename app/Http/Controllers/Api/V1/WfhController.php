<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponse;
use App\Http\Concerns\ValidatesReviewNotes;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewWfhRequestRequest;
use App\Http\Requests\RejectLeaveRequestRequest;
use App\Http\Requests\StoreWfhRequestRequest;
use App\Http\Requests\UploadWfhAttachmentsRequest;
use App\Http\Resources\WfhRequestResource;
use App\Models\WfhRequest;
use App\Services\WfhAttachmentService;
use App\Services\WfhRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class WfhController extends Controller
{
    use ApiResponse, ValidatesReviewNotes;

    public function __construct(
        private WfhRequestService $wfhRequestService,
        private WfhAttachmentService $attachmentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $requests = $this->wfhRequestService->listForUser($request->user(), $validated);

        return $this->success([
            'wfh_requests' => WfhRequestResource::collection($requests->items()),
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
        $pending = $this->wfhRequestService->pendingForReviewer($request->user());

        return $this->success([
            'wfh_requests' => WfhRequestResource::collection($pending),
        ]);
    }

    public function preview(PreviewWfhRequestRequest $request): JsonResponse
    {
        $preview = $this->wfhRequestService->previewApplication(
            $request->user(),
            $request->validated(),
        );

        return $this->success(['preview' => $preview]);
    }

    public function store(StoreWfhRequestRequest $request): JsonResponse
    {
        $files = array_merge(
            $request->file('attachments', []) ?? [],
            $request->file('proofs', []) ?? [],
        );

        $wfhRequest = $this->wfhRequestService->create(
            $request->user(),
            $request->validated(),
            $files,
        );

        return $this->success(
            ['wfh_request' => new WfhRequestResource($wfhRequest)],
            'WFH request submitted successfully.',
            201,
        );
    }

    public function show(Request $request, WfhRequest $wfhRequest): JsonResponse
    {
        $this->ensureAccessible($request, $wfhRequest);
        $wfhRequest->load(['employee', 'appliedBy', 'reviewedBy', 'attachments']);

        return $this->success(['wfh_request' => new WfhRequestResource($wfhRequest)]);
    }

    public function approve(Request $request, WfhRequest $wfhRequest): JsonResponse
    {
        $this->ensureCompanyRequest($request, $wfhRequest);
        $wfhRequest = $this->wfhRequestService->approve(
            $request->user(),
            $wfhRequest,
            $this->optionalReviewNotes($request),
        );

        return $this->success(
            ['wfh_request' => new WfhRequestResource($wfhRequest)],
            'WFH request approved.',
        );
    }

    public function reject(RejectLeaveRequestRequest $request, WfhRequest $wfhRequest): JsonResponse
    {
        $this->ensureCompanyRequest($request, $wfhRequest);
        $wfhRequest = $this->wfhRequestService->reject(
            $request->user(),
            $wfhRequest,
            $request->validated('notes'),
        );

        return $this->success(
            ['wfh_request' => new WfhRequestResource($wfhRequest)],
            'WFH request rejected.',
        );
    }

    public function cancel(Request $request, WfhRequest $wfhRequest): JsonResponse
    {
        $this->ensureAccessible($request, $wfhRequest);
        $wfhRequest = $this->wfhRequestService->cancel($request->user(), $wfhRequest);

        return $this->success(
            ['wfh_request' => new WfhRequestResource($wfhRequest)],
            'WFH request cancelled.',
        );
    }

    public function uploadAttachments(UploadWfhAttachmentsRequest $request, WfhRequest $wfhRequest): JsonResponse
    {
        $this->ensureAccessible($request, $wfhRequest);

        if (! $request->user()?->canUploadWfhAttachments($wfhRequest)) {
            throw new AccessDeniedHttpException('You are not allowed to upload attachments for this request.');
        }

        $wfhRequest->loadMissing('employee');
        $this->attachmentService->storeMany(
            $wfhRequest,
            $wfhRequest->employee,
            $request->file('proofs', []) ?? [],
        );

        $wfhRequest->load(['employee', 'appliedBy', 'reviewedBy', 'attachments']);

        return $this->success(
            ['wfh_request' => new WfhRequestResource($wfhRequest)],
            'Attachments uploaded successfully.',
        );
    }

    private function ensureCompanyRequest(Request $request, WfhRequest $wfhRequest): void
    {
        if ((int) $wfhRequest->company_id !== (int) $request->user()?->company_id) {
            abort(404);
        }
    }

    private function ensureAccessible(Request $request, WfhRequest $wfhRequest): void
    {
        if (! $request->user()?->canViewWfhRequest($wfhRequest)) {
            throw new AccessDeniedHttpException('You are not allowed to view this request.');
        }
    }
}
