<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestDay;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LeaveRequestService
{
    public function __construct(
        private LeaveBalanceService $leaveBalanceService,
        private LeaveAttachmentService $leaveAttachmentService,
        private AttendancePolicyService $attendancePolicyService,
        private PortalStartService $portalStartService,
        private EmployeeAccessService $employeeAccessService,
        private ActivityLogService $activityLogService,
        private LeaveTypeService $leaveTypeService,
        private WorkflowNotificationService $workflowNotificationService,
    ) {}

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = LeaveRequest::query()
            ->with(['employee', 'leaveType', 'appliedBy', 'reviewedBy', 'attachments'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('created_at');

        if (! $user->canViewAllLeaveRequests()) {
            $employee = $this->employeeAccessService->linkedEmployee($user);

            if (! $employee) {
                throw new AccessDeniedHttpException('No employee profile linked to your account.');
            }

            if ($user->canApproveLeave()) {
                $subordinateIds = $this->employeeAccessService->subordinateIdsForUser($user);
                $visibleEmployeeIds = array_values(array_unique([
                    ...$subordinateIds,
                    $employee->id,
                ]));

                $query->whereIn('employee_id', $visibleEmployeeIds);
            } else {
                $query->where('employee_id', $employee->id);
            }
        } elseif (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['leave_type_id'])) {
            $query->where('leave_type_id', $filters['leave_type_id']);
        }

        if ($year = $filters['year'] ?? null) {
            $query->whereYear('from_date', $year);
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function pendingForReviewer(User $user): Collection
    {
        return LeaveRequest::query()
            ->with(['employee.user', 'leaveType', 'appliedBy', 'attachments'])
            ->where('company_id', $user->company_id)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->orderBy('from_date')
            ->get()
            ->filter(fn (LeaveRequest $request) => $user->canReviewLeaveRequest($request))
            ->values();
    }

    public function pendingCountForCompany(int $companyId, User $user): int
    {
        return LeaveRequest::query()
            ->with(['employee.user', 'appliedBy'])
            ->where('company_id', $companyId)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->get()
            ->filter(fn (LeaveRequest $request) => $user->canReviewLeaveRequest($request))
            ->count();
    }

    public function approvedLeaveOnDate(Employee $employee, string $date): ?LeaveRequest
    {
        return $this->approvedLeaveDayOnDate($employee, $date)?->leaveRequest
            ?? $this->approvedLeaveRequestOnDate($employee, $date);
    }

    public function approvedLeaveRequestOnDate(Employee $employee, string $date): ?LeaveRequest
    {
        return LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('from_date', '<=', $date)
            ->whereDate('to_date', '>=', $date)
            ->with(['leaveType', 'reviewedBy'])
            ->first();
    }

    public function approvedLeaveDayOnDate(Employee $employee, string $date): ?LeaveRequestDay
    {
        return LeaveRequestDay::query()
            ->with(['leaveRequest.leaveType', 'leaveRequest.reviewedBy'])
            ->whereDate('date', $date)
            ->whereHas('leaveRequest', function ($query) use ($employee) {
                $query->where('employee_id', $employee->id)
                    ->where('status', LeaveRequest::STATUS_APPROVED);
            })
            ->first();
    }

    public function approvedLeaveDaysForRange(Employee $employee, string $fromDate, string $toDate): Collection
    {
        $days = LeaveRequestDay::query()
            ->with(['leaveRequest.leaveType', 'leaveRequest.reviewedBy'])
            ->whereHas('leaveRequest', function ($query) use ($employee) {
                $query->where('employee_id', $employee->id)
                    ->where('status', LeaveRequest::STATUS_APPROVED);
            })
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->get()
            ->keyBy(fn (LeaveRequestDay $day) => $day->date->toDateString());

        LeaveRequest::query()
            ->with(['leaveType', 'reviewedBy'])
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('from_date', '<=', $toDate)
            ->whereDate('to_date', '>=', $fromDate)
            ->get()
            ->each(function (LeaveRequest $request) use (&$days, $fromDate, $toDate) {
                foreach (CarbonPeriod::create(
                    max($fromDate, $request->from_date->toDateString()),
                    min($toDate, $request->to_date->toDateString()),
                ) as $date) {
                    $dateString = $date->toDateString();

                    if ($days->has($dateString)) {
                        continue;
                    }

                    $days->put($dateString, $this->syntheticLeaveDay($request, $dateString));
                }
            });

        return $days;
    }

    /**
     * @param  array<int>|Collection<int, int>  $employeeIds
     * @return Collection<int, LeaveRequestDay> keyed by employee_id
     */
    public function approvedLeaveDaysForEmployeesOnDate(array|Collection $employeeIds, string $date): Collection
    {
        $employeeIds = collect($employeeIds)->filter()->values();

        if ($employeeIds->isEmpty()) {
            return collect();
        }

        $days = LeaveRequestDay::query()
            ->with(['leaveRequest.leaveType', 'leaveRequest.reviewedBy'])
            ->whereDate('date', $date)
            ->whereHas('leaveRequest', function ($query) use ($employeeIds) {
                $query->whereIn('employee_id', $employeeIds)
                    ->where('status', LeaveRequest::STATUS_APPROVED);
            })
            ->get()
            ->keyBy(fn (LeaveRequestDay $day) => (int) $day->leaveRequest->employee_id);

        LeaveRequest::query()
            ->with(['leaveType', 'reviewedBy'])
            ->whereIn('employee_id', $employeeIds)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('from_date', '<=', $date)
            ->whereDate('to_date', '>=', $date)
            ->get()
            ->each(function (LeaveRequest $request) use (&$days, $date) {
                $employeeId = (int) $request->employee_id;

                if ($days->has($employeeId)) {
                    return;
                }

                $days->put($employeeId, $this->syntheticLeaveDay($request, $date));
            });

        return $days;
    }

    /**
     * @param  array<int>|Collection<int, int>  $employeeIds
     * @return Collection<string, LeaveRequestDay> keyed by "{employee_id}|{date}"
     */
    public function approvedLeaveDaysForEmployeesInRange(array|Collection $employeeIds, string $fromDate, string $toDate): Collection
    {
        $employeeIds = collect($employeeIds)->filter()->values();

        if ($employeeIds->isEmpty()) {
            return collect();
        }

        $days = LeaveRequestDay::query()
            ->with(['leaveRequest.leaveType', 'leaveRequest.reviewedBy'])
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->whereHas('leaveRequest', function ($query) use ($employeeIds) {
                $query->whereIn('employee_id', $employeeIds)
                    ->where('status', LeaveRequest::STATUS_APPROVED);
            })
            ->get()
            ->keyBy(fn (LeaveRequestDay $day) => $day->leaveRequest->employee_id.'|'.$day->date->toDateString());

        LeaveRequest::query()
            ->with(['leaveType', 'reviewedBy'])
            ->whereIn('employee_id', $employeeIds)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('from_date', '<=', $toDate)
            ->whereDate('to_date', '>=', $fromDate)
            ->get()
            ->each(function (LeaveRequest $request) use (&$days, $fromDate, $toDate) {
                foreach (CarbonPeriod::create(
                    max($fromDate, $request->from_date->toDateString()),
                    min($toDate, $request->to_date->toDateString()),
                ) as $date) {
                    $key = $request->employee_id.'|'.$date->toDateString();

                    if ($days->has($key)) {
                        continue;
                    }

                    $days->put($key, $this->syntheticLeaveDay($request, $date->toDateString()));
                }
            });

        return $days;
    }

    private function syntheticLeaveDay(LeaveRequest $request, string $date): LeaveRequestDay
    {
        $leaveDay = new LeaveRequestDay([
            'leave_request_id' => $request->id,
            'date' => $date,
            'session' => LeaveRequestDay::SESSION_FULL,
            'duration_minutes' => null,
            'day_value' => 1.0,
        ]);
        $leaveDay->setRelation('leaveRequest', $request);

        return $leaveDay;
    }

    public function leaveDayCalendarLabel(LeaveRequestDay $leaveDay): string
    {
        $typeName = $leaveDay->leaveRequest?->leaveType?->name ?? 'Leave';

        if ($leaveDay->session === LeaveRequestDay::SESSION_FULL) {
            return $typeName;
        }

        return $typeName.' · '.$leaveDay->sessionLabel();
    }

    public function leaveApprovalMeta(?LeaveRequest $request): array
    {
        $request?->loadMissing('reviewedBy');

        return [
            'leave_approved_by_name' => $request?->reviewedBy?->name,
            'leave_approved_at_label' => $request?->reviewed_at?->format('d M Y, h:i A'),
        ];
    }

    public function create(User $user, array $data, array $files = []): LeaveRequest
    {
        $employee = $this->resolveApplicableEmployee($user, $data['employee_id'] ?? null);
        $leaveType = LeaveType::query()
            ->where('company_id', $employee->company_id)
            ->where('status', 'active')
            ->findOrFail($data['leave_type_id']);

        $this->assertLeaveTypeAssigned($employee, $leaveType);
        $this->assertPaidLeaveAllowed($employee, $leaveType);

        $fromDate = Carbon::parse($data['from_date'])->toDateString();
        $toDate = Carbon::parse($data['to_date'])->toDateString();
        $session = $data['session'] ?? LeaveRequestDay::SESSION_FULL;
        $durationMinutes = isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null;

        if ($toDate < $fromDate) {
            throw ValidationException::withMessages([
                'to_date' => ['End date cannot be before start date.'],
            ]);
        }

        if ($leaveType->isHourlyLeave()) {
            if ($fromDate !== $toDate) {
                throw ValidationException::withMessages([
                    'to_date' => ['Short leave must be applied for a single date.'],
                ]);
            }

            $session = LeaveRequestDay::SESSION_HOURLY;
            $this->assertValidHourlyDuration($leaveType, $durationMinutes);
        } elseif ($session === LeaveRequestDay::SESSION_HOURLY) {
            throw ValidationException::withMessages([
                'session' => ['Select the Short Leave type to apply for short durations.'],
            ]);
        } elseif ($fromDate !== $toDate && $session !== LeaveRequestDay::SESSION_FULL) {
            throw ValidationException::withMessages([
                'session' => ['Half-day session is only allowed for a single date.'],
            ]);
        }

        $dayRows = $this->buildDayRows($employee, $leaveType, $fromDate, $toDate, $session, $durationMinutes);

        if ($dayRows->isEmpty()) {
            throw ValidationException::withMessages([
                'from_date' => ['No valid leave days found in the selected range.'],
            ]);
        }

        $totalDays = $this->resolveBalanceAmount($leaveType, $dayRows);

        $this->assertNoOverlap($employee, $fromDate, $toDate, $dayRows);
        $this->assertApplicationLimits($employee, $leaveType, $dayRows);
        $balance = $this->leaveBalanceService->balanceForType($employee, $leaveType->id, $this->leaveBalanceService->yearForDate($fromDate));

        if (! $this->leaveBalanceService->hasEnoughBalance($balance, $totalDays)) {
            throw ValidationException::withMessages([
                'leave_type_id' => ['Insufficient leave balance for this request.'],
            ]);
        }

        return DB::transaction(function () use ($user, $employee, $leaveType, $fromDate, $toDate, $totalDays, $data, $dayRows, $files, $balance) {
            $request = LeaveRequest::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'applied_by_user_id' => $user->id,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'total_days' => $totalDays,
                'reason' => trim($data['reason']),
                'status' => LeaveRequest::STATUS_PENDING,
            ]);

            foreach ($dayRows as $row) {
                LeaveRequestDay::create([
                    'leave_request_id' => $request->id,
                    ...$row,
                ]);
            }

            if ($files !== []) {
                $this->leaveAttachmentService->storeMany($request, $employee, $files);
            }

            $this->leaveBalanceService->reserve($balance, $totalDays);

            $fresh = $request->fresh()->load(['employee', 'leaveType', 'days', 'attachments', 'appliedBy']);

            $this->activityLogService->logWorkflowRequest(
                $user,
                'leave',
                $fresh,
                (int) $employee->id,
                'submitted',
                'Leave request submitted.',
                null,
                request(),
                [
                    'leave_type' => $leaveType->name,
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'total_days' => $totalDays,
                ],
            );

            $this->workflowNotificationService->notifyLeaveSubmitted($fresh, $user);

            return $fresh;
        });
    }

    public function addAttachments(User $user, LeaveRequest $request, array $files): LeaveRequest
    {
        if (! $user->canUploadLeaveProof($request)) {
            throw new AccessDeniedHttpException('You cannot upload documents for this leave request.');
        }

        if ($files === []) {
            throw ValidationException::withMessages([
                'proofs' => ['Select at least one file to upload.'],
            ]);
        }

        $request->loadMissing(['employee', 'attachments']);
        $existingCount = $request->attachments->count();

        if ($existingCount + count($files) > 10) {
            throw ValidationException::withMessages([
                'proofs' => ['A maximum of 10 supporting documents are allowed per leave request.'],
            ]);
        }

        $this->leaveAttachmentService->storeMany($request, $request->employee, $files);

        return $request->fresh()->load(['employee', 'leaveType', 'days', 'attachments', 'appliedBy', 'reviewedBy']);
    }

    public function approve(User $user, LeaveRequest $request, ?string $notes = null): LeaveRequest
    {
        $this->assertCanReview($user, $request);

        if ($request->status !== LeaveRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['leave' => ['Only pending requests can be approved.']]);
        }

        $request->loadMissing(['leaveType', 'attachments']);

        if ($request->leaveType?->requires_proof
            && $request->attachments->isEmpty()
            && ! $user->canBypassLeaveProofRequirement($request)) {
            throw ValidationException::withMessages([
                'leave' => ['Supporting documents must be uploaded before this leave can be approved.'],
            ]);
        }

        $balance = $this->leaveBalanceService->balanceForType(
            $request->employee,
            $request->leave_type_id,
            $this->leaveBalanceService->yearForDate($request->from_date->toDateString()),
        );

        return DB::transaction(function () use ($user, $request, $balance, $notes) {
            $request->update([
                'status' => LeaveRequest::STATUS_APPROVED,
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(),
                'review_notes' => $notes ? trim($notes) : null,
            ]);

            $this->leaveBalanceService->confirmUsage($balance, (float) $request->total_days);

            $fresh = $request->fresh()->load(['employee', 'leaveType', 'days', 'attachments', 'appliedBy', 'reviewedBy']);

            $this->activityLogService->logWorkflowRequest(
                $user,
                'leave',
                $fresh,
                (int) $fresh->employee_id,
                'approved',
                'Leave request approved.',
                $notes ? trim($notes) : null,
                request(),
            );

            $this->workflowNotificationService->notifyLeaveDecision($fresh, $user, 'approved');

            return $fresh;
        });
    }

    public function reject(User $user, LeaveRequest $request, string $notes): LeaveRequest
    {
        $this->assertCanReview($user, $request);

        if ($request->status !== LeaveRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['leave' => ['Only pending requests can be rejected.']]);
        }

        $balance = $this->leaveBalanceService->balanceForType(
            $request->employee,
            $request->leave_type_id,
            $this->leaveBalanceService->yearForDate($request->from_date->toDateString()),
        );

        return DB::transaction(function () use ($user, $request, $notes, $balance) {
            $request->update([
                'status' => LeaveRequest::STATUS_REJECTED,
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(),
                'review_notes' => trim($notes),
            ]);

            $this->leaveBalanceService->releasePending($balance, (float) $request->total_days);

            $fresh = $request->fresh()->load(['employee', 'leaveType', 'days', 'attachments', 'appliedBy', 'reviewedBy']);

            $this->activityLogService->logWorkflowRequest(
                $user,
                'leave',
                $fresh,
                (int) $fresh->employee_id,
                'rejected',
                'Leave request rejected.',
                trim($notes),
                request(),
            );

            $this->workflowNotificationService->notifyLeaveDecision($fresh, $user, 'rejected');

            return $fresh;
        });
    }

    public function cancel(User $user, LeaveRequest $request): LeaveRequest
    {
        if ((int) $request->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Leave request not found.');
        }

        if (! $user->canCancelLeaveRequest($request)) {
            throw new AccessDeniedHttpException('You cannot cancel this leave request.');
        }

        if ($request->status === LeaveRequest::STATUS_CANCELLED) {
            return $request;
        }

        if ($request->status === LeaveRequest::STATUS_REJECTED) {
            throw ValidationException::withMessages(['leave' => ['Rejected requests cannot be cancelled.']]);
        }

        $balance = $this->leaveBalanceService->balanceForType(
            $request->employee,
            $request->leave_type_id,
            $this->leaveBalanceService->yearForDate($request->from_date->toDateString()),
        );

        return DB::transaction(function () use ($user, $request, $balance) {
            if ($request->status === LeaveRequest::STATUS_PENDING) {
                $this->leaveBalanceService->releasePending($balance, (float) $request->total_days);
            }

            if ($request->status === LeaveRequest::STATUS_APPROVED) {
                $this->leaveBalanceService->restoreUsage($balance, (float) $request->total_days);
            }

            $request->update(['status' => LeaveRequest::STATUS_CANCELLED]);

            $fresh = $request->fresh()->load(['employee', 'leaveType', 'days', 'attachments', 'appliedBy', 'reviewedBy']);

            $this->activityLogService->logWorkflowRequest(
                $user,
                'leave',
                $fresh,
                (int) $fresh->employee_id,
                'cancelled',
                'Leave request cancelled.',
                null,
                request(),
            );

            return $fresh;
        });
    }

    public function belongsToCompany(LeaveRequest $request, int $companyId): bool
    {
        return (int) $request->company_id === $companyId;
    }

    private function resolveApplicableEmployee(User $user, ?int $employeeId): Employee
    {
        if ($employeeId !== null) {
            if (! $user->hasFullAccess() && ! $user->hasPermission('leave.manage')) {
                throw new AccessDeniedHttpException('You cannot apply leave for other employees.');
            }

            $employee = Employee::query()
                ->where('company_id', $user->company_id)
                ->findOrFail($employeeId);

            return $employee;
        }

        $employee = $user->employee;

        if (! $employee) {
            throw new AccessDeniedHttpException('No employee profile linked to your account.');
        }

        return $employee;
    }

    private function buildDayRows(
        Employee $employee,
        LeaveType $leaveType,
        string $fromDate,
        string $toDate,
        string $session,
        ?int $durationMinutes = null,
    ): Collection {
        if ($fromDate === $toDate) {
            if ($this->portalStartService->isBeforeAttendanceTracking($employee, $fromDate)) {
                return collect();
            }

            if ($this->isNonWorkingDay($employee, $fromDate)) {
                return collect();
            }

            if ($session === LeaveRequestDay::SESSION_HOURLY) {
                return collect([[
                    'date' => $fromDate,
                    'session' => $session,
                    'duration_minutes' => $durationMinutes,
                    'day_value' => $this->hourlyDayValue($employee, $durationMinutes),
                ]]);
            }

            $dayValue = in_array($session, [LeaveRequestDay::SESSION_FIRST_HALF, LeaveRequestDay::SESSION_SECOND_HALF], true) ? 0.5 : 1.0;

            return collect([[
                'date' => $fromDate,
                'session' => $session,
                'duration_minutes' => null,
                'day_value' => $dayValue,
            ]]);
        }

        $rows = collect();

        foreach (CarbonPeriod::create($fromDate, $toDate) as $date) {
            $dateString = $date->toDateString();

            if ($this->portalStartService->isBeforeAttendanceTracking($employee, $dateString)) {
                continue;
            }

            if ($this->isNonWorkingDay($employee, $dateString)) {
                continue;
            }

            $rows->push([
                'date' => $dateString,
                'session' => LeaveRequestDay::SESSION_FULL,
                'duration_minutes' => null,
                'day_value' => 1.0,
            ]);
        }

        return $rows;
    }

    private function isNonWorkingDay(Employee $employee, string $date): bool
    {
        if ($this->attendancePolicyService->holidayOnDate($employee->company_id, $date)) {
            return true;
        }

        $weeklyOff = $this->attendancePolicyService->weeklyOffWeekdaysForEmployee($employee);

        return $this->attendancePolicyService->isWeeklyOff($date, $weeklyOff);
    }

    public function daysUsedInMonth(Employee $employee, int $leaveTypeId, int $year, int $month): float
    {
        return (float) LeaveRequestDay::query()
            ->whereHas('leaveRequest', function ($query) use ($employee, $leaveTypeId) {
                $query->where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveTypeId)
                    ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED]);
            })
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('day_value');
    }

    public function hoursUsedInMonth(Employee $employee, int $leaveTypeId, int $year, int $month): float
    {
        $minutes = (int) LeaveRequestDay::query()
            ->whereHas('leaveRequest', function ($query) use ($employee, $leaveTypeId) {
                $query->where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveTypeId)
                    ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED]);
            })
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('duration_minutes');

        return round($minutes / 60, 2);
    }

    private function assertApplicationLimits(Employee $employee, LeaveType $leaveType, Collection $dayRows): void
    {
        $totalDays = round($dayRows->sum('day_value'), 3);

        if ($leaveType->isHourlyLeave()) {
            $durationMinutes = (int) ($dayRows->first()['duration_minutes'] ?? 0);
            $requestedHours = round($durationMinutes / 60, 2);

            if ($leaveType->max_days_per_request !== null && $requestedHours > (float) $leaveType->max_days_per_request) {
                throw ValidationException::withMessages([
                    'duration_minutes' => [
                        "Maximum {$leaveType->max_days_per_request} hour(s) allowed per request for {$leaveType->name}.",
                    ],
                ]);
            }

            if ($leaveType->max_hours_per_month === null) {
                return;
            }

            $date = Carbon::parse($dayRows->first()['date']);
            $year = (int) $date->format('Y');
            $month = (int) $date->format('n');
            $usedHours = $this->hoursUsedInMonth($employee, $leaveType->id, $year, $month);
            $limit = (float) $leaveType->max_hours_per_month;

            if (($usedHours + $requestedHours) > $limit) {
                $remaining = max(0, round($limit - $usedHours, 2));

                throw ValidationException::withMessages([
                    'from_date' => [
                        "Only {$remaining} hour(s) of {$leaveType->name} can be applied for ".$date->format('M Y')." (monthly limit: {$limit} hour(s)).",
                    ],
                ]);
            }

            return;
        }

        if ($leaveType->max_days_per_request !== null && $totalDays > (float) $leaveType->max_days_per_request) {
            throw ValidationException::withMessages([
                'from_date' => [
                    "Maximum {$leaveType->max_days_per_request} day(s) allowed per request for {$leaveType->name}.",
                ],
            ]);
        }

        if ($leaveType->max_days_per_month === null) {
            return;
        }

        $byMonth = $dayRows->groupBy(fn (array $row) => Carbon::parse($row['date'])->format('Y-n'));

        foreach ($byMonth as $monthKey => $rows) {
            [$year, $month] = array_map('intval', explode('-', (string) $monthKey));
            $requested = round((float) $rows->sum('day_value'), 2);
            $used = $this->daysUsedInMonth($employee, $leaveType->id, $year, $month);
            $limit = (float) $leaveType->max_days_per_month;
            $monthLabel = Carbon::create($year, $month, 1)->format('M Y');

            if ($requested > $limit) {
                throw ValidationException::withMessages([
                    'from_date' => [
                        "This request uses {$requested} working day(s) in {$monthLabel}, but {$leaveType->name} allows maximum {$limit} day(s) per month.",
                    ],
                ]);
            }

            if (round($used + $requested, 2) > $limit) {
                $remaining = max(0, round($limit - $used, 1));

                throw ValidationException::withMessages([
                    'from_date' => [
                        "Only {$remaining} day(s) of {$leaveType->name} can be applied for {$monthLabel} (monthly limit: {$limit}, already used: {$used}).",
                    ],
                ]);
            }
        }
    }

    public function previewApplication(User $user, array $data): array
    {
        $employee = $this->resolveApplicableEmployee($user, $data['employee_id'] ?? null);
        $leaveType = LeaveType::query()
            ->where('company_id', $employee->company_id)
            ->where('status', 'active')
            ->findOrFail($data['leave_type_id']);

        $this->assertLeaveTypeAssigned($employee, $leaveType);
        $this->assertPaidLeaveAllowed($employee, $leaveType);

        $fromDate = Carbon::parse($data['from_date'])->toDateString();
        $toDate = Carbon::parse($data['to_date'])->toDateString();
        $session = $data['session'] ?? LeaveRequestDay::SESSION_FULL;
        $durationMinutes = isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null;

        if ($leaveType->isHourlyLeave()) {
            $session = LeaveRequestDay::SESSION_HOURLY;
        }

        $dayRows = $this->buildDayRows($employee, $leaveType, $fromDate, $toDate, $session, $durationMinutes);

        try {
            $this->assertApplicationLimits($employee, $leaveType, $dayRows);

            return [
                'valid' => true,
                'working_days' => round((float) $dayRows->sum('day_value'), 2),
                'day_count' => $dayRows->count(),
                'days' => $dayRows->map(fn (array $row) => [
                    'date' => $row['date'],
                    'day_value' => (float) $row['day_value'],
                    'session' => $row['session'],
                ])->values()->all(),
            ];
        } catch (ValidationException $exception) {
            return [
                'valid' => false,
                'working_days' => round((float) $dayRows->sum('day_value'), 2),
                'day_count' => $dayRows->count(),
                'errors' => $exception->errors(),
            ];
        }
    }

    private function assertNoOverlap(Employee $employee, string $fromDate, string $toDate, Collection $dayRows): void
    {
        $dates = $dayRows->pluck('date')->all();

        $overlap = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
            ->where(function ($query) use ($fromDate, $toDate) {
                $query->whereDate('from_date', '<=', $toDate)
                    ->whereDate('to_date', '>=', $fromDate);
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'from_date' => ['Leave dates overlap with an existing pending or approved request.'],
            ]);
        }

        foreach ($dates as $date) {
            $approved = $this->approvedLeaveOnDate($employee, $date);

            if ($approved) {
                throw ValidationException::withMessages([
                    'from_date' => ["Leave already approved on {$date}."],
                ]);
            }
        }
    }

    private function assertValidHourlyDuration(LeaveType $leaveType, ?int $durationMinutes): void
    {
        if (! $durationMinutes || $durationMinutes <= 0) {
            throw ValidationException::withMessages([
                'duration_minutes' => ['Select how many hours of leave you need.'],
            ]);
        }

        if (! in_array($durationMinutes, $leaveType->allowedHourlyDurations(), true)) {
            $options = collect($leaveType->allowedHourlyDurations())
                ->map(fn (int $minutes) => LeaveRequestDay::formatDurationLabel($minutes))
                ->join(', ');

            throw ValidationException::withMessages([
                'duration_minutes' => ["Allowed durations for {$leaveType->name}: {$options}."],
            ]);
        }

        if ($leaveType->max_days_per_request !== null && $durationMinutes > ($leaveType->max_days_per_request * 60)) {
            throw ValidationException::withMessages([
                'duration_minutes' => ["Maximum {$leaveType->max_days_per_request} hour(s) allowed per short leave request."],
            ]);
        }
    }

    private function resolveBalanceAmount(LeaveType $leaveType, Collection $dayRows): float
    {
        if ($leaveType->isHourlyLeave()) {
            return round($dayRows->sum(fn (array $row) => ((int) ($row['duration_minutes'] ?? 0)) / 60), 2);
        }

        return round($dayRows->sum('day_value'), 3);
    }

    private function hourlyDayValue(Employee $employee, int $durationMinutes): float
    {
        $employee->loadMissing('shift');
        $requiredMinutes = $employee->shift?->requiredWorkMinutes() ?? 540;

        if ($requiredMinutes <= 0) {
            $requiredMinutes = 540;
        }

        return round($durationMinutes / $requiredMinutes, 3);
    }

    private function assertPaidLeaveAllowed(Employee $employee, LeaveType $leaveType): void
    {
        if (! $leaveType->is_paid || ! $employee->restrictsPaidLeave()) {
            return;
        }

        throw ValidationException::withMessages([
            'leave_type_id' => [$employee->paidLeaveRestrictionLabel() ?? 'Paid leave is not available for your current employment status.'],
        ]);
    }

    private function assertLeaveTypeAssigned(Employee $employee, LeaveType $leaveType): void
    {
        if (! $this->leaveTypeService->isAssignedToEmployee($employee, $leaveType->id)) {
            throw ValidationException::withMessages([
                'leave_type_id' => ['This leave type is not assigned to the employee.'],
            ]);
        }
    }

    private function assertCanReview(User $user, LeaveRequest $request): void
    {
        if (! $this->belongsToCompany($request, (int) $user->company_id)) {
            throw new NotFoundHttpException('Leave request not found.');
        }

        if (! $user->canReviewLeaveRequest($request)) {
            throw new AccessDeniedHttpException('You are not allowed to review this leave request.');
        }
    }
}
