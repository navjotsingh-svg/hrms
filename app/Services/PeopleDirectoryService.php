<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PeopleDirectoryService
{
    public function summaryForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = $this->baseQuery($user)
            ->with(['department', 'departments'])
            ->orderBy('first_name')
            ->orderBy('last_name');

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('designation', 'like', "%{$search}%")
                    ->orWhereHas('department', fn ($relation) => $relation->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('departments', fn ($relation) => $relation->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function orgChartForUser(User $user): array
    {
        $user->loadMissing('company');

        $employees = $this->baseQuery($user)
            ->with(['department'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $byManager = $employees->groupBy(
            fn (Employee $employee) => (string) ($employee->manager_id ?? 'none'),
        );

        $byDepartment = $employees->groupBy(
            fn (Employee $employee) => (string) ($employee->department_id ?? 'unassigned'),
        );

        $departmentNodes = $byDepartment
            ->map(function (Collection $departmentEmployees, string $departmentKey) use ($byManager) {
                $departmentId = $departmentKey === 'unassigned' ? null : (int) $departmentKey;
                $departmentName = $departmentId
                    ? ($departmentEmployees->first()->department?->name ?? 'Department')
                    : 'Unassigned';

                $departmentEmployeeIds = $departmentEmployees->pluck('id')->flip();

                $roots = $departmentEmployees->filter(function (Employee $employee) use ($departmentEmployeeIds) {
                    if (! $employee->manager_id) {
                        return true;
                    }

                    return ! $departmentEmployeeIds->has($employee->manager_id);
                });

                $departmentHeads = $this->departmentHeadRoots($roots, $byManager, $departmentEmployeeIds);

                if ($departmentHeads->isEmpty()) {
                    $departmentHeads = $roots->isNotEmpty()
                        ? $roots
                        : $departmentEmployees;
                }

                if ($departmentHeads->isEmpty()) {
                    return null;
                }

                return [
                    'type' => 'department',
                    'id' => $departmentId,
                    'name' => $departmentName,
                    'direct_reports_count' => $departmentHeads->count(),
                    'children' => $departmentHeads
                        ->map(fn (Employee $employee) => $this->buildOrgNode($employee, $byManager, $departmentEmployeeIds))
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return [
            'company' => [
                'name' => $user->company?->name ?? 'Company',
            ],
            'nodes' => $departmentNodes,
        ];
    }

    public function mapSummaryRow(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'initials' => $this->initials($employee),
            'employee_code' => $employee->employee_code,
            'department' => $this->departmentName($employee),
            'designation' => $employee->designation,
            'profile_url' => route('web.employees.show', $employee),
        ];
    }

    private function baseQuery(User $user)
    {
        return Employee::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active');
    }

    private function buildOrgNode(Employee $employee, Collection $byManager, Collection $departmentEmployeeIds): array
    {
        $children = $this->directReportsInDepartment($employee, $byManager, $departmentEmployeeIds);

        return [
            'type' => 'employee',
            'id' => $employee->id,
            'name' => $employee->full_name,
            'initials' => $this->initials($employee),
            'employee_code' => $employee->employee_code,
            'designation' => $employee->designation ?: '—',
            'department' => $this->departmentName($employee),
            'direct_reports_count' => $children->count(),
            'profile_url' => route('web.employees.show', $employee),
            'children' => $children
                ->map(fn (Employee $child) => $this->buildOrgNode($child, $byManager, $departmentEmployeeIds))
                ->values()
                ->all(),
        ];
    }

    private function directReportsInDepartment(
        Employee $employee,
        Collection $byManager,
        Collection $departmentEmployeeIds,
    ): Collection {
        return $byManager
            ->get((string) $employee->id, collect())
            ->filter(fn (Employee $report) => $departmentEmployeeIds->has($report->id));
    }

    /**
     * Skip company-wide team heads (no manager) and surface their in-department reports as roots.
     */
    private function departmentHeadRoots(
        Collection $roots,
        Collection $byManager,
        Collection $departmentEmployeeIds,
    ): Collection {
        $departmentHeads = collect();

        foreach ($roots as $root) {
            if (! $root->manager_id) {
                $reports = $this->directReportsInDepartment($root, $byManager, $departmentEmployeeIds);

                if ($reports->isNotEmpty()) {
                    $departmentHeads = $departmentHeads->merge($reports);

                    continue;
                }
            }

            $departmentHeads->push($root);
        }

        return $departmentHeads
            ->unique('id')
            ->sortBy(fn (Employee $employee) => strtolower($employee->full_name))
            ->values();
    }

    private function departmentName(Employee $employee): string
    {
        if ($employee->relationLoaded('department') && $employee->department?->name) {
            return $employee->department->name;
        }

        if ($employee->relationLoaded('departments') && $employee->departments->isNotEmpty()) {
            return $employee->departments->pluck('name')->filter()->implode(', ');
        }

        return '—';
    }

    private function initials(Employee $employee): string
    {
        $first = trim((string) $employee->first_name);
        $last = trim((string) $employee->last_name);
        $initials = strtoupper(substr($first, 0, 1).substr($last, 0, 1));

        return $initials !== '' ? $initials : '—';
    }
}
