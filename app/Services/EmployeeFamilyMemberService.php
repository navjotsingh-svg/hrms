<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeFamilyMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmployeeFamilyMemberService
{
    public function __construct(private EmployeeProfileApprovalService $approvalService) {}

    public function submitMany(User $user, Employee $employee, array $members): array
    {
        $created = [];

        return DB::transaction(function () use ($user, $employee, $members, &$created) {
            foreach ($members as $memberData) {
                $created[] = $this->submitOne($user, $employee, $memberData);
            }

            return $created;
        });
    }

    public function submitOne(User $user, Employee $employee, array $data): array
    {
        $memberId = $data['id'] ?? null;

        if ($memberId) {
            return $this->resubmitExisting($user, $employee, (int) $memberId, $data);
        }

        return $this->createPending($user, $employee, $data);
    }

    public function pendingForReviewer(User $user): Collection
    {
        if (! $user->canReviewEmployeeDocuments()) {
            return collect();
        }

        return EmployeeFamilyMember::query()
            ->with(['employee', 'submittedBy.role', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->where('status', 'pending')
            ->latest('submitted_at')
            ->get()
            ->filter(fn (EmployeeFamilyMember $member) => $user->canReviewFamilyMember($member))
            ->values();
    }

    public function approve(User $user, EmployeeFamilyMember $member, ?string $notes = null): EmployeeFamilyMember
    {
        $this->assertCanReview($user, $member);

        if ($member->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending family members can be approved.'],
            ]);
        }

        $member->update([
            'status' => 'approved',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'notes' => $notes ? trim($notes) : null,
        ]);

        return $member->fresh()->load(['employee', 'submittedBy.role', 'reviewedBy']);
    }

    public function reject(User $user, EmployeeFamilyMember $member, string $notes): EmployeeFamilyMember
    {
        $this->assertCanReview($user, $member);

        if ($member->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending family members can be rejected.'],
            ]);
        }

        $member->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'notes' => $notes,
        ]);

        return $member->fresh()->load(['employee', 'submittedBy.role', 'reviewedBy']);
    }

    public function assertBelongsToCompany(User $user, EmployeeFamilyMember $member): void
    {
        if ((int) $member->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Family member not found.');
        }
    }

    private function createPending(User $user, Employee $employee, array $data): array
    {
        $autoApproved = $this->approvalService->shouldAutoApprove($user, $employee);
        $sortOrder = EmployeeFamilyMember::query()
            ->where('employee_id', $employee->id)
            ->max('sort_order');

        $member = EmployeeFamilyMember::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'name' => $data['name'],
            'relation' => $data['relation'],
            'phone' => $data['phone'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'sort_order' => ($sortOrder ?? -1) + 1,
            'submitted_by_user_id' => $user->id,
            'submitted_at' => now(),
            ...$this->approvalService->submissionMeta($user, $employee),
        ])->load(['submittedBy.role', 'reviewedBy', 'employee']);

        return [
            'family_member' => $member,
            'is_resubmit' => false,
            'is_change' => false,
            'auto_approved' => $autoApproved,
        ];
    }

    private function resubmitExisting(User $user, Employee $employee, int $memberId, array $data): array
    {
        $member = EmployeeFamilyMember::query()
            ->where('employee_id', $employee->id)
            ->where('id', $memberId)
            ->firstOrFail();

        $autoApproved = $this->approvalService->shouldAutoApprove($user, $employee);

        if ($member->status === 'pending' && ! $autoApproved) {
            throw ValidationException::withMessages([
                'id' => ['This family member is pending approval and cannot be changed.'],
            ]);
        }

        if ($member->status === 'pending' && $autoApproved) {
            $member->update([
                'name' => $data['name'],
                'relation' => $data['relation'],
                'phone' => $data['phone'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'submitted_by_user_id' => $user->id,
                'submitted_at' => now(),
                ...$this->approvalService->submissionMeta($user, $employee),
            ]);

            return [
                'family_member' => $member->fresh()->load(['submittedBy.role', 'reviewedBy', 'employee']),
                'is_resubmit' => false,
                'is_change' => true,
                'auto_approved' => true,
            ];
        }

        if (! $member->canBeResubmitted()) {
            throw ValidationException::withMessages([
                'id' => ['This family member cannot be re-submitted.'],
            ]);
        }

        $isChange = $member->status === 'approved';
        $wasApprovedEmergency = $isChange && (int) $employee->emergency_contact_family_member_id === (int) $member->id;

        $member->update([
            'name' => $data['name'],
            'relation' => $data['relation'],
            'phone' => $data['phone'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'submitted_by_user_id' => $user->id,
            'submitted_at' => now(),
            ...$this->approvalService->submissionMeta($user, $employee),
        ]);

        if ($wasApprovedEmergency && ! $autoApproved) {
            $employee->update([
                'emergency_contact_name' => null,
                'emergency_contact_phone' => null,
                'emergency_contact_relation' => null,
                'emergency_contact_family_member_id' => null,
            ]);
        }

        return [
            'family_member' => $member->fresh()->load(['submittedBy.role', 'reviewedBy', 'employee']),
            'is_resubmit' => ! $isChange,
            'is_change' => $isChange,
            'auto_approved' => $autoApproved,
        ];
    }

    private function assertCanReview(User $user, EmployeeFamilyMember $member): void
    {
        $this->assertBelongsToCompany($user, $member);

        if (! $user->canReviewFamilyMember($member)) {
            throw new AccessDeniedHttpException('You are not allowed to review this family member.');
        }
    }
}
