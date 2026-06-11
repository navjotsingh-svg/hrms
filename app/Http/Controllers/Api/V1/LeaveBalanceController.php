<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\GrantCompOffRequest;
use App\Http\Requests\UpdateLeaveBalanceRequest;
use App\Http\Resources\LeaveBalanceResource;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Services\LeaveBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveBalanceController extends Controller
{
    use ApiResponse;

    public function __construct(private LeaveBalanceService $leaveBalanceService) {}

    public function myBalances(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->success(['balances' => [], 'year' => (int) now()->format('Y')]);
        }

        $year = (int) ($request->query('year') ?: now()->format('Y'));
        $balances = $this->leaveBalanceService->ensureBalancesForEmployee($employee, $year);

        return $this->success([
            'year' => $year,
            'balances' => LeaveBalanceResource::collection($balances),
        ]);
    }

    public function employeeBalances(Request $request, Employee $employee): JsonResponse
    {
        if ((int) $employee->company_id !== (int) $request->user()->company_id) {
            abort(404);
        }

        if (! $request->user()->canManageLeaveBalances()) {
            abort(403);
        }

        $year = (int) ($request->query('year') ?: now()->format('Y'));
        $balances = $this->leaveBalanceService->ensureBalancesForEmployee($employee, $year);

        return $this->success([
            'year' => $year,
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
            ],
            'balances' => LeaveBalanceResource::collection($balances),
        ]);
    }

    public function update(UpdateLeaveBalanceRequest $request, EmployeeLeaveBalance $balance): JsonResponse
    {
        if (! $this->leaveBalanceService->belongsToCompany($balance, (int) $request->user()->company_id)) {
            abort(404);
        }

        if (! $request->user()->canManageLeaveBalances()) {
            abort(403);
        }

        $balance->load('leaveType');
        $data = $request->validated();

        if (! isset($data['used']) && ! isset($data['adjusted'])) {
            abort(422, 'Either used or adjusted value is required.');
        }

        if (isset($data['used'])) {
            $balance = $this->leaveBalanceService->updateUsed($balance, (float) $data['used']);
        }

        if (isset($data['adjusted'])) {
            $balance = $this->leaveBalanceService->setCompOffCredit($balance, (float) $data['adjusted']);
        }

        return $this->success(
            ['balance' => new LeaveBalanceResource($balance)],
            'Leave balance updated successfully.',
        );
    }

    public function grantCompOff(GrantCompOffRequest $request, EmployeeLeaveBalance $balance): JsonResponse
    {
        if (! $this->leaveBalanceService->belongsToCompany($balance, (int) $request->user()->company_id)) {
            abort(404);
        }

        if (! $request->user()->canManageLeaveBalances()) {
            abort(403);
        }

        $balance->load('leaveType');
        $balance = $this->leaveBalanceService->grantCompOff($balance, (float) $request->validated()['days']);

        return $this->success(
            ['balance' => new LeaveBalanceResource($balance)],
            'Comp off granted successfully.',
        );
    }
}
