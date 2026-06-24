<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ArrayReportExport;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\JobPosting;
use App\Services\AnalyticsCatalogService;
use App\Services\AnalyticsReportService;
use App\Services\DepartmentService;
use App\Services\LeaveTypeService;
use App\Services\ReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AnalyticsCatalogService $catalogService,
        private AnalyticsReportService $reportService,
        private ReportsService $reportsService,
        private DepartmentService $departmentService,
        private LeaveTypeService $leaveTypeService,
    ) {}

    public function catalog(Request $request): JsonResponse
    {
        return $this->success([
            'sections' => $this->catalogService->sectionsForUser($request->user()),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;

        $departments = $this->departmentService->listForCompany($companyId, ['status' => 'active', 'per_page' => 200]);
        $leaveTypes = $user->canViewLeaveAnalytics()
            ? $this->leaveTypeService->activeForCompany($companyId)
            : collect();

        $jobs = $user->canManageHiring()
            ? JobPosting::query()
                ->where('company_id', $companyId)
                ->orderByDesc('created_at')
                ->limit(200)
                ->get(['id', 'title'])
            : collect();

        return $this->success([
            'departments' => collect($departments->items())->map(fn ($dept) => [
                'id' => $dept->id,
                'name' => $dept->name,
            ])->values(),
            'leave_types' => $leaveTypes->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
            ])->values(),
            'review_cycles' => $this->reportsService->reviewCycleOptions($user)->map(fn ($cycle) => [
                'id' => $cycle->id,
                'name' => $cycle->name,
                'status' => $cycle->status,
            ])->values(),
            'jobs' => $jobs->map(fn (JobPosting $job) => [
                'id' => $job->id,
                'title' => $job->title,
            ])->values(),
            'candidate_stages' => [
                Candidate::STAGE_APPLIED,
                Candidate::STAGE_SCREENING,
                Candidate::STAGE_INTERVIEW,
                Candidate::STAGE_OFFER,
                Candidate::STAGE_HIRED,
                Candidate::STAGE_REJECTED,
            ],
        ]);
    }

    public function show(Request $request, string $reportKey): JsonResponse
    {
        $validated = $this->validateFilters($request, $reportKey);

        return $this->success(
            $this->reportService->run($request->user(), $reportKey, $validated)
        );
    }

    public function export(Request $request, string $reportKey): BinaryFileResponse|StreamedResponse
    {
        $validated = $this->validateFilters($request, $reportKey);
        $payload = $this->reportService->export($request->user(), $reportKey, $validated);
        $slug = str($payload['report']['name'])->slug('-');
        $extension = ($payload['report']['export'] ?? 'csv') === 'excel' ? 'xlsx' : 'csv';
        $filename = "{$slug}-".now()->format('Ymd-His').".{$extension}";

        if ($extension === 'xlsx') {
            return Excel::download(
                new ArrayReportExport($payload['report']['name'], $payload['headings'], $payload['rows']),
                $filename
            );
        }

        return response()->streamDownload(function () use ($payload) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $payload['headings']);

            foreach ($payload['rows'] as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function validateFilters(Request $request, string $reportKey): array
    {
        if (! $this->catalogService->canAccessReport($request->user(), $reportKey)) {
            abort(403);
        }

        return $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'department_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'employment_type' => ['nullable', Rule::in(['full_time', 'part_time', 'contract', 'intern'])],
            'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'],
            'cycle_id' => ['nullable', 'integer', 'exists:performance_review_cycles,id'],
            'employee_status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'policy_status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'assignment_status' => ['nullable', Rule::in(['active', 'all'])],
            'date_type' => ['nullable', Rule::in(['expense_date', 'created_on'])],
            'candidate_status' => ['nullable', 'string', 'max:50'],
            'job_id' => ['nullable', 'integer', 'exists:job_postings,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ]);
    }
}
