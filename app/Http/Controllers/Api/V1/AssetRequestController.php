<?php



namespace App\Http\Controllers\Api\V1;



use App\Http\Concerns\ApiResponse;

use App\Http\Concerns\ValidatesReviewNotes;

use App\Http\Controllers\Controller;

use App\Http\Requests\RejectLeaveRequestRequest;

use App\Http\Requests\StoreAssetRequestRequest;

use App\Http\Resources\AssetRequestResource;

use App\Models\AssetRequest;

use App\Models\AssetRequestItem;

use App\Services\AssetRequestService;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use Illuminate\Validation\Rule;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;



class AssetRequestController extends Controller

{

    use ApiResponse, ValidatesReviewNotes;



    public function __construct(private AssetRequestService $assetRequestService) {}



    public function options(Request $request): JsonResponse

    {

        return $this->success([

            'asset_types' => $this->assetRequestService->optionsForEmployee($request->user()),

        ]);

    }



    public function index(Request $request): JsonResponse

    {

        $validated = $request->validate([

            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],

            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'cancelled', 'partially_reviewed'])],

            'asset_type_id' => ['nullable', 'integer', 'exists:asset_types,id'],

            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],

            'page' => ['nullable', 'integer', 'min:1'],

        ]);



        $requests = $this->assetRequestService->listForUser($request->user(), $validated);



        return $this->success([

            'asset_requests' => AssetRequestResource::collection($requests->items()),

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

        $pending = $this->assetRequestService->pendingForReviewer($request->user());



        return $this->success([

            'asset_requests' => AssetRequestResource::collection($pending),

        ]);

    }



    public function store(StoreAssetRequestRequest $request): JsonResponse

    {

        $assetRequest = $this->assetRequestService->create(

            $request->user(),

            $request->validated(),

        );



        return $this->success(

            ['asset_request' => new AssetRequestResource($assetRequest)],

            'Asset request submitted successfully.',

            201,

        );

    }



    public function show(Request $request, AssetRequest $assetRequest): JsonResponse

    {

        $this->ensureAccessible($request, $assetRequest);

        $assetRequest->load(['employee', 'items.assetType', 'items.reviewedBy', 'appliedBy', 'reviewedBy']);



        return $this->success(['asset_request' => new AssetRequestResource($assetRequest)]);

    }



    public function approve(Request $request, AssetRequest $assetRequest): JsonResponse

    {

        $this->ensureCompanyRequest($request, $assetRequest);

        $assetRequest = $this->assetRequestService->approve(

            $request->user(),

            $assetRequest,

            $this->optionalReviewNotes($request),

        );



        return $this->success(

            ['asset_request' => new AssetRequestResource($assetRequest)],

            'Pending assets approved.',

        );

    }



    public function reject(RejectLeaveRequestRequest $request, AssetRequest $assetRequest): JsonResponse

    {

        $this->ensureCompanyRequest($request, $assetRequest);

        $assetRequest = $this->assetRequestService->reject(

            $request->user(),

            $assetRequest,

            $request->validated('notes'),

        );



        return $this->success(

            ['asset_request' => new AssetRequestResource($assetRequest)],

            'Pending assets rejected.',

        );

    }



    public function approveItem(Request $request, AssetRequest $assetRequest, AssetRequestItem $item): JsonResponse

    {

        $this->ensureItemBelongsToRequest($assetRequest, $item);

        $this->ensureCompanyRequest($request, $assetRequest);



        $assetRequest = $this->assetRequestService->approveItem(

            $request->user(),

            $assetRequest,

            $item,

            $this->optionalReviewNotes($request),

        );



        return $this->success(

            ['asset_request' => new AssetRequestResource($assetRequest)],

            'Asset approved.',

        );

    }



    public function rejectItem(RejectLeaveRequestRequest $request, AssetRequest $assetRequest, AssetRequestItem $item): JsonResponse

    {

        $this->ensureItemBelongsToRequest($assetRequest, $item);

        $this->ensureCompanyRequest($request, $assetRequest);



        $assetRequest = $this->assetRequestService->rejectItem(

            $request->user(),

            $assetRequest,

            $item,

            $request->validated('notes'),

        );



        return $this->success(

            ['asset_request' => new AssetRequestResource($assetRequest)],

            'Asset rejected.',

        );

    }



    public function reviewItems(Request $request, AssetRequest $assetRequest): JsonResponse

    {

        $this->ensureCompanyRequest($request, $assetRequest);



        $validated = $request->validate([

            'action' => ['required', Rule::in(['approve', 'reject'])],

            'item_ids' => ['required', 'array', 'min:1'],

            'item_ids.*' => ['integer', 'distinct'],

            'notes' => ['nullable', 'string', 'max:2000'],

        ]);



        $assetRequest = $this->assetRequestService->reviewItems(

            $request->user(),

            $assetRequest,

            $validated['item_ids'],

            $validated['action'],

            $validated['notes'] ?? null,

        );



        $message = $validated['action'] === 'approve'

            ? 'Selected assets approved.'

            : 'Selected assets rejected.';



        return $this->success(

            ['asset_request' => new AssetRequestResource($assetRequest)],

            $message,

        );

    }



    public function cancel(Request $request, AssetRequest $assetRequest): JsonResponse

    {

        $this->ensureAccessible($request, $assetRequest);

        $assetRequest = $this->assetRequestService->cancel($request->user(), $assetRequest);



        return $this->success(

            ['asset_request' => new AssetRequestResource($assetRequest)],

            'Asset request cancelled.',

        );

    }



    private function ensureCompanyRequest(Request $request, AssetRequest $assetRequest): void

    {

        if ((int) $assetRequest->company_id !== (int) $request->user()?->company_id) {

            abort(404);

        }

    }



    private function ensureAccessible(Request $request, AssetRequest $assetRequest): void

    {

        if (! $request->user()?->canViewAssetRequest($assetRequest)) {

            throw new AccessDeniedHttpException('You are not allowed to view this request.');

        }

    }



    private function ensureItemBelongsToRequest(AssetRequest $assetRequest, AssetRequestItem $item): void

    {

        if ((int) $item->asset_request_id !== (int) $assetRequest->id) {

            abort(404);

        }

    }

}


