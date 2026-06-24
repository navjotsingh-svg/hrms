<?php

namespace App\Services;

use App\Models\AttendancePunch;
use App\Models\AttendanceRegularizationRequest;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AttendanceRegularizationService
{
    private const REGULARIZABLE_STATUSES = [
        'absent',
        'incomplete',
        'half_day',
    ];

    public function __construct(
        private AttendanceService $attendanceService,
        private PortalStartService $portalStartService,
        private AttendancePolicyService $attendancePolicyService,
        private ActivityLogService $activityLogService,
    ) {}

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = AttendanceRegularizationRequest::query()
            ->with(['employee', 'appliedBy', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->latest();

        $this->applyListFilters($query, $user, $filters);

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function summaryForUser(User $user, array $filters = []): array
    {
        $query = AttendanceRegularizationRequest::query()
            ->where('company_id', $user->company_id);

        $this->applyListFilters($query, $user, $filters);

        $counts = (clone $query)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $month = $filters['month'] ?? null;
        $monthLabel = null;

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $monthLabel = Carbon::createFromFormat('Y-m', $month)->format('F Y');
        }

        return [
            'month' => $month,
            'month_label' => $monthLabel,
            'total' => (int) $counts->sum(),
            'pending' => (int) ($counts[AttendanceRegularizationRequest::STATUS_PENDING] ?? 0),
            'approved' => (int) ($counts[AttendanceRegularizationRequest::STATUS_APPROVED] ?? 0),
            'rejected' => (int) ($counts[AttendanceRegularizationRequest::STATUS_REJECTED] ?? 0),
            'cancelled' => (int) ($counts[AttendanceRegularizationRequest::STATUS_CANCELLED] ?? 0),
        ];
    }

    private function applyListFilters($query, User $user, array $filters): void
    {
        if (! $user->canViewAllAttendance()) {
            $employeeId = $user->employee?->id;

            if (! $employeeId) {
                throw new AccessDeniedHttpException('No employee profile is linked to your account.');
            }

            $query->where('employee_id', $employeeId);
        } elseif (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['scope'])) {
            match ($filters['scope']) {
                'mine' => $query->where(function ($builder) use ($user) {
                    $builder->where('applied_by_user_id', $user->id);

                    if ($user->employee) {
                        $builder->orWhere('employee_id', $user->employee->id);
                    }
                }),
                'history' => $query->whereIn('status', [
                    AttendanceRegularizationRequest::STATUS_APPROVED,
                    AttendanceRegularizationRequest::STATUS_REJECTED,
                    AttendanceRegularizationRequest::STATUS_CANCELLED,
                ]),
                default => null,
            };
        }

        if (! empty($filters['month']) && preg_match('/^\d{4}-\d{2}$/', (string) $filters['month'])) {
            [$year, $month] = explode('-', $filters['month']);
            $query->whereYear('attendance_date', (int) $year)
                ->whereMonth('attendance_date', (int) $month);
        } elseif (! empty($filters['year'])) {
            $query->whereYear('attendance_date', (int) $filters['year']);
        }
    }

    public function pendingForReviewer(User $user): Collection
    {
        if (! $user->canApproveRegularization()) {
            return collect();
        }

        return AttendanceRegularizationRequest::query()
            ->with(['employee', 'appliedBy'])
            ->where('company_id', $user->company_id)
            ->where('status', AttendanceRegularizationRequest::STATUS_PENDING)
            ->latest()
            ->get()
            ->filter(fn (AttendanceRegularizationRequest $request) => $user->canReviewRegularizationRequest($request))
            ->values();
    }

    public function pendingGroupsForReviewer(User $user): array
    {
        $requests = $this->pendingForReviewer($user);
        $groups = [];

        foreach ($requests as $request) {
            $groupKey = $request->batch_id ?: ('single-' . $request->id);

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'batch_id' => $request->batch_id,
                    'employee' => $request->employee ? [
                        'id' => $request->employee->id,
                        'full_name' => $request->employee->full_name,
                        'employee_code' => $request->employee->employee_code,
                    ] : null,
                    'applied_by' => $request->appliedBy ? [
                        'id' => $request->appliedBy->id,
                        'name' => $request->appliedBy->name,
                    ] : null,
                    'reason' => $request->reason,
                    'requested_punch_in' => $request->requested_punch_in?->format('H:i'),
                    'requested_punch_out' => $request->requested_punch_out?->format('H:i'),
                    'requested_punch_in_label' => $request->requested_punch_in?->format('h:i A'),
                    'requested_punch_out_label' => $request->requested_punch_out?->format('h:i A'),
                    ...$this->formatOriginalPunchFields($request),
                    'created_at_label' => $request->created_at?->format('d M Y, h:i A'),
                    'sort_at' => $request->created_at?->timestamp ?? 0,
                    'dates' => [],
                    'request_ids' => [],
                    'can_review' => true,
                ];
            }

            $groups[$groupKey]['dates'][] = [
                'id' => $request->id,
                'attendance_date' => $request->attendance_date?->toDateString(),
                'attendance_date_label' => $request->attendance_date?->format('D, d M Y'),
                'attendance_date_short_label' => $request->attendance_date?->format('D, d M'),
                ...$this->formatOriginalPunchFields($request),
                'requested_punch_in_label' => $request->requested_punch_in?->format('h:i A'),
                'requested_punch_out_label' => $request->requested_punch_out?->format('h:i A'),
            ];
            $groups[$groupKey]['request_ids'][] = $request->id;
        }

        $grouped = array_values($groups);

        foreach ($grouped as &$group) {
            usort($group['dates'], fn (array $left, array $right) => strcmp($left['attendance_date'], $right['attendance_date']));
            $group['day_count'] = count($group['dates']);
            $group['is_batch'] = $group['day_count'] > 1 || ! empty($group['batch_id']);
        }
        unset($group);

        usort($grouped, function (array $left, array $right) {
            $leftDate = $left['dates'][0]['attendance_date'] ?? '';
            $rightDate = $right['dates'][0]['attendance_date'] ?? '';

            return strcmp($rightDate, $leftDate);
        });

        return $grouped;
    }

    public function pendingCountForCompany(int $companyId, User $user): int
    {
        return $this->pendingForReviewer($user)->count();
    }

    public function create(User $user, array $data): AttendanceRegularizationRequest
    {
        return DB::transaction(fn () => $this->createRequest($user, $data));
    }

    public function createBulk(User $user, array $data): array
    {
        $dates = array_values(array_unique($data['dates'] ?? []));
        $batchId = (string) Str::uuid();

        return DB::transaction(function () use ($user, $data, $dates, $batchId) {
            return array_map(
                fn (string $date) => $this->createRequest($user, [
                    ...$data,
                    'attendance_date' => $date,
                    'batch_id' => $batchId,
                ]),
                $dates,
            );
        });
    }

    private function createRequest(User $user, array $data): AttendanceRegularizationRequest
    {
        $employee = $this->resolveTargetEmployee(
            $user,
            isset($data['employee_id']) ? (int) $data['employee_id'] : null,
        );
        $date = $data['attendance_date'];
        $punchIn = $this->buildDateTime($date, $data['punch_in_time'] ?? null);
        $punchOut = $this->buildDateTime($date, $data['punch_out_time'] ?? null);

        if (! $punchIn && ! $punchOut) {
            throw ValidationException::withMessages([
                'punch_in_time' => 'Provide at least a punch in time.',
            ]);
        }

        if ($punchIn && $punchOut && $punchOut->lte($punchIn)) {
            throw ValidationException::withMessages([
                'punch_out_time' => 'Punch out must be after punch in.',
            ]);
        }

        $this->assertCanRequestForDate($user, $employee, $date);

        $originalPunches = $this->originalPunchesForDate($employee->id, $date);

        $request = AttendanceRegularizationRequest::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'batch_id' => $data['batch_id'] ?? null,
            'attendance_date' => $date,
            'requested_punch_in' => $punchIn,
            'requested_punch_out' => $punchOut,
            'original_punch_in' => $originalPunches['punch_in'],
            'original_punch_out' => $originalPunches['punch_out'],
            'reason' => trim($data['reason']),
            'status' => AttendanceRegularizationRequest::STATUS_PENDING,
            'applied_by_user_id' => $user->id,
        ]);

        $fresh = $request->load(['employee', 'appliedBy']);

        $this->activityLogService->logWorkflowRequest(
            $user,
            'attendance_regularization',
            $fresh,
            (int) $employee->id,
            'submitted',
            'Attendance regularization request submitted.',
            null,
            request(),
            ['attendance_date' => $date],
        );

        return $fresh;
    }

    public function approve(User $user, AttendanceRegularizationRequest $request): AttendanceRegularizationRequest
    {
        $this->assertCanReview($user, $request);

        if ($request->status !== AttendanceRegularizationRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be approved.',
            ]);
        }

        return DB::transaction(function () use ($user, $request) {
            $date = $request->attendance_date->toDateString();
            $employee = $request->employee;

            AttendancePunch::query()
                ->where('employee_id', $employee->id)
                ->whereDate('punched_at', $date)
                ->delete();

            if ($request->requested_punch_in) {
                $this->createRegularizedPunch(
                    $request,
                    AttendancePunch::TYPE_IN,
                    $request->requested_punch_in,
                );
            }

            if ($request->requested_punch_out) {
                $this->createRegularizedPunch(
                    $request,
                    AttendancePunch::TYPE_OUT,
                    $request->requested_punch_out,
                );
            }

            $request->update([
                'status' => AttendanceRegularizationRequest::STATUS_APPROVED,
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(),
                'review_notes' => null,
            ]);

            $fresh = $request->fresh(['employee', 'appliedBy', 'reviewedBy']);

            $this->activityLogService->logWorkflowRequest(
                $user,
                'attendance_regularization',
                $fresh,
                (int) $fresh->employee_id,
                'approved',
                'Attendance regularization request approved.',
                null,
                request(),
            );

            return $fresh;
        });
    }

    public function reject(User $user, AttendanceRegularizationRequest $request, string $notes): AttendanceRegularizationRequest
    {
        $this->assertCanReview($user, $request);

        if ($request->status !== AttendanceRegularizationRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be rejected.',
            ]);
        }

        $request->update([
            'status' => AttendanceRegularizationRequest::STATUS_REJECTED,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => trim($notes),
        ]);

        $fresh = $request->fresh(['employee', 'appliedBy', 'reviewedBy']);

        $this->activityLogService->logWorkflowRequest(
            $user,
            'attendance_regularization',
            $fresh,
            (int) $fresh->employee_id,
            'rejected',
            'Attendance regularization request rejected.',
            trim($notes),
            request(),
        );

        return $fresh;
    }

    public function approveBatch(User $user, string $batchId): array
    {
        $requests = $this->pendingRequestsForBatch($user, $batchId);

        return DB::transaction(function () use ($user, $requests) {
            return $requests
                ->map(fn (AttendanceRegularizationRequest $request) => $this->approve($user, $request))
                ->all();
        });
    }

    public function rejectBatch(User $user, string $batchId, string $notes): array
    {
        $requests = $this->pendingRequestsForBatch($user, $batchId);

        return DB::transaction(function () use ($user, $requests, $notes) {
            return $requests
                ->map(fn (AttendanceRegularizationRequest $request) => $this->reject($user, $request, $notes))
                ->all();
        });
    }

    private function pendingRequestsForBatch(User $user, string $batchId): Collection
    {
        $requests = AttendanceRegularizationRequest::query()
            ->with(['employee.user', 'appliedBy'])
            ->where('company_id', $user->company_id)
            ->where('batch_id', $batchId)
            ->where('status', AttendanceRegularizationRequest::STATUS_PENDING)
            ->orderBy('attendance_date')
            ->get();

        if ($requests->isEmpty()) {
            throw ValidationException::withMessages([
                'batch_id' => 'No pending regularization requests found for this batch.',
            ]);
        }

        foreach ($requests as $request) {
            if (! $user->canReviewRegularizationRequest($request)) {
                throw new AccessDeniedHttpException('You are not allowed to review one or more requests in this batch.');
            }
        }

        return $requests;
    }

    public function cancel(User $user, AttendanceRegularizationRequest $request): AttendanceRegularizationRequest
    {
        if (! $user->canCancelRegularizationRequest($request)) {
            throw new AccessDeniedHttpException('You are not allowed to cancel this request.');
        }

        if ($request->status !== AttendanceRegularizationRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be cancelled.',
            ]);
        }

        $request->update([
            'status' => AttendanceRegularizationRequest::STATUS_CANCELLED,
        ]);

        return $request->fresh(['employee', 'appliedBy', 'reviewedBy']);
    }

    public function pendingForEmployeeDate(Employee $employee, string $date): ?AttendanceRegularizationRequest
    {
        return AttendanceRegularizationRequest::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date)
            ->where('status', AttendanceRegularizationRequest::STATUS_PENDING)
            ->first();
    }

    public function pendingMapForRange(int $employeeId, Carbon $start, Carbon $end): Collection
    {
        return AttendanceRegularizationRequest::query()
            ->where('employee_id', $employeeId)
            ->where('status', AttendanceRegularizationRequest::STATUS_PENDING)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (AttendanceRegularizationRequest $request) => $request->attendance_date->toDateString());
    }

    public function canRequestForDate(User $user, Employee $employee, string $date): bool
    {
        try {
            $this->assertCanRequestForDate($user, $employee, $date);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function eligibleDatesForUser(User $user, ?int $employeeId = null, ?string $onlyDate = null): array
    {
        if ($user->canViewAllAttendance()) {
            if (! $user->employee) {
                return [
                    'employee' => null,
                    'dates' => [],
                    'pending_requests' => [],
                ];
            }

            $employeeId = (int) $user->employee->id;
        }

        $employee = $this->resolveTargetEmployee($user, $employeeId);
        $employee->loadMissing('shift');

        $pendingRequests = $this->pendingRequestsForEmployee($user, $employee);

        if (! $onlyDate) {
            return [
                'employee' => $this->formatEmployeeSummary($employee),
                'dates' => [],
                'pending_requests' => $pendingRequests,
            ];
        }

        if ($onlyDate > now()->toDateString()) {
            return [
                'employee' => $this->formatEmployeeSummary($employee),
                'dates' => [],
                'pending_requests' => $pendingRequests,
            ];
        }

        if ($this->portalStartService->isBeforeAttendanceTracking($employee, $onlyDate)) {
            return [
                'employee' => $this->formatEmployeeSummary($employee),
                'dates' => [],
                'pending_requests' => $pendingRequests,
            ];
        }

        $dates = [];
        $date = Carbon::parse($onlyDate);
        $dateString = $date->toDateString();
        $dayMeta = $this->attendanceService->dayStatusForEmployee($employee, $dateString);

        if (! $this->pendingForEmployeeDate($employee, $dateString)
            && ! $this->hasApprovedRegularizationForDate($employee->id, $dateString)
            && in_array($dayMeta['status'], self::REGULARIZABLE_STATUSES, true)
            && $this->canRequestForDate($user, $employee, $dateString)) {
            $dates[] = $this->formatEligibleDate($dateString, $date, $dayMeta, $employee);
        }

        return [
            'employee' => $this->formatEmployeeSummary($employee),
            'dates' => $dates,
            'pending_requests' => $pendingRequests,
        ];
    }

    private function pendingRequestsForEmployee(User $user, Employee $employee): array
    {
        return AttendanceRegularizationRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', AttendanceRegularizationRequest::STATUS_PENDING)
            ->orderByDesc('attendance_date')
            ->get()
            ->map(function (AttendanceRegularizationRequest $request) use ($user, $employee) {
                $dayMeta = $this->attendanceService->dayStatusForEmployee(
                    $employee,
                    $request->attendance_date->toDateString(),
                );

                return $this->formatPendingRequestDate(
                    $user,
                    $request,
                    $request->attendance_date->toDateString(),
                    $request->attendance_date->copy(),
                    $dayMeta,
                );
            })
            ->values()
            ->all();
    }

    private function formatPendingRequestDate(
        User $user,
        AttendanceRegularizationRequest $request,
        string $dateString,
        Carbon $date,
        array $dayMeta,
    ): array {
        return [
            'id' => $request->id,
            'date' => $dateString,
            'date_label' => $date->format('l, d M Y'),
            'date_short_label' => $date->format('D, d M'),
            'status' => $dayMeta['status'],
            'status_label' => $this->statusLabel($dayMeta['status']),
            'reason' => $request->reason,
            'submitted_at_label' => $request->created_at?->format('d M Y, h:i A'),
            ...$this->formatOriginalPunchFields($request),
            'requested_punch_in_label' => $request->requested_punch_in?->format('h:i A'),
            'requested_punch_out_label' => $request->requested_punch_out?->format('h:i A'),
            'can_cancel' => $user->canCancelRegularizationRequest($request),
        ];
    }

    private function hasApprovedRegularizationForDate(int $employeeId, string $date): bool
    {
        return AttendanceRegularizationRequest::query()
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', $date)
            ->where('status', AttendanceRegularizationRequest::STATUS_APPROVED)
            ->exists();
    }

    private function formatEligibleDate(
        string $dateString,
        Carbon $date,
        array $dayMeta,
        Employee $employee,
    ): array {
        $punches = AttendancePunch::query()
            ->where('employee_id', $employee->id)
            ->whereDate('punched_at', $dateString)
            ->orderBy('punched_at')
            ->get();

        $firstIn = $punches->firstWhere('punch_type', AttendancePunch::TYPE_IN);
        $lastOut = $punches->where('punch_type', AttendancePunch::TYPE_OUT)->last();
        $workedMinutes = (int) ($dayMeta['worked_minutes'] ?? 0);
        $requiredMinutes = (int) ($dayMeta['required_minutes'] ?? 0);

        return [
            'date' => $dateString,
            'date_label' => $date->format('l, d M Y'),
            'date_short_label' => $date->format('D, d M'),
            'status' => $dayMeta['status'],
            'status_label' => $this->statusLabel($dayMeta['status']),
            'punch_in_label' => $dayMeta['punch_in_label'],
            'punch_out_label' => $dayMeta['punch_out_label'],
            'worked_hours_label' => $this->formatMinutes($workedMinutes),
            'required_hours_label' => $this->formatMinutes($requiredMinutes),
            'suggested_punch_in' => $firstIn
                ? $firstIn->punched_at->format('H:i')
                : $this->shiftTime($employee, 'start'),
            'suggested_punch_out' => $lastOut
                ? $lastOut->punched_at->format('H:i')
                : $this->shiftTime($employee, 'end'),
        ];
    }

    private function formatEmployeeSummary(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'full_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
        ];
    }

    private function shiftTime(Employee $employee, string $type): string
    {
        $shift = $employee->shift;
        $time = $type === 'start' ? $shift?->start_time : $shift?->end_time;
        $fallback = $type === 'start' ? '09:00' : '18:00';

        if (! $time) {
            return $fallback;
        }

        return substr((string) $time, 0, 5);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'present' => 'Present',
            'half_day' => 'Half Day',
            'short_leave' => 'Short Leave',
            'absent' => 'Absent',
            'incomplete' => 'Incomplete',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
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

    private function assertCanRequestForDate(User $user, Employee $employee, string $date): void
    {
        if ((int) $employee->company_id !== (int) $user->company_id) {
            throw new AccessDeniedHttpException('Employee not found in your company.');
        }

        if (! $user->canRegularizeAttendance()) {
            throw new AccessDeniedHttpException('You are not allowed to request attendance regularization.');
        }

        if (! $user->canViewAllAttendance() && (int) $user->employee?->id !== (int) $employee->id) {
            throw new AccessDeniedHttpException('You can only regularize your own attendance.');
        }

        if ($date > now()->toDateString()) {
            throw ValidationException::withMessages([
                'attendance_date' => 'Future dates cannot be regularized.',
            ]);
        }

        if ($this->portalStartService->isBeforeAttendanceTracking($employee, $date)) {
            throw ValidationException::withMessages([
                'attendance_date' => 'Attendance tracking had not started on this date.',
            ]);
        }

        if ($this->attendancePolicyService->holidayOnDate($employee->company_id, $date)) {
            throw ValidationException::withMessages([
                'attendance_date' => 'Holiday dates cannot be regularized.',
            ]);
        }

        if ($this->attendancePolicyService->isWeeklyOff(
            $date,
            $this->attendancePolicyService->weeklyOffWeekdaysForEmployee($employee),
        )) {
            throw ValidationException::withMessages([
                'attendance_date' => 'Weekly off dates cannot be regularized.',
            ]);
        }

        if ($this->pendingForEmployeeDate($employee, $date)) {
            throw ValidationException::withMessages([
                'attendance_date' => 'A pending regularization request already exists for this date.',
            ]);
        }

        if ($this->hasApprovedRegularizationForDate((int) $employee->id, $date)) {
            throw ValidationException::withMessages([
                'attendance_date' => 'This date already has an approved regularization request.',
            ]);
        }

        $dayMeta = $this->attendanceService->dayStatusForEmployee($employee, $date);

        if ($dayMeta['status'] === 'on_leave') {
            throw ValidationException::withMessages([
                'attendance_date' => 'Approved leave days cannot be regularized.',
            ]);
        }

        if ($dayMeta['status'] === 'present') {
            throw ValidationException::withMessages([
                'attendance_date' => 'Present days do not need regularization.',
            ]);
        }

        if (! in_array($dayMeta['status'], self::REGULARIZABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'attendance_date' => 'This date is not eligible for regularization.',
            ]);
        }
    }

    private function resolveTargetEmployee(User $user, ?int $employeeId): Employee
    {
        if ($employeeId) {
            if (! $user->canViewAllAttendance()) {
                throw new AccessDeniedHttpException('You cannot regularize attendance for other employees.');
            }

            if ($user->employee && (int) $employeeId !== (int) $user->employee->id) {
                throw new AccessDeniedHttpException('You can only regularize your own attendance.');
            }

            $employee = Employee::query()
                ->where('company_id', $user->company_id)
                ->whereKey($employeeId)
                ->first();

            if (! $employee) {
                throw new NotFoundHttpException('Employee not found.');
            }

            return $employee;
        }

        if ($user->employee) {
            return $user->employee;
        }

        if ($user->canViewAllAttendance()) {
            if ($user->employee) {
                return $user->employee;
            }

            throw ValidationException::withMessages([
                'employee_id' => 'Your account is not linked to an employee profile.',
            ]);
        }

        throw new NotFoundHttpException('No employee profile is linked to your account.');
    }

    private function buildDateTime(string $date, ?string $time): ?Carbon
    {
        if (! $time) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}");
    }

    private function createRegularizedPunch(
        AttendanceRegularizationRequest $request,
        string $type,
        Carbon $punchedAt,
    ): void {
        AttendancePunch::create([
            'company_id' => $request->company_id,
            'employee_id' => $request->employee_id,
            'punch_type' => $type,
            'punched_at' => $punchedAt,
            'latitude' => 0,
            'longitude' => 0,
            'location_name' => 'Regularized attendance',
            'selfie_path' => null,
            'source' => AttendancePunch::SOURCE_REGULARIZATION,
            'regularization_request_id' => $request->id,
        ]);
    }

    private function originalPunchesForDate(int $employeeId, string $date): array
    {
        $punches = AttendancePunch::query()
            ->where('employee_id', $employeeId)
            ->whereDate('punched_at', $date)
            ->orderBy('punched_at')
            ->get();

        $firstIn = $punches->firstWhere('punch_type', AttendancePunch::TYPE_IN);
        $lastOut = $punches->where('punch_type', AttendancePunch::TYPE_OUT)->last();

        return [
            'punch_in' => $firstIn?->punched_at,
            'punch_out' => $lastOut?->punched_at,
        ];
    }

    private function formatOriginalPunchFields(AttendanceRegularizationRequest $request): array
    {
        $punchIn = $request->original_punch_in;
        $punchOut = $request->original_punch_out;

        if (! $punchIn && ! $punchOut && $request->status === AttendanceRegularizationRequest::STATUS_PENDING) {
            $original = $this->originalPunchesForDate((int) $request->employee_id, $request->attendance_date->toDateString());
            $punchIn = $original['punch_in'];
            $punchOut = $original['punch_out'];
        }

        return [
            'original_punch_in' => $punchIn?->format('H:i'),
            'original_punch_out' => $punchOut?->format('H:i'),
            'original_punch_in_label' => $punchIn?->format('h:i A'),
            'original_punch_out_label' => $punchOut?->format('h:i A'),
        ];
    }

    private function assertCanReview(User $user, AttendanceRegularizationRequest $request): void
    {
        if (! $user->canReviewRegularizationRequest($request)) {
            throw new AccessDeniedHttpException('You are not allowed to review this request.');
        }
    }
}
