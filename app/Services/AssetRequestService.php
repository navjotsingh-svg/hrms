<?php



namespace App\Services;



use App\Models\AssetRequest;

use App\Models\AssetRequestItem;

use App\Models\AssetType;

use App\Models\Employee;

use App\Models\EmployeeAsset;

use App\Models\User;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\DB;

use Illuminate\Validation\ValidationException;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;



class AssetRequestService

{

    public function __construct(

        private EmployeeAccessService $employeeAccessService,

        private AssetTypeService $assetTypeService,

        private EmployeeAssetService $employeeAssetService,

        private ActivityLogService $activityLogService,

        private WorkflowNotificationService $workflowNotificationService,

    ) {}



    public function optionsForEmployee(User $user): Collection

    {

        $employee = $this->employeeAccessService->linkedEmployee($user);



        if (! $employee) {

            throw new AccessDeniedHttpException('No employee profile is linked to your account.');

        }



        $assetTypes = $this->assetTypeService->activeForCompany($employee->company_id);

        $assignedIds = EmployeeAsset::query()

            ->where('employee_id', $employee->id)

            ->where('is_assigned', true)

            ->pluck('asset_type_id')

            ->all();



        $pendingIds = AssetRequestItem::query()

            ->where('status', AssetRequestItem::STATUS_PENDING)

            ->whereHas('assetRequest', fn ($query) => $query

                ->where('employee_id', $employee->id)

                ->where('status', AssetRequest::STATUS_PENDING))

            ->pluck('asset_type_id')

            ->all();



        return $assetTypes->map(fn (AssetType $type) => [

            'id' => $type->id,

            'name' => $type->name,

            'sort_order' => $type->sort_order,

            'is_assigned' => in_array($type->id, $assignedIds, true),

            'has_pending_request' => in_array($type->id, $pendingIds, true),

            'can_request' => ! in_array($type->id, $assignedIds, true)

                && ! in_array($type->id, $pendingIds, true),

        ])->values();

    }



    public function listForUser(User $user, array $filters = []): LengthAwarePaginator

    {

        $query = AssetRequest::query()

            ->with(['employee', 'items.assetType', 'items.reviewedBy', 'appliedBy', 'reviewedBy'])

            ->where('company_id', $user->company_id)

            ->latest();



        if ($user->canViewAllAssetRequests()) {

            if (! empty($filters['employee_id'])) {

                $query->where('employee_id', (int) $filters['employee_id']);

            }

        } else {

            $employee = $this->employeeAccessService->linkedEmployee($user);



            if (! $employee) {

                throw new AccessDeniedHttpException('No employee profile is linked to your account.');

            }



            $visibleEmployeeIds = array_values(array_unique([

                ...$this->employeeAccessService->subordinateIdsForUser($user),

                $employee->id,

            ]));



            $query->whereIn('employee_id', $visibleEmployeeIds);

        }



        if (! empty($filters['status'])) {

            $query->where('status', $filters['status']);

        }



        if (! empty($filters['asset_type_id'])) {

            $query->whereHas('items', fn ($itemQuery) => $itemQuery

                ->where('asset_type_id', (int) $filters['asset_type_id']));

        }



        return $query->paginate($filters['per_page'] ?? 10);

    }



    public function pendingForReviewer(User $user): Collection

    {

        return AssetRequest::query()

            ->with(['employee.user', 'items.assetType', 'items.reviewedBy', 'appliedBy'])

            ->where('company_id', $user->company_id)

            ->where('status', AssetRequest::STATUS_PENDING)

            ->whereHas('items', fn ($query) => $query->where('status', AssetRequestItem::STATUS_PENDING))

            ->latest()

            ->get()

            ->filter(fn (AssetRequest $request) => $user->canReviewAssetRequest($request))

            ->values();

    }



    public function create(User $user, array $data): AssetRequest

    {

        if (! isset($data['asset_type_ids']) && isset($data['asset_type_id'])) {

            $data['asset_type_ids'] = [(int) $data['asset_type_id']];

        }



        $assetTypeIds = collect($data['asset_type_ids'] ?? [])

            ->map(fn ($id) => (int) $id)

            ->filter(fn ($id) => $id > 0)

            ->unique()

            ->values()

            ->all();



        if ($assetTypeIds === []) {

            throw ValidationException::withMessages([

                'asset_type_ids' => ['Select at least one asset to request.'],

            ]);

        }



        $request = DB::transaction(function () use ($user, $data, $assetTypeIds) {

            $employee = $this->resolveApplicableEmployee($user, $data['employee_id'] ?? null);

            $assetTypes = collect();



            foreach ($assetTypeIds as $assetTypeId) {

                $assetType = $this->resolveAssetType($employee, $assetTypeId);

                $this->assertCanRequest($employee, $assetType);

                $assetTypes->push($assetType);

            }



            $assetRequest = AssetRequest::create([

                'company_id' => $employee->company_id,

                'employee_id' => $employee->id,

                'applied_by_user_id' => $user->id,

                'reason' => trim($data['reason']),

                'status' => AssetRequest::STATUS_PENDING,

            ]);



            foreach ($assetTypes as $assetType) {

                AssetRequestItem::create([

                    'asset_request_id' => $assetRequest->id,

                    'asset_type_id' => $assetType->id,

                    'status' => AssetRequestItem::STATUS_PENDING,

                ]);

            }



            $this->activityLogService->logWorkflowRequest(

                $user,

                'asset_request',

                $assetRequest,

                (int) $employee->id,

                'submitted',

                'Asset request submitted.',

                null,

                request(),

                ['assets' => $assetTypes->pluck('name')->join(', ')],

            );



            return $assetRequest->fresh(['employee', 'items.assetType', 'items.reviewedBy', 'appliedBy']);

        });



        $this->workflowNotificationService->notifyAssetRequestSubmitted($request, $user);



        return $request;

    }



    public function approve(User $user, AssetRequest $request, ?string $notes = null): AssetRequest

    {

        $request->loadMissing('items');



        $pendingItemIds = $request->items

            ->filter(fn (AssetRequestItem $item) => $item->isPending())

            ->pluck('id')

            ->all();



        if ($pendingItemIds === []) {

            throw ValidationException::withMessages([

                'status' => 'No pending assets remain in this request.',

            ]);

        }



        return $this->reviewItems($user, $request, $pendingItemIds, 'approve', $notes);

    }



    public function reject(User $user, AssetRequest $request, string $notes): AssetRequest

    {

        $request->loadMissing('items');



        $pendingItemIds = $request->items

            ->filter(fn (AssetRequestItem $item) => $item->isPending())

            ->pluck('id')

            ->all();



        if ($pendingItemIds === []) {

            throw ValidationException::withMessages([

                'status' => 'No pending assets remain in this request.',

            ]);

        }



        return $this->reviewItems($user, $request, $pendingItemIds, 'reject', $notes);

    }



    public function approveItem(User $user, AssetRequest $request, AssetRequestItem $item, ?string $notes = null): AssetRequest

    {

        return $this->reviewItems($user, $request, [$item->id], 'approve', $notes);

    }



    public function rejectItem(User $user, AssetRequest $request, AssetRequestItem $item, string $notes): AssetRequest

    {

        return $this->reviewItems($user, $request, [$item->id], 'reject', $notes);

    }



    public function reviewItems(

        User $user,

        AssetRequest $request,

        array $itemIds,

        string $action,

        ?string $notes = null,

    ): AssetRequest {

        if (! in_array($action, ['approve', 'reject'], true)) {

            throw ValidationException::withMessages(['action' => ['Invalid review action.']]);

        }



        if ($action === 'reject' && ! filled(trim((string) $notes))) {

            throw ValidationException::withMessages([

                'notes' => ['Rejection reason is required.'],

            ]);

        }



        $itemIds = collect($itemIds)

            ->map(fn ($id) => (int) $id)

            ->filter(fn ($id) => $id > 0)

            ->unique()

            ->values()

            ->all();



        if ($itemIds === []) {

            throw ValidationException::withMessages([

                'item_ids' => ['Select at least one asset to review.'],

            ]);

        }



        $request->loadMissing(['employee', 'items.assetType']);



        if ($request->status === AssetRequest::STATUS_CANCELLED) {

            throw ValidationException::withMessages([

                'status' => 'Cancelled requests cannot be reviewed.',

            ]);

        }



        $items = $request->items->whereIn('id', $itemIds)->values();



        if ($items->count() !== count($itemIds)) {

            throw ValidationException::withMessages([

                'item_ids' => ['One or more selected assets were not found in this request.'],

            ]);

        }



        foreach ($items as $item) {

            if (! $user->canReviewAssetRequestItem($item)) {

                throw new AccessDeniedHttpException('You are not allowed to review one or more selected assets.');

            }

        }



        DB::transaction(function () use ($user, $request, $items, $action, $notes) {

            foreach ($items as $item) {

                $item->update([

                    'status' => $action === 'approve'

                        ? AssetRequestItem::STATUS_APPROVED

                        : AssetRequestItem::STATUS_REJECTED,

                    'reviewed_by_user_id' => $user->id,

                    'reviewed_at' => now(),

                    'review_notes' => filled(trim((string) $notes)) ? trim((string) $notes) : null,

                ]);



                if ($action === 'approve') {

                    $this->employeeAssetService->assignAssetType(

                        $request->employee,

                        (int) $item->asset_type_id,

                        'Assigned via approved asset request #'.$request->id,

                    );

                }



                $this->activityLogService->logWorkflowRequest(

                    $user,

                    'asset_request_item',

                    $item,

                    (int) $request->employee_id,

                    $action === 'approve' ? 'approved' : 'rejected',

                    sprintf(

                        'Asset request item %s.',

                        $action === 'approve' ? 'approved' : 'rejected',

                    ),

                    filled(trim((string) $notes)) ? trim((string) $notes) : null,

                    request(),

                    ['asset' => $item->assetType?->name],

                );

            }



            $this->syncRequestStatus($request);

        });



        $fresh = $request->fresh(['employee', 'items.assetType', 'items.reviewedBy', 'appliedBy', 'reviewedBy']);



        foreach ($items as $item) {

            $reviewedItem = $fresh->items->firstWhere('id', $item->id);



            if ($reviewedItem) {

                $this->workflowNotificationService->notifyAssetRequestItemDecision(

                    $fresh,

                    $reviewedItem,

                    $user,

                    $action === 'approve' ? 'approved' : 'rejected',

                );

            }

        }



        return $fresh;

    }



    public function cancel(User $user, AssetRequest $request): AssetRequest

    {

        if (! $user->canCancelAssetRequest($request)) {

            throw new AccessDeniedHttpException('You are not allowed to cancel this request.');

        }



        if (! in_array($request->status, [

            AssetRequest::STATUS_PENDING,

            AssetRequest::STATUS_PARTIALLY_REVIEWED,

        ], true)) {

            throw ValidationException::withMessages([

                'status' => 'Only open requests can be cancelled.',

            ]);

        }



        $request->update([

            'status' => AssetRequest::STATUS_CANCELLED,

        ]);



        return $request->fresh(['employee', 'items.assetType', 'items.reviewedBy', 'appliedBy', 'reviewedBy']);

    }



    private function syncRequestStatus(AssetRequest $request): void

    {

        $request->loadMissing('items');



        if ($request->status === AssetRequest::STATUS_CANCELLED) {

            return;

        }



        $statuses = $request->items->pluck('status')->unique()->values();



        if ($statuses->contains(AssetRequestItem::STATUS_PENDING)) {

            $newStatus = AssetRequest::STATUS_PENDING;

        } elseif ($statuses->count() === 1) {

            $newStatus = $statuses->first() === AssetRequestItem::STATUS_APPROVED

                ? AssetRequest::STATUS_APPROVED

                : AssetRequest::STATUS_REJECTED;

        } else {

            $newStatus = AssetRequest::STATUS_PARTIALLY_REVIEWED;

        }



        $latestReview = $request->items

            ->filter(fn (AssetRequestItem $item) => $item->reviewed_at !== null)

            ->sortByDesc('reviewed_at')

            ->first();



        $request->update([

            'status' => $newStatus,

            'reviewed_by_user_id' => $latestReview?->reviewed_by_user_id,

            'reviewed_at' => $latestReview?->reviewed_at,

            'review_notes' => $newStatus === AssetRequest::STATUS_PARTIALLY_REVIEWED

                ? $this->buildPartialReviewSummary($request)

                : $latestReview?->review_notes,

        ]);

    }



    private function buildPartialReviewSummary(AssetRequest $request): string

    {

        $approved = $request->items

            ->filter(fn (AssetRequestItem $item) => $item->status === AssetRequestItem::STATUS_APPROVED)

            ->map(fn (AssetRequestItem $item) => $item->assetType?->name)

            ->filter()

            ->values();



        $rejected = $request->items

            ->filter(fn (AssetRequestItem $item) => $item->status === AssetRequestItem::STATUS_REJECTED)

            ->map(fn (AssetRequestItem $item) => $item->assetType?->name)

            ->filter()

            ->values();



        $parts = [];



        if ($approved->isNotEmpty()) {

            $parts[] = 'Approved: '.$approved->join(', ');

        }



        if ($rejected->isNotEmpty()) {

            $parts[] = 'Rejected: '.$rejected->join(', ');

        }



        return implode(' | ', $parts);

    }



    private function resolveApplicableEmployee(User $user, ?int $employeeId): Employee

    {

        if ($employeeId !== null) {

            if (! $user->hasFullAccess() && ! $user->isHrManager()) {

                throw new AccessDeniedHttpException('You cannot submit asset requests for other employees.');

            }



            return Employee::query()

                ->where('company_id', $user->company_id)

                ->findOrFail($employeeId);

        }



        $employee = $this->employeeAccessService->linkedEmployee($user);



        if (! $employee) {

            throw new AccessDeniedHttpException('No employee profile is linked to your account.');

        }



        return $employee;

    }



    private function resolveAssetType(Employee $employee, int $assetTypeId): AssetType

    {

        $assetType = AssetType::query()

            ->where('company_id', $employee->company_id)

            ->where('id', $assetTypeId)

            ->where('status', 'active')

            ->first();



        if (! $assetType) {

            throw ValidationException::withMessages([

                'asset_type_ids' => ['One or more selected assets are not available.'],

            ]);

        }



        return $assetType;

    }



    private function assertCanRequest(Employee $employee, AssetType $assetType): void

    {

        $alreadyAssigned = EmployeeAsset::query()

            ->where('employee_id', $employee->id)

            ->where('asset_type_id', $assetType->id)

            ->where('is_assigned', true)

            ->exists();



        if ($alreadyAssigned) {

            throw ValidationException::withMessages([

                'asset_type_ids' => ["You already have {$assetType->name} assigned."],

            ]);

        }



        $pending = AssetRequestItem::query()

            ->where('asset_type_id', $assetType->id)

            ->where('status', AssetRequestItem::STATUS_PENDING)

            ->whereHas('assetRequest', fn ($query) => $query

                ->where('employee_id', $employee->id)

                ->where('status', AssetRequest::STATUS_PENDING))

            ->exists();



        if ($pending) {

            throw ValidationException::withMessages([

                'asset_type_ids' => ["You already have a pending request for {$assetType->name}."],

            ]);

        }

    }

}


