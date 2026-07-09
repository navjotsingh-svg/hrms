<?php



namespace App\Services;



use App\Models\AttendancePunch;

use App\Models\Employee;

use App\Models\LeaveRequest;

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

    private const DEFAULT_WIDGET_KEYS = [

        'employees_by_department',

        'attendance_status',

        'leave_by_status',

        'analytics_attendance_summary',

        'analytics_expense_summary',

        'pending_requests',

    ];



    /** @var array<string, array<string, mixed>> */

    private const CORE_WIDGET_CATALOG = [

        'employees_by_status' => [

            'key' => 'employees_by_status',

            'label' => 'Employees by Status',

            'description' => 'Active and inactive employee headcount',

            'chart_type' => 'donut',

            'category' => 'people',

            'uses_date_range' => false,

            'permission' => 'home.dashboard.view',

        ],

        'employees_by_department' => [

            'key' => 'employees_by_department',

            'label' => 'Employees by Department',

            'description' => 'Headcount grouped by department',

            'chart_type' => 'bar',

            'category' => 'people',

            'uses_date_range' => false,

            'permission' => 'home.dashboard.view',

        ],

        'leave_by_status' => [

            'key' => 'leave_by_status',

            'label' => 'Leave by Status',

            'description' => 'Leave requests overlapping the selected period',

            'chart_type' => 'donut',

            'category' => 'leave',

            'uses_date_range' => true,

            'permission' => 'home.dashboard.view',

        ],

        'attendance_status' => [

            'key' => 'attendance_status',

            'label' => 'Attendance Overview',

            'description' => 'Present, on leave, and absent counts for the selected period',

            'chart_type' => 'donut',

            'category' => 'attendance',

            'uses_date_range' => true,

            'permission' => 'home.dashboard.view',

        ],

        'pending_requests' => [

            'key' => 'pending_requests',

            'label' => 'Pending Requests',

            'description' => 'Requests awaiting your review',

            'chart_type' => 'bar',

            'category' => 'requests',

            'uses_date_range' => false,

            'permission' => 'home.dashboard.view',

        ],

    ];



    public function __construct(

        private RequestHubService $requestHubService,

        private AnalyticsCatalogService $analyticsCatalogService,

        private AnalyticsReportService $analyticsReportService,

        private LeaveBalanceAnalyticsService $leaveBalanceAnalyticsService,

        private AttendanceService $attendanceService,

        private DateRangePresetService $dateRangePresetService,

    ) {}



    /** @return array<int, array<string, mixed>> */

    public function availableWidgets(User $user): array

    {

        return collect($this->fullWidgetCatalog($user))

            ->filter(fn (array $widget) => $user->hasPermission($widget['permission']))

            ->map(fn (array $widget) => collect($widget)->except(['permission', 'analytics_report_key'])->all())

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

            'employees_by_department' => $this->employeesByDepartment((int) $user->company_id),

            'leave_by_status' => $this->leaveByStatus((int) $user->company_id, $range['from'], $range['to']),

            'attendance_status', 'attendance_today' => $this->attendanceStatus((int) $user->company_id, $range['from'], $range['to']),

            'pending_requests' => $this->pendingRequests($user),

            default => $this->analyticsWidgetData($user, $widgetKey, $filters),

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

        return collect(self::DEFAULT_WIDGET_KEYS)

            ->filter(fn (string $key) => in_array($key, $this->allowedWidgetKeys($user), true))

            ->values()

            ->all();

    }



    /** @return array<int, string> */

    private function allowedWidgetKeys(User $user): array

    {

        return collect($this->fullWidgetCatalog($user))

            ->filter(fn (array $widget) => $user->hasPermission($widget['permission']))

            ->keys()

            ->all();

    }



    /** @return array<string, array<string, mixed>> */

    private function fullWidgetCatalog(User $user): array

    {

        $catalog = self::CORE_WIDGET_CATALOG;



        foreach ($this->analyticsCatalogService->allReportDefinitions() as $report) {

            $reportKey = (string) ($report['key'] ?? '');



            if ($reportKey === '' || ! $this->analyticsCatalogService->canAccessReport($user, $reportKey)) {

                continue;

            }



            $widgetKey = $this->analyticsWidgetKey($reportKey);

            $catalog[$widgetKey] = [

                'key' => $widgetKey,

                'label' => $report['name'],

                'description' => $report['description'],

                'chart_type' => $this->inferChartType($reportKey),

                'category' => $report['section_key'] ?? 'analytics',

                'uses_date_range' => $this->reportUsesDateRange($report),

                'analytics_report_key' => $reportKey,

                'permission' => 'home.dashboard.view',

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

}


