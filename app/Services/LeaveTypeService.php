<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LeaveTypeService
{
    public function defaultTypes(): array
    {
        return [
            ['name' => 'Casual Leave', 'code' => 'CL', 'annual_quota' => 12, 'max_days_per_request' => 2, 'max_days_per_month' => 2, 'requires_proof' => false, 'color' => '#3b82f6', 'sort_order' => 1],
            ['name' => 'Sick Leave', 'code' => 'SL', 'annual_quota' => 6, 'requires_proof' => true, 'color' => '#ef4444', 'sort_order' => 2],
            ['name' => 'Earned Leave', 'code' => 'EL', 'annual_quota' => 15, 'requires_proof' => false, 'color' => '#22c55e', 'sort_order' => 3],
            ['name' => 'Maternity Leave', 'code' => 'MAT', 'annual_quota' => 182, 'requires_proof' => true, 'color' => '#ec4899', 'sort_order' => 4],
            ['name' => 'Paternity Leave', 'code' => 'PAT', 'annual_quota' => 15, 'requires_proof' => true, 'color' => '#6366f1', 'sort_order' => 5],
            ['name' => 'Pink Leave', 'code' => 'PINK', 'annual_quota' => 12, 'requires_proof' => false, 'color' => '#f472b6', 'sort_order' => 6],
            ['name' => 'Bereavement Leave', 'code' => 'BL', 'annual_quota' => 5, 'requires_proof' => false, 'color' => '#6b7280', 'sort_order' => 7],
            ['name' => 'Marriage Leave', 'code' => 'MRL', 'annual_quota' => 3, 'requires_proof' => true, 'color' => '#f59e0b', 'sort_order' => 8],
            ['name' => 'Comp Off', 'code' => 'COMP', 'annual_quota' => 0, 'requires_proof' => false, 'color' => '#14b8a6', 'sort_order' => 9],
            ['name' => 'Loss of Pay', 'code' => 'LOP', 'annual_quota' => null, 'is_paid' => false, 'requires_proof' => false, 'color' => '#64748b', 'sort_order' => 10],
            ['name' => 'Short Leave', 'code' => 'SHL', 'annual_quota' => 24, 'max_days_per_request' => 2, 'is_hourly_leave' => true, 'max_hours_per_month' => 4, 'allowed_hourly_durations' => [60, 120], 'requires_proof' => false, 'color' => '#0ea5e9', 'sort_order' => 11],
        ];
    }

    public function ensureDefaultsForCompany(int $companyId): void
    {
        foreach ($this->defaultTypes() as $item) {
            $existing = LeaveType::withTrashed()
                ->where('company_id', $companyId)
                ->where('code', $item['code'])
                ->first();

            if ($existing) {
                if (! $existing->trashed()) {
                    $this->repairStandardLeaveType($existing, $item);
                }

                continue;
            }

            LeaveType::create([
                'company_id' => $companyId,
                ...$this->attributesFromDefault($item),
            ]);
        }

        $this->removeLegacyHourlyLeaveDuplicate($companyId);
    }

    public function syncDefaultsForAllCompanies(): void
    {
        Company::query()->pluck('id')->each(fn (int $companyId) => $this->ensureDefaultsForCompany($companyId));
    }

    public function listForCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $this->ensureDefaultsForCompany($companyId);

        $query = LeaveType::query()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function activeForCompany(int $companyId): Collection
    {
        $this->ensureDefaultsForCompany($companyId);

        return LeaveType::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get();
    }

    public function activeForEmployee(Employee $employee): Collection
    {
        $this->ensureDefaultsForCompany($employee->company_id);
        $employee->loadMissing('leaveTypes');

        return $employee->leaveTypes
            ->filter(fn (LeaveType $type) => $type->status === 'active' && ! $type->trashed())
            ->when(
                $employee->restrictsPaidLeave(),
                fn (Collection $types) => $types->filter(fn (LeaveType $type) => ! $type->is_paid),
            )
            ->sortBy(fn (LeaveType $type) => [$type->sort_order, $type->name])
            ->values();
    }

    public function isAssignedToEmployee(Employee $employee, int $leaveTypeId): bool
    {
        $employee->loadMissing('leaveTypes');

        return $employee->leaveTypes->contains(
            fn (LeaveType $type) => (int) $type->id === $leaveTypeId && $type->status === 'active' && ! $type->trashed(),
        );
    }

    public function create(int $companyId, array $data): LeaveType
    {
        $data = $this->normalizeHourlyPolicy($data);

        return LeaveType::create([
            ...$data,
            'company_id' => $companyId,
        ]);
    }

    public function update(LeaveType $leaveType, array $data): LeaveType
    {
        $leaveType->update($this->normalizeHourlyPolicy($data));

        return $leaveType->fresh();
    }

    private function attributesFromDefault(array $item): array
    {
        return [
            'name' => $item['name'],
            'annual_quota' => $item['annual_quota'],
            'max_days_per_request' => $item['max_days_per_request'] ?? null,
            'max_days_per_month' => $item['max_days_per_month'] ?? null,
            'is_hourly_leave' => $item['is_hourly_leave'] ?? false,
            'max_hours_per_month' => $item['max_hours_per_month'] ?? null,
            'allowed_hourly_durations' => $item['allowed_hourly_durations'] ?? null,
            'is_paid' => $item['is_paid'] ?? true,
            'requires_proof' => $item['requires_proof'] ?? false,
            'color' => $item['color'],
            'sort_order' => $item['sort_order'],
            'status' => 'active',
        ];
    }

    private function repairStandardLeaveType(LeaveType $type, array $defaults): void
    {
        $isShortLeave = ($defaults['is_hourly_leave'] ?? false) === true;

        if ($isShortLeave) {
            if ($type->code !== 'SHL' || ! $type->is_hourly_leave) {
                $type->update($this->attributesFromDefault($defaults));
            }

            return;
        }

        if (
            $type->is_hourly_leave
            || $type->name === 'Short Leave'
            || $type->max_hours_per_month !== null
            || $type->allowed_hourly_durations !== null
        ) {
            $type->update($this->attributesFromDefault($defaults));

            return;
        }

        if ($type->code === 'CL') {
            $updates = [];

            if ($type->max_days_per_request === null && isset($defaults['max_days_per_request'])) {
                $updates['max_days_per_request'] = $defaults['max_days_per_request'];
            }

            if ($type->max_days_per_month === null && isset($defaults['max_days_per_month'])) {
                $updates['max_days_per_month'] = $defaults['max_days_per_month'];
            }

            if ($updates !== []) {
                $type->update($updates);
            }
        }
    }

    private function removeLegacyHourlyLeaveDuplicate(int $companyId): void
    {
        $hasShortLeave = LeaveType::query()
            ->where('company_id', $companyId)
            ->where('code', 'SHL')
            ->exists();

        if (! $hasShortLeave) {
            return;
        }

        LeaveType::query()
            ->where('company_id', $companyId)
            ->where('code', 'HL')
            ->delete();
    }

    private function normalizeHourlyPolicy(array $data): array
    {
        if (empty($data['is_hourly_leave'])) {
            $data['is_hourly_leave'] = false;
            $data['max_hours_per_month'] = null;
            $data['allowed_hourly_durations'] = null;
        } else {
            $data['max_days_per_month'] = null;
        }

        return $data;
    }

    public function delete(LeaveType $leaveType): void
    {
        if ($leaveType->trashed()) {
            return;
        }

        $leaveType->update(['status' => 'inactive']);
        $leaveType->delete();
    }

    public function belongsToCompany(LeaveType $leaveType, int $companyId): bool
    {
        return (int) $leaveType->company_id === $companyId;
    }
}
