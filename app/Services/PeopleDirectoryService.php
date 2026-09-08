<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PeopleDirectoryService
{
    public function summaryForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = $this->baseQuery($user)
            ->with(['department', 'departments'])
            ->orderedByName();

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
            ->with(['department', 'role', 'user.role'])
            ->orderedByName()
            ->get();

        $byManager = $employees->groupBy(
            fn (Employee $employee) => (string) ($employee->manager_id ?? 'none'),
        );

        $roots = $this->orgChartRoots($employees, $byManager);
        $rootIds = $roots->pluck('id');

        $nodes = $roots
            ->map(fn (Employee $employee) => $this->buildOrgNode($employee, $byManager, $rootIds))
            ->filter(fn (array $node) => ! empty($node['children']))
            ->values()
            ->all();

        return [
            'company' => [
                'name' => $user->company?->name ?? 'Company',
            ],
            'nodes' => $nodes,
        ];
    }

    public function mapSummaryRow(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'initials' => $this->initials($employee),
            'profile_photo_url' => $employee->profilePhotoUrl(),
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

    /**
     * Build a node for the org chart tree. Leaf employees are included under their manager.
     * Root-level filtering (admins without teams) is handled separately.
     *
     * @return array<string, mixed>
     */
    private function buildOrgNode(Employee $employee, Collection $byManager, Collection $rootIds): array
    {
        $children = $this->directReports($employee, $byManager)
            ->reject(fn (Employee $child) => $rootIds->contains($child->id))
            ->map(fn (Employee $child) => $this->buildOrgNode($child, $byManager, $rootIds))
            ->values();

        return [
            'type' => 'employee',
            'id' => $employee->id,
            'name' => $employee->full_name,
            'initials' => $this->initials($employee),
            'profile_photo_url' => $employee->profilePhotoUrl(),
            'employee_code' => $employee->employee_code,
            'designation' => $employee->designation ?: '—',
            'department' => $this->departmentName($employee),
            'is_company_admin' => $employee->role?->slug === Role::SLUG_COMPANY_ADMIN,
            'direct_reports_count' => $children->count(),
            'profile_url' => route('web.employees.show', $employee),
            'children' => $children->all(),
        ];
    }

    private function directReports(Employee $employee, Collection $byManager): Collection
    {
        return $byManager
            ->get((string) $employee->id, collect())
            ->sortBy(fn (Employee $report) => strtolower($report->full_name))
            ->values();
    }

    /** @return Collection<int, Employee> */
    private function orgChartRoots(Collection $employees, Collection $byManager): Collection
    {
        $employeeById = $employees->keyBy('id');
        $hasDirectReports = fn (Employee $employee) => $this->directReports($employee, $byManager)->isNotEmpty();
        $isTopLevel = function (Employee $employee) use ($employeeById): bool {
            if (! $employee->manager_id) {
                return true;
            }

            return ! $employeeById->has($employee->manager_id);
        };

        $withTeams = $employees->filter(fn (Employee $employee) => $hasDirectReports($employee));
        $adminWithTeams = $withTeams->filter(fn (Employee $employee) => $this->isCompanyAdmin($employee));

        if ($adminWithTeams->count() >= 2) {
            return $adminWithTeams
                ->sortBy(fn (Employee $employee) => strtolower($employee->full_name))
                ->values();
        }

        $topLevelWithTeams = $withTeams->filter(fn (Employee $employee) => $isTopLevel($employee));

        if ($topLevelWithTeams->count() >= 2) {
            return $topLevelWithTeams
                ->sortBy(fn (Employee $employee) => strtolower($employee->full_name))
                ->values();
        }

        if ($topLevelWithTeams->isNotEmpty()) {
            return $topLevelWithTeams->values();
        }

        if ($adminWithTeams->isNotEmpty()) {
            return $adminWithTeams->values();
        }

        return collect();
    }

    private function isCompanyAdmin(Employee $employee): bool
    {
        return $employee->role?->slug === Role::SLUG_COMPANY_ADMIN
            || $employee->user?->role?->slug === Role::SLUG_COMPANY_ADMIN;
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
        return $employee->initials();
    }
}
