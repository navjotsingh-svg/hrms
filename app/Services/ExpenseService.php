<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseGroup;
use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExpenseService
{
    public function __construct(
        private EmployeeAccessService $employeeAccessService,
        private ExpenseAttachmentService $attachmentService,
        private ActivityLogService $activityLogService,
    ) {}

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Expense::query()
            ->with(['employee', 'expenseType', 'expenseGroup', 'reviewedBy', 'attachments'])
            ->where('company_id', $user->company_id)
            ->where('is_independent', true)
            ->orderByDesc('expense_date')
            ->orderByDesc('created_at');

        $this->applyVisibilityScope($user, $query, $filters['belongs_to'] ?? null);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->whereHas('expenseType', fn ($typeQuery) => $typeQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                        $employeeQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_code', 'like', "%{$search}%");
                    })
                    ->orWhere('merchant', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function pendingIndependentForReviewer(User $user): Collection
    {
        return Expense::query()
            ->with(['employee.user', 'expenseType', 'attachments'])
            ->where('company_id', $user->company_id)
            ->where('is_independent', true)
            ->where('status', Expense::STATUS_PENDING)
            ->orderBy('expense_date')
            ->get()
            ->filter(fn (Expense $expense) => $user->canReviewExpense($expense))
            ->values();
    }

    public function storeIndependent(User $user, array $data, array $files = []): Expense
    {
        $employee = $this->requireOwnEmployee($user);

        return DB::transaction(function () use ($user, $employee, $data, $files) {
            $expense = Expense::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'is_independent' => true,
                'expense_date' => $data['expense_date'],
                'merchant' => $data['merchant'] ?? null,
                'expense_type_id' => $data['expense_type_id'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'claim_reimbursement' => (bool) ($data['claim_reimbursement'] ?? true),
                'status' => Expense::STATUS_DRAFT,
                'submitted_by_user_id' => $user->id,
            ]);

            if ($files !== []) {
                $this->attachmentService->storeMany($expense, $employee, $files);
            }

            if (! empty($data['submit'])) {
                return $this->submitIndependent($user, $expense->fresh(['employee', 'expenseType', 'attachments']));
            }

            return $expense->fresh(['employee', 'expenseType', 'attachments']);
        });
    }

    public function submitIndependent(User $user, Expense $expense): Expense
    {
        $this->assertOwnIndependentExpense($user, $expense);

        if ($expense->status !== Expense::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft expenses can be submitted.',
            ]);
        }

        $expense->update([
            'status' => Expense::STATUS_PENDING,
            'submitted_by_user_id' => $user->id,
        ]);

        $fresh = $expense->fresh(['employee', 'expenseType', 'attachments', 'reviewedBy']);

        $this->activityLogService->logWorkflowRequest(
            $user,
            'expense',
            $fresh,
            (int) $fresh->employee_id,
            'submitted',
            'Expense claim submitted.',
            null,
            request(),
            ['amount' => $fresh->amount],
        );

        return $fresh;
    }

    public function approve(User $user, Expense $expense, ?string $notes = null): Expense
    {
        $this->assertCanReview($user, $expense);

        if ($expense->status !== Expense::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending expenses can be approved.',
            ]);
        }

        $expense->update([
            'status' => Expense::STATUS_APPROVED,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        $fresh = $expense->fresh(['employee', 'expenseType', 'attachments', 'reviewedBy']);

        $this->activityLogService->logWorkflowRequest(
            $user,
            'expense',
            $fresh,
            (int) $fresh->employee_id,
            'approved',
            'Expense claim approved.',
            $notes,
            request(),
        );

        return $fresh;
    }

    public function reject(User $user, Expense $expense, string $notes): Expense
    {
        $this->assertCanReview($user, $expense);

        if ($expense->status !== Expense::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending expenses can be rejected.',
            ]);
        }

        $expense->update([
            'status' => Expense::STATUS_REJECTED,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        $fresh = $expense->fresh(['employee', 'expenseType', 'attachments', 'reviewedBy']);

        $this->activityLogService->logWorkflowRequest(
            $user,
            'expense',
            $fresh,
            (int) $fresh->employee_id,
            'rejected',
            'Expense claim rejected.',
            $notes,
            request(),
        );

        return $fresh;
    }

    public function markAsPaid(User $user, Expense $expense): Expense
    {
        if (! $user->canMarkExpensePaid($expense)) {
            throw new AccessDeniedHttpException('You are not allowed to mark this expense as paid.');
        }

        $expense->update([
            'payout_status' => Expense::PAYOUT_PAID,
            'paid_at' => now(),
        ]);

        $fresh = $expense->fresh(['employee', 'expenseType', 'expenseGroup', 'attachments', 'reviewedBy']);

        $this->activityLogService->logWorkflowRequest(
            $user,
            'expense',
            $fresh,
            (int) $fresh->employee_id,
            'paid',
            'Expense reimbursement marked as paid.',
            null,
            request(),
            ['amount' => $fresh->amount],
        );

        return $fresh;
    }

    public function cancel(User $user, Expense $expense): Expense
    {
        $this->assertOwnIndependentExpense($user, $expense);

        if (! in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_PENDING], true)) {
            throw ValidationException::withMessages([
                'status' => 'This expense cannot be cancelled.',
            ]);
        }

        $expense->update(['status' => Expense::STATUS_CANCELLED]);

        return $expense->fresh(['employee', 'expenseType', 'attachments', 'reviewedBy']);
    }

    public function updateExpense(User $user, Expense $expense, array $data, array $files = []): Expense
    {
        if (! $user->canEditOwnExpense($expense)) {
            throw new AccessDeniedHttpException('You are not allowed to edit this expense.');
        }

        $employee = $this->requireOwnEmployee($user);

        $expense->update([
            'expense_date' => $data['expense_date'],
            'merchant' => $data['merchant'] ?? null,
            'expense_type_id' => $data['expense_type_id'],
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'claim_reimbursement' => (bool) ($data['claim_reimbursement'] ?? true),
        ]);

        if ($files !== []) {
            $this->attachmentService->storeMany($expense, $employee, $files);
        }

        return $expense->fresh(['employee', 'expenseType', 'expenseGroup', 'attachments', 'reviewedBy']);
    }

    /** @deprecated Use updateExpense() */
    public function updateIndependent(User $user, Expense $expense, array $data, array $files = []): Expense
    {
        return $this->updateExpense($user, $expense, $data, $files);
    }

    public function resolveForUser(User $user, Expense $expense): Expense
    {
        if ((int) $expense->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Expense not found.');
        }

        if (! $user->canViewExpense($expense)) {
            throw new AccessDeniedHttpException('You are not allowed to view this expense.');
        }

        return $expense->load(['employee', 'expenseType', 'expenseGroup', 'attachments', 'reviewedBy']);
    }

    public function pendingReimbursementTotal(Employee $employee, int $year, int $month): float
    {
        return (float) $this->reimbursableForPayrollQuery($employee, $year, $month)->sum('amount');
    }

    public function markPaidForPayroll(PayrollPeriod $period, Employee $employee): void
    {
        $this->reimbursableForPayrollQuery($employee, $period->year, $period->month)
            ->update([
                'payout_status' => Expense::PAYOUT_PAID,
                'payroll_period_id' => $period->id,
                'paid_at' => now(),
            ]);
    }

    /**
     * Approved reimbursable expenses included in a payroll month when either:
     * - the expense date falls in that month, or
     * - the expense was approved (reviewed) in that month.
     */
    private function reimbursableForPayrollQuery(Employee $employee, int $year, int $month)
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $end = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        return Expense::query()
            ->where('employee_id', $employee->id)
            ->where('status', Expense::STATUS_APPROVED)
            ->where('claim_reimbursement', true)
            ->where('payout_status', Expense::PAYOUT_UNPAID)
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($dateQuery) use ($start, $end) {
                    $dateQuery->whereDate('expense_date', '>=', $start)
                        ->whereDate('expense_date', '<=', $end);
                })->orWhere(function ($reviewQuery) use ($start, $end) {
                    $reviewQuery->whereNotNull('reviewed_at')
                        ->whereDate('reviewed_at', '>=', $start)
                        ->whereDate('reviewed_at', '<=', $end);
                });
            });
    }

    public function releasePayrollPeriod(PayrollPeriod $period): void
    {
        Expense::query()
            ->where('payroll_period_id', $period->id)
            ->update([
                'payout_status' => Expense::PAYOUT_UNPAID,
                'payroll_period_id' => null,
                'paid_at' => null,
            ]);
    }

    public function exportRows(User $user, array $filters = []): Collection
    {
        $filters['per_page'] = 1000;

        return collect($this->listForUser($user, $filters)->items());
    }

    private function applyVisibilityScope(User $user, $query, ?string $belongsTo): void
    {
        if ($user->canViewAllExpenses()) {
            if ($belongsTo === 'myself') {
                $employee = $this->employeeAccessService->linkedEmployee($user);

                if ($employee) {
                    $query->where('employee_id', $employee->id);
                }
            } elseif ($belongsTo === 'reportees') {
                $subordinateIds = $this->employeeAccessService->subordinateIdsForUser($user);

                if ($subordinateIds === []) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('employee_id', $subordinateIds);
                }
            }

            return;
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee) {
            throw new AccessDeniedHttpException('No employee profile linked to your account.');
        }

        if ($user->canApproveExpenses() && $belongsTo === 'reportees') {
            $subordinateIds = $this->employeeAccessService->subordinateIdsForUser($user);
            $query->whereIn('employee_id', $subordinateIds);

            return;
        }

        if ($user->canApproveExpenses() && $belongsTo === 'all') {
            $subordinateIds = $this->employeeAccessService->subordinateIdsForUser($user);
            $visibleIds = array_values(array_unique([...$subordinateIds, $employee->id]));
            $query->whereIn('employee_id', $visibleIds);

            return;
        }

        $query->where('employee_id', $employee->id);
    }

    private function requireOwnEmployee(User $user): Employee
    {
        if (! $user->canApplyExpenses()) {
            throw new AccessDeniedHttpException('You are not allowed to submit expenses.');
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee) {
            throw new AccessDeniedHttpException('No employee profile linked to your account.');
        }

        return $employee;
    }

    private function assertOwnIndependentExpense(User $user, Expense $expense): void
    {
        if (! $expense->is_independent) {
            throw ValidationException::withMessages([
                'expense' => 'This expense belongs to a group and must be managed through the group.',
            ]);
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee || (int) $employee->id !== (int) $expense->employee_id) {
            throw new AccessDeniedHttpException('You can only manage your own expenses.');
        }
    }

    private function assertCanReview(User $user, Expense $expense): void
    {
        if (! $user->canReviewExpense($expense)) {
            throw new AccessDeniedHttpException('You are not allowed to review this expense.');
        }
    }
}
