<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestDay;
use App\Models\User;
use App\Models\WfhRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WfhRequestService
{
    public function __construct(
        private EmployeeAccessService $employeeAccessService,
        private AttendancePolicyService $attendancePolicyService,
        private PortalStartService $portalStartService,
        private LeaveRequestService $leaveRequestService,
        private WfhAttachmentService $attachmentService,
        private ActivityLogService $activityLogService,
        private WorkflowNotificationService $workflowNotificationService,
    ) {}

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = WfhRequest::query()
            ->with(['employee', 'appliedBy', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->latest();

        if ($user->canViewAllWfhRequests()) {
            if (! empty($filters['employee_id'])) {
                $query->where('employee_id', (int) $filters['employee_id']);
            }
        } else {
            $employee = $this->employeeAccessService->linkedEmployee($user);

            if (! $employee) {
                throw new AccessDeniedHttpException('No employee profile is linked to your account.');
            }

            $visibleEmployeeIds = array_values(array_unique([
                ...$this->employeeAccessService->subordinateIdsForUser($user),
                $employee->id,
            ]));

            $query->whereIn('employee_id', $visibleEmployeeIds);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['year'])) {
            $year = (int) $filters['year'];
            $query->where(function ($builder) use ($year) {
                $builder->whereYear('from_date', $year)
                    ->orWhereYear('to_date', $year);
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function pendingForReviewer(User $user): Collection
    {
        return WfhRequest::query()
            ->with(['employee.user', 'appliedBy', 'attachments'])
            ->where('company_id', $user->company_id)
            ->where('status', WfhRequest::STATUS_PENDING)
            ->orderBy('from_date')
            ->get()
            ->filter(fn (WfhRequest $request) => $user->canReviewWfhRequest($request))
            ->values();
    }

    public function previewApplication(User $user, array $data): array
    {
        $employee = $this->resolveApplicableEmployee($user, $data['employee_id'] ?? null);
        $fromDate = Carbon::parse($data['from_date'])->toDateString();
        $toDate = Carbon::parse($data['to_date'])->toDateString();
        $workingDays = $this->workingDays($employee, $fromDate, $toDate);

        try {
            $this->assertValidApplication($employee, $fromDate, $toDate, $workingDays);

            return [
                'valid' => true,
                'working_days' => $workingDays->count(),
                'day_count' => $workingDays->count(),
                'days' => $workingDays->map(fn (string $date) => [
                    'date' => $date,
                    'day_value' => 1.0,
                ])->values()->all(),
            ];
        } catch (ValidationException $exception) {
            return [
                'valid' => false,
                'working_days' => $workingDays->count(),
                'day_count' => $workingDays->count(),
                'errors' => $exception->errors(),
            ];
        }
    }

    public function create(User $user, array $data, array $files = []): WfhRequest
    {
        $request = DB::transaction(function () use ($user, $data, $files) {
            $employee = $this->resolveApplicableEmployee($user, $data['employee_id'] ?? null);
            $fromDate = Carbon::parse($data['from_date'])->toDateString();
            $toDate = Carbon::parse($data['to_date'])->toDateString();
            $workingDays = $this->workingDays($employee, $fromDate, $toDate);

            $this->assertValidApplication($employee, $fromDate, $toDate, $workingDays);

            $request = WfhRequest::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'applied_by_user_id' => $user->id,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'total_days' => $workingDays->count(),
                'reason' => trim($data['reason']),
                'status' => WfhRequest::STATUS_PENDING,
            ]);

            if ($files !== []) {
                $this->attachmentService->storeMany($request, $employee, $files);
            }

            $this->activityLogService->logWorkflowRequest(
                $user,
                'wfh_request',
                $request,
                (int) $employee->id,
                'submitted',
                'Work From Home request submitted.',
                null,
                request(),
                ['from_date' => $fromDate, 'to_date' => $toDate],
            );

            return $request->fresh(['employee', 'appliedBy', 'attachments']);
        });

        $this->workflowNotificationService->notifyWfhSubmitted($request, $user);

        return $request;
    }

    public function approve(User $user, WfhRequest $request, ?string $notes = null): WfhRequest
    {
        $this->assertCanReview($user, $request);

        if ($request->status !== WfhRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be approved.',
            ]);
        }

        $request->update([
            'status' => WfhRequest::STATUS_APPROVED,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $notes ? trim($notes) : null,
        ]);

        $fresh = $request->fresh(['employee', 'appliedBy', 'reviewedBy', 'attachments']);

        $this->activityLogService->logWorkflowRequest(
            $user,
            'wfh_request',
            $fresh,
            (int) $fresh->employee_id,
            'approved',
            'Work From Home request approved.',
            $notes ? trim($notes) : null,
            request(),
        );

        $this->workflowNotificationService->notifyWfhDecision($fresh, $user, 'approved');

        return $fresh;
    }

    public function reject(User $user, WfhRequest $request, string $notes): WfhRequest
    {
        $this->assertCanReview($user, $request);

        if ($request->status !== WfhRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be rejected.',
            ]);
        }

        $request->update([
            'status' => WfhRequest::STATUS_REJECTED,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => trim($notes),
        ]);

        $fresh = $request->fresh(['employee', 'appliedBy', 'reviewedBy', 'attachments']);

        $this->activityLogService->logWorkflowRequest(
            $user,
            'wfh_request',
            $fresh,
            (int) $fresh->employee_id,
            'rejected',
            'Work From Home request rejected.',
            trim($notes),
            request(),
        );

        $this->workflowNotificationService->notifyWfhDecision($fresh, $user, 'rejected');

        return $fresh;
    }

    public function cancel(User $user, WfhRequest $request): WfhRequest
    {
        if (! $user->canCancelWfhRequest($request)) {
            throw new AccessDeniedHttpException('You are not allowed to cancel this request.');
        }

        if ($request->status !== WfhRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be cancelled.',
            ]);
        }

        $request->update([
            'status' => WfhRequest::STATUS_CANCELLED,
        ]);

        return $request->fresh(['employee', 'appliedBy', 'reviewedBy', 'attachments']);
    }

    public function approvedOnDate(Employee $employee, string $date): ?WfhRequest
    {
        return WfhRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', WfhRequest::STATUS_APPROVED)
            ->whereDate('from_date', '<=', $date)
            ->whereDate('to_date', '>=', $date)
            ->first();
    }

    /** @return Collection<string, WfhRequest> */
    public function approvedDatesForRange(Employee $employee, string $fromDate, string $toDate): Collection
    {
        $requests = WfhRequest::query()
            ->with(['reviewedBy'])
            ->where('employee_id', $employee->id)
            ->where('status', WfhRequest::STATUS_APPROVED)
            ->whereDate('from_date', '<=', $toDate)
            ->whereDate('to_date', '>=', $fromDate)
            ->get();

        $map = collect();

        foreach ($requests as $request) {
            $rangeStart = max($fromDate, $request->from_date->toDateString());
            $rangeEnd = min($toDate, $request->to_date->toDateString());

            foreach (CarbonPeriod::create($rangeStart, $rangeEnd) as $date) {
                $dateString = $date->toDateString();

                if ($this->isNonWorkingDay($employee, $dateString)) {
                    continue;
                }

                $map->put($dateString, $request);
            }
        }

        return $map;
    }

    public function wfhApprovalMeta(?WfhRequest $request): array
    {
        return [
            'wfh_request_id' => $request?->id,
            'wfh_approved_by_name' => $request?->reviewedBy?->name,
            'wfh_approved_at_label' => $request?->reviewed_at?->format('d M Y, h:i A'),
        ];
    }

    private function assertValidApplication(
        Employee $employee,
        string $fromDate,
        string $toDate,
        Collection $workingDays,
    ): void {
        if ($toDate < $fromDate) {
            throw ValidationException::withMessages([
                'to_date' => ['End date cannot be before start date.'],
            ]);
        }

        if ($workingDays->isEmpty()) {
            throw ValidationException::withMessages([
                'from_date' => ['No valid working days found in the selected range.'],
            ]);
        }

        $this->assertNoWfhOverlap($employee, $fromDate, $toDate);
        $this->assertNoLeaveOverlap($employee, $workingDays);
    }

    private function assertNoWfhOverlap(Employee $employee, string $fromDate, string $toDate): void
    {
        $overlap = WfhRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [WfhRequest::STATUS_PENDING, WfhRequest::STATUS_APPROVED])
            ->whereDate('from_date', '<=', $toDate)
            ->whereDate('to_date', '>=', $fromDate)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'from_date' => ['WFH dates overlap with an existing pending or approved request.'],
            ]);
        }
    }

    private function assertNoLeaveOverlap(Employee $employee, Collection $workingDays): void
    {
        foreach ($workingDays as $date) {
            if ($this->leaveRequestService->approvedLeaveDayOnDate($employee, $date)) {
                throw ValidationException::withMessages([
                    'from_date' => ["Approved leave already exists on {$date}."],
                ]);
            }

            $pendingLeave = LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->where('status', LeaveRequest::STATUS_PENDING)
                ->whereDate('from_date', '<=', $date)
                ->whereDate('to_date', '>=', $date)
                ->exists();

            if ($pendingLeave) {
                throw ValidationException::withMessages([
                    'from_date' => ["A pending leave request already exists on {$date}."],
                ]);
            }
        }
    }

    /** @return Collection<int, string> */
    private function workingDays(Employee $employee, string $fromDate, string $toDate): Collection
    {
        if ($fromDate === $toDate) {
            return $this->isEligibleWorkingDay($employee, $fromDate)
                ? collect([$fromDate])
                : collect();
        }

        $days = collect();

        foreach (CarbonPeriod::create($fromDate, $toDate) as $date) {
            $dateString = $date->toDateString();

            if ($this->isEligibleWorkingDay($employee, $dateString)) {
                $days->push($dateString);
            }
        }

        return $days;
    }

    private function isEligibleWorkingDay(Employee $employee, string $date): bool
    {
        if ($this->portalStartService->isBeforeAttendanceTracking($employee, $date)) {
            return false;
        }

        return ! $this->isNonWorkingDay($employee, $date);
    }

    private function isNonWorkingDay(Employee $employee, string $date): bool
    {
        if ($this->attendancePolicyService->holidayOnDate($employee->company_id, $date)) {
            return true;
        }

        $weeklyOff = $this->attendancePolicyService->weeklyOffWeekdaysForEmployee($employee);

        return $this->attendancePolicyService->isWeeklyOff($date, $weeklyOff);
    }

    private function resolveApplicableEmployee(User $user, ?int $employeeId): Employee
    {
        if ($employeeId) {
            if (! $user->canViewAllWfhRequests()) {
                throw new AccessDeniedHttpException('You cannot apply WFH for other employees.');
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

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee) {
            throw new AccessDeniedHttpException('No employee profile is linked to your account.');
        }

        if ((int) $employee->company_id !== (int) $user->company_id) {
            throw new AccessDeniedHttpException('Employee not found in your company.');
        }

        return $employee;
    }

    private function assertCanReview(User $user, WfhRequest $request): void
    {
        if (! $user->canReviewWfhRequest($request)) {
            throw new AccessDeniedHttpException('You are not allowed to review this request.');
        }
    }
}
