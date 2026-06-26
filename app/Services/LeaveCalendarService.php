<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class LeaveCalendarService
{
    public function __construct(private AttendancePolicyService $attendancePolicyService) {}

    public function calendarForUser(User $user, int $year, int $month, bool $includeHolidays = true): array
    {
        if (! $user->canViewAllLeaveRequests()) {
            throw new AccessDeniedHttpException('You do not have permission to view the leave calendar.');
        }

        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

        $requests = LeaveRequest::query()
            ->with(['employee', 'leaveType', 'days'])
            ->where('company_id', $user->company_id)
            ->whereNot('status', LeaveRequest::STATUS_CANCELLED)
            ->whereDate('from_date', '<=', $gridEnd->toDateString())
            ->whereDate('to_date', '>=', $gridStart->toDateString())
            ->orderBy('from_date')
            ->get();

        $entries = $requests->map(function (LeaveRequest $request) {
            $employee = $request->employee;
            $color = $request->leaveType?->color ?: '#7c3aed';

            return [
                'id' => $request->id,
                'employee_id' => $request->employee_id,
                'employee_name' => $employee?->full_name ?? 'Employee',
                'employee_code' => $employee?->employee_code,
                'label' => $this->entryLabel($employee?->full_name, $employee?->employee_code),
                'leave_type' => $request->leaveType?->name,
                'leave_type_id' => $request->leave_type_id,
                'color' => $color,
                'status' => $request->status,
                'status_label' => ucfirst($request->status),
                'from_date' => $request->from_date?->format('Y-m-d'),
                'to_date' => $request->to_date?->format('Y-m-d'),
                'total_days' => $request->total_days,
            ];
        })->values()->all();

        $leaveTypes = LeaveType::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'color'])
            ->map(fn (LeaveType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'code' => $type->code,
                'color' => $type->color ?: '#64748b',
            ])
            ->values()
            ->all();

        $holidays = [];

        if ($includeHolidays) {
            $holidayByDate = $this->attendancePolicyService->holidaysForRange(
                $user->company_id,
                $gridStart,
                $gridEnd
            );

            $seen = [];
            $holidays = [];

            foreach ($holidayByDate as $dateString => $holiday) {
                if (isset($seen[$holiday->id])) {
                    continue;
                }

                $seen[$holiday->id] = true;

                if ($holiday->isFixed()) {
                    [$resolvedFrom, $resolvedTo] = $holiday->resolvedBoundsForYear($year);
                    $clipFrom = $resolvedFrom->greaterThan($gridStart) ? $resolvedFrom : $gridStart->copy();
                    $clipTo = $resolvedTo->lessThan($gridEnd) ? $resolvedTo : $gridEnd->copy();
                } else {
                    $clipFrom = $holiday->from_date->greaterThan($gridStart) ? $holiday->from_date : $gridStart->copy();
                    $clipTo = $holiday->to_date->lessThan($gridEnd) ? $holiday->to_date : $gridEnd->copy();
                }

                $holidays[] = [
                    'id' => $holiday->id,
                    'name' => $holiday->name,
                    'from_date' => $clipFrom->format('Y-m-d'),
                    'to_date' => $clipTo->format('Y-m-d'),
                    'date' => $clipFrom->format('Y-m-d'),
                    'date_label' => $holiday->displayDateLabel(),
                    'type' => $holiday->type,
                    'frequency' => $holiday->frequency,
                ];
            }

            usort($holidays, fn ($a, $b) => strcmp($a['from_date'], $b['from_date']));
        }

        $days = [];
        foreach (CarbonPeriod::create($gridStart, $gridEnd) as $date) {
            $dateString = $date->format('Y-m-d');
            $days[] = [
                'date' => $dateString,
                'day' => (int) $date->format('j'),
                'is_current_month' => $date->month === $month,
                'is_today' => $date->isToday(),
                'weekday' => (int) $date->dayOfWeekIso,
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'month_label' => $monthStart->format('F Y'),
            'today' => now()->format('Y-m-d'),
            'grid_start' => $gridStart->format('Y-m-d'),
            'grid_end' => $gridEnd->format('Y-m-d'),
            'days' => $days,
            'entries' => $entries,
            'leave_types' => $leaveTypes,
            'holidays' => $holidays,
            'summary' => [
                'employees_on_leave' => collect($entries)->pluck('employee_id')->unique()->count(),
                'leave_requests' => count($entries),
                'approved' => collect($entries)->where('status', LeaveRequest::STATUS_APPROVED)->count(),
                'pending' => collect($entries)->where('status', LeaveRequest::STATUS_PENDING)->count(),
                'rejected' => collect($entries)->where('status', LeaveRequest::STATUS_REJECTED)->count(),
            ],
        ];
    }

    private function entryLabel(?string $name, ?string $code): string
    {
        $name = trim($name ?? '') ?: 'Employee';

        if (! $code) {
            return $name;
        }

        return "{$name} ({$code})";
    }
}
