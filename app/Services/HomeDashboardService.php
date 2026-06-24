<?php

namespace App\Services;

use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\UserHomeDashboardWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class HomeDashboardService
{
    private const DEFAULT_WIDGET_KEYS = [
        'employees_by_status',
        'employees_by_department',
        'leave_by_status',
        'attendance_today',
        'pending_requests',
    ];

    /** @var array<string, array<string, mixed>> */
    private const WIDGET_CATALOG = [
        'employees_by_status' => [
            'key' => 'employees_by_status',
            'label' => 'Employees by Status',
            'description' => 'Active and inactive employee headcount',
            'chart_type' => 'donut',
            'permission' => 'home.dashboard.view',
        ],
        'employees_by_department' => [
            'key' => 'employees_by_department',
            'label' => 'Employees by Department',
            'description' => 'Headcount grouped by department',
            'chart_type' => 'bar',
            'permission' => 'home.dashboard.view',
        ],
        'leave_by_status' => [
            'key' => 'leave_by_status',
            'label' => 'Leave by Status',
            'description' => 'Leave requests grouped by status',
            'chart_type' => 'donut',
            'permission' => 'home.dashboard.view',
        ],
        'attendance_today' => [
            'key' => 'attendance_today',
            'label' => 'Attendance Today',
            'description' => 'Present, on leave, and absent counts for today',
            'chart_type' => 'donut',
            'permission' => 'home.dashboard.view',
        ],
        'pending_requests' => [
            'key' => 'pending_requests',
            'label' => 'Pending Requests',
            'description' => 'Requests awaiting your review',
            'chart_type' => 'bar',
            'permission' => 'home.dashboard.view',
        ],
    ];

    public function __construct(private RequestHubService $requestHubService) {}

    /** @return array<int, array<string, mixed>> */
    public function availableWidgets(User $user): array
    {
        return collect(self::WIDGET_CATALOG)
            ->filter(fn (array $widget) => $user->hasPermission($widget['permission']))
            ->map(fn (array $widget) => collect($widget)->except('permission')->all())
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
                'catalog' => self::WIDGET_CATALOG[$widget->widget_key] ?? null,
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

    public function chartData(User $user, string $widgetKey): array
    {
        if (! $user->hasPermission('home.dashboard.view') || ! $user->company_id) {
            throw new AccessDeniedHttpException('You are not allowed to view dashboard charts.');
        }

        if (! in_array($widgetKey, $this->allowedWidgetKeys($user), true)) {
            throw ValidationException::withMessages([
                'widget_key' => ['Invalid widget key.'],
            ]);
        }

        return match ($widgetKey) {
            'employees_by_status' => $this->employeesByStatus((int) $user->company_id),
            'employees_by_department' => $this->employeesByDepartment((int) $user->company_id),
            'leave_by_status' => $this->leaveByStatus((int) $user->company_id),
            'attendance_today' => $this->attendanceToday((int) $user->company_id),
            'pending_requests' => $this->pendingRequests($user),
            default => ['labels' => [], 'series' => []],
        };
    }

    /** @return array<int, array<string, mixed>> */
    public function widgetsWithData(User $user): array
    {
        return collect($this->widgetsForUser($user))
            ->map(function (array $widget) use ($user) {
                $widget['data'] = $this->chartData($user, $widget['key']);

                return $widget;
            })
            ->all();
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
        return collect(self::WIDGET_CATALOG)
            ->filter(fn (array $widget) => $user->hasPermission($widget['permission']))
            ->keys()
            ->all();
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
                'catalog' => collect(self::WIDGET_CATALOG[$widgetKey] ?? [])->except('permission')->all(),
            ])
            ->all();
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

    private function leaveByStatus(int $companyId): array
    {
        $statuses = [
            LeaveRequest::STATUS_PENDING,
            LeaveRequest::STATUS_APPROVED,
            LeaveRequest::STATUS_REJECTED,
            LeaveRequest::STATUS_CANCELLED,
        ];

        $counts = LeaveRequest::query()
            ->where('company_id', $companyId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'labels' => collect($statuses)->map(fn ($status) => ucfirst($status))->all(),
            'series' => collect($statuses)->map(fn ($status) => (int) ($counts[$status] ?? 0))->all(),
        ];
    }

    private function attendanceToday(int $companyId): array
    {
        $active = Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->count();

        $present = AttendancePunch::query()
            ->where('company_id', $companyId)
            ->where('punch_type', AttendancePunch::TYPE_IN)
            ->whereDate('punched_at', today())
            ->distinct('employee_id')
            ->count('employee_id');

        $onLeave = LeaveRequest::query()
            ->where('company_id', $companyId)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('from_date', '<=', today())
            ->whereDate('to_date', '>=', today())
            ->distinct('employee_id')
            ->count('employee_id');

        $absent = max(0, $active - $present - $onLeave);

        return [
            'labels' => ['Present', 'On Leave', 'Absent'],
            'series' => [$present, $onLeave, $absent],
            'meta' => [
                'active_employees' => $active,
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
