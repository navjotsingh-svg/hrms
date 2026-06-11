<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeePaymentMethod;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmployeePaymentMethodService
{
    public function __construct(private EmployeeProfileApprovalService $approvalService) {}

    public function assertCanSubmit(Employee $employee, string $paymentMode, ?User $user = null): void
    {
        $existing = $this->findForMode($employee, $paymentMode);

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
            'payment_mode' => ['This payment option is pending approval and cannot be changed.'],
        ]);
    }

    public function submit(User $user, Employee $employee, array $data): array
    {
        $autoApproved = $this->approvalService->shouldAutoApprove($user, $employee);
        $paymentMode = $data['payment_mode'];
        $this->assertCanSubmit($employee, $paymentMode, $user);

        if ($paymentMode !== 'bank_transfer') {
            $data['bank_name'] = null;
            $data['bank_branch'] = null;
            $data['bank_address'] = null;
            $data['account_holder_name'] = null;
            $data['account_number'] = null;
            $data['ifsc_code'] = null;
        }

        return DB::transaction(function () use ($user, $employee, $data, $paymentMode, $autoApproved) {
            $existing = $this->findForMode($employee, $paymentMode);
            $isResubmit = $existing && in_array($existing->status, ['rejected', 'approved'], true);
            $isChange = $existing && $existing->status === 'approved';

            $payload = [
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'payment_mode' => $paymentMode,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_branch' => $data['bank_branch'] ?? null,
                'bank_address' => $data['bank_address'] ?? null,
                'account_holder_name' => $data['account_holder_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'ifsc_code' => isset($data['ifsc_code']) ? strtoupper($data['ifsc_code']) : null,
                'submitted_by_user_id' => $user->id,
                'submitted_at' => now(),
                ...$this->approvalService->submissionMeta($user, $employee),
            ];

            if ($existing) {
                $existing->update($payload);
                $method = $existing->fresh()->load(['submittedBy.role', 'reviewedBy', 'employee']);
            } else {
                $method = EmployeePaymentMethod::create($payload)
                    ->load(['submittedBy.role', 'reviewedBy', 'employee']);
            }

            return [
                'payment_method' => $method,
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

        return EmployeePaymentMethod::query()
            ->with(['employee', 'submittedBy.role', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->where('status', 'pending')
            ->latest('submitted_at')
            ->get()
            ->filter(fn (EmployeePaymentMethod $method) => $user->canReviewPaymentMethod($method))
            ->values();
    }

    public function approve(User $user, EmployeePaymentMethod $method): EmployeePaymentMethod
    {
        $this->assertCanReview($user, $method);

        if ($method->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending payment options can be approved.'],
            ]);
        }

        $method->update([
            'status' => 'approved',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'notes' => null,
        ]);

        return $method->fresh()->load(['employee', 'submittedBy.role', 'reviewedBy']);
    }

    public function reject(User $user, EmployeePaymentMethod $method, string $notes): EmployeePaymentMethod
    {
        $this->assertCanReview($user, $method);

        if ($method->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending payment options can be rejected.'],
            ]);
        }

        $method->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'notes' => $notes,
        ]);

        return $method->fresh()->load(['employee', 'submittedBy.role', 'reviewedBy']);
    }

    public function assertBelongsToCompany(User $user, EmployeePaymentMethod $method): void
    {
        if ((int) $method->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Payment option not found.');
        }
    }

    private function assertCanReview(User $user, EmployeePaymentMethod $method): void
    {
        $this->assertBelongsToCompany($user, $method);

        if (! $user->canReviewPaymentMethod($method)) {
            throw new AccessDeniedHttpException('You are not allowed to review this payment option.');
        }
    }

    private function findForMode(Employee $employee, string $paymentMode): ?EmployeePaymentMethod
    {
        return EmployeePaymentMethod::query()
            ->where('employee_id', $employee->id)
            ->where('payment_mode', $paymentMode)
            ->first();
    }
}
