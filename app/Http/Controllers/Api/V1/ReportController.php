<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ArrayReportExport;
use App\Exports\PayrollPeriodExport;
use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\PayrollPeriod;
use App\Services\DepartmentService;
use App\Services\LeaveTypeService;
use App\Services\PayrollService;
use App\Services\ProjectService;
use App\Services\ReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ReportsService $reportsService,
        private PayrollService $payrollService,
        private DepartmentService $departmentService,
        private LeaveTypeService $leaveTypeService,
        private ProjectService $projectService,
    ) {}

    public function catalog(Request $request): JsonResponse
    {
        if (! $request->user()->canViewReports()) {
            abort(403);
        }

        return $this->success([
            'reports' => $this->reportsService->catalogForUser($request->user()),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        if (! $request->user()->canViewReports()) {
            abort(403);
        }

        $user = $request->user();
        $companyId = (int) $user->company_id;

        $departments = $this->departmentService->listForCompany($companyId, ['status' => 'active', 'per_page' => 200]);
        $leaveTypes = $user->canViewLeaveAnalytics() || $user->canViewAllLeaveRequests()
            ? $this->leaveTypeService->activeForCompany($companyId)
            : collect();
        $projects = $user->canManageProjects() || $user->canReviewTeamTimesheets()
            ? $this->projectService->listForCompany($companyId, ['per_page' => 200])->items()
            : [];

        return $this->success([
            'departments' => collect($departments->items())->map(fn ($dept) => [
                'id' => $dept->id,
                'name' => $dept->name,
            ])->values(),
            'leave_types' => $leaveTypes->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
            ])->values(),
            'projects' => collect($projects)->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
            ])->values(),
            'payroll_periods' => $this->reportsService->payrollPeriodOptions($user)->map(fn ($period) => [
                'id' => $period->id,
                'label' => $period->label(),
                'status' => $period->status,
            ])->values(),
            'review_cycles' => $this->reportsService->reviewCycleOptions($user)->map(fn ($cycle) => [
                'id' => $cycle->id,
                'name' => $cycle->name,
                'status' => $cycle->status,
            ])->values(),
        ]);
    }

    public function show(Request $request, string $type): JsonResponse
    {
        $validated = $this->validateFilters($request, $type);

        return $this->success(
            $this->reportsService->run($request->user(), $type, $validated)
        );
    }

    public function export(Request $request, string $type): BinaryFileResponse
    {
        $validated = $this->validateFilters($request, $type);
        $user = $request->user();

        if ($type === 'payroll' && ! empty($validated['payroll_period_id'])) {
            $period = PayrollPeriod::query()
                ->where('company_id', $user->company_id)
                ->where('id', $validated['payroll_period_id'])
                ->firstOrFail();

            $payslips = $this->payrollService->listPayslipsForPeriod($period);
            $filename = 'payroll-'.str($period->label())->slug('-').'-'.now()->format('Ymd-His').'.xlsx';

            return Excel::download(new PayrollPeriodExport($period, $payslips), $filename);
        }

        $payload = $this->reportsService->export($user, $type, $validated);
        $slug = str($payload['report']['name'])->slug('-');
        $filename = "{$slug}-".now()->format('Ymd-His').'.xlsx';

        return Excel::download(
            new ArrayReportExport($payload['report']['name'], $payload['headings'], $payload['rows']),
            $filename
        );
    }

    private function validateFilters(Request $request, string $type): array
    {
        return $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'department_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'employment_type' => ['nullable', Rule::in(['full_time', 'part_time', 'contract', 'intern'])],
            'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'],
            'payroll_period_id' => ['nullable', 'integer', 'exists:payroll_periods,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'cycle_id' => ['nullable', 'integer', 'exists:performance_review_cycles,id'],
            'employee_status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'policy_status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'assignment_status' => ['nullable', Rule::in(['active', 'all'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ]);
    }
}
