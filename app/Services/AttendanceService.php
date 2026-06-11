<?php

namespace App\Services;

use App\Models\AttendancePunch;
use App\Models\AttendanceRegularizationRequest;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequestDay;
use App\Models\Shift;
use App\Models\User;
use App\Models\WeeklyOffDay;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AttendanceService
{
    public function __construct(
        private ImageCompressor $imageCompressor,
        private AttendancePolicyService $attendancePolicyService,
        private PortalStartService $portalStartService,
        private GeocodingService $geocodingService,
        private LeaveRequestService $leaveRequestService,
    ) {}

    public function canMarkAttendance(User $user): bool
    {
        return $user->employee
            && ! $user->isSuperAdmin()
            && ! $user->isCompanyAdmin();
    }

    public function canViewAllAttendance(User $user): bool
    {
        return $user->isCompanyAdmin()
            || $user->isHrManager()
            || $user->hasPermission('attendance.manage');
    }

    public function canViewTeamAttendance(User $user): bool
    {
        return $user->employee
            && Employee::query()
                ->where('company_id', $user->company_id)
                ->where('manager_id', $user->employee->id)
                ->exists();
    }

    public function teamEmployeesForUser(User $user): array
    {
        if (! $user->employee) {
            return [];
        }

        return Employee::query()
            ->where('company_id', $user->company_id)
            ->where('manager_id', $user->employee->id)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
            ])
            ->values()
            ->all();
    }

    public function resolveViewableEmployee(User $user, ?int $employeeId = null): Employee
    {
        if ($employeeId === null) {
            $employee = $user->employee;

            if (! $employee) {
                throw new NotFoundHttpException('No employee profile is linked to your account.');
            }

            return $employee->load('shift');
        }

        $employee = Employee::query()
            ->where('company_id', $user->company_id)
            ->whereKey($employeeId)
            ->with('shift')
            ->first();

        if (! $employee) {
            throw new NotFoundHttpException('Employee not found.');
        }

        if ($this->canViewAllAttendance($user)) {
            return $employee;
        }

        $isSelf = $user->employee && (int) $user->employee->id === (int) $employee->id;
        $isDirectManager = $user->employee && (int) $employee->manager_id === (int) $user->employee->id;

        if (! $isSelf && ! $isDirectManager) {
            throw new AccessDeniedHttpException('You are not allowed to view attendance for other employees.');
        }

        return $employee;
    }

    public function todayStatus(User $user): array
    {
        $employee = $user->employee;

        if (! $employee) {
            return [
                'can_mark' => false,
                'next_punch_type' => null,
                'today_punches' => [],
                'today_worked_minutes' => 0,
                'required_minutes' => 0,
                'is_complete' => false,
            ];
        }

        $employee->loadMissing('shift');
        $today = now()->toDateString();
        $punches = $this->punchesForDate($employee, $today);
        $dayMeta = $this->resolveDayMeta(
            $employee,
            $today,
            $punches,
            $this->workedMinutesForPunches($punches, $this->shouldIncludeOpenSession($punches, true)),
            $this->requiredMinutesForEmployee($employee),
            false,
            null,
            null,
            true,
        );

        return [
            'can_mark' => $this->canMarkAttendance($user) && $dayMeta['can_mark'],
            'next_punch_type' => $dayMeta['can_mark'] ? $this->nextPunchType($punches) : null,
            'today_punches' => $punches->map(fn (AttendancePunch $punch) => $this->formatPunch($punch))->values()->all(),
            'today_worked_minutes' => $dayMeta['worked_minutes'],
            'required_minutes' => $dayMeta['required_minutes'],
            'is_complete' => $dayMeta['status'] === 'present',
            'status' => $dayMeta['status'],
            'status_label' => $dayMeta['status_label'],
            'punch_in_label' => $dayMeta['punch_in_label'],
            'punch_out_label' => $dayMeta['punch_out_label'],
            'day_message' => $dayMeta['day_message'],
            'awaiting_punch_out' => (bool) ($dayMeta['awaiting_punch_out'] ?? false),
        ];
    }

    public function punch(User $user, UploadedFile $selfie, float $latitude, float $longitude, ?string $locationName = null): array
    {
        if (! $this->canMarkAttendance($user)) {
            throw new AccessDeniedHttpException('You are not allowed to mark attendance.');
        }

        $employee = $user->employee->loadMissing('shift');
        $today = now()->toDateString();
        $todayPunches = $this->punchesForDate($employee, $today);
        $dayMeta = $this->resolveDayMeta(
            $employee,
            $today,
            $todayPunches,
            $this->workedMinutesForPunches($todayPunches, $this->shouldIncludeOpenSession($todayPunches, true)),
            $this->requiredMinutesForEmployee($employee),
            false,
            null,
            null,
            true,
        );

        if (! $dayMeta['can_mark']) {
            throw ValidationException::withMessages([
                'punch' => [$dayMeta['day_message'] ?? 'Attendance cannot be marked today.'],
            ]);
        }

        $punches = $this->punchesForDate($employee, $today);
        $punchType = $this->nextPunchType($punches);

        if (! $punchType) {
            throw ValidationException::withMessages([
                'punch' => ['Unable to determine the next punch action.'],
            ]);
        }

        $relativeDirectory = AttendancePunch::PUBLIC_UPLOAD_DIR."/{$employee->company_id}/{$employee->id}";
        $selfiePath = $this->imageCompressor->compressAndSave(
            $selfie,
            public_path($relativeDirectory),
            $relativeDirectory,
            480,
            70,
            true,
        );

        $locationName = trim((string) $locationName);
        $locationName = $locationName !== ''
            ? $locationName
            : $this->geocodingService->reverseGeocode($latitude, $longitude);

        $punch = AttendancePunch::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'punch_type' => $punchType,
            'punched_at' => now(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'location_name' => $locationName,
            'selfie_path' => $selfiePath,
        ]);

        $updatedPunches = $this->punchesForDate($employee, $today);

        return [
            'punch' => $this->formatPunch($punch),
            'next_punch_type' => $this->nextPunchType($updatedPunches),
            'today_worked_minutes' => $this->workedMinutesForPunches(
                $updatedPunches,
                $this->shouldIncludeOpenSession($updatedPunches, true),
            ),
            'required_minutes' => $this->requiredMinutesForEmployee($employee),
            'is_complete' => $this->isDayComplete($employee, $updatedPunches, true),
        ];
    }

    public function calendar(User $user, string $month, ?int $employeeId = null): array
    {
        $employee = $this->resolveViewableEmployee($user, $employeeId);

        return $this->calendarForEmployee($employee, $month);
    }

    public function calendarForEmployee(Employee $employee, string $month): array
    {
        $employee->loadMissing('shift');
        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $start = $monthDate->copy()->startOfMonth();
        $end = $monthDate->copy()->endOfMonth();
        $today = now()->toDateString();

        $punchesByDate = AttendancePunch::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('punched_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy('punched_at')
            ->get()
            ->groupBy(fn (AttendancePunch $punch) => $punch->punched_at->toDateString());

        $requiredMinutes = $this->requiredMinutesForEmployee($employee);
        $weeklyOffWeekdays = $this->attendancePolicyService->weeklyOffWeekdays($employee->company_id);
        $holidays = $this->attendancePolicyService->holidaysForRange($employee->company_id, $start, $end);
        $pendingRegularizations = AttendanceRegularizationRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', AttendanceRegularizationRequest::STATUS_PENDING)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (AttendanceRegularizationRequest $request) => $request->attendance_date->toDateString());
        $approvedLeaveDays = $this->leaveRequestService->approvedLeaveDaysForRange(
            $employee,
            $start->toDateString(),
            $end->toDateString(),
        );
        $days = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateString = $date->toDateString();
            $dayPunches = $punchesByDate->get($dateString, collect());
            $isFuture = $dateString > $today;
            $isToday = $dateString === $today;
            $workedMinutes = $dayPunches->isEmpty()
                ? 0
                : $this->workedMinutesForPunches($dayPunches, $this->shouldIncludeOpenSession($dayPunches, $isToday));
            $dayMeta = $this->resolveDayMeta(
                $employee,
                $dateString,
                $dayPunches,
                $workedMinutes,
                $requiredMinutes,
                $isFuture,
                $holidays->get($dateString),
                $weeklyOffWeekdays,
                $isToday,
                $approvedLeaveDays->get($dateString),
            );

            $status = $dayMeta['status'];
            $statusLabel = $dayMeta['status_label'];

            if ($pendingRegularizations->has($dateString)) {
                $status = 'regularization_pending';
                $statusLabel = 'Regularization Pending';
            }

            $days[] = [
                'date' => $dateString,
                'day' => (int) $date->format('j'),
                'weekday' => $date->format('D'),
                'status' => $status,
                'status_label' => $statusLabel,
                'holiday_name' => $dayMeta['holiday_name'],
                'worked_minutes' => $dayMeta['worked_minutes'],
                'worked_hours_label' => $this->formatMinutes($dayMeta['worked_minutes']),
                'required_minutes' => $requiredMinutes,
                'required_hours_label' => $this->formatMinutes($requiredMinutes),
                'punch_count' => $dayPunches->count(),
                'punch_in_label' => $dayMeta['punch_in_label'],
                'punch_out_label' => $dayMeta['punch_out_label'],
                'punch_entries' => $dayPunches
                    ->map(fn (AttendancePunch $punch) => $this->formatCalendarPunchEntry($punch))
                    ->values()
                    ->all(),
                'is_today' => $isToday,
                'is_future' => $isFuture,
                'regularization_request_id' => $pendingRegularizations->get($dateString)?->id,
                'leave_type_name' => $dayMeta['leave_type_name'] ?? null,
                'leave_session_label' => $dayMeta['leave_session_label'] ?? null,
                'leave_request_id' => $dayMeta['leave_request_id'] ?? null,
                'leave_approved_by_name' => $dayMeta['leave_approved_by_name'] ?? null,
                'leave_approved_at_label' => $dayMeta['leave_approved_at_label'] ?? null,
                'awaiting_punch_out' => (bool) ($dayMeta['awaiting_punch_out'] ?? false),
            ];
        }

        return [
            'month' => $month,
            'month_label' => $monthDate->format('F Y'),
            'employee' => $this->formatEmployee($employee),
            'shift' => $this->formatShift($employee->shift),
            'required_minutes' => $requiredMinutes,
            'required_hours_label' => $this->formatMinutes($requiredMinutes),
            'days' => $days,
            'portal_start_date' => $this->portalStartService->portalStartDate($employee->company_id),
            'employee_portal_access_date' => $this->portalStartService->effectivePortalAccessDate($employee),
            'employee_attendance_start_date' => $this->portalStartService->attendanceTrackingStartDate($employee),
            'summary' => [
                'present_days' => collect($days)->where('status', 'present')->count(),
                'half_day_days' => collect($days)->where('status', 'half_day')->count(),
                'short_leave_days' => collect($days)->where('status', 'short_leave')->count(),
                'absent_days' => collect($days)->where('status', 'absent')->count(),
                'weekly_off_days' => collect($days)->where('status', 'weekly_off')->count(),
                'holiday_days' => collect($days)->where('status', 'holiday')->count(),
                'on_leave_days' => collect($days)->where('status', 'on_leave')->count(),
            ],
            'weekly_off_labels' => array_map(
                fn (int $weekday) => WeeklyOffDay::label($weekday),
                $weeklyOffWeekdays,
            ),
            'month_holidays' => $holidays->values()->map(fn (Holiday $holiday) => [
                'name' => $holiday->name,
                'date' => $holiday->date->toDateString(),
                'date_label' => $holiday->date->format('d M'),
            ])->all(),
        ];
    }

    public function payrollAttendanceMetrics(Employee $employee, int $year, int $month): array
    {
        $monthKey = sprintf('%04d-%02d', $year, $month);
        $calendar = $this->calendarForEmployee($employee, $monthKey);
        $monthStart = Carbon::create($year, $month, 1);
        $daysInMonth = $monthStart->daysInMonth;
        $periodStart = $monthStart->toDateString();
        $periodEnd = $monthStart->copy()->endOfMonth()->toDateString();

        // For the current month, only count days up to yesterday. Today is
        // still in progress (status can change until the day ends) and future
        // days are neither payable nor LOP; the salary stays prorated by the
        // monthly daily rate (gross / days in month).
        $today = now()->toDateString();

        if ($periodEnd >= $today) {
            $periodEnd = now()->subDay()->toDateString();
        }

        if ($employee->joining_date) {
            $joiningDate = $employee->joining_date->toDateString();

            if ($joiningDate > $periodEnd) {
                return [
                    'month_days' => $daysInMonth,
                    'payable_days' => 0.0,
                    'lop_days' => 0.0,
                    'paid_days' => 0.0,
                ];
            }

            if ($joiningDate > $periodStart) {
                $periodStart = $joiningDate;
            }
        }

        if ($periodEnd < $periodStart) {
            return [
                'month_days' => $daysInMonth,
                'payable_days' => 0.0,
                'lop_days' => 0.0,
                'paid_days' => 0.0,
            ];
        }

        $payableDays = (float) (Carbon::parse($periodStart)->diffInDays(Carbon::parse($periodEnd)) + 1);
        $lopDays = 0.0;

        foreach ($calendar['days'] as $day) {
            $date = $day['date'];

            if ($date < $periodStart || $date > $periodEnd) {
                continue;
            }

            if (in_array($day['status'], ['before_portal', 'future', 'weekly_off', 'holiday'], true)) {
                continue;
            }

            $lopDays += $this->payrollLopUnitsForDay($employee, $day);
        }

        $lopDays = round($lopDays, 1);
        $paidDays = round(max($payableDays - $lopDays, 0), 1);

        return [
            'month_days' => $daysInMonth,
            'payable_days' => round($payableDays, 1),
            'lop_days' => $lopDays,
            'paid_days' => $paidDays,
        ];
    }

    private function payrollLopUnitsForDay(Employee $employee, array $day): float
    {
        $status = $day['status'];

        if ($status === 'regularization_pending') {
            return 1.0;
        }

        $leaveDay = $this->leaveRequestService->approvedLeaveDayOnDate($employee, $day['date']);

        if ($leaveDay) {
            return $this->leaveDayPayrollLop($leaveDay, $employee);
        }

        $legacyRequest = $this->leaveRequestService->approvedLeaveRequestOnDate($employee, $day['date']);

        if ($legacyRequest) {
            return ($legacyRequest->leaveType?->is_paid ?? true) ? 0.0 : 1.0;
        }

        return match ($status) {
            'absent' => 1.0,
            'half_day' => 0.5,
            'short_leave' => $this->shortLeaveAttendanceLop($employee, $day),
            'present', 'incomplete', 'on_leave' => 0.0,
            default => 0.0,
        };
    }

    private function leaveDayPayrollLop(LeaveRequestDay $leaveDay, Employee $employee): float
    {
        $leaveType = $leaveDay->leaveRequest?->leaveType;

        if ($leaveType?->is_paid) {
            return 0.0;
        }

        if ($leaveDay->session === LeaveRequestDay::SESSION_HOURLY || $leaveType?->isHourlyLeave()) {
            $dayValue = (float) $leaveDay->day_value;

            if ($dayValue > 0) {
                return $dayValue;
            }

            if ($leaveDay->duration_minutes) {
                return $this->hourlyLeaveDayValue($employee, (int) $leaveDay->duration_minutes);
            }

            return 0.0;
        }

        if (in_array($leaveDay->session, [
            LeaveRequestDay::SESSION_FIRST_HALF,
            LeaveRequestDay::SESSION_SECOND_HALF,
        ], true)) {
            return (float) ($leaveDay->day_value ?: 0.5);
        }

        return (float) ($leaveDay->day_value ?: 1.0);
    }

    private function shortLeaveAttendanceLop(Employee $employee, array $day): float
    {
        $workedMinutes = (int) ($day['worked_minutes'] ?? 0);
        $requiredMinutes = (int) ($day['required_minutes'] ?? 0) ?: $this->requiredMinutesForEmployee($employee);

        if ($requiredMinutes <= 0 || $workedMinutes <= 0) {
            return 0.5;
        }

        $shortfallMinutes = max($requiredMinutes - $workedMinutes, 0);

        if ($shortfallMinutes <= 0) {
            return 0.0;
        }

        return $this->hourlyLeaveDayValue($employee, $shortfallMinutes);
    }

    private function hourlyLeaveDayValue(Employee $employee, int $durationMinutes): float
    {
        $employee->loadMissing('shift');
        $requiredMinutes = $this->requiredMinutesForEmployee($employee);

        if ($requiredMinutes <= 0) {
            $requiredMinutes = 540;
        }

        return round($durationMinutes / $requiredMinutes, 3);
    }

    public function dayStatusForEmployee(Employee $employee, string $date): array
    {
        $employee->loadMissing('shift');
        $punches = $this->punchesForDate($employee, $date);
        $isToday = $date === now()->toDateString();
        $requiredMinutes = $this->requiredMinutesForEmployee($employee);
        $workedMinutes = $this->workedMinutesForPunches(
            $punches,
            $this->shouldIncludeOpenSession($punches, $isToday),
        );

        return $this->resolveDayMeta(
            $employee,
            $date,
            $punches,
            $workedMinutes,
            $requiredMinutes,
            $date > now()->toDateString(),
            $this->attendancePolicyService->holidayOnDate($employee->company_id, $date),
            null,
            $isToday,
        );
    }

    public function dayDetail(User $user, string $date, ?int $employeeId = null): array
    {
        $employee = $this->resolveViewableEmployee($user, $employeeId);
        $day = Carbon::createFromFormat('Y-m-d', $date);
        $punches = $this->punchesForDate($employee, $date);
        $isToday = $date === now()->toDateString();
        $requiredMinutes = $this->requiredMinutesForEmployee($employee);
        $workedMinutes = $this->workedMinutesForPunches(
            $punches,
            $this->shouldIncludeOpenSession($punches, $isToday),
        );
        $dayMeta = $this->resolveDayMeta(
            $employee,
            $date,
            $punches,
            $workedMinutes,
            $requiredMinutes,
            $date > now()->toDateString(),
            null,
            null,
            $isToday,
        );

        $pendingRequest = AttendanceRegularizationRequest::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date)
            ->where('status', AttendanceRegularizationRequest::STATUS_PENDING)
            ->first();

        $latestRequest = AttendanceRegularizationRequest::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date)
            ->latest()
            ->first();

        return [
            'date' => $date,
            'date_label' => $day->format('l, d M Y'),
            'employee' => $this->formatEmployee($employee),
            'shift' => $this->formatShift($employee->shift),
            'required_minutes' => $requiredMinutes,
            'required_hours_label' => $this->formatMinutes($requiredMinutes),
            'worked_minutes' => $dayMeta['worked_minutes'],
            'worked_hours_label' => $this->formatMinutes($dayMeta['worked_minutes']),
            'status' => $pendingRequest ? 'regularization_pending' : $dayMeta['status'],
            'status_label' => $pendingRequest ? 'Regularization Pending' : $dayMeta['status_label'],
            'holiday_name' => $dayMeta['holiday_name'],
            'punch_in_label' => $dayMeta['punch_in_label'],
            'punch_out_label' => $dayMeta['punch_out_label'],
            'day_message' => $dayMeta['day_message'],
            'punches' => $punches->map(fn (AttendancePunch $punch) => $this->formatPunch($punch))->values()->all(),
            'segments' => $this->workSegments($punches, $this->shouldIncludeOpenSession($punches, $isToday)),
            'awaiting_punch_out' => (bool) ($dayMeta['awaiting_punch_out'] ?? false),
            'regularization_request' => $latestRequest ? [
                'id' => $latestRequest->id,
                'status' => $latestRequest->status,
                'status_label' => ucfirst($latestRequest->status),
                'requested_punch_in_label' => $latestRequest->requested_punch_in?->format('h:i A'),
                'requested_punch_out_label' => $latestRequest->requested_punch_out?->format('h:i A'),
                'reason' => $latestRequest->reason,
            ] : null,
            'can_request_regularization' => app(AttendanceRegularizationService::class)
                ->canRequestForDate($user, $employee, $date),
            'leave_type_name' => $dayMeta['leave_type_name'] ?? null,
            'leave_session_label' => $dayMeta['leave_session_label'] ?? null,
            'leave_request_id' => $dayMeta['leave_request_id'] ?? null,
            'leave_approved_by_name' => $dayMeta['leave_approved_by_name'] ?? null,
            'leave_approved_at_label' => $dayMeta['leave_approved_at_label'] ?? null,
        ];
    }

    public function todayOverview(User $user, ?string $date = null): array
    {
        if (! $this->canViewAllAttendance($user)) {
            throw new AccessDeniedHttpException('You are not allowed to view company attendance.');
        }

        $date = $date ?? now()->toDateString();

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw ValidationException::withMessages([
                'date' => ['Invalid date format.'],
            ]);
        }

        $companyId = (int) $user->company_id;
        $isToday = $date === now()->toDateString();
        $day = Carbon::createFromFormat('Y-m-d', $date);

        $employees = Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->with(['department', 'shift'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $employeeIds = $employees->pluck('id');

        $punchesByEmployee = AttendancePunch::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('punched_at', $date)
            ->orderBy('punched_at')
            ->get()
            ->groupBy('employee_id');

        $leaveDaysByEmployee = $this->leaveRequestService->approvedLeaveDaysForEmployeesOnDate($employeeIds, $date);

        $pendingRegularizations = AttendanceRegularizationRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', $date)
            ->where('status', AttendanceRegularizationRequest::STATUS_PENDING)
            ->get()
            ->keyBy('employee_id');

        $weeklyOffWeekdays = $this->attendancePolicyService->weeklyOffWeekdays($companyId);
        $holiday = $this->attendancePolicyService->holidayOnDate($companyId, $date);

        $summary = [
            'total' => 0,
            'marked' => 0,
            'not_marked' => 0,
            'present' => 0,
            'half_day' => 0,
            'absent' => 0,
            'on_leave' => 0,
            'incomplete' => 0,
            'holiday' => 0,
            'weekly_off' => 0,
            'before_portal' => 0,
        ];

        $rows = [];

        foreach ($employees as $employee) {
            $punches = $punchesByEmployee->get($employee->id, collect());
            $requiredMinutes = $this->requiredMinutesForEmployee($employee);
            $workedMinutes = $punches->isEmpty()
                ? 0
                : $this->workedMinutesForPunches($punches, $this->shouldIncludeOpenSession($punches, $isToday));

            $dayMeta = $this->resolveDayMeta(
                $employee,
                $date,
                $punches,
                $workedMinutes,
                $requiredMinutes,
                $date > now()->toDateString(),
                $holiday,
                $weeklyOffWeekdays,
                $isToday,
                $leaveDaysByEmployee->get($employee->id),
            );

            $status = $dayMeta['status'];
            $statusLabel = $dayMeta['status_label'];

            if ($pendingRegularizations->has($employee->id)) {
                $status = 'regularization_pending';
                $statusLabel = 'Regularization Pending';
            }

            $requiresMarking = ! in_array($status, [
                'holiday',
                'weekly_off',
                'on_leave',
                'before_portal',
            ], true);

            $hasMarked = $punches->isNotEmpty();
            $markedLabel = match (true) {
                ! $requiresMarking => '—',
                $hasMarked && ($dayMeta['awaiting_punch_out'] ?? false) => 'Partial',
                $hasMarked => 'Yes',
                default => 'No',
            };

            $summary['total']++;
            $summaryKey = match ($status) {
                'regularization_pending' => 'incomplete',
                default => $status,
            };

            if (array_key_exists($summaryKey, $summary)) {
                $summary[$summaryKey]++;
            }

            if ($requiresMarking) {
                if ($hasMarked) {
                    $summary['marked']++;
                } else {
                    $summary['not_marked']++;
                }
            }

            $rows[] = [
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
                'department' => $employee->department?->name,
                'designation' => $employee->designation,
                'punch_in_label' => $dayMeta['punch_in_label'],
                'punch_out_label' => $dayMeta['punch_out_label'],
                'worked_minutes' => $dayMeta['worked_minutes'],
                'worked_hours_label' => $this->formatMinutes($dayMeta['worked_minutes']),
                'required_hours_label' => $this->formatMinutes($requiredMinutes),
                'has_marked' => $hasMarked,
                'marked_label' => $markedLabel,
                'requires_marking' => $requiresMarking,
                'status' => $status,
                'status_label' => $statusLabel ?: $this->statusLabel($status),
                'holiday_name' => $dayMeta['holiday_name'],
                'leave_type_name' => $dayMeta['leave_type_name'] ?? null,
                'leave_session_label' => $dayMeta['leave_session_label'] ?? null,
                'awaiting_punch_out' => (bool) ($dayMeta['awaiting_punch_out'] ?? false),
                'punch_count' => $punches->count(),
            ];
        }

        return [
            'date' => $date,
            'date_label' => $day->format('l, d M Y'),
            'is_today' => $isToday,
            'summary' => $summary,
            'employees' => $rows,
        ];
    }

    private function punchesForDate(Employee $employee, string $date): Collection
    {
        return AttendancePunch::query()
            ->where('employee_id', $employee->id)
            ->whereDate('punched_at', $date)
            ->orderBy('punched_at')
            ->get();
    }

    private function nextPunchType(Collection $punches): ?string
    {
        if ($punches->isEmpty()) {
            return AttendancePunch::TYPE_IN;
        }

        $last = $punches->last();

        return $last->punch_type === AttendancePunch::TYPE_IN
            ? AttendancePunch::TYPE_OUT
            : AttendancePunch::TYPE_IN;
    }

    private function workedMinutesForPunches(Collection $punches, bool $includeOpenSession): int
    {
        $minutes = 0;
        $openIn = null;

        foreach ($punches as $punch) {
            if ($punch->punch_type === AttendancePunch::TYPE_IN) {
                $openIn = $punch->punched_at;
                continue;
            }

            if ($punch->punch_type === AttendancePunch::TYPE_OUT && $openIn) {
                $minutes += $openIn->diffInMinutes($punch->punched_at);
                $openIn = null;
            }
        }

        if ($includeOpenSession && $openIn) {
            $minutes += $openIn->diffInMinutes(now());
        }

        return $minutes;
    }

    private function workSegments(Collection $punches, bool $includeOpenSession): array
    {
        $segments = [];
        $openIn = null;

        foreach ($punches as $punch) {
            if ($punch->punch_type === AttendancePunch::TYPE_IN) {
                $openIn = $punch;
                continue;
            }

            if ($punch->punch_type === AttendancePunch::TYPE_OUT && $openIn) {
                $minutes = $openIn->punched_at->diffInMinutes($punch->punched_at);
                $segments[] = [
                    'punch_in_at' => $openIn->punched_at->toIso8601String(),
                    'punch_out_at' => $punch->punched_at->toIso8601String(),
                    'minutes' => $minutes,
                    'duration_label' => $this->formatMinutes($minutes),
                ];
                $openIn = null;
            }
        }

        if ($includeOpenSession && $openIn) {
            $minutes = $openIn->punched_at->diffInMinutes(now());
            $segments[] = [
                'punch_in_at' => $openIn->punched_at->toIso8601String(),
                'punch_out_at' => null,
                'minutes' => $minutes,
                'duration_label' => $this->formatMinutes($minutes).' (ongoing)',
            ];
        }

        return $segments;
    }

    private function requiredMinutesForEmployee(Employee $employee): int
    {
        return $employee->shift?->requiredWorkMinutes() ?? 540;
    }

    private function isDayComplete(Employee $employee, Collection $punches, bool $isToday): bool
    {
        if ($punches->isEmpty() || $this->hasUnclosedPunchSession($punches)) {
            return false;
        }

        $required = $this->requiredMinutesForEmployee($employee);
        $worked = $this->workedMinutesForPunches($punches, $this->shouldIncludeOpenSession($punches, $isToday));

        return $worked >= $required;
    }

    private function shouldIncludeOpenSession(Collection $punches, bool $isToday): bool
    {
        return $isToday && ! $this->hasUnclosedPunchSession($punches);
    }

    private function resolveDayMeta(
        Employee $employee,
        string $dateString,
        Collection $punches,
        int $workedMinutes,
        int $requiredMinutes,
        bool $isFuture,
        ?Holiday $holiday = null,
        ?array $weeklyOffWeekdays = null,
        bool $isToday = false,
        ?LeaveRequestDay $approvedLeaveDay = null,
    ): array {
        $companyId = $employee->company_id;
        $weeklyOffWeekdays ??= $this->attendancePolicyService->weeklyOffWeekdays($companyId);
        $holiday ??= $this->attendancePolicyService->holidayOnDate($companyId, $dateString);
        $punchSummary = $this->summarizeDayPunches($punches);

        if ($this->portalStartService->isBeforeAttendanceTracking($employee, $dateString)) {
            return [
                'status' => 'before_portal',
                'status_label' => '',
                'holiday_name' => null,
                'worked_minutes' => 0,
                'required_minutes' => $requiredMinutes,
                'punch_in_label' => null,
                'punch_out_label' => null,
                'can_mark' => false,
                'day_message' => $this->portalStartService->beforeTrackingReason($employee, $dateString),
            ];
        }

        if ($holiday) {
            return [
                'status' => 'holiday',
                'status_label' => $holiday->name,
                'holiday_name' => $holiday->name,
                'worked_minutes' => $workedMinutes,
                'required_minutes' => $requiredMinutes,
                'punch_in_label' => $punchSummary['punch_in_label'],
                'punch_out_label' => $punchSummary['punch_out_label'],
                'can_mark' => false,
                'day_message' => "Today is a holiday: {$holiday->name}.",
            ];
        }

        if ($this->attendancePolicyService->isWeeklyOff($dateString, $weeklyOffWeekdays)) {
            return [
                'status' => 'weekly_off',
                'status_label' => 'Weekly Off',
                'holiday_name' => null,
                'worked_minutes' => $workedMinutes,
                'required_minutes' => $requiredMinutes,
                'punch_in_label' => $punchSummary['punch_in_label'],
                'punch_out_label' => $punchSummary['punch_out_label'],
                'can_mark' => false,
                'day_message' => 'This is a weekly off day.',
            ];
        }

        $approvedLeaveDay ??= $this->leaveRequestService->approvedLeaveDayOnDate($employee, $dateString);

        if ($approvedLeaveDay) {
            return $this->approvedLeaveDayMeta(
                $approvedLeaveDay,
                $workedMinutes,
                $requiredMinutes,
                $punchSummary,
            );
        }

        $legacyLeaveRequest = $this->leaveRequestService->approvedLeaveRequestOnDate($employee, $dateString);

        if ($legacyLeaveRequest) {
            return array_merge([
                'status' => 'on_leave',
                'status_label' => $legacyLeaveRequest->leaveType?->name ?? 'On Leave',
                'holiday_name' => null,
                'worked_minutes' => $workedMinutes,
                'required_minutes' => $requiredMinutes,
                'punch_in_label' => $punchSummary['punch_in_label'],
                'punch_out_label' => $punchSummary['punch_out_label'],
                'can_mark' => false,
                'day_message' => 'Approved leave for this day.',
                'leave_type_name' => $legacyLeaveRequest->leaveType?->name,
                'leave_session_label' => 'Full Day',
                'leave_request_id' => $legacyLeaveRequest->id,
            ], $this->leaveRequestService->leaveApprovalMeta($legacyLeaveRequest));
        }

        if ($isFuture) {
            return [
                'status' => 'future',
                'status_label' => 'Upcoming',
                'holiday_name' => null,
                'worked_minutes' => $workedMinutes,
                'required_minutes' => $requiredMinutes,
                'punch_in_label' => $punchSummary['punch_in_label'],
                'punch_out_label' => $punchSummary['punch_out_label'],
                'can_mark' => false,
                'day_message' => null,
                'leave_type_name' => null,
                'leave_session_label' => null,
                'leave_request_id' => null,
            ];
        }

        if ($this->hasUnclosedPunchSession($punches)) {
            if ($isToday) {
                return [
                    'status' => 'incomplete',
                    'status_label' => '',
                    'holiday_name' => null,
                    'worked_minutes' => $workedMinutes,
                    'required_minutes' => $requiredMinutes,
                    'punch_in_label' => $punchSummary['punch_in_label'],
                    'punch_out_label' => null,
                    'can_mark' => true,
                    'day_message' => null,
                    'awaiting_punch_out' => true,
                ];
            }

            return [
                'status' => 'absent',
                'status_label' => $this->statusLabel('absent'),
                'holiday_name' => null,
                'worked_minutes' => $workedMinutes,
                'required_minutes' => $requiredMinutes,
                'punch_in_label' => $punchSummary['punch_in_label'],
                'punch_out_label' => null,
                'can_mark' => true,
                'day_message' => 'Punch out was not marked for this day.',
                'awaiting_punch_out' => false,
            ];
        }

        if ($isToday && ($requiredMinutes <= 0 || $workedMinutes < $requiredMinutes)) {
            return [
                'status' => 'incomplete',
                'status_label' => 'In progress',
                'holiday_name' => null,
                'worked_minutes' => $workedMinutes,
                'required_minutes' => $requiredMinutes,
                'punch_in_label' => $punchSummary['punch_in_label'],
                'punch_out_label' => $punchSummary['punch_out_label'],
                'can_mark' => true,
                'day_message' => null,
            ];
        }

        $status = $this->attendanceStatus($punches, $workedMinutes, $requiredMinutes);

        return [
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'holiday_name' => null,
            'worked_minutes' => $workedMinutes,
            'required_minutes' => $requiredMinutes,
            'punch_in_label' => $punchSummary['punch_in_label'],
            'punch_out_label' => $punchSummary['punch_out_label'],
            'can_mark' => true,
            'day_message' => null,
        ];
    }

    private function approvedLeaveDayMeta(
        LeaveRequestDay $leaveDay,
        int $workedMinutes,
        int $requiredMinutes,
        array $punchSummary,
    ): array {
        $leaveDay->loadMissing('leaveRequest.leaveType', 'leaveRequest.reviewedBy');

        return array_merge([
            'status' => 'on_leave',
            'status_label' => $this->leaveRequestService->leaveDayCalendarLabel($leaveDay),
            'holiday_name' => null,
            'worked_minutes' => $workedMinutes,
            'required_minutes' => $requiredMinutes,
            'punch_in_label' => $punchSummary['punch_in_label'],
            'punch_out_label' => $punchSummary['punch_out_label'],
            'can_mark' => false,
            'day_message' => 'Approved leave for this day.',
            'leave_type_name' => $leaveDay->leaveRequest?->leaveType?->name,
            'leave_session_label' => $leaveDay->sessionLabel(),
            'leave_request_id' => $leaveDay->leave_request_id,
        ], $this->leaveRequestService->leaveApprovalMeta($leaveDay->leaveRequest));
    }

    private function hasUnclosedPunchSession(Collection $punches): bool
    {
        if ($punches->isEmpty()) {
            return false;
        }

        return $punches->last()->punch_type === AttendancePunch::TYPE_IN;
    }

    private function attendanceStatus(Collection $punches, int $workedMinutes, int $requiredMinutes): string
    {
        if ($punches->isEmpty() || $workedMinutes <= 0) {
            return 'absent';
        }

        if ($requiredMinutes <= 0 || $workedMinutes >= $requiredMinutes) {
            return 'present';
        }

        $halfDayThreshold = (int) floor($requiredMinutes / 2);

        if ($workedMinutes >= $halfDayThreshold) {
            return 'half_day';
        }

        return 'absent';
    }

    private function dayStatus(Collection $punches, int $workedMinutes, int $requiredMinutes, bool $isFuture): string
    {
        if ($isFuture) {
            return 'future';
        }

        return $this->attendanceStatus($punches, $workedMinutes, $requiredMinutes);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'present' => 'Present',
            'half_day' => 'Half Day',
            'short_leave' => 'Short Leave',
            'absent' => 'Absent',
            'weekly_off' => 'Weekly Off',
            'holiday' => 'Holiday',
            'future' => 'Upcoming',
            'before_portal' => '',
            'on_leave' => 'On Leave',
            'regularization_pending' => 'Regularization Pending',
            'incomplete' => 'In progress',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function formatCalendarPunchEntry(AttendancePunch $punch): array
    {
        return [
            'type' => $punch->punch_type,
            'label' => sprintf(
                '%s %s %s',
                $punch->punched_at->format('H:i'),
                $punch->punched_at->format('M d'),
                $punch->punch_type === AttendancePunch::TYPE_IN ? 'In' : 'Out',
            ),
        ];
    }

    private function summarizeDayPunches(Collection $punches): array
    {
        $firstIn = $punches->firstWhere('punch_type', AttendancePunch::TYPE_IN);
        $lastOut = $punches->where('punch_type', AttendancePunch::TYPE_OUT)->last();

        return [
            'punch_in_label' => $firstIn?->punched_at->format('h:i A'),
            'punch_out_label' => $lastOut?->punched_at->format('h:i A'),
        ];
    }

    private function formatPunch(AttendancePunch $punch): array
    {
        return [
            'id' => $punch->id,
            'punch_type' => $punch->punch_type,
            'punch_label' => $punch->punch_type === AttendancePunch::TYPE_IN ? 'Punch In' : 'Punch Out',
            'punched_at' => $punch->punched_at->toIso8601String(),
            'punched_at_label' => $punch->punched_at->format('h:i A'),
            'latitude' => $punch->latitude,
            'longitude' => $punch->longitude,
            'location_name' => $punch->location_name,
            'location_label' => $punch->locationLabel(),
            'selfie_url' => $punch->selfie_path ? $punch->selfieUrl() : null,
            'source' => $punch->source ?? AttendancePunch::SOURCE_LIVE,
            'is_regularized' => $punch->isRegularized(),
        ];
    }

    private function formatEmployee(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'full_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
            'department' => $employee->department?->name,
        ];
    }

    private function formatShift(?Shift $shift): ?array
    {
        if (! $shift) {
            return null;
        }

        $requiredMinutes = $shift->requiredWorkMinutes();

        return [
            'id' => $shift->id,
            'name' => $shift->name,
            'time_range' => $shift->time_range,
            'required_minutes' => $requiredMinutes,
            'required_hours_label' => $this->formatMinutes($requiredMinutes),
        ];
    }

    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($hours === 0) {
            return "{$remaining}m";
        }

        if ($remaining === 0) {
            return "{$hours}h";
        }

        return "{$hours}h {$remaining}m";
    }
}
