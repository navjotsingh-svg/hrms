<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseGroup;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExpenseGroupService
{
    public function __construct(
        private EmployeeAccessService $employeeAccessService,
        private ExpenseAttachmentService $attachmentService,
    ) {}

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = ExpenseGroup::query()
            ->with(['employee', 'reviewedBy', 'expenses.expenseType'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('created_at');

        $this->applyVisibilityScope($user, $query, $filters['belongs_to'] ?? null);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                        $employeeQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_code', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function draftOptionsForUser(User $user): Collection
    {
        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee) {
            return collect();
        }

        return ExpenseGroup::query()
            ->where('company_id', $user->company_id)
            ->where('employee_id', $employee->id)
            ->where('status', ExpenseGroup::STATUS_DRAFT)
            ->orderByDesc('created_at')
            ->get(['id', 'name']);
    }

    public function pendingForReviewer(User $user): Collection
    {
        return ExpenseGroup::query()
            ->with(['employee.user', 'expenses.expenseType'])
            ->where('company_id', $user->company_id)
            ->where('status', ExpenseGroup::STATUS_PENDING)
            ->orderBy('from_date')
            ->get()
            ->filter(fn (ExpenseGroup $group) => $user->canReviewExpenseGroup($group))
            ->values();
    }

    public function store(User $user, array $data): ExpenseGroup
    {
        $employee = $this->requireOwnEmployee($user);

        if ($data['to_date'] < $data['from_date']) {
            throw ValidationException::withMessages([
                'to_date' => 'End date must be on or after start date.',
            ]);
        }

        return ExpenseGroup::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'from_date' => $data['from_date'],
            'to_date' => $data['to_date'],
            'travel_advance_amount' => $data['travel_advance_amount'] ?? 0,
            'status' => ExpenseGroup::STATUS_DRAFT,
            'submitted_by_user_id' => $user->id,
        ])->load(['employee', 'expenses.expenseType']);
    }

    public function update(User $user, ExpenseGroup $group, array $data): ExpenseGroup
    {
        if (! $user->canEditOwnExpenseGroup($group)) {
            throw new AccessDeniedHttpException('You are not allowed to edit this expense group.');
        }

        if ($data['to_date'] < $data['from_date']) {
            throw ValidationException::withMessages([
                'to_date' => 'End date must be on or after start date.',
            ]);
        }

        $group->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'from_date' => $data['from_date'],
            'to_date' => $data['to_date'],
            'travel_advance_amount' => $data['travel_advance_amount'] ?? 0,
        ]);

        return $group->fresh(['employee', 'expenses.expenseType', 'expenses.attachments', 'reviewedBy']);
    }

    public function addExpense(User $user, ExpenseGroup $group, array $data, array $files = []): Expense
    {
        $this->assertOwnDraftGroup($user, $group);
        $employee = $this->requireOwnEmployee($user);

        return DB::transaction(function () use ($user, $group, $employee, $data, $files) {
            $expense = Expense::create([
                'company_id' => $group->company_id,
                'employee_id' => $group->employee_id,
                'expense_group_id' => $group->id,
                'is_independent' => false,
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

            return $expense->load(['expenseType', 'attachments']);
        });
    }

    public function submit(User $user, ExpenseGroup $group): ExpenseGroup
    {
        $this->assertOwnDraftGroup($user, $group);

        $group->loadCount('expenses');

        if ($group->expenses_count < 1) {
            throw ValidationException::withMessages([
                'expenses' => 'Add at least one expense before submitting the group.',
            ]);
        }

        return DB::transaction(function () use ($user, $group) {
            Expense::query()
                ->where('expense_group_id', $group->id)
                ->where('status', Expense::STATUS_DRAFT)
                ->update([
                    'status' => Expense::STATUS_PENDING,
                    'submitted_by_user_id' => $user->id,
                ]);

            $group->update([
                'status' => ExpenseGroup::STATUS_PENDING,
                'submitted_by_user_id' => $user->id,
            ]);

            return $group->fresh(['employee', 'expenses.expenseType', 'expenses.attachments', 'reviewedBy']);
        });
    }

    public function approve(User $user, ExpenseGroup $group, ?string $notes = null): ExpenseGroup
    {
        $this->assertCanReview($user, $group);

        if ($group->status !== ExpenseGroup::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending expense groups can be approved.',
            ]);
        }

        return DB::transaction(function () use ($user, $group, $notes) {
            Expense::query()
                ->where('expense_group_id', $group->id)
                ->where('status', Expense::STATUS_PENDING)
                ->update([
                    'status' => Expense::STATUS_APPROVED,
                    'reviewed_by_user_id' => $user->id,
                    'reviewed_at' => now(),
                    'review_notes' => $notes,
                ]);

            $group->update([
                'status' => ExpenseGroup::STATUS_APPROVED,
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            return $group->fresh(['employee', 'expenses.expenseType', 'expenses.attachments', 'reviewedBy']);
        });
    }

    public function reject(User $user, ExpenseGroup $group, string $notes): ExpenseGroup
    {
        $this->assertCanReview($user, $group);

        if ($group->status !== ExpenseGroup::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending expense groups can be rejected.',
            ]);
        }

        return DB::transaction(function () use ($user, $group, $notes) {
            Expense::query()
                ->where('expense_group_id', $group->id)
                ->where('status', Expense::STATUS_PENDING)
                ->update([
                    'status' => Expense::STATUS_REJECTED,
                    'reviewed_by_user_id' => $user->id,
                    'reviewed_at' => now(),
                    'review_notes' => $notes,
                ]);

            $group->update([
                'status' => ExpenseGroup::STATUS_REJECTED,
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            return $group->fresh(['employee', 'expenses.expenseType', 'expenses.attachments', 'reviewedBy']);
        });
    }

    public function cancel(User $user, ExpenseGroup $group): ExpenseGroup
    {
        $this->assertOwnGroup($user, $group);

        if (! in_array($group->status, [ExpenseGroup::STATUS_DRAFT, ExpenseGroup::STATUS_PENDING], true)) {
            throw ValidationException::withMessages([
                'status' => 'This expense group cannot be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($group) {
            Expense::query()
                ->where('expense_group_id', $group->id)
                ->whereIn('status', [Expense::STATUS_DRAFT, Expense::STATUS_PENDING])
                ->update(['status' => Expense::STATUS_CANCELLED]);

            $group->update(['status' => ExpenseGroup::STATUS_CANCELLED]);

            return $group->fresh(['employee', 'expenses.expenseType', 'expenses.attachments', 'reviewedBy']);
        });
    }

    public function resolveForUser(User $user, ExpenseGroup $group): ExpenseGroup
    {
        if ((int) $group->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Expense group not found.');
        }

        if (! $user->canViewExpenseGroup($group)) {
            throw new AccessDeniedHttpException('You are not allowed to view this expense group.');
        }

        return $group->load(['employee', 'expenses.expenseType', 'expenses.attachments', 'reviewedBy']);
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

        if ($user->canApproveExpenses() && ($belongsTo === 'all' || $belongsTo === null)) {
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

    private function assertOwnDraftGroup(User $user, ExpenseGroup $group): void
    {
        $this->assertOwnGroup($user, $group);

        if ($group->status !== ExpenseGroup::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft expense groups can be edited.',
            ]);
        }
    }

    private function assertOwnGroup(User $user, ExpenseGroup $group): void
    {
        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee || (int) $employee->id !== (int) $group->employee_id) {
            throw new AccessDeniedHttpException('You can only manage your own expense groups.');
        }
    }

    private function assertCanReview(User $user, ExpenseGroup $group): void
    {
        if (! $user->canReviewExpenseGroup($group)) {
            throw new AccessDeniedHttpException('You are not allowed to review this expense group.');
        }
    }
}
