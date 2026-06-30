<?php

namespace App\Services;

use App\Models\AttendancePunch;
use App\Models\AttendanceRegularizationRequest;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\PerformanceReview;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AnalyticsReportService
{
    public function __construct(
        private AnalyticsCatalogService $catalogService,
        private ReportsService $reportsService,
        private AttendanceService $attendanceService,
    ) {}

    public function run(User $user, string $reportKey, array $filters = []): array
    {
        if (! $this->catalogService->canAccessReport($user, $reportKey)) {
            throw new AccessDeniedHttpException('You do not have permission to view this analytics report.');
        }

        $definition = $this->catalogService->reportDefinition($reportKey);

        if (! empty($definition['dedicated_route'])) {
            throw ValidationException::withMessages([
                'report' => ['This report uses a dedicated analytics page.'],
            ]);
        }

        $payload = match ($reportKey) {
            'employee-master' => $this->employeeMasterReport($user, $filters),
            'attendance-daily-status' => $this->attendanceDailyStatusReport($user, $filters),
            'attendance-today-status' => $this->attendanceTodayStatusReport($user, $filters),
            'attendance-summary' => $this->attendanceSummaryReport($user, $filters),
            'attendance-clocks-hours' => $this->attendanceClocksHoursReport($user, $filters),
            'regularization-summary' => $this->regularizationSummaryReport($user, $filters),
            'regularization-details' => $this->regularizationDetailsReport($user, $filters),
            'expense-summary' => $this->expenseSummaryReport($user, $filters),
            'candidate-summary' => $this->candidateSummaryReport($user, $filters),
            'review-cycle-summary' => $this->reviewCycleSummaryReport($user, $filters),
            default => throw ValidationException::withMessages(['report' => ['Unknown analytics report.']]),
        };

        return array_merge($payload, [
            'report' => [
                'key' => $definition['key'],
                'name' => $definition['name'],
                'description' => $definition['description'],
                'section_key' => $definition['section_key'] ?? null,
                'export' => $definition['export'] ?? 'csv',
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /** @return array{headings: string[], rows: array<int, array<int|string|null>>, report: array<string, string>, generated_at: string} */
    public function export(User $user, string $reportKey, array $filters = []): array
    {
        $filters['page'] = 1;
        $filters['per_page'] = 100000;

        $payload = $this->run($user, $reportKey, $filters);

        return [
            'headings' => $payload['headings'],
            'rows' => $payload['rows'],
            'report' => $payload['report'],
            'generated_at' => $payload['generated_at'],
        ];
    }

    private function attendanceDailyStatusReport(User $user, array $filters): array
    {
        [$from, $to] = $this->parseDateRange($filters);
        $employees = $this->employeeQuery($user, $filters)->get();

        $headings = [
            'Employee ID', 'Full Name', 'Department', 'Designation', 'Date of Joining',
            'Attendance Date', 'Status', 'First Clock In', 'Last Clock Out', 'Hours Worked',
        ];

        $rows = collect();

        foreach ($employees as $employee) {
            foreach (CarbonPeriod::create($from, $to) as $date) {
                $dateString = $date->toDateString();
                $dayMeta = $this->attendanceService->dayStatusForEmployee($employee, $dateString);

                if (in_array($dayMeta['status'] ?? '', ['before_portal', 'future'], true)) {
                    continue;
                }

                $rows->push([
                    $employee->employee_code,
                    $employee->full_name,
                    $employee->department?->name,
                    $employee->designation,
                    $employee->joining_date?->format('d-m-Y'),
                    $date->format('d-m-Y'),
                    $dayMeta['status_label'] ?? ucfirst(str_replace('_', ' ', (string) ($dayMeta['status'] ?? ''))),
                    $dayMeta['punch_in_label'] ?? null,
                    $dayMeta['punch_out_label'] ?? null,
                    $dayMeta['worked_minutes'] ? $this->formatMinutes((int) $dayMeta['worked_minutes']) : null,
                ]);
            }
        }

        return $this->finishReport($headings, $rows, $filters, 'attendance-daily-status');
    }

    private function attendanceTodayStatusReport(User $user, array $filters): array
    {
        $allowedIds = $this->employeeQuery($user, $filters)->pluck('id')->all();
        $shiftByEmployeeId = Employee::query()
            ->with('shift')
            ->whereIn('id', $allowedIds)
            ->get()
            ->mapWithKeys(fn (Employee $e) => [$e->id => $e->shift?->name]);

        $overview = $this->attendanceService->todayOverview($user);
        $rows = collect($overview['employees'] ?? [])
            ->filter(fn (array $row) => in_array($row['employee_id'] ?? null, $allowedIds, true));

        $headings = [
            'Employee ID', 'Full Name', 'Department', 'Designation', 'Status',
            'First Clock In', 'Last Clock Out', 'Hours Worked', 'Shift',
        ];

        $mapped = $rows->map(fn (array $row) => [
            $row['employee_code'] ?? null,
            $row['employee_name'] ?? null,
            $row['department'] ?? null,
            $row['designation'] ?? null,
            $row['status_label'] ?? null,
            $row['punch_in_label'] ?? null,
            $row['punch_out_label'] ?? null,
            $row['worked_hours_label'] ?? null,
            $shiftByEmployeeId->get($row['employee_id'] ?? 0),
        ]);

        return $this->finishReport($headings, $mapped, $filters, 'attendance-today-status');
    }

    private function attendanceSummaryReport(User $user, array $filters): array
    {
        [$from, $to] = $this->parseDateRange($filters);
        $employees = $this->employeeQuery($user, $filters)->get();

        $headings = [
            'Employee ID', 'Full Name', 'Department', 'Designation', 'Date of Joining',
            'Present', 'Late', 'Leave', 'Weekly Off', 'Holiday', 'Absent', 'Hours Worked',
        ];

        $rows = $employees->map(function (Employee $employee) use ($from, $to) {
            $counts = [
                'present' => 0,
                'late' => 0,
                'on_leave' => 0,
                'weekly_off' => 0,
                'holiday' => 0,
                'absent' => 0,
            ];
            $totalMinutes = 0;

            foreach (CarbonPeriod::create($from, $to) as $date) {
                $dayMeta = $this->attendanceService->dayStatusForEmployee($employee, $date->toDateString());
                $status = (string) ($dayMeta['status'] ?? '');

                if (in_array($status, ['before_portal', 'future'], true)) {
                    continue;
                }

                if ($status === 'half_day' || $status === 'short_leave') {
                    $counts['present']++;
                } elseif (array_key_exists($status, $counts)) {
                    $counts[$status]++;
                } elseif ($status === 'incomplete') {
                    $counts['present']++;
                }

                $totalMinutes += (int) ($dayMeta['worked_minutes'] ?? 0);
            }

            return [
                $employee->employee_code,
                $employee->full_name,
                $employee->department?->name,
                $employee->designation,
                $employee->joining_date?->format('d-m-Y'),
                $counts['present'],
                $counts['late'],
                $counts['on_leave'],
                $counts['weekly_off'],
                $counts['holiday'],
                $counts['absent'],
                $this->formatMinutes($totalMinutes),
            ];
        });

        return $this->finishReport($headings, $rows, $filters, 'attendance-summary');
    }

    private function attendanceClocksHoursReport(User $user, array $filters): array
    {
        [$from, $to] = $this->parseDateRange($filters);
        $employees = $this->employeeQuery($user, $filters)->get();
        $employeeIds = $employees->pluck('id');

        $punches = AttendancePunch::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('punched_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('punched_at')
            ->get()
            ->groupBy(fn (AttendancePunch $p) => $p->employee_id.'|'.$p->punched_at->toDateString());

        $headings = [
            'Employee ID', 'Full Name', 'Department', 'Designation', 'Work Date',
            'First Clock In', 'Last Clock Out', 'First Clock In Source', 'Last Clock Out Source',
            'Hours Worked', 'Status', 'Shift',
        ];

        $rows = collect();

        foreach ($employees as $employee) {
            foreach (CarbonPeriod::create($from, $to) as $date) {
                $dateString = $date->toDateString();
                $dayPunches = $punches->get($employee->id.'|'.$dateString, collect());
                $dayMeta = $this->attendanceService->dayStatusForEmployee($employee, $dateString);

                if ($dayPunches->isEmpty() && in_array($dayMeta['status'] ?? '', ['before_portal', 'future'], true)) {
                    continue;
                }

                $firstIn = $dayPunches->firstWhere('punch_type', AttendancePunch::TYPE_IN);
                $lastOut = $dayPunches->where('punch_type', AttendancePunch::TYPE_OUT)->last();

                $rows->push([
                    $employee->employee_code,
                    $employee->full_name,
                    $employee->department?->name,
                    $employee->designation,
                    $date->format('d-m-Y'),
                    $firstIn?->punched_at?->format('H:i'),
                    $lastOut?->punched_at?->format('H:i'),
                    $firstIn?->source ?? null,
                    $lastOut?->source ?? null,
                    $dayMeta['worked_minutes'] ? $this->formatMinutes((int) $dayMeta['worked_minutes']) : null,
                    $dayMeta['status_label'] ?? null,
                    $employee->shift?->name,
                ]);
            }
        }

        return $this->finishReport($headings, $rows, $filters, 'attendance-clocks-hours');
    }

    private function regularizationSummaryReport(User $user, array $filters): array
    {
        [$from, $to] = $this->parseDateRange($filters, dateField: 'requested_on');

        $query = AttendanceRegularizationRequest::query()
            ->with(['employee.department'])
            ->where('company_id', $user->company_id);

        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        $this->applyRegularizationFilters($query, $filters);

        $grouped = $query->get()->groupBy('employee_id');

        $headings = [
            'Employee ID', 'Full Name', 'Department', 'Designation', 'Date of Joining',
            'Work Email', 'No. of Requests', 'No. of Dates',
        ];

        $rows = $grouped->map(function (Collection $requests) {
            $employee = $requests->first()?->employee;

            return [
                $employee?->employee_code,
                $employee?->full_name,
                $employee?->department?->name,
                $employee?->designation,
                $employee?->joining_date?->format('d-m-Y'),
                $employee?->email,
                $requests->count(),
                $requests->pluck('attendance_date')->map(fn ($d) => $d?->toDateString())->unique()->count(),
            ];
        })->values();

        return $this->finishReport($headings, $rows, $filters, 'regularization-summary');
    }

    private function regularizationDetailsReport(User $user, array $filters): array
    {
        [$from, $to] = $this->parseDateRange($filters, dateField: 'requested_on');

        $query = AttendanceRegularizationRequest::query()
            ->with(['employee', 'appliedBy', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('created_at');

        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        $this->applyRegularizationFilters($query, $filters);

        $headings = [
            'Requested By', 'Employee ID', 'Request Type', 'Requested On', 'Request Status',
            'Requested for Date', 'Existing Clock In', 'Regularised Clock In',
            'Existing Clock Out', 'Regularised Clock Out', 'Actioned By', 'Actioned On', 'Action Note',
        ];

        $rows = $query->get()->map(fn (AttendanceRegularizationRequest $r) => [
            $r->employee?->full_name,
            $r->employee?->employee_code,
            'Attendance',
            $r->created_at?->format('d-m-Y H:i'),
            ucfirst((string) $r->status),
            $r->attendance_date?->format('d-m-Y'),
            $r->original_punch_in?->format('H:i'),
            $r->requested_punch_in?->format('H:i'),
            $r->original_punch_out?->format('H:i'),
            $r->requested_punch_out?->format('H:i'),
            $r->reviewedBy?->name,
            $r->reviewed_at?->format('d-m-Y H:i'),
            $r->review_notes,
        ]);

        return $this->finishReport($headings, $rows, $filters, 'regularization-details');
    }

    private function expenseSummaryReport(User $user, array $filters): array
    {
        [$from, $to] = $this->parseDateRange($filters, required: true);
        $dateType = $filters['date_type'] ?? 'expense_date';
        $dateColumn = $dateType === 'created_on' ? 'created_at' : 'expense_date';

        $query = Expense::query()
            ->with(['employee.department'])
            ->where('company_id', $user->company_id)
            ->where('is_independent', true);

        if ($dateColumn === 'created_at') {
            $query->whereBetween('created_at', [$from, $to]);
        } else {
            $query->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()]);
        }

        if (! empty($filters['status'])) {
            $query->whereHas('employee', fn ($q) => $q->where('status', $filters['status']));
        }

        if (! empty($filters['department_id'])) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $filters['department_id']));
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['employment_type'])) {
            $query->whereHas('employee', fn ($q) => $q->where('employment_type', $filters['employment_type']));
        }

        $headings = [
            'Employee ID', 'Full Name', 'Department', 'Designation', 'Is Reimbursable',
            'Declared Amount', 'Approved Amount', 'Pending Amount', 'Rejected Amount', 'Cancelled Amount',
        ];

        $rows = $query->get()
            ->groupBy(fn (Expense $e) => $e->employee_id.'|'.(int) $e->claim_reimbursement)
            ->map(function (Collection $group) {
                $employee = $group->first()?->employee;
                $isReimbursable = (bool) $group->first()?->claim_reimbursement;

                $declared = $group->sum(fn (Expense $e) => (float) $e->amount);
                $approved = $group->where('status', Expense::STATUS_APPROVED)->sum(fn (Expense $e) => (float) $e->amount);
                $pending = $group->where('status', Expense::STATUS_PENDING)->sum(fn (Expense $e) => (float) $e->amount);
                $rejected = $group->where('status', Expense::STATUS_REJECTED)->sum(fn (Expense $e) => (float) $e->amount);
                $cancelled = $group->where('status', Expense::STATUS_CANCELLED)->sum(fn (Expense $e) => (float) $e->amount);

                return [
                    $employee?->employee_code,
                    $employee?->full_name,
                    $employee?->department?->name,
                    $employee?->designation,
                    $isReimbursable ? 'Yes' : 'No',
                    number_format($declared, 2, '.', ''),
                    number_format($approved, 2, '.', ''),
                    number_format($pending, 2, '.', ''),
                    number_format($rejected, 2, '.', ''),
                    number_format($cancelled, 2, '.', ''),
                ];
            })
            ->values();

        return $this->finishReport($headings, $rows, $filters, 'expense-summary');
    }

    private function candidateSummaryReport(User $user, array $filters): array
    {
        $query = Candidate::query()
            ->with(['job.department'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('applied_at');

        if (! empty($filters['candidate_status'])) {
            $statuses = is_array($filters['candidate_status'])
                ? $filters['candidate_status']
                : [$filters['candidate_status']];
            $query->whereIn('stage', $statuses);
        }

        if (! empty($filters['department_id'])) {
            $departmentId = (int) $filters['department_id'];
            $query->whereHas('job', fn ($q) => $q->where('department_id', $departmentId));
        }

        if (! empty($filters['job_id'])) {
            $query->where('job_id', $filters['job_id']);
        }

        $headings = [
            'Candidate Name', 'Status', 'Job Title', 'Department',
            'Email', 'Phone', 'Source', 'Applied On', 'Created On',
        ];

        $rows = $query->get()->map(fn (Candidate $c) => [
            trim($c->first_name.' '.$c->last_name),
            ucfirst(str_replace('_', ' ', (string) $c->stage)),
            $c->job?->title,
            $c->job?->department?->name ?? null,
            $c->email,
            $c->phone,
            ucfirst(str_replace('_', ' ', (string) $c->source)),
            $c->applied_at?->format('d-m-Y'),
            $c->created_at?->format('d-m-Y'),
        ]);

        return $this->finishReport($headings, $rows, $filters, 'candidate-summary');
    }

    private function reviewCycleSummaryReport(User $user, array $filters): array
    {
        if (empty($filters['cycle_id'])) {
            throw ValidationException::withMessages([
                'cycle_id' => ['Review cycle is required for this report.'],
            ]);
        }

        $query = PerformanceReview::query()
            ->with(['cycle', 'reviewee.department', 'reviewer', 'reviewerUser', 'pair'])
            ->where('cycle_id', $filters['cycle_id'])
            ->whereHas('cycle', fn ($q) => $q->where('company_id', $user->company_id))
            ->orderByDesc('submitted_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $headings = [
            'Review Filled Date', 'Reviewee Name', 'Employee ID', 'Department', 'Designation',
            'Reviewer Name', 'Reviewer Role', 'Status', 'Overall Rating', 'Summary Notes',
        ];

        $rows = $query->get()->map(fn (PerformanceReview $review) => [
            $review->submitted_at?->format('d-m-Y'),
            $review->reviewee?->full_name,
            $review->reviewee?->employee_code,
            $review->reviewee?->department?->name,
            $review->reviewee?->designation,
            $review->reviewer?->full_name ?? $review->reviewerUser?->name,
            $review->pair?->relationship ? ucfirst(str_replace('_', ' ', (string) $review->pair->relationship)) : null,
            ucfirst(str_replace('_', ' ', (string) $review->status)),
            $review->overall_rating,
            $review->summary_notes,
        ]);

        return $this->finishReport($headings, $rows, $filters, 'review-cycle-summary');
    }

    private function employeeMasterReport(User $user, array $filters): array
    {
        $full = $this->reportsService->run($user, 'employees', array_merge($filters, [
            'page' => 1,
            'per_page' => 100000,
        ]));

        return $this->finishReport($full['headings'], collect($full['rows']), $filters, 'employee-master');
    }

    private function employeeQuery(User $user, array $filters)
    {
        if (! $this->attendanceService->canViewAllAttendance($user)) {
            throw new AccessDeniedHttpException('You are not allowed to view company attendance analytics.');
        }

        $query = Employee::query()
            ->with(['department', 'shift'])
            ->where('company_id', $user->company_id)
            ->orderedByName();

        $status = $filters['status'] ?? 'active';

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('id', $filters['employee_id']);
        }

        if (! empty($filters['employment_type'])) {
            $query->where('employment_type', $filters['employment_type']);
        }

        return $query;
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<AttendanceRegularizationRequest>  $query */
    private function applyRegularizationFilters($query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['department_id'])) {
            $departmentId = (int) $filters['department_id'];
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        if (! empty($filters['employment_type'])) {
            $query->whereHas('employee', fn ($q) => $q->where('employment_type', $filters['employment_type']));
        }
    }

    /** @return array{from: Carbon, to: Carbon}|array{null, null} */
    private function parseDateRange(array $filters, bool $required = true, string $dateField = 'attendance_date'): array
    {
        if (empty($filters['from_date']) || empty($filters['to_date'])) {
            if ($required) {
                $label = $dateField === 'requested_on' ? 'Requested On' : 'Date range';

                throw ValidationException::withMessages([
                    'from_date' => ["{$label} start date is required."],
                    'to_date' => ["{$label} end date is required."],
                ]);
            }

            return [null, null];
        }

        $from = Carbon::parse($filters['from_date'])->startOfDay();
        $to = Carbon::parse($filters['to_date'])->endOfDay();

        if ($from->year !== $to->year) {
            throw ValidationException::withMessages([
                'to_date' => ['Date range must be within the same calendar year.'],
            ]);
        }

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'to_date' => ['To date must be on or after from date.'],
            ]);
        }

        return [$from, $to];
    }

    /** @return array{headings: string[], rows: array<int, array<int|string|null>>, pagination: array<string, int|null>, charts: array<string, mixed>} */
    private function finishReport(array $headings, Collection $rows, array $filters, string $reportKey): array
    {
        return array_merge(
            $this->paginateRows($headings, $rows, $filters),
            ['charts' => $this->buildCharts($reportKey, $headings, $rows)],
        );
    }

    /** @return array<string, mixed> */
    private function buildCharts(string $reportKey, array $headings, Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return ['sections' => []];
        }

        $col = fn (string $name) => array_search($name, $headings, true);

        $sections = match ($reportKey) {
            'attendance-daily-status', 'attendance-today-status' => [
                $this->chartSection('Records by Status', $this->countByColumn($rows, $col('Status'))),
                $this->chartSection('Records by Department', $this->countByColumn($rows, $col('Department'))),
            ],
            'attendance-summary' => [
                $this->chartSection('Present Days by Department', $this->sumByColumns($rows, $col('Department'), $col('Present'))),
                $this->chartSection('Absent Days by Department', $this->sumByColumns($rows, $col('Department'), $col('Absent'))),
            ],
            'attendance-clocks-hours' => [
                $this->chartSection('Records by Department', $this->countByColumn($rows, $col('Department'))),
            ],
            'regularization-summary' => [
                $this->chartSection('Requests by Department', $this->sumByColumns($rows, $col('Department'), $col('No. of Requests'))),
                $this->chartSection('Top Employees by Requests', $this->sumByColumns($rows, $col('Full Name'), $col('No. of Requests'), 10)),
            ],
            'regularization-details' => [
                $this->chartSection('Requests by Status', $this->countByColumn($rows, $col('Request Status'))),
            ],
            'expense-summary' => [
                $this->chartSection('Approved Amount by Department', $this->sumByColumns($rows, $col('Department'), $col('Approved Amount'))),
                $this->chartSection('Declared Amount by Department', $this->sumByColumns($rows, $col('Department'), $col('Declared Amount'))),
            ],
            'candidate-summary' => [
                $this->chartSection('Candidates by Status', $this->countByColumn($rows, $col('Status'))),
                $this->chartSection('Candidates by Department', $this->countByColumn($rows, $col('Department'))),
            ],
            'employee-master' => [
                $this->chartSection('Headcount by Department', $this->countByColumn($rows, $col('Department'))),
                $this->chartSection('Headcount by Employment Type', $this->countByColumn($rows, $col('Employment Type'))),
            ],
            'review-cycle-summary' => [
                $this->chartSection('Reviews by Status', $this->countByColumn($rows, $col('Status'))),
                $this->chartSection('Reviews by Department', $this->countByColumn($rows, $col('Department'))),
            ],
            default => [
                $this->chartSection('Summary', $this->countByColumn($rows, $col('Department'))),
            ],
        };

        return ['sections' => array_values(array_filter($sections))];
    }

    /** @return array{title: string, items: array<int, array{label: string, value: float}>}|null */
    private function chartSection(string $title, array $items): ?array
    {
        if ($items === []) {
            return null;
        }

        return [
            'title' => $title,
            'items' => $items,
        ];
    }

    /** @return array<int, array{label: string, value: float}> */
    private function countByColumn(Collection $rows, int|false $groupIdx, ?int $limit = null): array
    {
        if ($groupIdx === false) {
            return [];
        }

        return $this->formatChartItems(
            $rows->groupBy(fn ($row) => (string) ($row[$groupIdx] ?? 'Unknown'))
                ->map(fn (Collection $group, string $label) => [
                    'label' => $label ?: 'Unknown',
                    'value' => (float) $group->count(),
                ])
                ->sortByDesc('value')
                ->values(),
            $limit,
        );
    }

    /** @return array<int, array{label: string, value: float}> */
    private function sumByColumns(Collection $rows, int|false $groupIdx, int|false $valueIdx, ?int $limit = null): array
    {
        if ($groupIdx === false || $valueIdx === false) {
            return [];
        }

        return $this->formatChartItems(
            $rows->groupBy(fn ($row) => (string) ($row[$groupIdx] ?? 'Unknown'))
                ->map(fn (Collection $group, string $label) => [
                    'label' => $label ?: 'Unknown',
                    'value' => round($group->sum(fn ($row) => (float) preg_replace('/[^\d.-]/', '', (string) ($row[$valueIdx] ?? 0))), 2),
                ])
                ->sortByDesc('value')
                ->values(),
            $limit,
        );
    }

    /** @param  Collection<int, array{label: string, value: float|int}>|array<int, array{label: string, value: float|int}>  $items */
    private function formatChartItems(Collection|array $items, ?int $limit = null): array
    {
        $collection = $items instanceof Collection ? $items : collect($items);

        if ($limit) {
            $collection = $collection->take($limit);
        }

        return $collection->map(fn ($item) => [
            'label' => (string) ($item['label'] ?? 'Unknown'),
            'value' => (float) ($item['value'] ?? 0),
        ])->values()->all();
    }

    /** @return array{headings: string[], rows: array<int, array<int|string|null>>, pagination: array<string, int|null>} */
    private function paginateRows(array $headings, Collection $rows, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['per_page'] ?? 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;
        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'headings' => $headings,
            'rows' => $items->all(),
            'pagination' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total ? (($page - 1) * $perPage) + 1 : 0,
                'to' => min($page * $perPage, $total),
            ],
        ];
    }

    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }
}
