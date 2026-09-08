<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeComplianceField;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmployeeComplianceFieldService
{
    public function __construct(private EmployeeProfileApprovalService $approvalService) {}

    public function assertCanSubmit(Employee $employee, string $fieldType, ?User $user = null): void
    {
        $existing = $this->findForType($employee, $fieldType);

        if (! $existing) {
            return;
        }

        if (in_array($existing->status, ['rejected', 'approved'], true)) {
            return;
        }

        if ($user && $this->approvalService->shouldAutoApprove($user, $employee)) {
            return;
        }

        throw ValidationException::withMessages([
            'field_type' => ['This compliance field is pending approval and cannot be changed.'],
        ]);
    }

    public function submit(User $user, Employee $employee, string $fieldType, string $value): array
    {
        $autoApproved = $this->approvalService->shouldAutoApprove($user, $employee);
        $this->assertCanSubmit($employee, $fieldType, $user);

        $value = $this->normalizeValue($fieldType, $value);

        return DB::transaction(function () use ($user, $employee, $fieldType, $value, $autoApproved) {
            $existing = $this->findForType($employee, $fieldType);
            $isResubmit = $existing && in_array($existing->status, ['rejected', 'approved'], true);
            $isChange = $existing && $existing->status === 'approved';

            $payload = [
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'field_type' => $fieldType,
                'value' => $value,
                'submitted_by_user_id' => $user->id,
                'submitted_at' => now(),
                ...$this->approvalService->submissionMeta($user, $employee),
            ];

            if ($existing) {
                $existing->update($payload);
                $field = $existing->fresh()->load(['submittedBy.role', 'reviewedBy', 'employee']);
            } else {
                $field = EmployeeComplianceField::create($payload)
                    ->load(['submittedBy.role', 'reviewedBy', 'employee']);
            }

            if ($field->status === 'approved') {
                $column = $field->employeeColumn();

                if ($column) {
                    $field->employee->update([$column => $field->value]);
                }
            }

            return [
                'compliance_field' => $field,
                'is_resubmit' => $isResubmit,
                'is_change' => $isChange,
                'auto_approved' => $autoApproved,
            ];
        });
    }

    public function pendingForReviewer(User $user): Collection
    {
        if (! $user->canReviewEmployeeDocuments()) {
            return collect();
        }

        return EmployeeComplianceField::query()
            ->with(['employee', 'submittedBy.role', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->where('status', 'pending')
            ->latest('submitted_at')
            ->get()
            ->filter(fn (EmployeeComplianceField $field) => $user->canReviewComplianceField($field))
            ->values();
    }

    public function approve(User $user, EmployeeComplianceField $field, ?string $notes = null): EmployeeComplianceField
    {
        $this->assertCanReview($user, $field);

        if ($field->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending compliance fields can be approved.'],
            ]);
        }

        DB::transaction(function () use ($user, $field, $notes) {
            $field->update([
                'status' => 'approved',
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(),
                'notes' => $notes ? trim($notes) : null,
            ]);

            $column = $field->employeeColumn();

            if ($column) {
                $field->employee->update([$column => $field->value]);
            }
        });

        return $field->fresh()->load(['employee', 'submittedBy.role', 'reviewedBy']);
    }

    public function reject(User $user, EmployeeComplianceField $field, string $notes): EmployeeComplianceField
    {
        $this->assertCanReview($user, $field);

        if ($field->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending compliance fields can be rejected.'],
            ]);
        }

        $field->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'notes' => $notes,
        ]);

        return $field->fresh()->load(['employee', 'submittedBy.role', 'reviewedBy']);
    }

    public function assertBelongsToCompany(User $user, EmployeeComplianceField $field): void
    {
        if ((int) $field->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Compliance field not found.');
        }
    }

    private function assertCanReview(User $user, EmployeeComplianceField $field): void
    {
        $this->assertBelongsToCompany($user, $field);

        if (! $user->canReviewComplianceField($field)) {
            throw new AccessDeniedHttpException('You are not allowed to review this compliance field.');
        }
    }

    private function findForType(Employee $employee, string $fieldType): ?EmployeeComplianceField
    {
        return EmployeeComplianceField::query()
            ->where('employee_id', $employee->id)
            ->where('field_type', $fieldType)
            ->first();
    }

    private function normalizeValue(string $fieldType, string $value): string
    {
        $value = trim($value);

        return match ($fieldType) {
            'pan' => strtoupper($value),
            default => $value,
        };
    }
}
