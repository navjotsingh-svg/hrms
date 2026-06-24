<?php

namespace App\Services;

use App\Http\Resources\TimesheetCommentResource;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Role;
use App\Models\TimesheetComment;
use App\Models\TimesheetEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TimesheetService
{
    public function __construct(
        private ProjectService $projectService,
        private EmployeeAccessService $employeeAccessService,
    ) {}

    public function entriesForDate(User $user, string $workDate, ?int $employeeId = null): Collection
    {
        $employee = $this->resolveViewableEmployee($user, $employeeId);

        return TimesheetEntry::query()
            ->with('project')
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $workDate)
            ->orderBy('start_time')
            ->get();
    }

    public function recentDays(User $user, int $limit = 30, ?int $employeeId = null): Collection
    {
        $employee = $this->resolveViewableEmployee($user, $employeeId);

        $dates = TimesheetEntry::query()
            ->where('employee_id', $employee->id)
            ->select('work_date')
            ->distinct()
            ->orderByDesc('work_date')
            ->limit($limit)
            ->pluck('work_date');

        if ($dates->isEmpty()) {
            return new Collection();
        }

        return TimesheetEntry::query()
            ->with('project')
            ->where('employee_id', $employee->id)
            ->whereIn('work_date', $dates)
            ->orderByDesc('work_date')
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (TimesheetEntry $entry) => $entry->work_date->format('Y-m-d'));
    }

    public function projectOptions(User $user): Collection
    {
        $employee = $this->requireOwnEmployee($user);

        return $this->projectService->assignedToEmployee(
            (int) $user->company_id,
            (int) $employee->id,
        );
    }

    /**
     * @return array{
     *     employees: array<int, array{id: int, full_name: string, employee_code: string|null, manager_id: int|null, is_team_lead: bool}>,
     *     groups: array<int, array{label: string, employee_ids: array<int>}>
     * }
     */
    public function teamEmployeesPayload(User $user): array
    {
        if (! $user->canReviewTeamTimesheets()) {
            return ['employees' => [], 'groups' => []];
        }

        $reviewableIds = $this->reviewableEmployeeIds($user);

        if ($reviewableIds === []) {
            return ['employees' => [], 'groups' => []];
        }

        $employees = Employee::query()
            ->where('company_id', $user->company_id)
            ->whereIn('id', $reviewableIds)
            ->where('status', 'active')
            ->with(['user.role', 'role'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Employee $employee) => $this->mapEmployeeOption($employee))
            ->values()
            ->all();

        $groups = ($user->hasFullAccess() || $user->hasPermission('projects.manage') || $user->hasPermission('attendance.manage'))
            ? $this->teamGroupsForEmployees($user, collect($employees))
            : [];

        return [
            'employees' => $employees,
            'groups' => $groups,
        ];
    }

    public function commentsGroupedByProject(User $user, int $employeeId, string $workDate): array
    {
        $this->assertCanViewEmployeeTimesheet($user, $employeeId);

        $comments = TimesheetComment::query()
            ->with(['user.role', 'replies.user.role'])
            ->where('company_id', $user->company_id)
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $workDate)
            ->whereNull('parent_id')
            ->whereNotNull('project_id')
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn (TimesheetComment $comment) => (int) $comment->project_id);

        $grouped = [];

        foreach ($comments as $projectId => $threads) {
            $grouped[(string) $projectId] = TimesheetCommentResource::collection($threads)->resolve();
        }

        return $grouped;
    }

    public function addComment(
        User $user,
        int $employeeId,
        string $workDate,
        string $body,
        ?int $parentId = null,
        ?int $projectId = null,
    ): TimesheetComment {
        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => 'Comment cannot be empty.',
            ]);
        }

        $this->assertCanViewEmployeeTimesheet($user, $employeeId);

        if ($parentId) {
            $parent = TimesheetComment::query()
                ->where('company_id', $user->company_id)
                ->where('employee_id', $employeeId)
                ->whereDate('work_date', $workDate)
                ->whereKey($parentId)
                ->first();

            if (! $parent) {
                throw ValidationException::withMessages([
                    'parent_id' => 'The comment you are replying to was not found.',
                ]);
            }

            $projectId = (int) $parent->project_id;

            if (! $this->canReplyToTimesheet($user, $employeeId)) {
                throw new AccessDeniedHttpException('You cannot reply on this timesheet.');
            }
        } else {
            if (! $projectId) {
                throw ValidationException::withMessages([
                    'project_id' => 'Select which project submission this comment is for.',
                ]);
            }

            if (! $this->canReviewEmployeeTimesheet($user, $employeeId)) {
                throw new AccessDeniedHttpException('Only managers can start a comment on a project submission.');
            }

            $this->assertProjectSubmissionExists($employeeId, $workDate, $projectId, (int) $user->company_id);
        }

        return TimesheetComment::create([
            'company_id' => $user->company_id,
            'employee_id' => $employeeId,
            'work_date' => $workDate,
            'project_id' => $projectId,
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'body' => $body,
        ])->load(['user.role', 'replies.user.role']);
    }

    public function submitDay(User $user, string $workDate, array $entries): Collection
    {
        if (! Carbon::parse($workDate)->isToday()) {
            throw ValidationException::withMessages([
                'work_date' => 'You can only submit a day report for today. Past dates are view-only.',
            ]);
        }

        $employee = $this->requireOwnEmployee($user);
        $assignedProjectIds = $this->projectOptions($user)->pluck('id')->all();

        $normalizedEntries = collect($entries)->map(function (array $entry) use ($assignedProjectIds, $workDate) {
            $projectId = (int) $entry['project_id'];

            if (! in_array($projectId, $assignedProjectIds, true)) {
                throw ValidationException::withMessages([
                    'entries' => 'One or more selected projects are not assigned to you.',
                ]);
            }

            $project = Project::query()->find($projectId);

            if (! $project || $project->status !== Project::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'entries' => 'One or more selected projects are not active.',
                ]);
            }

            if ($project->start_date && Carbon::parse($workDate)->lt($project->start_date)) {
                throw ValidationException::withMessages([
                    'work_date' => 'Work date is before the project start date.',
                ]);
            }

            if ($project->end_date && Carbon::parse($workDate)->gt($project->end_date)) {
                throw ValidationException::withMessages([
                    'work_date' => 'Work date is after the project end date.',
                ]);
            }

            $hours = $this->calculateHours($entry['start_time'], $entry['end_time']);

            return [
                'project_id' => $projectId,
                'start_time' => $this->normalizeTime($entry['start_time']),
                'end_time' => $this->normalizeTime($entry['end_time']),
                'hours' => $hours,
                'notes' => $entry['notes'] ?? null,
            ];
        });

        $duplicateProjects = $normalizedEntries->pluck('project_id')->duplicates();

        if ($duplicateProjects->isNotEmpty()) {
            throw ValidationException::withMessages([
                'entries' => 'Each project can only be logged once per day.',
            ]);
        }

        return DB::transaction(function () use ($user, $employee, $workDate, $normalizedEntries) {
            TimesheetEntry::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate)
                ->delete();

            foreach ($normalizedEntries as $entry) {
                TimesheetEntry::create([
                    'company_id' => $user->company_id,
                    'employee_id' => $employee->id,
                    'project_id' => $entry['project_id'],
                    'work_date' => $workDate,
                    'start_time' => $entry['start_time'],
                    'end_time' => $entry['end_time'],
                    'hours' => $entry['hours'],
                    'notes' => $entry['notes'],
                ]);
            }

            return $this->entriesForDate($user, $workDate);
        });
    }

    /**
     * @return array<int>
     */
    public function reviewableEmployeeIds(User $user): array
    {
        if ($user->hasFullAccess()) {
            return Employee::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'active')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if (! $user->hasPermission('projects.manage') && ! $user->hasPermission('attendance.manage')) {
            return [];
        }

        $ids = collect($this->employeeAccessService->subordinateIdsForUser($user));

        if ($user->hasPermission('projects.manage')) {
            $ids = $ids->merge($this->employeesOnSubordinateTeamLeadProjects($user))->unique();
        }

        return $ids
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function canReviewEmployeeTimesheet(User $user, int $employeeId): bool
    {
        if ((int) $user->company_id === 0) {
            return false;
        }

        if ($user->hasFullAccess()) {
            return true;
        }

        return in_array($employeeId, $this->reviewableEmployeeIds($user), true);
    }

    public function canReplyToTimesheet(User $user, int $employeeId): bool
    {
        if ($this->canReviewEmployeeTimesheet($user, $employeeId)) {
            return true;
        }

        $linkedEmployee = $this->employeeAccessService->linkedEmployee($user);

        return $linkedEmployee && (int) $linkedEmployee->id === $employeeId;
    }

    public function calculateHours(string $startTime, string $endTime): float
    {
        $start = Carbon::createFromFormat('H:i', $this->normalizeTime($startTime));
        $end = Carbon::createFromFormat('H:i', $this->normalizeTime($endTime));

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'entries' => 'End time must be after start time for each project entry.',
            ]);
        }

        return round($start->diffInMinutes($end) / 60, 2);
    }

    public function normalizeTime(string $time): string
    {
        return Carbon::parse($time)->format('H:i');
    }

    private function resolveViewableEmployee(User $user, ?int $employeeId): Employee
    {
        if ($employeeId === null) {
            return $this->requireOwnEmployee($user);
        }

        $this->assertCanViewEmployeeTimesheet($user, $employeeId);

        return Employee::query()
            ->where('company_id', $user->company_id)
            ->whereKey($employeeId)
            ->firstOrFail();
    }

    private function assertCanViewEmployeeTimesheet(User $user, int $employeeId): void
    {
        if ($user->isCompanyAdmin()) {
            return;
        }

        $linkedEmployee = $this->employeeAccessService->linkedEmployee($user);

        if ($linkedEmployee && (int) $linkedEmployee->id === $employeeId) {
            return;
        }

        if ($this->canReviewEmployeeTimesheet($user, $employeeId)) {
            return;
        }

        throw new AccessDeniedHttpException('You are not allowed to view this timesheet.');
    }

    private function requireOwnEmployee(User $user): Employee
    {
        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee' => 'Your account is not linked to an employee profile.',
            ]);
        }

        return $employee;
    }

    /**
     * Employees assigned to projects created by team leads in the manager's tree.
     *
     * @return array<int>
     */
    private function employeesOnSubordinateTeamLeadProjects(User $user): array
    {
        $subordinateIds = $this->employeeAccessService->subordinateIdsForUser($user);

        if ($subordinateIds === []) {
            return [];
        }

        $teamLeadUserIds = User::query()
            ->where('company_id', $user->company_id)
            ->whereHas('role', fn ($query) => $query->where('slug', Role::SLUG_TEAM_LEAD))
            ->whereHas('employee', fn ($query) => $query->whereIn('id', $subordinateIds))
            ->pluck('id');

        if ($teamLeadUserIds->isEmpty()) {
            return [];
        }

        return Employee::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->whereHas('projects', fn ($query) => $query->whereIn('created_by_user_id', $teamLeadUserIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  SupportCollection<int, array{id: int, full_name: string, employee_code: string|null, manager_id: int|null, is_team_lead: bool}>  $employees
     * @return array<int, array{label: string, employee_ids: array<int>}>
     */
    private function teamGroupsForEmployees(User $user, SupportCollection $employees): array
    {
        $reviewableIds = $employees->pluck('id')->map(fn ($id) => (int) $id);
        $teamLeads = $employees->filter(fn (array $employee) => $employee['is_team_lead']);

        if ($teamLeads->isEmpty()) {
            return [[
                'label' => 'Team members',
                'employee_ids' => $reviewableIds->values()->all(),
            ]];
        }

        $groups = [];
        $assignedIds = collect();

        foreach ($teamLeads as $teamLead) {
            $memberIds = collect($this->employeeAccessService->descendantIds(
                (int) $teamLead['id'],
                (int) $user->company_id,
            ))
                ->intersect($reviewableIds)
                ->prepend((int) $teamLead['id'])
                ->unique()
                ->values();

            if ($memberIds->isEmpty()) {
                continue;
            }

            $assignedIds = $assignedIds->merge($memberIds);

            $groups[] = [
                'label' => $teamLead['full_name'].' (Team Lead)',
                'employee_ids' => $memberIds->all(),
            ];
        }

        $remainingIds = $reviewableIds->diff($assignedIds)->values();

        if ($remainingIds->isNotEmpty()) {
            $groups[] = [
                'label' => 'Other team members',
                'employee_ids' => $remainingIds->all(),
            ];
        }

        return $groups;
    }

    private function assertProjectSubmissionExists(int $employeeId, string $workDate, int $projectId, int $companyId): void
    {
        $exists = TimesheetEntry::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('project_id', $projectId)
            ->whereDate('work_date', $workDate)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'project_id' => 'No timesheet submission exists for this project on the selected date.',
            ]);
        }
    }

    private function mapEmployeeOption(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'full_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
            'manager_id' => $employee->manager_id,
            'is_team_lead' => $employee->user?->isTeamLead()
                || $employee->role?->slug === Role::SLUG_TEAM_LEAD,
        ];
    }
}
