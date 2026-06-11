<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\StoreLeaveTypeRequest;
use App\Http\Requests\UpdateLeaveTypeRequest;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveType;
use App\Services\LeaveRequestService;
use App\Services\LeaveTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveTypeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private LeaveTypeService $leaveTypeService,
        private LeaveRequestService $leaveRequestService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $types = $this->leaveTypeService->listForCompany($request->user()->company_id, $validated);

        return $this->success([
            'leave_types' => LeaveTypeResource::collection($types->items()),
            'pagination' => [
                'current_page' => $types->currentPage(),
                'last_page' => $types->lastPage(),
                'per_page' => $types->perPage(),
                'total' => $types->total(),
                'from' => $types->firstItem(),
                'to' => $types->lastItem(),
            ],
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $types = $this->leaveTypeService->activeForCompany($request->user()->company_id);
        $employee = $request->user()->employee;
        $year = (int) ($validated['year'] ?? now()->format('Y'));
        $month = (int) ($validated['month'] ?? now()->format('n'));

        $leaveTypes = $types->map(function ($type) use ($request, $employee, $year, $month) {
            $data = (new LeaveTypeResource($type))->resolve($request);

            if ($employee) {
                if ($type->isHourlyLeave()) {
                    $data['monthly_used'] = $this->leaveRequestService->hoursUsedInMonth($employee, $type->id, $year, $month);
                    $data['monthly_remaining'] = $type->max_hours_per_month === null
                        ? null
                        : max(0, round((float) $type->max_hours_per_month - $data['monthly_used'], 2));
                    $data['monthly_limit_unit'] = 'hours';
                } else {
                    $data['monthly_used'] = $this->leaveRequestService->daysUsedInMonth($employee, $type->id, $year, $month);
                    $data['monthly_remaining'] = $type->max_days_per_month === null
                        ? null
                        : max(0, round((float) $type->max_days_per_month - $data['monthly_used'], 1));
                    $data['monthly_limit_unit'] = 'days';
                }
            }

            return $data;
        });

        return $this->success([
            'leave_types' => $leaveTypes,
        ]);
    }

    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        $type = $this->leaveTypeService->create($request->user()->company_id, $request->validated());

        return $this->success(['leave_type' => new LeaveTypeResource($type)], 'Leave type created successfully.', 201);
    }

    public function show(Request $request, LeaveType $leaveType): JsonResponse
    {
        $this->ensureCompanyType($request, $leaveType);

        return $this->success(['leave_type' => new LeaveTypeResource($leaveType)]);
    }

    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType): JsonResponse
    {
        $this->ensureCompanyType($request, $leaveType);
        $type = $this->leaveTypeService->update($leaveType, $request->validated());

        return $this->success(['leave_type' => new LeaveTypeResource($type)], 'Leave type updated successfully.');
    }

    public function destroy(Request $request, LeaveType $leaveType): JsonResponse
    {
        $this->ensureCompanyType($request, $leaveType);
        $this->leaveTypeService->delete($leaveType);

        return $this->success(null, 'Leave type deleted successfully.');
    }

    private function ensureCompanyType(Request $request, LeaveType $leaveType): void
    {
        if (! $this->leaveTypeService->belongsToCompany($leaveType, $request->user()->company_id)) {
            abort(404);
        }
    }
}
