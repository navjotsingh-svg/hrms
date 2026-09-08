<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public const OTHER_PROJECT_NAME = 'Other';

    public function __construct(private EmployeeAccessService $employeeAccessService) {}

    public function listForCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = Project::query()
            ->with(['employees', 'createdBy.employee'])
            ->where('company_id', $companyId)
            ->latest();

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function assignedToEmployee(int $companyId, int $employeeId): Collection
    {
        return Project::query()
            ->with('employees')
            ->where('company_id', $companyId)
            ->where('status', Project::STATUS_ACTIVE)
            ->whereHas('employees', fn ($query) => $query->where('employees.id', $employeeId))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{
     *     employees: Collection<int, Employee>,
     *     auto_assign: Collection<int, Employee>,
     *     assigner_role: string
     * }
     */
    public function assigneeContext(User $user): array
    {
        $companyId = (int) $user->company_id;
        $employee = $this->employeeAccessService->linkedEmployee($user);

        if ($user->hasFullAccess()) {
            $selectableIds = Employee::query()
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->pluck('id')
                ->all();

            return $this->buildAssigneeContext($companyId, $selectableIds, collect(), 'company_admin');
        }

        if (! $user->hasPermission('projects.manage')) {
            return $this->buildAssigneeContext($companyId, [], collect(), 'unknown');
        }

        if (! $employee) {
            return $this->buildAssigneeContext($companyId, [], collect(), 'unknown');
        }

        $subordinateIds = $this->employeeAccessService->subordinateIdsForUser($user);

        if ($subordinateIds === []) {
            return $this->buildAssigneeContext($companyId, [], collect(), 'unknown');
        }

        $directReportIds = Employee::query()
            ->where('company_id', $companyId)
            ->where('manager_id', $employee->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $isDirectManagerOnly = collect($subordinateIds)->every(
            fn (int $id) => $directReportIds->contains($id),
        );

        if ($isDirectManagerOnly) {
            $autoAssign = collect([$employee]);
            $selectableIds = array_values(array_diff($subordinateIds, [$employee->id]));

            return $this->buildAssigneeContext($companyId, $selectableIds, $autoAssign, 'team_lead');
        }

        return $this->buildAssigneeContext($companyId, $subordinateIds, collect(), 'department_head');
    }

    /**
     * @param  array<int>  $selectableIds
     * @return array{
     *     employees: Collection<int, Employee>,
     *     auto_assign: Collection<int, Employee>,
     *     assigner_role: string
     * }
     */
    private function buildAssigneeContext(int $companyId, array $selectableIds, SupportCollection $autoAssign, string $assignerRole): array
    {
        $employees = Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereIn('id', $selectableIds)
            ->orderedByName()
            ->get(['id', 'first_name', 'last_name', 'employee_code']);

        return [
            'employees' => $employees,
            'auto_assign' => $autoAssign->values(),
            'assigner_role' => $assignerRole,
        ];
    }

    /**
     * @param  array<int>  $submittedIds
     * @return array<int>
     */
    public function resolveEmployeeIds(User $user, array $submittedIds): array
    {
        $context = $this->assigneeContext($user);
        $allowedIds = $context['employees']->pluck('id')
            ->merge($context['auto_assign']->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $submittedIds = collect($submittedIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        foreach ($submittedIds as $employeeId) {
            if (! in_array($employeeId, $allowedIds, true)) {
                throw ValidationException::withMessages([
                    'employee_ids' => 'One or more selected assignees are not in your team.',
                ]);
            }
        }

        $autoIds = $context['auto_assign']->pluck('id')->map(fn ($id) => (int) $id)->all();
        $manualIds = array_values(array_diff($submittedIds, $autoIds));
        $resolved = array_values(array_unique(array_merge($autoIds, $manualIds)));

        if ($resolved === []) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Select at least one assignee from your team.',
            ]);
        }

        if ($context['assigner_role'] === 'department_head' && $manualIds === []) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Select at least one subordinate to assign to this project.',
            ]);
        }

        return $resolved;
    }

    public function create(User $user, array $data): Project
    {
        $employeeIds = $this->resolveEmployeeIds($user, $data['employee_ids'] ?? []);
        unset($data['employee_ids']);

        $project = Project::create([
            ...$data,
            'company_id' => $user->company_id,
            'created_by_user_id' => $user->id,
        ]);

        $project->employees()->sync($employeeIds);

        return $project->load(['employees', 'createdBy.employee']);
    }

    public function update(User $user, Project $project, array $data): Project
    {
        $employeeIds = null;

        if (array_key_exists('employee_ids', $data)) {
            $employeeIds = $this->resolveEmployeeIds($user, $data['employee_ids']);
            unset($data['employee_ids']);
        }

        $project->update($data);

        if (is_array($employeeIds)) {
            $project->employees()->sync($employeeIds);
        }

        return $project->fresh(['employees', 'createdBy.employee']);
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }

    public function belongsToCompany(Project $project, int $companyId): bool
    {
        return (int) $project->company_id === $companyId;
    }

    public function otherProjectForCompany(int $companyId, ?User $actor = null): Project
    {
        return Project::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'name' => self::OTHER_PROJECT_NAME,
            ],
            [
                'description' => 'Non-project work and general tasks',
                'status' => Project::STATUS_ACTIVE,
                'start_date' => now()->toDateString(),
                'created_by_user_id' => $this->resolveOtherProjectCreatorId($companyId, $actor),
            ],
        );
    }

    private function resolveOtherProjectCreatorId(int $companyId, ?User $actor): int
    {
        if ($actor && (int) $actor->company_id === $companyId) {
            return (int) $actor->id;
        }

        $adminId = User::query()
            ->where('company_id', $companyId)
            ->whereHas('role', fn ($query) => $query->where('slug', Role::SLUG_COMPANY_ADMIN))
            ->value('id');

        if ($adminId) {
            return (int) $adminId;
        }

        $fallbackId = User::query()->where('company_id', $companyId)->value('id');

        if (! $fallbackId) {
            throw ValidationException::withMessages([
                'project' => ['Unable to create the Other project for this company.'],
            ]);
        }

        return (int) $fallbackId;
    }

    public function isOtherProject(Project $project): bool
    {
        return $project->name === self::OTHER_PROJECT_NAME;
    }
}
