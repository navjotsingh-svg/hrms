<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ResignationRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ResignationRequestService
{
    public function __construct(
        private EmployeeAccessService $employeeAccessService,
        private ExitCaseService $exitCaseService,
        private ActivityLogService $activityLogService,
        private WorkflowNotificationService $workflowNotificationService,
    ) {}

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = ResignationRequest::query()
            ->with(['employee', 'appliedBy', 'reviewedBy', 'exitCase'])
            ->where('company_id', $user->company_id)
            ->latest();

        if ($user->canViewAllOffboardingRequests()) {
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

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function pendingForReviewer(User $user): Collection
    {
        return ResignationRequest::query()
            ->with(['employee.user', 'appliedBy'])
            ->where('company_id', $user->company_id)
            ->where('status', ResignationRequest::STATUS_PENDING)
            ->latest()
            ->get()
            ->filter(fn (ResignationRequest $request) => $user->canReviewResignationRequest($request))
            ->values();
    }

    public function create(User $user, array $data): ResignationRequest
    {
        $request = DB::transaction(function () use ($user, $data) {
            $employee = $this->resolveApplicableEmployee($user, $data['employee_id'] ?? null);
            $this->assertNoOpenResignation($employee);

            $proposedLwd = Carbon::parse($data['proposed_last_working_date'])->toDateString();

            if ($proposedLwd < now()->toDateString()) {
                throw ValidationException::withMessages([
                    'proposed_last_working_date' => ['Last working date cannot be in the past.'],
                ]);
            }

            $request = ResignationRequest::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'applied_by_user_id' => $user->id,
                'proposed_last_working_date' => $proposedLwd,
                'notice_period_days' => isset($data['notice_period_days']) ? (int) $data['notice_period_days'] : null,
                'reason' => trim($data['reason']),
                'status' => ResignationRequest::STATUS_PENDING,
            ]);

            $this->activityLogService->logWorkflowRequest(
                $user,
                'resignation_request',
                $request,
                (int) $employee->id,
                'submitted',
                'Resignation request submitted.',
                null,
                request(),
            );

            return $request->fresh(['employee', 'appliedBy']);
        });

        $this->workflowNotificationService->notifyResignationSubmitted($request, $user);

        return $request;
    }

    public function approve(User $user, ResignationRequest $request, ?string $notes = null, ?string $approvedLwd = null): ResignationRequest
    {
        $this->assertCanReview($user, $request);

        if ($request->status !== ResignationRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending resignation requests can be approved.',
            ]);
        }

        $lastWorkingDate = $approvedLwd
            ? Carbon::parse($approvedLwd)->toDateString()
            : $request->proposed_last_working_date->toDateString();

        DB::transaction(function () use ($user, $request, $notes, $lastWorkingDate) {
            $request->update([
                'status' => ResignationRequest::STATUS_APPROVED,
                'approved_last_working_date' => $lastWorkingDate,
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(),
                'review_notes' => $notes ? trim($notes) : null,
            ]);

            $this->exitCaseService->createFromResignation($request);

            $this->activityLogService->logWorkflowRequest(
                $user,
                'resignation_request',
                $request,
                (int) $request->employee_id,
                'approved',
                'Resignation request approved.',
                $notes ? trim($notes) : null,
                request(),
            );
        });

        $fresh = $request->fresh(['employee', 'appliedBy', 'reviewedBy', 'exitCase']);

        $this->workflowNotificationService->notifyResignationDecision($fresh, $user, 'approved');

        return $fresh;
    }

    public function reject(User $user, ResignationRequest $request, string $notes): ResignationRequest
    {
        $this->assertCanReview($user, $request);

        if ($request->status !== ResignationRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending resignation requests can be rejected.',
            ]);
        }

        $request->update([
            'status' => ResignationRequest::STATUS_REJECTED,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => trim($notes),
        ]);

        $fresh = $request->fresh(['employee', 'appliedBy', 'reviewedBy']);

        $this->activityLogService->logWorkflowRequest(
            $user,
            'resignation_request',
            $fresh,
            (int) $fresh->employee_id,
            'rejected',
            'Resignation request rejected.',
            trim($notes),
            request(),
        );

        $this->workflowNotificationService->notifyResignationDecision($fresh, $user, 'rejected');

        return $fresh;
    }

    public function cancel(User $user, ResignationRequest $request): ResignationRequest
    {
        if (! $user->canCancelResignationRequest($request)) {
            throw new AccessDeniedHttpException('You are not allowed to cancel this request.');
        }

        if ($request->status !== ResignationRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending resignation requests can be cancelled.',
            ]);
        }

        $request->update(['status' => ResignationRequest::STATUS_CANCELLED]);

        return $request->fresh(['employee', 'appliedBy', 'reviewedBy']);
    }

    private function resolveApplicableEmployee(User $user, ?int $employeeId): Employee
    {
        if ($employeeId !== null) {
            throw new AccessDeniedHttpException('You cannot submit resignation for other employees.');
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee) {
            throw new AccessDeniedHttpException('No employee profile is linked to your account.');
        }

        if ($employee->status !== 'active') {
            throw ValidationException::withMessages([
                'employee_id' => ['Only active employees can submit resignation.'],
            ]);
        }

        return $employee;
    }

    private function assertNoOpenResignation(Employee $employee): void
    {
        $exists = ResignationRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [ResignationRequest::STATUS_PENDING, ResignationRequest::STATUS_APPROVED])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'reason' => ['You already have an open resignation request.'],
            ]);
        }
    }

    private function assertCanReview(User $user, ResignationRequest $request): void
    {
        if (! $user->canReviewResignationRequest($request)) {
            throw new AccessDeniedHttpException('You are not allowed to review this request.');
        }
    }
}
