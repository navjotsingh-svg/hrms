<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AttendancePunch;
use App\Models\AttendanceRegularizationRequest;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AttendanceCorrectionService
{
    private const CORRECTABLE_STATUSES = [
        'present',
        'half_day',
        'incomplete',
        'regularization_pending',
    ];

    public function __construct(
        private AttendanceService $attendanceService,
        private PortalStartService $portalStartService,
        private AttendancePolicyService $attendancePolicyService,
        private ActivityLogService $activityLogService,
    ) {}

    public function canMarkAbsent(User $user): bool
    {
        return $user->hasFullAccess() || $user->canManageAttendanceMasters();
    }

    public function canMarkEmployeeAbsent(
        User $user,
        Employee $employee,
        string $date,
        string $status,
        int $punchCount,
        bool $hasPendingRegularization,
    ): bool {
        if (! $this->canMarkAbsent($user)) {
            return false;
        }

        if ((int) $employee->company_id !== (int) $user->company_id) {
            return false;
        }

        try {
            $this->assertDateIsCorrectable($employee, $date, $status);
        } catch (\Throwable) {
            return false;
        }

        if ($status === 'absent') {
            return false;
        }

        if ($punchCount > 0 || $hasPendingRegularization) {
            return true;
        }

        return in_array($status, self::CORRECTABLE_STATUSES, true);
    }

    /** @return array<int, array{reason: string, marked_by_name: ?string, marked_at_label: string}> */
    public function absentRemarksForEmployeesOnDate(int $companyId, iterable $employeeIds, string $date): array
    {
        $employeeIds = collect($employeeIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($employeeIds->isEmpty()) {
            return [];
        }

        $remarks = [];

        ActivityLog::query()
            ->where('company_id', $companyId)
            ->where('module', 'attendance')
            ->where('action', 'mark_absent')
            ->whereIn('employee_id', $employeeIds)
            ->where('metadata->attendance_date', $date)
            ->orderByDesc('logged_at')
            ->get()
            ->each(function (ActivityLog $log) use (&$remarks) {
                $employeeId = (int) $log->employee_id;

                if ($employeeId <= 0 || isset($remarks[$employeeId]) || ! filled($log->action_note)) {
                    return;
                }

                $remarks[$employeeId] = [
                    'reason' => trim((string) $log->action_note),
                    'marked_by_name' => $log->user_name,
                    'marked_at_label' => $log->logged_at?->format('d M Y, h:i A') ?? '',
                ];
            });

        return $remarks;
    }

    /** @return array<string, mixed> */
    public function markAbsent(User $user, int $employeeId, string $date, string $reason): array
    {
        $this->assertCanMarkAbsent($user);

        $employee = Employee::query()
            ->where('company_id', $user->company_id)
            ->whereKey($employeeId)
            ->first();

        if (! $employee) {
            throw new NotFoundHttpException('Employee not found.');
        }

        $reason = trim($reason);
        $dayMeta = $this->attendanceService->dayStatusForEmployee($employee, $date);
        $status = (string) ($dayMeta['status'] ?? '');

        $punchCount = AttendancePunch::query()
            ->where('employee_id', $employee->id)
            ->whereDate('punched_at', $date)
            ->count();

        $pendingRegularization = AttendanceRegularizationRequest::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date)
            ->where('status', AttendanceRegularizationRequest::STATUS_PENDING)
            ->first();

        if ($pendingRegularization) {
            $status = 'regularization_pending';
        }

        $this->assertDateIsCorrectable($employee, $date, $status);

        if (
            $punchCount === 0
            && ! $pendingRegularization
            && $status === 'absent'
        ) {
            throw ValidationException::withMessages([
                'employee_id' => 'This employee is already marked absent for the selected date.',
            ]);
        }

        if (
            $punchCount === 0
            && ! $pendingRegularization
            && ! in_array($status, self::CORRECTABLE_STATUSES, true)
        ) {
            throw ValidationException::withMessages([
                'employee_id' => 'This attendance record cannot be marked absent.',
            ]);
        }

        $deletedPunchCount = 0;
        $cancelledRequestId = null;

        DB::transaction(function () use ($user, $employee, $date, $reason, $pendingRegularization, &$deletedPunchCount, &$cancelledRequestId) {
            $deletedPunchCount = AttendancePunch::query()
                ->where('employee_id', $employee->id)
                ->whereDate('punched_at', $date)
                ->delete();

            if ($pendingRegularization) {
                $pendingRegularization->update([
                    'status' => AttendanceRegularizationRequest::STATUS_CANCELLED,
                    'reviewed_by_user_id' => $user->id,
                    'reviewed_at' => now(),
                    'review_notes' => 'Cancelled because attendance was marked absent by HR.',
                ]);
                $cancelledRequestId = $pendingRegularization->id;
            }

            $this->activityLogService->logChange(
                $user,
                'attendance',
                'mark_absent',
                $employee,
                (int) $employee->id,
                "Marked {$employee->full_name} absent for {$date}.",
                [],
                ['status' => 'absent'],
                request(),
                $reason,
                [
                    'attendance_date' => $date,
                    'employee_code' => $employee->employee_code,
                    'deleted_punch_count' => $deletedPunchCount,
                    'cancelled_regularization_id' => $cancelledRequestId,
                ],
            );
        });

        $updatedDay = $this->attendanceService->dayStatusForEmployee($employee, $date);

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name,
            'date' => $date,
            'date_label' => Carbon::createFromFormat('Y-m-d', $date)->format('l, d M Y'),
            'deleted_punch_count' => $deletedPunchCount,
            'cancelled_regularization_id' => $cancelledRequestId,
            'day' => $updatedDay,
            'message' => 'Employee marked absent successfully.',
        ];
    }

    private function assertCanMarkAbsent(User $user): void
    {
        if (! $this->canMarkAbsent($user)) {
            throw new AccessDeniedHttpException('You are not allowed to mark attendance as absent.');
        }
    }

    private function assertDateIsCorrectable(Employee $employee, string $date, string $status): void
    {
        if ($date > now()->toDateString()) {
            throw ValidationException::withMessages([
                'date' => 'Future dates cannot be corrected.',
            ]);
        }

        if ($this->portalStartService->isBeforeAttendanceTracking($employee, $date)) {
            throw ValidationException::withMessages([
                'date' => 'Attendance tracking had not started on this date.',
            ]);
        }

        if ($this->attendancePolicyService->holidayOnDate($employee->company_id, $date)) {
            throw ValidationException::withMessages([
                'date' => 'Holiday dates cannot be marked absent.',
            ]);
        }

        if ($this->attendancePolicyService->isWeeklyOff(
            $date,
            $this->attendancePolicyService->weeklyOffWeekdaysForEmployee($employee),
        )) {
            throw ValidationException::withMessages([
                'date' => 'Weekly off dates cannot be marked absent.',
            ]);
        }

        if (in_array($status, ['holiday', 'weekly_off', 'on_leave', 'before_portal', 'future'], true)) {
            throw ValidationException::withMessages([
                'date' => 'This date cannot be marked absent.',
            ]);
        }
    }
}
