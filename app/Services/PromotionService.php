<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PromotionNomination;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PromotionService
{
    public function __construct(private EmployeeAccessService $employeeAccessService) {}

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = PromotionNomination::query()
            ->with(['employee', 'reviewCycle'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('created_at');

        if (! $user->canManagePerformance()) {
            $employee = $this->employeeAccessService->linkedEmployee($user);

            if (! $employee) {
                $query->whereRaw('1 = 0');
            } else {
                $subordinateIds = $this->employeeAccessService->subordinateIdsForUser($user);
                $visibleIds = array_values(array_unique([$employee->id, ...$subordinateIds]));
                $query->whereIn('employee_id', $visibleIds);
            }
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('proposed_designation', 'like', "%{$search}%")
                    ->orWhere('current_designation', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                        $employeeQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function store(User $user, array $data): PromotionNomination
    {
        $this->assertCanNominate($user);

        $employee = Employee::query()
            ->where('company_id', $user->company_id)
            ->findOrFail($data['employee_id']);

        return PromotionNomination::create([
            'company_id' => $user->company_id,
            'employee_id' => $employee->id,
            'current_designation' => $data['current_designation'] ?? $employee->designation,
            'proposed_designation' => $data['proposed_designation'],
            'justification' => $data['justification'] ?? null,
            'review_cycle_id' => $data['review_cycle_id'] ?? null,
            'effective_date' => $data['effective_date'] ?? null,
            'status' => PromotionNomination::STATUS_DRAFT,
            'created_by_user_id' => $user->id,
        ])->fresh(['employee', 'reviewCycle']);
    }

    public function update(User $user, PromotionNomination $nomination, array $data): PromotionNomination
    {
        $this->resolveNomination($user, $nomination);
        $this->assertCanEdit($user, $nomination);

        $nomination->update([
            'proposed_designation' => $data['proposed_designation'],
            'justification' => $data['justification'] ?? null,
            'review_cycle_id' => $data['review_cycle_id'] ?? null,
            'effective_date' => $data['effective_date'] ?? null,
        ]);

        return $nomination->fresh(['employee', 'reviewCycle']);
    }

    public function updateStatus(User $user, PromotionNomination $nomination, string $status): PromotionNomination
    {
        $nomination = $this->resolveNomination($user, $nomination);

        return match ($status) {
            PromotionNomination::STATUS_NOMINATED => $this->nominate($user, $nomination),
            PromotionNomination::STATUS_APPROVED => $this->approve($user, $nomination),
            PromotionNomination::STATUS_REJECTED => $this->reject($user, $nomination),
            PromotionNomination::STATUS_CANCELLED => $this->cancel($user, $nomination),
            default => throw new AccessDeniedHttpException('Invalid status transition.'),
        };
    }

    public function resolveNomination(User $user, PromotionNomination $nomination): PromotionNomination
    {
        if ((int) $nomination->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Promotion nomination not found.');
        }

        return $nomination->load(['employee', 'reviewCycle']);
    }

    private function nominate(User $user, PromotionNomination $nomination): PromotionNomination
    {
        $this->assertCanEdit($user, $nomination);

        if (! in_array($nomination->status, [PromotionNomination::STATUS_DRAFT, PromotionNomination::STATUS_NOMINATED], true)) {
            throw new AccessDeniedHttpException('This nomination cannot be submitted.');
        }

        $nomination->update([
            'status' => PromotionNomination::STATUS_NOMINATED,
            'nominated_by_user_id' => $user->id,
        ]);

        return $nomination->fresh(['employee', 'reviewCycle']);
    }

    private function approve(User $user, PromotionNomination $nomination): PromotionNomination
    {
        if (! $user->canManagePerformance()) {
            throw new AccessDeniedHttpException('Only performance managers can approve promotions.');
        }

        if ($nomination->status !== PromotionNomination::STATUS_NOMINATED) {
            throw new AccessDeniedHttpException('Only nominated promotions can be approved.');
        }

        return DB::transaction(function () use ($user, $nomination) {
            $nomination->update([
                'status' => PromotionNomination::STATUS_APPROVED,
                'approved_by_user_id' => $user->id,
                'approved_at' => now(),
            ]);

            if ($nomination->employee && $nomination->proposed_designation) {
                $nomination->employee->update([
                    'designation' => $nomination->proposed_designation,
                ]);
            }

            return $nomination->fresh(['employee', 'reviewCycle']);
        });
    }

    private function reject(User $user, PromotionNomination $nomination): PromotionNomination
    {
        if (! $user->canManagePerformance()) {
            throw new AccessDeniedHttpException('Only performance managers can reject promotions.');
        }

        $nomination->update([
            'status' => PromotionNomination::STATUS_REJECTED,
            'approved_by_user_id' => $user->id,
            'approved_at' => now(),
        ]);

        return $nomination->fresh(['employee', 'reviewCycle']);
    }

    private function cancel(User $user, PromotionNomination $nomination): PromotionNomination
    {
        $this->assertCanEdit($user, $nomination);

        if (in_array($nomination->status, [PromotionNomination::STATUS_APPROVED, PromotionNomination::STATUS_REJECTED], true)) {
            throw new AccessDeniedHttpException('Approved or rejected nominations cannot be cancelled.');
        }

        $nomination->update(['status' => PromotionNomination::STATUS_CANCELLED]);

        return $nomination->fresh(['employee', 'reviewCycle']);
    }

    private function assertCanNominate(User $user): void
    {
        if (! $user->canManagePerformance() && ! $user->canReviewPerformance() && ! $user->hasPermission('employees.manage')) {
            throw new AccessDeniedHttpException('You are not allowed to create promotion nominations.');
        }
    }

    private function assertCanEdit(User $user, PromotionNomination $nomination): void
    {
        if ($user->canManagePerformance()) {
            return;
        }

        if ((int) $nomination->created_by_user_id === (int) $user->id && $nomination->status === PromotionNomination::STATUS_DRAFT) {
            return;
        }

        throw new AccessDeniedHttpException('You are not allowed to edit this nomination.');
    }
}
