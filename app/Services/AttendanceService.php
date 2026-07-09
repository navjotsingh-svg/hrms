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
use App\Models\WfhRequest;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        private WfhRequestService $wfhRequestService,
        private EmployeeAccessService $employeeAccessService,
        private FaceVerificationService $faceVerificationService,
        private AttendanceNetworkService $attendanceNetworkService,
    ) {}

    public function canMarkAttendance(User $user): bool
    {
        return $user->canMarkAttendance();
    }

    public function canViewAllAttendance(User $user): bool
    {
        return $user->hasFullAccess()
            || $user->hasPermission('attendance.manage');
    }

    public function canViewTeamAttendance(User $user): bool
    {
        return $user->canViewTeamAttendance();
    }

    public function canViewCompanyTeamAttendance(User $user): bool
    {
        return $user->canViewCompanyTeamAttendance();
    }

    public function teamEmployeesForUser(User $user): array
    {
        $query = Employee::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->orderedByName();

        if (! $this->canViewAllAttendance($user) && ! $this->canViewCompanyTeamAttendance($user)) {
            $scopeIds = $this->employeeAccessService->teamScopeEmployeeIds($user);

            if ($scopeIds === []) {
                return [];
            }

            $query->whereIn('id', $scopeIds);
        }

        return $query
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
            $employee = $this->employeeAccessService->linkedEmployee($user);

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

        if ($this->canViewAllAttendance($user) || $this->canViewCompanyTeamAttendance($user)) {
            return $employee;
        }

        $linkedEmployee = $this->employeeAccessService->linkedEmployee($user);
        $isSelf = $linkedEmployee && (int) $linkedEmployee->id === (int) $employee->id;
        $subordinateIds = $this->employeeAccessService->subordinateIdsForUser($user);

        if (! $isSelf && ! in_array((int) $employee->id, $subordinateIds, true)) {
            throw new AccessDeniedHttpException('You are not allowed to view attendance for other employees.');
        }

        return $employee;
    }

    public function todayStatus(User $user): array
    {
        $employee = $this->employeeAccessService->linkedEmployee($user);

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
            'current_punch_in_label' => $dayMeta['current_punch_in_label'] ?? null,
            'punch_out_label' => $dayMeta['punch_out_label'],
            'day_message' => $dayMeta['day_message'],
            'awaiting_punch_out' => (bool) ($dayMeta['awaiting_punch_out'] ?? false),
            'expected_clock_out_at' => $dayMeta['expected_clock_out_at'] ?? null,
            'expected_clock_out_label' => $dayMeta['expected_clock_out_label'] ?? null,
            'profile_photo_url' => $employee->profilePhotoUrl(),
            'face_match_threshold' => $this->faceVerificationService->thresholdPercent((int) $employee->company_id),
            'require_face_match' => $this->faceVerificationService->requiresFaceMatch((int) $employee->company_id),
            'requires_profile_photo' => $this->faceVerificationService->requiresFaceMatch((int) $employee->company_id),
            'has_profile_photo' => filled($employee->profile_photo_path),
            'has_face_reference' => filled($employee->profile_face_descriptor),
        ];
    }

    public function punch(
        User $user,
        UploadedFile $selfie,
        float $latitude,
        float $longitude,
        ?string $locationName = null,
        ?float $faceMatchScore = null,
        ?array $selfieFaceDescriptor = null,
        ?string $ipAddress = null,
        ?string $macAddress = null,
    ): array {
        if (! $this->canMarkAttendance($user)) {
            throw new AccessDeniedHttpException('You are not allowed to mark attendance.');
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee) {
            throw new NotFoundHttpException('No employee profile is linked to your account.');
        }

        $employee->loadMissing('shift');
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

        $this->attendanceNetworkService->assertIpAllowed((int) $employee->company_id, $ipAddress);

        $verifiedMatchScore = $this->faceVerificationService->assertPunchAllowed(
            $employee,
            $faceMatchScore,
            $selfieFaceDescriptor,
        );

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
            'ip_address' => $ipAddress,
            'mac_address' => $macAddress,
            'face_match_score' => $verifiedMatchScore,
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
        $weeklyOffWeekdays = $this->attendancePolicyService->weeklyOffWeekdaysForEmployee($employee);
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
        $approvedWfhDates = $this->wfhRequestService->approvedDatesForRange(
            $employee,
            $start->toDateString(),
            $end->toDateString(),
        );
        $joiningDate = $employee->joining_date?->toDateString();
        $days = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateString = $date->toDateString();
            $holidayForDay = $holidays->get($dateString);
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
                $holidayForDay,
                $weeklyOffWeekdays,
                $isToday,
                $approvedLeaveDays->get($dateString),
                $approvedWfhDates->get($dateString),
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
                'holiday_id' => $holidayForDay?->id,
                'holiday_type' => $holidayForDay?->type,
                'holiday_frequency' => $holidayForDay?->frequency,
                'holiday_date_label' => $holidayForDay?->displayDateLabel(),
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
                'expected_clock_out_at' => $dayMeta['expected_clock_out_at'] ?? null,
                'expected_clock_out_label' => $dayMeta['expected_clock_out_label'] ?? null,
                'is_joining_date' => $joiningDate !== null && $dateString === $joiningDate,
                'joining_date_label' => ($joiningDate !== null && $dateString === $joiningDate)
                    ? $employee->joining_date->format('d M Y')
                    : null,
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
            'employee_joining_date' => $joiningDate,
            'employee_joining_date_label' => $employee->joining_date?->format('d M Y'),
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
            'month_holidays' => $this->formatMonthHolidays($holidays, $start, $end),
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

        if ($employee->last_working_date) {
            $lastWorkingDate = $employee->last_working_date->toDateString();

            if ($lastWorkingDate < $periodStart) {
                return [
                    'month_days' => $daysInMonth,
                    'payable_days' => 0.0,
                    'lop_days' => 0.0,
                    'paid_days' => 0.0,
                ];
            }

            if ($lastWorkingDate < $periodEnd) {
                $periodEnd = $lastWorkingDate;
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
        $holiday = $this->attendancePolicyService->holidayOnDate($employee->company_id, $date);
        $dayMeta = $this->resolveDayMeta(
            $employee,
            $date,
            $punches,
            $workedMinutes,
            $requiredMinutes,
            $date > now()->toDateString(),
            $holiday,
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
            'holiday' => $holiday ? [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'type' => $holiday->type,
                'frequency' => $holiday->frequency,
                'date_label' => $holiday->displayDateLabel(),
            ] : null,
            'punch_in_label' => $dayMeta['punch_in_label'],
            'current_punch_in_label' => $dayMeta['current_punch_in_label'] ?? null,
            'punch_out_label' => $dayMeta['punch_out_label'],
            'day_message' => $dayMeta['day_message'],
            'punches' => $punches->map(fn (AttendancePunch $punch) => $this->formatPunch($punch))->values()->all(),
            'segments' => $this->workSegments($punches, $this->shouldIncludeOpenSession($punches, $isToday)),
            'awaiting_punch_out' => (bool) ($dayMeta['awaiting_punch_out'] ?? false),
            'expected_clock_out_at' => $dayMeta['expected_clock_out_at'] ?? null,
            'expected_clock_out_label' => $dayMeta['expected_clock_out_label'] ?? null,
            'is_joining_date' => $employee->joining_date?->toDateString() === $date,
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
            'face_match_threshold' => $this->faceVerificationService->thresholdPercent((int) $employee->company_id),
            'require_face_match' => $this->faceVerificationService->requiresFaceMatch((int) $employee->company_id),
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
            ->orderedByName()
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

        $holiday = $this->attendancePolicyService->holidayOnDate($companyId, $date);

        $correctionService = app(AttendanceCorrectionService::class);
        $absentRemarksByEmployee = $correctionService->absentRemarksForEmployeesOnDate($companyId, $employeeIds, $date);

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
        $canMarkAbsent = $correctionService->canMarkAbsent($user);

        foreach ($employees as $employee) {
            $punches = $punchesByEmployee->get($employee->id, collect());
            $requiredMinutes = $this->requiredMinutesForEmployee($employee);
            $weeklyOffWeekdays = $this->attendancePolicyService->weeklyOffWeekdaysForEmployee($employee);
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
                'can_mark_absent' => $canMarkAbsent && $correctionService->canMarkEmployeeAbsent(
                    $user,
                    $employee,
                    $date,
                    $status,
                    $punches->count(),
                    $pendingRegularizations->has($employee->id),
                ),
                'absent_remark' => $absentRemarksByEmployee[$employee->id] ?? null,
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

    public function companyMonthMatrix(User $user, string $month, array $filters = []): array
    {
        $canViewAll = $this->canViewAllAttendance($user);
        $canViewCompanyTeam = $this->canViewCompanyTeamAttendance($user);
        $canViewTeam = ! $canViewAll && $this->canViewTeamAttendance($user);

        if (! $canViewAll && ! $canViewTeam) {
            throw new AccessDeniedHttpException('You are not allowed to view team attendance.');
        }

        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $start = $monthDate->copy()->startOfMonth();
        $end = $monthDate->copy()->endOfMonth();
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $today = now()->toDateString();
        $companyId = (int) $user->company_id;

        $query = Employee::query()
            ->where('company_id', $companyId)
            ->with(['department', 'shift'])
            ->orderedByName();

        if (! $canViewAll && ! $canViewCompanyTeam) {
            $scopeIds = $this->employeeAccessService->teamScopeEmployeeIds($user);

            if ($scopeIds === []) {
                throw new AccessDeniedHttpException('You are not allowed to view team attendance.');
            }

            $query->whereIn('id', $scopeIds);
        }

        $status = $filters['status'] ?? 'active';

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if (! empty($filters['department_id'])) {
            $departmentId = (int) $filters['department_id'];
            $query->where(function ($builder) use ($departmentId) {
                $builder
                    ->where('department_id', $departmentId)
                    ->orWhereHas('departments', fn ($relation) => $relation->where('departments.id', $departmentId));
            });
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        $allEmployeeIds = (clone $query)->pluck('id');

        /** @var LengthAwarePaginator $employees */
        $employees = $query->paginate((int) ($filters['per_page'] ?? 25));

        $employeeIds = $allEmployeeIds->isNotEmpty() ? $allEmployeeIds : collect();

        $punchesByEmployee = $employeeIds->isEmpty()
            ? collect()
            : AttendancePunch::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereBetween('punched_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                ->orderBy('punched_at')
                ->get()
                ->groupBy('employee_id')
                ->map(fn (Collection $punches) => $punches->groupBy(
                    fn (AttendancePunch $punch) => $punch->punched_at->toDateString(),
                ));

        $leaveDays = $employeeIds->isEmpty()
            ? collect()
            : $this->leaveRequestService->approvedLeaveDaysForEmployeesInRange($employeeIds, $startDate, $endDate);

        $pendingRegularizations = $employeeIds->isEmpty()
            ? collect()
            : AttendanceRegularizationRequest::query()
                ->whereIn('employee_id', $employeeIds)
                ->where('status', AttendanceRegularizationRequest::STATUS_PENDING)
                ->whereBetween('attendance_date', [$startDate, $endDate])
                ->get()
                ->keyBy(fn (AttendanceRegularizationRequest $request) => $request->employee_id.'|'.$request->attendance_date->toDateString());

        $holidays = $this->attendancePolicyService->holidaysForRange($companyId, $start, $end);

        $dayColumns = [];
        $summary = [
            'employees' => $allEmployeeIds->count(),
            'present' => 0,
            'half_day' => 0,
            'short_leave' => 0,
            'absent' => 0,
            'on_leave' => 0,
            'holiday' => 0,
            'weekly_off' => 0,
            'regularization_pending' => 0,
            'incomplete' => 0,
        ];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateString = $date->toDateString();
            $dayColumns[] = [
                'date' => $dateString,
                'day' => (int) $date->format('j'),
                'weekday' => $date->format('D'),
                'is_today' => $dateString === $today,
                'is_weekend' => in_array((int) $date->dayOfWeek, [0, 6], true),
            ];
        }

        $summaryEmployees = Employee::query()
            ->whereIn('id', $allEmployeeIds)
            ->with('shift')
            ->get()
            ->keyBy('id');

        foreach ($summaryEmployees as $employee) {
            $requiredMinutes = $this->requiredMinutesForEmployee($employee);
            $weeklyOffWeekdays = $this->attendancePolicyService->weeklyOffWeekdaysForEmployee($employee);
            $employeePunches = $punchesByEmployee->get($employee->id, collect());

            foreach ($dayColumns as $column) {
                $dateString = $column['date'];
                $isFuture = $dateString > $today;
                $isToday = $dateString === $today;
                $dayPunches = $employeePunches->get($dateString, collect());
                $workedMinutes = $dayPunches->isEmpty()
                    ? 0
                    : $this->workedMinutesForPunches($dayPunches, $this->shouldIncludeOpenSession($dayPunches, $isToday));
                $holidayForDay = $holidays->get($dateString);
                $leaveDay = $leaveDays->get($employee->id.'|'.$dateString);

                $dayMeta = $this->resolveDayMeta(
                    $employee,
                    $dateString,
                    $dayPunches,
                    $workedMinutes,
                    $requiredMinutes,
                    $isFuture,
                    $holidayForDay,
                    $weeklyOffWeekdays,
                    $isToday,
                    $leaveDay,
                );

                $statusKey = $dayMeta['status'];

                if ($pendingRegularizations->has($employee->id.'|'.$dateString)) {
                    $statusKey = 'regularization_pending';
                }

                if ($statusKey === 'before_portal' || $statusKey === 'future') {
                    continue;
                }

                if (array_key_exists($statusKey, $summary)) {
                    $summary[$statusKey]++;
                } elseif ($statusKey === 'incomplete') {
                    $summary['incomplete']++;
                }
            }
        }

        $employeeRows = collect($employees->items())->map(function (Employee $employee) use (
            $dayColumns,
            $punchesByEmployee,
            $leaveDays,
            $pendingRegularizations,
            $holidays,
            $today,
        ) {
            $requiredMinutes = $this->requiredMinutesForEmployee($employee);
            $weeklyOffWeekdays = $this->attendancePolicyService->weeklyOffWeekdaysForEmployee($employee);
            $employeePunches = $punchesByEmployee->get($employee->id, collect());
            $cells = [];
            $rowSummary = [
                'present' => 0,
                'half_day' => 0,
                'absent' => 0,
                'on_leave' => 0,
            ];

            foreach ($dayColumns as $column) {
                $dateString = $column['date'];
                $isFuture = $dateString > $today;
                $isToday = $dateString === $today;
                $dayPunches = $employeePunches->get($dateString, collect());
                $workedMinutes = $dayPunches->isEmpty()
                    ? 0
                    : $this->workedMinutesForPunches($dayPunches, $this->shouldIncludeOpenSession($dayPunches, $isToday));
                $holidayForDay = $holidays->get($dateString);
                $leaveDay = $leaveDays->get($employee->id.'|'.$dateString);

                $dayMeta = $this->resolveDayMeta(
                    $employee,
                    $dateString,
                    $dayPunches,
                    $workedMinutes,
                    $requiredMinutes,
                    $isFuture,
                    $holidayForDay,
                    $weeklyOffWeekdays,
                    $isToday,
                    $leaveDay,
                );

                $status = $dayMeta['status'];
                $statusLabel = $dayMeta['status_label'];

                if ($pendingRegularizations->has($employee->id.'|'.$dateString)) {
                    $status = 'regularization_pending';
                    $statusLabel = 'Regularization Pending';
                }

                if (in_array($status, ['present', 'half_day', 'absent', 'on_leave'], true)) {
                    $rowSummary[$status]++;
                }

                $cells[] = $this->formatMatrixCell(
                    $dateString,
                    $status,
                    $statusLabel,
                    $dayMeta,
                    $isToday,
                    $isFuture,
                );
            }

            return [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'department' => $employee->department?->name,
                'designation' => $employee->designation,
                'cells' => $cells,
                'summary' => $rowSummary,
            ];
        });

        return [
            'month' => $month,
            'month_label' => $monthDate->format('F Y'),
            'days' => $dayColumns,
            'employees' => $employeeRows->values()->all(),
            'summary' => $summary,
            'pagination' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
                'from' => $employees->firstItem(),
                'to' => $employees->lastItem(),
            ],
            'scope' => ($canViewAll || $canViewCompanyTeam) ? 'company' : 'team',
        ];
    }

    /** @return array<string, mixed> */
    private function formatMatrixCell(
        string $date,
        string $status,
        string $statusLabel,
        array $dayMeta,
        bool $isToday,
        bool $isFuture,
    ): array {
        return [
            'date' => $date,
            'status' => $status,
            'status_label' => $statusLabel,
            'abbrev' => $this->matrixStatusAbbrev($status),
            'punch_in_label' => $dayMeta['punch_in_label'] ?? null,
            'punch_out_label' => $dayMeta['punch_out_label'] ?? null,
            'worked_hours_label' => $this->formatMinutes((int) ($dayMeta['worked_minutes'] ?? 0)),
            'holiday_name' => $dayMeta['holiday_name'] ?? null,
            'leave_type_name' => $dayMeta['leave_type_name'] ?? null,
            'leave_session_label' => $dayMeta['leave_session_label'] ?? null,
            'awaiting_punch_out' => (bool) ($dayMeta['awaiting_punch_out'] ?? false),
            'is_today' => $isToday,
            'is_future' => $isFuture,
            'is_clickable' => ! in_array($status, ['before_portal', 'future'], true),
        ];
    }

    private function matrixStatusAbbrev(string $status): string
    {
        return match ($status) {
            'present' => 'P',
            'half_day' => 'HD',
            'short_leave' => 'SL',
            'absent' => 'A',
            'on_leave' => 'L',
            'holiday' => 'H',
            'weekly_off' => 'WO',
            'regularization_pending' => 'RP',
            'incomplete' => '…',
            'before_portal', 'future' => '',
            default => '·',
        };
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
        ?WfhRequest $approvedWfhRequest = null,
    ): array {
        $companyId = $employee->company_id;
        $weeklyOffWeekdays ??= $this->attendancePolicyService->weeklyOffWeekdaysForEmployee($employee);
        $holiday ??= $this->attendancePolicyService->holidayOnDate($companyId, $dateString);
        $punchSummary = $this->summarizeDayPunches($punches);

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
        $approvedWfhRequest ??= $this->wfhRequestService->approvedOnDate($employee, $dateString);

        if ($approvedWfhRequest) {
            return $this->approvedWfhDayMeta(
                $approvedWfhRequest,
                $workedMinutes,
                $requiredMinutes,
                $punchSummary,
            );
        }

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
            $leaveType = $legacyLeaveRequest->leaveType;
            $allowsPunch = $leaveType?->allowsAttendancePunch() ?? false;

            return array_merge([
                'status' => $allowsPunch ? 'wfh' : 'on_leave',
                'status_label' => $allowsPunch
                    ? ($leaveType?->name ?? 'Work From Home')
                    : ($legacyLeaveRequest->leaveType?->name ?? 'On Leave'),
                'holiday_name' => null,
                'worked_minutes' => $workedMinutes,
                'required_minutes' => $requiredMinutes,
                'punch_in_label' => $punchSummary['punch_in_label'],
                'punch_out_label' => $punchSummary['punch_out_label'],
                'can_mark' => $allowsPunch,
                'day_message' => $allowsPunch
                    ? 'Work from home approved — punch in/out to log your hours.'
                    : 'Approved leave for this day.',
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
                return array_merge([
                    'status' => 'incomplete',
                    'status_label' => '',
                    'holiday_name' => null,
                    'worked_minutes' => $workedMinutes,
                    'required_minutes' => $requiredMinutes,
                    'punch_in_label' => $punchSummary['punch_in_label'],
                    'current_punch_in_label' => $punchSummary['current_punch_in_label'],
                    'punch_out_label' => null,
                    'can_mark' => true,
                    'day_message' => null,
                    'awaiting_punch_out' => true,
                ], $this->expectedClockOutForPunches($punches, $requiredMinutes));
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
        $leaveType = $leaveDay->leaveRequest?->leaveType;
        $allowsPunch = $leaveType?->allowsAttendancePunch() ?? false;

        return array_merge([
            'status' => $allowsPunch ? 'wfh' : 'on_leave',
            'status_label' => $allowsPunch
                ? ($leaveType?->name ?? 'Work From Home')
                : $this->leaveRequestService->leaveDayCalendarLabel($leaveDay),
            'holiday_name' => null,
            'worked_minutes' => $workedMinutes,
            'required_minutes' => $requiredMinutes,
            'punch_in_label' => $punchSummary['punch_in_label'],
            'punch_out_label' => $punchSummary['punch_out_label'],
            'can_mark' => $allowsPunch,
            'day_message' => $allowsPunch
                ? 'Work from home approved — punch in/out to log your hours.'
                : 'Approved leave for this day.',
            'leave_type_name' => $leaveDay->leaveRequest?->leaveType?->name,
            'leave_session_label' => $leaveDay->sessionLabel(),
            'leave_request_id' => $leaveDay->leave_request_id,
        ], $this->leaveRequestService->leaveApprovalMeta($leaveDay->leaveRequest));
    }

    private function approvedWfhDayMeta(
        WfhRequest $wfhRequest,
        int $workedMinutes,
        int $requiredMinutes,
        array $punchSummary,
    ): array {
        $wfhRequest->loadMissing('reviewedBy');

        return array_merge([
            'status' => 'wfh',
            'status_label' => 'Work From Home',
            'holiday_name' => null,
            'worked_minutes' => $workedMinutes,
            'required_minutes' => $requiredMinutes,
            'punch_in_label' => $punchSummary['punch_in_label'],
            'punch_out_label' => $punchSummary['punch_out_label'],
            'can_mark' => true,
            'day_message' => 'Work from home approved — punch in/out to log your hours.',
            'leave_type_name' => 'Work From Home',
            'leave_session_label' => 'Full Day',
            'leave_request_id' => null,
        ], $this->wfhRequestService->wfhApprovalMeta($wfhRequest));
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
            'wfh' => 'Work From Home',
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
        $currentIn = $this->hasUnclosedPunchSession($punches) ? $punches->last() : null;

        return [
            'punch_in_label' => $firstIn?->punched_at->format('h:i A'),
            'current_punch_in_label' => $currentIn?->punched_at->format('h:i A'),
            'punch_out_label' => $lastOut?->punched_at->format('h:i A'),
        ];
    }

    /**
     * When a punch-in session is still open, estimate the clock-out time needed
     * to complete the employee's full required working hours for the day.
     *
     * @return array{expected_clock_out_at: ?string, expected_clock_out_label: ?string}
     */
    private function expectedClockOutForPunches(Collection $punches, int $requiredMinutes): array
    {
        if ($requiredMinutes <= 0 || ! $this->hasUnclosedPunchSession($punches)) {
            return [
                'expected_clock_out_at' => null,
                'expected_clock_out_label' => null,
            ];
        }

        $openIn = null;
        $closedMinutes = 0;

        foreach ($punches as $punch) {
            if ($punch->punch_type === AttendancePunch::TYPE_IN) {
                $openIn = $punch->punched_at;
                continue;
            }

            if ($punch->punch_type === AttendancePunch::TYPE_OUT && $openIn) {
                $closedMinutes += $openIn->diffInMinutes($punch->punched_at);
                $openIn = null;
            }
        }

        if (! $openIn) {
            return [
                'expected_clock_out_at' => null,
                'expected_clock_out_label' => null,
            ];
        }

        $remainingMinutes = max($requiredMinutes - $closedMinutes, 0);
        $expectedAt = $openIn->copy()->addMinutes($remainingMinutes);

        return [
            'expected_clock_out_at' => $expectedAt->toIso8601String(),
            'expected_clock_out_label' => $expectedAt->format('h:i A'),
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
            'ip_address' => $punch->ip_address,
            'mac_address' => $punch->mac_address,
            'face_match_score' => $punch->face_match_score !== null ? (float) $punch->face_match_score : null,
            'has_face_verification' => $punch->face_match_score !== null,
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
            'joining_date' => $employee->joining_date?->toDateString(),
            'joining_date_label' => $employee->joining_date?->format('d M Y'),
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

    /** @param  \Illuminate\Support\Collection<string, Holiday>  $holidaysByDate */
    private function formatMonthHolidays(Collection $holidaysByDate, Carbon $monthStart, Carbon $monthEnd): array
    {
        $seen = [];
        $items = [];

        foreach ($holidaysByDate as $holiday) {
            if (isset($seen[$holiday->id])) {
                continue;
            }

            $seen[$holiday->id] = true;

            if ($holiday->isFixed()) {
                [$resolvedFrom, $resolvedTo] = $holiday->resolvedBoundsForYear((int) $monthStart->format('Y'));
                $clipFrom = $resolvedFrom->greaterThan($monthStart) ? $resolvedFrom : $monthStart->copy()->startOfDay();
                $clipTo = $resolvedTo->lessThan($monthEnd) ? $resolvedTo : $monthEnd->copy()->startOfDay();
            } else {
                $clipFrom = $holiday->from_date->greaterThan($monthStart)
                    ? $holiday->from_date->copy()->startOfDay()
                    : $monthStart->copy()->startOfDay();
                $clipTo = $holiday->to_date->lessThan($monthEnd)
                    ? $holiday->to_date->copy()->startOfDay()
                    : $monthEnd->copy()->startOfDay();
            }

            $items[] = [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'from_date' => $clipFrom->toDateString(),
                'to_date' => $clipTo->toDateString(),
                'date' => $clipFrom->toDateString(),
                'date_label' => $clipFrom->equalTo($clipTo)
                    ? $clipFrom->format('d M Y')
                    : $clipFrom->format('d M Y').' – '.$clipTo->format('d M Y'),
                'pattern_label' => $holiday->displayDateLabel(),
                'frequency' => $holiday->frequency,
                'type' => $holiday->type,
            ];
        }

        usort($items, fn (array $left, array $right) => strcmp($left['from_date'], $right['from_date']));

        return $items;
    }
}
