<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\GoalKeyResult;
use App\Models\PerformanceKpi;
use Illuminate\Validation\ValidationException;

class GoalProgressSyncService
{
    public function onKpiUpdated(PerformanceKpi $kpi): void
    {
        $goalsToRecalculate = [];

        GoalKeyResult::query()
            ->where('performance_kpi_id', $kpi->id)
            ->with('goal')
            ->get()
            ->each(function (GoalKeyResult $keyResult) use (&$goalsToRecalculate) {
                $keyResult->syncFromLinkedKpi();

                if ($keyResult->goal) {
                    $goalsToRecalculate[$keyResult->goal_id] = $keyResult->goal;
                }
            });

        foreach ($goalsToRecalculate as $goal) {
            $this->recalculateGoalTree($goal);
        }
    }

    public function recalculateGoalTree(Goal $goal): void
    {
        $current = Goal::query()->find($goal->id);

        while ($current) {
            $current->recalculateProgress();
            $current = $current->parent_goal_id
                ? Goal::query()->find($current->parent_goal_id)
                : null;
        }
    }

    public function assertKpiLinkableToGoal(Goal $goal, PerformanceKpi $kpi): void
    {
        if ((int) $kpi->company_id !== (int) $goal->company_id) {
            throw ValidationException::withMessages([
                'performance_kpi_id' => ['KPI must belong to the same company as the goal.'],
            ]);
        }

        if ($goal->employee_id && (int) $kpi->employee_id !== (int) $goal->employee_id) {
            throw ValidationException::withMessages([
                'performance_kpi_id' => ['KPI must belong to the same employee as the goal.'],
            ]);
        }
    }
}
