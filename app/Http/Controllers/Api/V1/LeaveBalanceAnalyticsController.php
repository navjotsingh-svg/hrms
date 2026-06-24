<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\LeaveBalanceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveBalanceAnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(private LeaveBalanceAnalyticsService $analyticsService) {}

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->canViewLeaveAnalytics()) {
            abort(403);
        }

        $validated = $this->validatedFilters($request);
        $payload = $this->analyticsService->report($request->user(), $validated);

        return $this->success($payload);
    }

    public function detail(Request $request, Employee $employee, LeaveType $leaveType): JsonResponse
    {
        if (! $request->user()->canViewLeaveAnalytics()) {
            abort(403);
        }

        $validated = $this->validatedFilters($request, requireDates: true);
        $payload = $this->analyticsService->detailTimeline(
            $request->user(),
            $employee,
            $leaveType,
            $validated,
        );

        return $this->success($payload);
    }

    public function export(Request $request): StreamedResponse
    {
        if (! $request->user()->canViewLeaveAnalytics()) {
            abort(403);
        }

        $validated = $this->validatedFilters($request, requireDates: true);
        $rows = $this->analyticsService->exportRows($request->user(), $validated);
        $from = $validated['from_date'];
        $to = $validated['to_date'];
        $filename = "leave-balances-report-{$from}-to-{$to}.csv";

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Employee ID',
                'Full Name',
                'Department',
                'Designation',
                'Date of Joining',
                'Policy Name',
                'From Balance',
                'Initial Balance',
                'Accrued Leaves',
                'Manual Reset Leaves',
                'Expiration Changes',
                'Carry Forward Changes',
                'Leaves Taken',
                'To Balance',
                'Balance Change',
                'Balance Change Type',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['employee_code'],
                    $row['employee_name'],
                    $row['department'],
                    $row['designation'],
                    $row['joining_date_label'],
                    $row['policy_name'],
                    $row['is_unlimited'] ? 'Unlimited' : number_format((float) $row['from_balance'], 2, '.', ''),
                    number_format((float) $row['initial_balance'], 2, '.', ''),
                    number_format((float) $row['accrued_leaves'], 2, '.', ''),
                    number_format((float) $row['manual_reset_leaves'], 2, '.', ''),
                    number_format((float) $row['expiration_changes'], 2, '.', ''),
                    number_format((float) $row['carry_forward_changes'], 2, '.', ''),
                    number_format((float) $row['leaves_taken'], 2, '.', ''),
                    $row['is_unlimited'] ? 'Unlimited' : number_format((float) $row['to_balance'], 2, '.', ''),
                    $row['is_unlimited'] ? '—' : number_format((float) $row['balance_change'], 2, '.', ''),
                    $this->changeTypeLabel($row['balance_change_type']),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function validatedFilters(Request $request, bool $requireDates = false): array
    {
        return $request->validate([
            'from_date' => [$requireDates ? 'required' : 'nullable', 'date'],
            'to_date' => [$requireDates ? 'required' : 'nullable', 'date', 'after_or_equal:from_date'],
            'employee_status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'employment_type' => ['nullable', Rule::in(['all', 'full_time', 'part_time', 'contract', 'intern'])],
            'policy_status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'assignment_status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'department_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'leave_type_id' => ['nullable', 'integer'],
            'designation' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ]);
    }

    private function changeTypeLabel(string $type): string
    {
        return match ($type) {
            'increase' => 'Increase',
            'decrease' => 'Decrease',
            'no_change' => 'No Change',
            'unlimited' => 'Unlimited',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
