<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProbationCompletionService
{
    public function __construct(
        private ActivityLogService $activityLogService,
        private WorkflowNotificationService $workflowNotificationService,
    ) {}

    /** @return array{completed: int, reminded: int} */
    public function processDaily(): array
    {
        $completed = $this->processDueCompletions()->count();
        $reminded = $this->processUpcomingReminders()->count();

        return [
            'completed' => $completed,
            'reminded' => $reminded,
        ];
    }

    /** @return Collection<int, Employee> */
    public function processDueCompletions(): Collection
    {
        if (! config('probation.auto_confirm_on_end_date', true)) {
            return collect();
        }

        $processed = collect();

        foreach ($this->employeesDueForCompletion() as $employee) {
            if ($this->completeProbation($employee)) {
                $processed->push($employee);
            }
        }

        return $processed;
    }

    /** @return Collection<int, Employee> */
    public function processUpcomingReminders(): Collection
    {
        $reminderDays = max(1, (int) config('probation.reminder_days_before', 7));
        $targetDate = now()->startOfDay()->addDays($reminderDays)->toDateString();
        $processed = collect();

        Employee::query()
            ->with(['manager.user', 'company', 'role'])
            ->where('status', 'active')
            ->where('probation_applicable', true)
            ->whereIn('probation_status', ['on_probation', 'extended'])
            ->whereDate('probation_end_date', $targetDate)
            ->orderBy('company_id')
            ->chunkById(100, function ($employees) use (&$processed) {
                foreach ($employees as $employee) {
                    if ($this->hasRecentEndingSoonAlert($employee)) {
                        continue;
                    }

                    $this->workflowNotificationService->notifyProbationEndingSoon($employee);
                    $processed->push($employee);
                }
            });

        return $processed;
    }

    public function completeProbation(Employee $employee, ?User $actor = null): bool
    {
        if (! $employee->probation_applicable) {
            return false;
        }

        if (! in_array($employee->probation_status, ['on_probation', 'extended'], true)) {
            return false;
        }

        if ($employee->status !== 'active') {
            return false;
        }

        if ($employee->probation_end_date && $employee->probation_end_date->startOfDay()->gt(now()->startOfDay())) {
            return false;
        }

        $previousStatus = $employee->probation_status;

        DB::transaction(function () use ($employee, $previousStatus, $actor) {
            $employee->update(['probation_status' => 'confirmed']);

            $this->activityLogService->write([
                'user' => $actor,
                'company_id' => (int) $employee->company_id,
                'employee_id' => (int) $employee->id,
                'module' => 'employees',
                'action' => 'probation.completed',
                'subject' => $employee,
                'message' => 'Probation period completed. Employment confirmed automatically.',
                'old_values' => ['probation_status' => $previousStatus],
                'new_values' => ['probation_status' => 'confirmed'],
                'metadata' => [
                    'probation_end_date' => $employee->probation_end_date?->toDateString(),
                    'automated' => $actor === null,
                ],
            ]);
        });

        $employee->refresh()->load(['manager.user', 'company', 'role', 'user']);

        $this->workflowNotificationService->notifyProbationCompleted($employee, $actor);

        return true;
    }

    /** @return Collection<int, Employee> */
    private function employeesDueForCompletion(): Collection
    {
        return Employee::query()
            ->with(['manager.user', 'company', 'role', 'user'])
            ->where('status', 'active')
            ->where('probation_applicable', true)
            ->whereIn('probation_status', ['on_probation', 'extended'])
            ->whereNotNull('probation_end_date')
            ->whereDate('probation_end_date', '<=', now()->toDateString())
            ->orderBy('company_id')
            ->get();
    }

    private function hasRecentEndingSoonAlert(Employee $employee): bool
    {
        return UserNotification::query()
            ->where('company_id', $employee->company_id)
            ->where('type', UserNotification::TYPE_PROBATION_ENDING_SOON)
            ->where('related_type', 'employee')
            ->where('related_id', $employee->id)
            ->where('created_at', '>=', now()->subDays(14))
            ->exists();
    }
}
