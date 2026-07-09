<?php



namespace App\Services;



use App\Models\AttendancePunch;

use App\Models\AttendanceRegularizationRequest;

use App\Models\Employee;

use App\Models\EmployeeSalary;

use App\Models\Expense;

use App\Models\LeaveRequest;

use App\Models\Role;

use App\Models\TimesheetEntry;

use App\Models\User;

use App\Models\UserHomeDashboardWidget;

use Carbon\Carbon;

use Carbon\CarbonPeriod;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\DB;

use Illuminate\Validation\ValidationException;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;



class HomeDashboardService

{

    /** @return array<int, array{key: string, label: string}> */

    public function galleryTabs(): array

    {

        return config('home-dashboard.gallery_tabs', []);

    }



    /** @return array<int, array{key: string, label: string}> */

    public function galleryTabsForUser(User $user): array

    {

        $categories = collect($this->availableWidgets($user))

            ->pluck('category')

            ->filter()

            ->unique()

            ->flip();



        return collect($this->galleryTabs())

            ->filter(fn (array $tab) => $categories->has($tab['key'] ?? ''))

            ->values()

            ->all();

    }



    /** @return array<int, string> */

    public function recommendedWidgetKeys(?User $user = null): array

    {

        if ($user) {

            $roleSlug = $user->role?->slug;

            $byRole = config('home-dashboard.recommended_by_role', []);



            if ($roleSlug && ! empty($byRole[$roleSlug])) {

                return collect($byRole[$roleSlug])

                    ->filter(fn (string $key) => in_array($key, $this->allowedWidgetKeys($user), true))

                    ->values()

                    ->all();

            }

        }



        return config('home-dashboard.recommended', []);

    }



    public function __construct(

        private RequestHubService $requestHubService,

        private AnalyticsCatalogService $analyticsCatalogService,

        private AnalyticsReportService $analyticsReportService,

        private LeaveBalanceAnalyticsService $leaveBalanceAnalyticsService,

        private AttendanceService $attendanceService,

        private DateRangePresetService $dateRangePresetService,

        private EmployeeAccessService $employeeAccessService,

    ) {}



    /** @return array<int, array<string, mixed>> */

    public function availableWidgets(User $user): array

    {

        $recommended = $this->recommendedWidgetKeys($user);



        return collect($this->fullWidgetCatalog($user))

            ->filter(fn (array $widget) => $user->hasPermission($widget['permission']))

            ->filter(fn (array $widget) => $this->widgetVisibleToUser($user, $widget))

            ->filter(fn (array $widget) => empty($widget['hidden_from_gallery']))

            ->map(fn (array $widget) => collect($widget)

                ->except(['permission', 'analytics_report_key'])

                ->merge(['recommended' => in_array($widget['key'], $recommended, true)])

                ->all())

            ->values()

            ->all();

    }



    /** @return array<int, array<string, mixed>> */

    public function widgetsForUser(User $user): array

    {

        if (! $user->hasPermission('home.dashboard.view') || ! $user->company_id) {

            return [];

        }



        $saved = UserHomeDashboardWidget::query()

            ->where('user_id', $user->id)

            ->where('company_id', $user->company_id)

            ->orderBy('sort_order')

            ->orderBy('id')

            ->get();



        if ($saved->isEmpty()) {

            return $this->buildWidgetRows($user, $this->defaultWidgetKeys($user));

        }



        $allowedKeys = $this->allowedWidgetKeys($user);



        return $saved

            ->filter(fn (UserHomeDashboardWidget $widget) => in_array($widget->widget_key, $allowedKeys, true))

            ->values()

            ->map(fn (UserHomeDashboardWidget $widget, int $index) => [

                'key' => $widget->widget_key,

                'sort_order' => $widget->sort_order ?: ($index + 1),

                'settings' => $widget->settings ?? [],

                'catalog' => $this->widgetCatalogEntry($user, $widget->widget_key),

            ])

            ->all();

    }



    /** @param  array<int, string>  $widgetKeys */

    public function syncWidgets(User $user, array $widgetKeys): array

    {

        if (! $user->hasPermission('home.dashboard.manage')) {

            throw new AccessDeniedHttpException('You are not allowed to manage dashboard widgets.');

        }



        if (! $user->company_id) {

            throw ValidationException::withMessages([

                'widgets' => ['You must belong to a company to customize dashboard widgets.'],

            ]);

        }



        $allowedKeys = $this->allowedWidgetKeys($user);

        $widgetKeys = array_values(array_unique($widgetKeys));



        foreach ($widgetKeys as $widgetKey) {

            if (! in_array($widgetKey, $allowedKeys, true)) {

                throw ValidationException::withMessages([

                    'widgets' => ["Invalid widget key: {$widgetKey}."],

                ]);

            }

        }



        DB::transaction(function () use ($user, $widgetKeys): void {

            UserHomeDashboardWidget::query()

                ->where('user_id', $user->id)

                ->where('company_id', $user->company_id)

                ->delete();



            foreach ($widgetKeys as $index => $widgetKey) {

                UserHomeDashboardWidget::query()->create([

                    'user_id' => $user->id,

                    'company_id' => $user->company_id,

                    'widget_key' => $widgetKey,

                    'sort_order' => $index + 1,

                ]);

            }

        });



        return $this->widgetsForUser($user);

    }



    /** @param  array<string, mixed>  $rangeInput */

    public function chartData(User $user, string $widgetKey, array $rangeInput = []): array

    {

        if (! $user->hasPermission('home.dashboard.view') || ! $user->company_id) {

            throw new AccessDeniedHttpException('You are not allowed to view dashboard charts.');

        }



        if (! in_array($widgetKey, $this->allowedWidgetKeys($user), true)) {

            throw ValidationException::withMessages([

                'widget_key' => ['Invalid widget key.'],

            ]);

        }



        $range = $this->dateRangePresetService->resolve($rangeInput);

        $filters = $this->dateRangePresetService->toFilterParams($range);



        return match ($widgetKey) {

            'employees_by_status' => $this->employeesByStatus((int) $user->company_id),

            'employees_by_department', 'head_count_by_department' => $this->employeesByDepartment((int) $user->company_id),

            'leave_by_status' => $this->leaveByStatus((int) $user->company_id, $range['from'], $range['to']),

            'attendance_status', 'attendance_today' => $this->attendanceStatus((int) $user->company_id, $range['from'], $range['to']),

            'pending_requests', 'team_pending_approvals' => $this->pendingRequests($user),

            default => $this->resolveGalleryOrAnalyticsWidgetData($user, $widgetKey, $filters, $range),

        };

    }



    /** @param  array<string, mixed>  $rangeInput */

    public function widgetsWithData(User $user, array $rangeInput = []): array

    {

        $range = $this->dateRangePresetService->resolve($rangeInput);



        return collect($this->widgetsForUser($user))

            ->map(function (array $widget) use ($user, $rangeInput, $range) {

                $widget['data'] = $this->chartData($user, $widget['key'], $rangeInput);

                $widget['data']['meta'] = array_merge($widget['data']['meta'] ?? [], [

                    'date_range' => [

                        'preset' => $range['preset'],

                        'from_date' => $range['from_date'],

                        'to_date' => $range['to_date'],

                    ],

                ]);



                return $widget;

            })

            ->all();

    }



    /** @return array<int, array{key: string, label: string}> */

    public function dateRangePresets(): array

    {

        return $this->dateRangePresetService->presets();

    }



    /** @return array<int, string> */

    private function defaultWidgetKeys(User $user): array

    {

        $roleSlug = $user->role?->slug;

        $byRole = config('home-dashboard.default_widgets_by_role', []);



        $defaults = ($roleSlug && ! empty($byRole[$roleSlug]))

            ? $byRole[$roleSlug]

            : config('home-dashboard.default_widgets', [

                'head_count_by_department',

                'gender_breakdown',

                'attendance_status',

            ]);



        return collect($defaults)

            ->filter(fn (string $key) => in_array($key, $this->allowedWidgetKeys($user), true))

            ->values()

            ->all();

    }



    /** @return array<int, string> */

    private function allowedWidgetKeys(User $user): array

    {

        return collect($this->fullWidgetCatalog($user))

            ->filter(fn (array $widget) => $user->hasPermission($widget['permission']))

            ->filter(fn (array $widget) => $this->widgetVisibleToUser($user, $widget))

            ->keys()

            ->all();

    }



    /** @param  array<string, mixed>  $widget */

    private function widgetVisibleToUser(User $user, array $widget): bool

    {

        $roleSlug = $user->role?->slug;



        if (! $roleSlug) {

            return false;

        }



        $audiences = $widget['audiences'] ?? ['company_admin', 'hr_manager', 'department_head'];



        return in_array($roleSlug, $audiences, true);

    }



    /** @return array<string, array<string, mixed>> */

    private function fullWidgetCatalog(User $user): array

    {

        $catalog = collect(config('home-dashboard.widgets', []))

            ->map(fn (array $widget, string $key) => array_merge($widget, ['key' => $key]))

            ->all();



        foreach ($this->analyticsCatalogService->allReportDefinitions() as $report) {

            $reportKey = (string) ($report['key'] ?? '');



            if ($reportKey === '' || ! $this->analyticsCatalogService->canAccessReport($user, $reportKey)) {

                continue;

            }



            $widgetKey = $this->analyticsWidgetKey($reportKey);



            if (isset($catalog[$widgetKey])) {

                continue;

            }



            $catalog[$widgetKey] = [

                'key' => $widgetKey,

                'label' => $report['name'],

                'description' => $report['description'],

                'chart_type' => $this->inferChartType($reportKey),

                'chart_type_label' => $this->inferChartType($reportKey) === 'bar' ? 'Bar Chart' : 'Pie Chart',

                'category' => $report['section_key'] ?? 'analytics',

                'uses_date_range' => $this->reportUsesDateRange($report),

                'analytics_report_key' => $reportKey,

                'permission' => 'home.dashboard.view',

                'hidden_from_gallery' => true,

            ];

        }



        return $catalog;

    }



    /** @return array<string, mixed>|null */

    private function widgetCatalogEntry(User $user, string $widgetKey): ?array

    {

        $entry = $this->fullWidgetCatalog($user)[$widgetKey] ?? null;



        if (! $entry) {

            return null;

        }



        return collect($entry)->except(['permission', 'analytics_report_key'])->all();

    }



    /** @param  array<int, string>  $widgetKeys */

    private function buildWidgetRows(User $user, array $widgetKeys): array

    {

        return collect($widgetKeys)

            ->values()

            ->map(fn (string $widgetKey, int $index) => [

                'key' => $widgetKey,

                'sort_order' => $index + 1,

                'settings' => [],

                'catalog' => $this->widgetCatalogEntry($user, $widgetKey),

            ])

            ->all();

    }



    private function analyticsWidgetKey(string $reportKey): string

    {

        return 'analytics_'.str_replace('-', '_', $reportKey);

    }



    private function reportKeyFromWidgetKey(string $widgetKey): ?string

    {

        if (! str_starts_with($widgetKey, 'analytics_')) {

            return null;

        }



        return str_replace('_', '-', substr($widgetKey, strlen('analytics_')));

    }



    /** @param  array<string, mixed>  $report */

    private function reportUsesDateRange(array $report): bool

    {

        $filters = $report['filters'] ?? [];



        return in_array('from_date', $filters, true) && in_array('to_date', $filters, true);

    }



    private function inferChartType(string $reportKey): string

    {

        return match ($reportKey) {

            'attendance-summary',

            'expense-summary',

            'regularization-summary',

            'attendance-clocks-hours',

            'employee-master' => 'bar',

            default => 'donut',

        };

    }



    /** @param  array<string, mixed>  $filters */

    private function analyticsWidgetData(User $user, string $widgetKey, array $filters): array

    {

        $reportKey = $this->reportKeyFromWidgetKey($widgetKey);



        if (! $reportKey) {

            return ['labels' => [], 'series' => [], 'meta' => ['empty' => true]];

        }



        try {

            if ($reportKey === 'leave-balances') {

                return $this->leaveBalancesWidgetData($user, $filters);

            }

            if ($reportKey === 'attendance-today-status'
                && (($filters['from_date'] ?? null) !== now()->toDateString()
                    || ($filters['to_date'] ?? null) !== now()->toDateString())) {
                $reportKey = 'attendance-daily-status';
            }

            return $this->analyticsReportService->chartDataForWidget($user, $reportKey, $filters);

        } catch (\Throwable $exception) {

            report($exception);



            return [

                'labels' => [],

                'series' => [],

                'meta' => [

                    'empty' => true,

                    'error' => 'Unable to load analytics for this period.',

                ],

            ];

        }

    }



    /** @param  array<string, mixed>  $filters */

    private function leaveBalancesWidgetData(User $user, array $filters): array

    {

        $payload = $this->leaveBalanceAnalyticsService->report($user, array_merge($filters, [

            'page' => 1,

            'per_page' => 100000,

            'allow_cross_year' => true,

        ]));



        $items = collect($payload['charts']['balance_change_by_type'] ?? [])

            ->map(fn (array $row) => [

                'label' => (string) ($row['type_label'] ?? $row['type'] ?? 'Unknown'),

                'value' => (float) ($row['count'] ?? 0),

            ])

            ->all();



        return [

            'labels' => collect($items)->pluck('label')->all(),

            'series' => collect($items)->pluck('value')->map(fn ($value) => (float) $value)->all(),

            'meta' => [

                'chart_title' => 'Balance changes by type',

                'report_key' => 'leave-balances',

            ],

        ];

    }



    private function employeesByStatus(int $companyId): array

    {

        $rows = Employee::query()

            ->where('company_id', $companyId)

            ->selectRaw('status, COUNT(*) as total')

            ->groupBy('status')

            ->orderBy('status')

            ->get();



        return [

            'labels' => $rows->pluck('status')->map(fn ($status) => ucfirst((string) $status))->all(),

            'series' => $rows->pluck('total')->map(fn ($total) => (int) $total)->all(),

        ];

    }



    private function employeesByDepartment(int $companyId): array

    {

        $rows = Employee::query()

            ->where('employees.company_id', $companyId)

            ->where('employees.status', 'active')

            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')

            ->selectRaw("COALESCE(departments.name, 'Unassigned') as label, COUNT(*) as total")

            ->groupBy('label')

            ->orderByDesc('total')

            ->get();



        return [

            'labels' => $rows->pluck('label')->all(),

            'series' => $rows->pluck('total')->map(fn ($total) => (int) $total)->all(),

        ];

    }



    private function leaveByStatus(int $companyId, Carbon $from, Carbon $to): array

    {

        $statuses = [

            LeaveRequest::STATUS_PENDING,

            LeaveRequest::STATUS_APPROVED,

            LeaveRequest::STATUS_REJECTED,

            LeaveRequest::STATUS_CANCELLED,

        ];



        $counts = LeaveRequest::query()

            ->where('company_id', $companyId)

            ->whereDate('from_date', '<=', $to->toDateString())

            ->whereDate('to_date', '>=', $from->toDateString())

            ->selectRaw('status, COUNT(*) as total')

            ->groupBy('status')

            ->pluck('total', 'status');



        return [

            'labels' => collect($statuses)->map(fn ($status) => ucfirst($status))->all(),

            'series' => collect($statuses)->map(fn ($status) => (int) ($counts[$status] ?? 0))->all(),

        ];

    }



    private function attendanceStatus(int $companyId, Carbon $from, Carbon $to): array

    {

        if ($from->toDateString() === $to->toDateString()) {

            return $this->attendanceForSingleDay($companyId, $from);

        }



        return $this->attendanceForPeriod($companyId, $from, $to);

    }



    private function attendanceForSingleDay(int $companyId, Carbon $date): array

    {

        $active = Employee::query()

            ->where('company_id', $companyId)

            ->where('status', 'active')

            ->count();



        $present = AttendancePunch::query()

            ->where('company_id', $companyId)

            ->where('punch_type', AttendancePunch::TYPE_IN)

            ->whereDate('punched_at', $date)

            ->distinct('employee_id')

            ->count('employee_id');



        $onLeave = LeaveRequest::query()

            ->where('company_id', $companyId)

            ->where('status', LeaveRequest::STATUS_APPROVED)

            ->whereDate('from_date', '<=', $date)

            ->whereDate('to_date', '>=', $date)

            ->distinct('employee_id')

            ->count('employee_id');



        $absent = max(0, $active - $present - $onLeave);



        return [

            'labels' => ['Present', 'On Leave', 'Absent'],

            'series' => [$present, $onLeave, $absent],

            'meta' => [

                'active_employees' => $active,

                'single_day' => true,

            ],

        ];

    }



    private function attendanceForPeriod(int $companyId, Carbon $from, Carbon $to): array

    {

        $employees = Employee::query()

            ->where('company_id', $companyId)

            ->where('status', 'active')

            ->get();



        $totals = [

            'present' => 0,

            'on_leave' => 0,

            'absent' => 0,

        ];



        foreach ($employees as $employee) {

            foreach (CarbonPeriod::create($from, $to) as $date) {

                $dayMeta = $this->attendanceService->dayStatusForEmployee($employee, $date->toDateString());

                $status = (string) ($dayMeta['status'] ?? '');



                if (in_array($status, ['before_portal', 'future', 'weekly_off', 'holiday'], true)) {

                    continue;

                }



                if (in_array($status, ['present', 'late', 'half_day', 'short_leave', 'incomplete'], true)) {

                    $totals['present']++;

                } elseif ($status === 'on_leave') {

                    $totals['on_leave']++;

                } elseif ($status === 'absent') {

                    $totals['absent']++;

                }

            }

        }



        return [

            'labels' => ['Present Days', 'Leave Days', 'Absent Days'],

            'series' => [$totals['present'], $totals['on_leave'], $totals['absent']],

            'meta' => [

                'period_total' => true,

            ],

        ];

    }



    private function pendingRequests(User $user): array

    {

        if (! $this->requestHubService->canReviewAny($user)) {

            return [

                'labels' => ['Pending'],

                'series' => [0],

                'meta' => ['can_review' => false],

            ];

        }



        $pending = collect($this->requestHubService->pendingForUser($user));



        $groups = $pending

            ->groupBy(fn (array $item) => (string) ($item['kind'] ?? 'other'))

            ->map(fn (Collection $items) => $items->count())

            ->sortDesc();



        if ($groups->isEmpty()) {

            return [

                'labels' => ['None'],

                'series' => [0],

                'meta' => ['can_review' => true],

            ];

        }



        return [

            'labels' => $groups->keys()->map(fn ($kind) => ucwords(str_replace('_', ' ', $kind)))->all(),

            'series' => $groups->values()->map(fn ($count) => (int) $count)->all(),

            'meta' => [

                'can_review' => true,

                'total' => $pending->count(),

            ],

        ];

    }



    /** @param  array<string, mixed>  $filters */

    /** @param  array{from: Carbon, to: Carbon, preset: string, from_date: string, to_date: string}  $range */

    private function resolveGalleryOrAnalyticsWidgetData(User $user, string $widgetKey, array $filters, array $range): array

    {

        if (array_key_exists($widgetKey, config('home-dashboard.widgets', []))) {

            return $this->galleryChartData($user, $widgetKey, $filters, $range['from'], $range['to']);

        }



        return $this->analyticsWidgetData($user, $widgetKey, $filters);

    }



    /** @param  array<string, mixed>  $filters */

    private function galleryChartData(User $user, string $widgetKey, array $filters, Carbon $from, Carbon $to): array

    {

        $companyId = (int) $user->company_id;



        return match ($widgetKey) {

            'head_count_by_designation' => $this->headCountGrouped($companyId, 'designation', 'Unassigned'),

            'head_count_by_work_location' => $this->headCountGrouped($companyId, 'city', 'Unassigned'),

            'employees_joined_by_month' => $this->employeesJoinedByMonth($companyId, $from, $to, false),

            'employees_joined_by_month_by_gender' => $this->employeesJoinedByMonth($companyId, $from, $to, true),

            'gender_breakdown' => $this->genderBreakdown($companyId),

            'tenure_distribution_by_department' => $this->tenureByDepartment($companyId),

            'tenure_distribution_by_work_location' => $this->tenureByWorkLocation($companyId),

            'employee_length_of_service_distribution' => $this->serviceLengthDistribution($companyId),

            'age_vs_designation_with_tenure' => $this->averageAgeByDesignation($companyId),

            'age_vs_tenure_by_department' => $this->averageAgeByDepartment($companyId),

            'leave_balance_by_department' => $this->leaveBalanceSection($user, $filters, 'balance_change_by_department', 'department', 'balance_change'),

            'leave_balance_by_dept_policy_designation' => $this->leaveBalancePolicyMix($user, $filters),

            'leave_balance_by_employee_policy' => $this->leaveBalanceTopEmployeePolicy($user, $filters),

            'leave_balance_change_by_type_department' => $this->leaveBalanceSection($user, $filters, 'balance_change_by_type', 'type_label', 'count'),

            'hours_worked_by_department' => $this->hoursWorkedGrouped($companyId, $from, $to, 'department'),

            'hours_worked_by_person' => $this->hoursWorkedGrouped($companyId, $from, $to, 'employee'),

            'average_status_by_department' => $this->presentRatioByDepartment($companyId, $from, $to),

            'hours_worked_distribution_by_shift' => $this->hoursWorkedByShift($companyId, $from, $to),

            'hours_worked_by_date_by_person' => $this->hoursWorkedByDate($companyId, $from, $to),

            'hours_worked_trend_by_person' => $this->hoursWorkedGrouped($companyId, $from, $to, 'employee'),

            'hours_worked_trend_by_department' => $this->hoursWorkedGrouped($companyId, $from, $to, 'department'),

            'hours_worked_trend_by_designation' => $this->hoursWorkedGrouped($companyId, $from, $to, 'designation'),

            'regularization_requests_by_employee' => $this->regularizationByEmployee($companyId, $from, $to),

            'regularization_requests_vs_days' => $this->regularizationRequestsVsDays($companyId, $from, $to),

            'regularization_days_by_department' => $this->regularizationDaysGrouped($companyId, $from, $to, 'department'),

            'regularization_days_by_designation' => $this->regularizationDaysGrouped($companyId, $from, $to, 'designation'),

            'total_salary_by_department' => $this->salaryAggregateByDepartment($companyId, 'sum'),

            'average_salary_by_department' => $this->salaryAggregateByDepartment($companyId, 'avg'),

            'max_salary_by_department' => $this->salaryAggregateByDepartment($companyId, 'max'),

            'salary_distribution_by_department' => $this->salaryAggregateByDepartment($companyId, 'avg'),

            'salary_distribution_by_department_gender' => $this->salaryByDepartmentGender($companyId),

            'total_salary_by_work_location' => $this->salaryAggregateByWorkLocation($companyId, 'sum'),

            'average_salary_by_work_location' => $this->salaryAggregateByWorkLocation($companyId, 'avg'),

            'max_salary_by_work_location' => $this->salaryAggregateByWorkLocation($companyId, 'max'),

            'salary_distribution_by_work_location' => $this->salaryAggregateByWorkLocation($companyId, 'avg'),

            'salary_distribution_by_work_location_gender' => $this->salaryByWorkLocationGender($companyId),

            'salary_breakdown_by_department' => $this->salaryAggregateByDepartment($companyId, 'sum'),

            'salary_breakdown_by_work_location' => $this->salaryAggregateByWorkLocation($companyId, 'sum'),

            'ctc_vs_tenure_heatmap' => $this->ctcByTenureBand($companyId),

            'age_vs_ctc_by_department' => $this->salaryAggregateByDepartment($companyId, 'avg'),

            'approved_expenses_by_department' => $this->approvedExpensesByDepartment($companyId, $from, $to),

            'expense_approval_ratio_by_employee' => $this->expenseApprovalRatioByEmployee($companyId, $from, $to),

            'reimbursement_status_analysis' => $this->reimbursementStatusAnalysis($companyId, $from, $to),

            'my_attendance_overview' => $this->scopedAttendanceOverview($user, $from, $to),

            'my_leave_by_status' => $this->scopedLeaveByStatus($user, $from, $to),

            'my_hours_by_date' => $this->scopedHoursWorkedByDate($user, $from, $to),

            'my_expense_by_status' => $this->scopedExpenseByStatus($user, $from, $to),

            'my_regularization_by_status' => $this->scopedRegularizationByStatus($user, $from, $to),

            'team_attendance_overview' => $this->scopedAttendanceOverview($user, $from, $to),

            'team_leave_by_status' => $this->scopedLeaveByStatus($user, $from, $to),

            'team_hours_by_person' => $this->scopedHoursWorkedByPerson($user, $from, $to),

            'team_attendance_trend' => $this->scopedAttendanceTrend($user, $from, $to),

            'team_pending_approvals' => $this->pendingRequests($user),

            'team_regularization_by_employee' => $this->scopedRegularizationByEmployee($user, $from, $to),

            'team_expense_by_status' => $this->scopedExpenseByStatus($user, $from, $to),

            default => ['labels' => [], 'series' => [], 'meta' => ['empty' => true]],

        };

    }



    private function headCountGrouped(int $companyId, string $column, string $fallback): array

    {

        $rows = Employee::query()

            ->where('company_id', $companyId)

            ->where('status', 'active')

            ->selectRaw("COALESCE(NULLIF(TRIM({$column}), ''), ?) as label, COUNT(*) as total", [$fallback])

            ->groupBy('label')

            ->orderByDesc('total')

            ->limit(12)

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function employeesJoinedByMonth(int $companyId, Carbon $from, Carbon $to, bool $includeGender): array

    {

        $query = Employee::query()

            ->where('company_id', $companyId)

            ->whereNotNull('joining_date')

            ->whereBetween('joining_date', [$from->toDateString(), $to->toDateString()]);



        if ($includeGender) {

            $rows = $query

                ->selectRaw("DATE_FORMAT(joining_date, '%b %Y') as month_label, COALESCE(NULLIF(TRIM(gender), ''), 'Not specified') as gender_label, COUNT(*) as total")

                ->groupBy('month_label', 'gender_label')

                ->orderByRaw('MIN(joining_date)')

                ->get()

                ->map(fn ($row) => [

                    'label' => "{$row->month_label} · {$row->gender_label}",

                    'total' => (int) $row->total,

                ]);

        } else {

            $rows = $query

                ->selectRaw("DATE_FORMAT(joining_date, '%b %Y') as label, COUNT(*) as total")

                ->groupBy('label')

                ->orderByRaw('MIN(joining_date)')

                ->get();

        }



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function genderBreakdown(int $companyId): array

    {

        $rows = Employee::query()

            ->where('company_id', $companyId)

            ->where('status', 'active')

            ->selectRaw("COALESCE(NULLIF(TRIM(gender), ''), 'Not specified') as label, COUNT(*) as total")

            ->groupBy('label')

            ->orderByDesc('total')

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function tenureByDepartment(int $companyId): array

    {

        $rows = Employee::query()

            ->where('employees.company_id', $companyId)

            ->where('employees.status', 'active')

            ->whereNotNull('employees.joining_date')

            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')

            ->selectRaw("COALESCE(departments.name, 'Unassigned') as label, ROUND(AVG(DATEDIFF(CURDATE(), employees.joining_date) / 365.25), 1) as total")

            ->groupBy('label')

            ->orderByDesc('total')

            ->limit(12)

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function tenureByWorkLocation(int $companyId): array

    {

        $rows = Employee::query()

            ->where('company_id', $companyId)

            ->where('status', 'active')

            ->whereNotNull('joining_date')

            ->selectRaw("COALESCE(NULLIF(TRIM(city), ''), 'Unassigned') as label, ROUND(AVG(DATEDIFF(CURDATE(), joining_date) / 365.25), 1) as total")

            ->groupBy('label')

            ->orderByDesc('total')

            ->limit(12)

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function serviceLengthDistribution(int $companyId): array

    {

        $employees = Employee::query()

            ->where('company_id', $companyId)

            ->where('status', 'active')

            ->whereNotNull('joining_date')

            ->get(['joining_date']);



        $bands = [

            '0-1 yrs' => 0,

            '1-3 yrs' => 0,

            '3-5 yrs' => 0,

            '5+ yrs' => 0,

        ];



        foreach ($employees as $employee) {

            $years = $employee->joining_date?->diffInYears(now()) ?? 0;

            if ($years < 1) {

                $bands['0-1 yrs']++;

            } elseif ($years < 3) {

                $bands['1-3 yrs']++;

            } elseif ($years < 5) {

                $bands['3-5 yrs']++;

            } else {

                $bands['5+ yrs']++;

            }

        }



        return [

            'labels' => array_keys($bands),

            'series' => array_values($bands),

        ];

    }



    private function averageAgeByDesignation(int $companyId): array

    {

        $rows = Employee::query()

            ->where('company_id', $companyId)

            ->where('status', 'active')

            ->whereNotNull('date_of_birth')

            ->selectRaw("COALESCE(NULLIF(TRIM(designation), ''), 'Unassigned') as label, ROUND(AVG(DATEDIFF(CURDATE(), date_of_birth) / 365.25), 1) as total")

            ->groupBy('label')

            ->orderByDesc('total')

            ->limit(12)

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function averageAgeByDepartment(int $companyId): array

    {

        $rows = Employee::query()

            ->where('employees.company_id', $companyId)

            ->where('employees.status', 'active')

            ->whereNotNull('employees.date_of_birth')

            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')

            ->selectRaw("COALESCE(departments.name, 'Unassigned') as label, ROUND(AVG(DATEDIFF(CURDATE(), employees.date_of_birth) / 365.25), 1) as total")

            ->groupBy('label')

            ->orderByDesc('total')

            ->limit(12)

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    /** @param  array<string, mixed>  $filters */

    private function leaveBalanceSection(User $user, array $filters, string $section, string $labelKey, string $valueKey): array

    {

        $payload = $this->leaveBalanceAnalyticsService->report($user, array_merge($filters, [

            'page' => 1,

            'per_page' => 100000,

            'allow_cross_year' => true,

        ]));



        $items = collect($payload['charts'][$section] ?? []);



        return [

            'labels' => $items->pluck($labelKey)->map(fn ($value) => (string) $value)->take(12)->all(),

            'series' => $items->pluck($valueKey)->map(fn ($value) => (float) $value)->take(12)->all(),

        ];

    }



    /** @param  array<string, mixed>  $filters */

    private function leaveBalancePolicyMix(User $user, array $filters): array

    {

        $payload = $this->leaveBalanceAnalyticsService->report($user, array_merge($filters, [

            'page' => 1,

            'per_page' => 100000,

            'allow_cross_year' => true,

        ]));



        $items = collect($payload['charts']['leaves_taken_by_policy'] ?? []);



        return $this->chartSeriesFromRows($items, 'policy', 'leaves_taken');

    }



    /** @param  array<string, mixed>  $filters */

    private function leaveBalanceTopEmployeePolicy(User $user, array $filters): array

    {

        $payload = $this->leaveBalanceAnalyticsService->report($user, array_merge($filters, [

            'page' => 1,

            'per_page' => 100000,

            'allow_cross_year' => true,

        ]));



        $items = collect($payload['rows'] ?? [])

            ->sortByDesc('balance_change')

            ->take(12)

            ->map(fn (array $row) => [

                'label' => trim(($row['employee_name'] ?? 'Employee').' · '.($row['policy_name'] ?? 'Policy')),

                'total' => abs((float) ($row['balance_change'] ?? 0)),

            ]);



        return $this->chartSeriesFromRows($items, 'label', 'total');

    }



    private function hoursWorkedGrouped(int $companyId, Carbon $from, Carbon $to, string $group): array

    {

        $query = TimesheetEntry::query()

            ->join('employees', 'employees.id', '=', 'timesheet_entries.employee_id')

            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')

            ->leftJoin('shifts', 'shifts.id', '=', 'employees.shift_id')

            ->where('employees.company_id', $companyId)

            ->whereBetween('timesheet_entries.work_date', [$from->toDateString(), $to->toDateString()]);



        $rows = match ($group) {

            'department' => $query

                ->selectRaw("COALESCE(departments.name, 'Unassigned') as label, ROUND(SUM(timesheet_entries.hours), 1) as total")

                ->groupBy('label')

                ->orderByDesc('total')

                ->limit(12)

                ->get(),

            'designation' => $query

                ->selectRaw("COALESCE(NULLIF(TRIM(employees.designation), ''), 'Unassigned') as label, ROUND(SUM(timesheet_entries.hours), 1) as total")

                ->groupBy('label')

                ->orderByDesc('total')

                ->limit(12)

                ->get(),

            default => $query

                ->selectRaw("CONCAT(employees.first_name, ' ', employees.last_name) as label, ROUND(SUM(timesheet_entries.hours), 1) as total")

                ->groupBy('employees.id', 'employees.first_name', 'employees.last_name')

                ->orderByDesc('total')

                ->limit(12)

                ->get(),

        };



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function hoursWorkedByShift(int $companyId, Carbon $from, Carbon $to): array

    {

        $rows = TimesheetEntry::query()

            ->join('employees', 'employees.id', '=', 'timesheet_entries.employee_id')

            ->leftJoin('shifts', 'shifts.id', '=', 'employees.shift_id')

            ->where('employees.company_id', $companyId)

            ->whereBetween('timesheet_entries.work_date', [$from->toDateString(), $to->toDateString()])

            ->selectRaw("COALESCE(shifts.name, 'Unassigned') as label, ROUND(SUM(timesheet_entries.hours), 1) as total")

            ->groupBy('label')

            ->orderByDesc('total')

            ->limit(12)

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function hoursWorkedByDate(int $companyId, Carbon $from, Carbon $to): array

    {

        $rows = TimesheetEntry::query()

            ->join('employees', 'employees.id', '=', 'timesheet_entries.employee_id')

            ->where('employees.company_id', $companyId)

            ->whereBetween('timesheet_entries.work_date', [$from->toDateString(), $to->toDateString()])

            ->selectRaw('timesheet_entries.work_date as label, ROUND(SUM(timesheet_entries.hours), 1) as total')

            ->groupBy('timesheet_entries.work_date')

            ->orderBy('timesheet_entries.work_date')

            ->limit(31)

            ->get()

            ->map(fn ($row) => [

                'label' => Carbon::parse($row->label)->format('d M'),

                'total' => (float) $row->total,

            ]);



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function presentRatioByDepartment(int $companyId, Carbon $from, Carbon $to): array

    {

        $departments = Employee::query()

            ->where('employees.company_id', $companyId)

            ->where('employees.status', 'active')

            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')

            ->selectRaw("COALESCE(departments.name, 'Unassigned') as label, employees.id")

            ->get()

            ->groupBy('label');



        $labels = [];

        $series = [];



        foreach ($departments as $label => $employees) {

            $present = 0;

            $total = 0;



            foreach ($employees as $employee) {

                foreach (CarbonPeriod::create($from, $to) as $date) {

                    $dayMeta = $this->attendanceService->dayStatusForEmployee(

                        Employee::query()->find($employee->id),

                        $date->toDateString()

                    );

                    $status = (string) ($dayMeta['status'] ?? '');

                    if (in_array($status, ['before_portal', 'future', 'weekly_off', 'holiday'], true)) {

                        continue;

                    }

                    $total++;

                    if (in_array($status, ['present', 'late', 'half_day', 'short_leave', 'incomplete'], true)) {

                        $present++;

                    }

                }

            }



            if ($total === 0) {

                continue;

            }



            $labels[] = (string) $label;

            $series[] = round(($present / $total) * 100, 1);

        }



        return ['labels' => $labels, 'series' => $series];

    }



    private function regularizationByEmployee(int $companyId, Carbon $from, Carbon $to): array

    {

        $rows = AttendanceRegularizationRequest::query()

            ->join('employees', 'employees.id', '=', 'attendance_regularization_requests.employee_id')

            ->where('employees.company_id', $companyId)

            ->whereBetween('attendance_regularization_requests.attendance_date', [$from->toDateString(), $to->toDateString()])

            ->selectRaw("CONCAT(employees.first_name, ' ', employees.last_name) as label, COUNT(*) as total")

            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name')

            ->orderByDesc('total')

            ->limit(12)

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function regularizationRequestsVsDays(int $companyId, Carbon $from, Carbon $to): array

    {

        $rows = AttendanceRegularizationRequest::query()

            ->join('employees', 'employees.id', '=', 'attendance_regularization_requests.employee_id')

            ->where('employees.company_id', $companyId)

            ->whereBetween('attendance_regularization_requests.attendance_date', [$from->toDateString(), $to->toDateString()])

            ->selectRaw("CONCAT(employees.first_name, ' ', employees.last_name) as label, COUNT(*) as total")

            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name')

            ->orderByDesc('total')

            ->limit(12)

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function regularizationDaysGrouped(int $companyId, Carbon $from, Carbon $to, string $group): array

    {

        $query = AttendanceRegularizationRequest::query()

            ->join('employees', 'employees.id', '=', 'attendance_regularization_requests.employee_id')

            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')

            ->where('employees.company_id', $companyId)

            ->whereBetween('attendance_regularization_requests.attendance_date', [$from->toDateString(), $to->toDateString()]);



        $rows = $group === 'designation'

            ? $query

                ->selectRaw("COALESCE(NULLIF(TRIM(employees.designation), ''), 'Unassigned') as label, COUNT(*) as total")

                ->groupBy('label')

                ->orderByDesc('total')

                ->limit(12)

                ->get()

            : $query

                ->selectRaw("COALESCE(departments.name, 'Unassigned') as label, COUNT(*) as total")

                ->groupBy('label')

                ->orderByDesc('total')

                ->limit(12)

                ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function latestSalariesQuery(int $companyId)

    {

        $latestIds = EmployeeSalary::query()

            ->selectRaw('MAX(id) as id')

            ->where('company_id', $companyId)

            ->groupBy('employee_id');



        return EmployeeSalary::query()

            ->joinSub($latestIds, 'latest_salaries', fn ($join) => $join->on('employee_salaries.id', '=', 'latest_salaries.id'))

            ->join('employees', 'employees.id', '=', 'employee_salaries.employee_id')

            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')

            ->where('employees.company_id', $companyId)

            ->where('employees.status', 'active');

    }



    private function salaryAggregateByDepartment(int $companyId, string $aggregate): array

    {

        $expression = match ($aggregate) {

            'avg' => 'ROUND(AVG(employee_salaries.annual_ctc), 0)',

            'max' => 'ROUND(MAX(employee_salaries.annual_ctc), 0)',

            default => 'ROUND(SUM(employee_salaries.annual_ctc), 0)',

        };



        $rows = $this->latestSalariesQuery($companyId)

            ->selectRaw("COALESCE(departments.name, 'Unassigned') as label, {$expression} as total")

            ->groupBy('label')

            ->orderByDesc('total')

            ->limit(12)

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function salaryAggregateByWorkLocation(int $companyId, string $aggregate): array

    {

        $expression = match ($aggregate) {

            'avg' => 'ROUND(AVG(employee_salaries.annual_ctc), 0)',

            'max' => 'ROUND(MAX(employee_salaries.annual_ctc), 0)',

            default => 'ROUND(SUM(employee_salaries.annual_ctc), 0)',

        };



        $rows = $this->latestSalariesQuery($companyId)

            ->selectRaw("COALESCE(NULLIF(TRIM(employees.city), ''), 'Unassigned') as label, {$expression} as total")

            ->groupBy('label')

            ->orderByDesc('total')

            ->limit(12)

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function salaryByDepartmentGender(int $companyId): array

    {

        $rows = $this->latestSalariesQuery($companyId)

            ->selectRaw("CONCAT(COALESCE(departments.name, 'Unassigned'), ' · ', COALESCE(NULLIF(TRIM(employees.gender), ''), 'Not specified')) as label, ROUND(AVG(employee_salaries.annual_ctc), 0) as total")

            ->groupBy('label')

            ->orderByDesc('total')

            ->limit(12)

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function salaryByWorkLocationGender(int $companyId): array

    {

        $rows = $this->latestSalariesQuery($companyId)

            ->selectRaw("CONCAT(COALESCE(NULLIF(TRIM(employees.city), ''), 'Unassigned'), ' · ', COALESCE(NULLIF(TRIM(employees.gender), ''), 'Not specified')) as label, ROUND(AVG(employee_salaries.annual_ctc), 0) as total")

            ->groupBy('label')

            ->orderByDesc('total')

            ->limit(12)

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function ctcByTenureBand(int $companyId): array

    {

        $rows = $this->latestSalariesQuery($companyId)

            ->whereNotNull('employees.joining_date')

            ->get(['employee_salaries.annual_ctc', 'employees.joining_date']);



        $bands = [

            '0-1 yrs' => ['sum' => 0, 'count' => 0],

            '1-3 yrs' => ['sum' => 0, 'count' => 0],

            '3-5 yrs' => ['sum' => 0, 'count' => 0],

            '5+ yrs' => ['sum' => 0, 'count' => 0],

        ];



        foreach ($rows as $row) {

            $years = $row->joining_date?->diffInYears(now()) ?? 0;

            $key = match (true) {

                $years < 1 => '0-1 yrs',

                $years < 3 => '1-3 yrs',

                $years < 5 => '3-5 yrs',

                default => '5+ yrs',

            };

            $bands[$key]['sum'] += (float) $row->annual_ctc;

            $bands[$key]['count']++;

        }



        return [

            'labels' => array_keys($bands),

            'series' => collect($bands)->map(fn (array $band) => $band['count'] ? round($band['sum'] / $band['count'], 0) : 0)->all(),

        ];

    }



    private function approvedExpensesByDepartment(int $companyId, Carbon $from, Carbon $to): array

    {

        $rows = Expense::query()

            ->join('employees', 'employees.id', '=', 'expenses.employee_id')

            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')

            ->where('employees.company_id', $companyId)

            ->where('expenses.status', Expense::STATUS_APPROVED)

            ->whereBetween('expenses.expense_date', [$from->toDateString(), $to->toDateString()])

            ->selectRaw("COALESCE(departments.name, 'Unassigned') as label, ROUND(SUM(expenses.amount), 2) as total")

            ->groupBy('label')

            ->orderByDesc('total')

            ->limit(12)

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function expenseApprovalRatioByEmployee(int $companyId, Carbon $from, Carbon $to): array

    {

        $rows = Expense::query()

            ->join('employees', 'employees.id', '=', 'expenses.employee_id')

            ->where('employees.company_id', $companyId)

            ->whereBetween('expenses.expense_date', [$from->toDateString(), $to->toDateString()])

            ->selectRaw("CONCAT(employees.first_name, ' ', employees.last_name) as label, ROUND(SUM(CASE WHEN expenses.status = ? THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) * 100, 1) as total", [Expense::STATUS_APPROVED])

            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name')

            ->orderByDesc('total')

            ->limit(12)

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function reimbursementStatusAnalysis(int $companyId, Carbon $from, Carbon $to): array

    {

        $rows = Expense::query()

            ->join('employees', 'employees.id', '=', 'expenses.employee_id')

            ->where('employees.company_id', $companyId)

            ->whereBetween('expenses.expense_date', [$from->toDateString(), $to->toDateString()])

            ->selectRaw('expenses.payout_status as label, COUNT(*) as total')

            ->groupBy('expenses.payout_status')

            ->orderByDesc('total')

            ->get()

            ->map(fn ($row) => [

                'label' => ucfirst(str_replace('_', ' ', (string) $row->label)),

                'total' => (int) $row->total,

            ]);



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    /** @return \Illuminate\Database\Eloquent\Builder<Employee> */

    private function scopedEmployeesQuery(User $user)

    {

        $query = Employee::query()

            ->where('company_id', (int) $user->company_id)

            ->where('status', 'active');



        $scopeIds = $this->scopedEmployeeIds($user);



        if ($scopeIds !== null) {

            if ($scopeIds === []) {

                return $query->whereRaw('1 = 0');

            }



            $query->whereIn('id', $scopeIds);

        }



        return $query;

    }



    /** @return array<int>|null */

    private function scopedEmployeeIds(User $user): ?array

    {

        if ($user->hasRole(Role::SLUG_EMPLOYEE)) {

            $employee = $this->employeeAccessService->linkedEmployee($user);



            return $employee ? [(int) $employee->id] : [];

        }



        if ($user->isTeamLead() || $user->isDepartmentHead()) {

            return $this->employeeAccessService->teamScopeEmployeeIds($user);

        }



        return null;

    }



    private function scopedAttendanceOverview(User $user, Carbon $from, Carbon $to): array

    {

        $employees = $this->scopedEmployeesQuery($user)->get();



        if ($employees->isEmpty()) {

            return [

                'labels' => ['No data'],

                'series' => [0],

                'meta' => ['empty' => true],

            ];

        }



        if ($from->toDateString() === $to->toDateString() && $employees->count() === 1) {

            $dayMeta = $this->attendanceService->dayStatusForEmployee($employees->first(), $from->toDateString());

            $status = (string) ($dayMeta['status'] ?? '');

            $present = in_array($status, ['present', 'late', 'half_day', 'short_leave', 'incomplete'], true) ? 1 : 0;

            $onLeave = $status === 'on_leave' ? 1 : 0;

            $absent = $status === 'absent' ? 1 : 0;



            return [

                'labels' => ['Present', 'On Leave', 'Absent'],

                'series' => [$present, $onLeave, $absent],

                'meta' => ['single_day' => true],

            ];

        }



        $totals = ['present' => 0, 'on_leave' => 0, 'absent' => 0];



        foreach ($employees as $employee) {

            foreach (CarbonPeriod::create($from, $to) as $date) {

                $dayMeta = $this->attendanceService->dayStatusForEmployee($employee, $date->toDateString());

                $status = (string) ($dayMeta['status'] ?? '');



                if (in_array($status, ['before_portal', 'future', 'weekly_off', 'holiday'], true)) {

                    continue;

                }



                if (in_array($status, ['present', 'late', 'half_day', 'short_leave', 'incomplete'], true)) {

                    $totals['present']++;

                } elseif ($status === 'on_leave') {

                    $totals['on_leave']++;

                } elseif ($status === 'absent') {

                    $totals['absent']++;

                }

            }

        }



        return [

            'labels' => ['Present Days', 'Leave Days', 'Absent Days'],

            'series' => [$totals['present'], $totals['on_leave'], $totals['absent']],

            'meta' => ['period_total' => true],

        ];

    }



    private function scopedLeaveByStatus(User $user, Carbon $from, Carbon $to): array

    {

        $statuses = [

            LeaveRequest::STATUS_PENDING,

            LeaveRequest::STATUS_APPROVED,

            LeaveRequest::STATUS_REJECTED,

            LeaveRequest::STATUS_CANCELLED,

        ];



        $query = LeaveRequest::query()

            ->where('company_id', (int) $user->company_id)

            ->whereDate('from_date', '<=', $to->toDateString())

            ->whereDate('to_date', '>=', $from->toDateString());



        $scopeIds = $this->scopedEmployeeIds($user);



        if ($scopeIds !== null) {

            if ($scopeIds === []) {

                return [

                    'labels' => collect($statuses)->map(fn ($status) => ucfirst($status))->all(),

                    'series' => array_fill(0, count($statuses), 0),

                    'meta' => ['empty' => true],

                ];

            }



            $query->whereIn('employee_id', $scopeIds);

        }



        $counts = $query

            ->selectRaw('status, COUNT(*) as total')

            ->groupBy('status')

            ->pluck('total', 'status');



        return [

            'labels' => collect($statuses)->map(fn ($status) => ucfirst($status))->all(),

            'series' => collect($statuses)->map(fn ($status) => (int) ($counts[$status] ?? 0))->all(),

        ];

    }



    private function scopedHoursWorkedByDate(User $user, Carbon $from, Carbon $to): array

    {

        $query = TimesheetEntry::query()

            ->join('employees', 'employees.id', '=', 'timesheet_entries.employee_id')

            ->where('employees.company_id', (int) $user->company_id)

            ->whereBetween('timesheet_entries.work_date', [$from->toDateString(), $to->toDateString()]);



        $scopeIds = $this->scopedEmployeeIds($user);



        if ($scopeIds !== null) {

            if ($scopeIds === []) {

                return ['labels' => [], 'series' => [], 'meta' => ['empty' => true]];

            }



            $query->whereIn('employees.id', $scopeIds);

        }



        $rows = $query

            ->selectRaw('timesheet_entries.work_date as label, ROUND(SUM(timesheet_entries.hours), 1) as total')

            ->groupBy('timesheet_entries.work_date')

            ->orderBy('timesheet_entries.work_date')

            ->limit(31)

            ->get()

            ->map(fn ($row) => [

                'label' => Carbon::parse($row->label)->format('d M'),

                'total' => (float) $row->total,

            ]);



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function scopedHoursWorkedByPerson(User $user, Carbon $from, Carbon $to): array

    {

        $query = TimesheetEntry::query()

            ->join('employees', 'employees.id', '=', 'timesheet_entries.employee_id')

            ->where('employees.company_id', (int) $user->company_id)

            ->whereBetween('timesheet_entries.work_date', [$from->toDateString(), $to->toDateString()]);



        $scopeIds = $this->scopedEmployeeIds($user);



        if ($scopeIds !== null) {

            if ($scopeIds === []) {

                return ['labels' => [], 'series' => [], 'meta' => ['empty' => true]];

            }



            $query->whereIn('employees.id', $scopeIds);

        }



        $rows = $query

            ->selectRaw("CONCAT(employees.first_name, ' ', employees.last_name) as label, ROUND(SUM(timesheet_entries.hours), 1) as total")

            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name')

            ->orderByDesc('total')

            ->limit(12)

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function scopedAttendanceTrend(User $user, Carbon $from, Carbon $to): array

    {

        $employees = $this->scopedEmployeesQuery($user)->get();



        if ($employees->isEmpty()) {

            return ['labels' => [], 'series' => [], 'meta' => ['empty' => true]];

        }



        $labels = [];

        $series = [];



        foreach (CarbonPeriod::create($from, $to) as $date) {

            $present = 0;



            foreach ($employees as $employee) {

                $dayMeta = $this->attendanceService->dayStatusForEmployee($employee, $date->toDateString());

                $status = (string) ($dayMeta['status'] ?? '');



                if (in_array($status, ['present', 'late', 'half_day', 'short_leave', 'incomplete'], true)) {

                    $present++;

                }

            }



            $labels[] = $date->format('d M');

            $series[] = $present;

        }



        return [

            'labels' => $labels,

            'series' => $series,

            'meta' => ['line' => true],

        ];

    }



    private function scopedExpenseByStatus(User $user, Carbon $from, Carbon $to): array

    {

        $query = Expense::query()

            ->join('employees', 'employees.id', '=', 'expenses.employee_id')

            ->where('employees.company_id', (int) $user->company_id)

            ->whereBetween('expenses.expense_date', [$from->toDateString(), $to->toDateString()]);



        $scopeIds = $this->scopedEmployeeIds($user);



        if ($scopeIds !== null) {

            if ($scopeIds === []) {

                return ['labels' => [], 'series' => [], 'meta' => ['empty' => true]];

            }



            $query->whereIn('employees.id', $scopeIds);

        }



        $rows = $query

            ->selectRaw('expenses.status as label, COUNT(*) as total')

            ->groupBy('expenses.status')

            ->orderByDesc('total')

            ->get()

            ->map(fn ($row) => [

                'label' => ucfirst(str_replace('_', ' ', (string) $row->label)),

                'total' => (int) $row->total,

            ]);



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function scopedRegularizationByStatus(User $user, Carbon $from, Carbon $to): array

    {

        $query = AttendanceRegularizationRequest::query()

            ->join('employees', 'employees.id', '=', 'attendance_regularization_requests.employee_id')

            ->where('employees.company_id', (int) $user->company_id)

            ->whereBetween('attendance_regularization_requests.attendance_date', [$from->toDateString(), $to->toDateString()]);



        $scopeIds = $this->scopedEmployeeIds($user);



        if ($scopeIds !== null) {

            if ($scopeIds === []) {

                return ['labels' => [], 'series' => [], 'meta' => ['empty' => true]];

            }



            $query->whereIn('employees.id', $scopeIds);

        }



        $rows = $query

            ->selectRaw('attendance_regularization_requests.status as label, COUNT(*) as total')

            ->groupBy('attendance_regularization_requests.status')

            ->orderByDesc('total')

            ->get()

            ->map(fn ($row) => [

                'label' => ucfirst(str_replace('_', ' ', (string) $row->label)),

                'total' => (int) $row->total,

            ]);



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function scopedRegularizationByEmployee(User $user, Carbon $from, Carbon $to): array

    {

        $query = AttendanceRegularizationRequest::query()

            ->join('employees', 'employees.id', '=', 'attendance_regularization_requests.employee_id')

            ->where('employees.company_id', (int) $user->company_id)

            ->whereBetween('attendance_regularization_requests.attendance_date', [$from->toDateString(), $to->toDateString()]);



        $scopeIds = $this->scopedEmployeeIds($user);



        if ($scopeIds !== null) {

            if ($scopeIds === []) {

                return ['labels' => [], 'series' => [], 'meta' => ['empty' => true]];

            }



            $query->whereIn('employees.id', $scopeIds);

        }



        $rows = $query

            ->selectRaw("CONCAT(employees.first_name, ' ', employees.last_name) as label, COUNT(*) as total")

            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name')

            ->orderByDesc('total')

            ->limit(12)

            ->get();



        return $this->chartSeriesFromRows($rows, 'label', 'total');

    }



    private function chartSeriesFromRows(Collection|iterable $rows, string $labelKey, string $valueKey): array

    {

        $collection = $rows instanceof Collection ? $rows : collect($rows);



        return [

            'labels' => $collection->pluck($labelKey)->map(fn ($value) => (string) $value)->all(),

            'series' => $collection->pluck($valueKey)->map(fn ($value) => is_numeric($value) ? (float) $value : 0)->all(),

        ];

    }

}


