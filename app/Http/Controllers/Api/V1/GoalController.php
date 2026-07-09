<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\GoalKeyResult;
use App\Services\GoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GoalController extends Controller
{
    use ApiResponse;

    public function __construct(private GoalService $goalService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'active', 'completed', 'cancelled'])],
            'search' => ['nullable', 'string', 'max:255'],
            'scope' => ['nullable', Rule::in(['all', 'team'])],
            'level' => ['nullable', Rule::in(['company', 'department', 'individual'])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $goals = $this->goalService->listForUser($request->user(), $validated);

        return $this->success([
            'goals' => $goals->getCollection()->map(fn (Goal $goal) => $this->formatGoal($goal))->values(),
            'pagination' => [
                'current_page' => $goals->currentPage(),
                'last_page' => $goals->lastPage(),
                'per_page' => $goals->perPage(),
                'total' => $goals->total(),
                'from' => $goals->firstItem(),
                'to' => $goals->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateGoalPayload($request);

        $goal = $this->goalService->store($request->user(), $validated);

        return $this->success(
            ['goal' => $this->formatGoal($goal, true)],
            'Goal created successfully.',
            201
        );
    }

    public function show(Request $request, Goal $goal): JsonResponse
    {
        $goal = $this->goalService->resolveGoal($request->user(), $goal);

        return $this->success([
            'goal' => $this->formatGoal($goal, true),
        ]);
    }

    public function update(Request $request, Goal $goal): JsonResponse
    {
        $validated = $this->validateGoalPayload($request);

        $goal = $this->goalService->update($request->user(), $goal, $validated);

        return $this->success(
            ['goal' => $this->formatGoal($goal, true)],
            'Goal updated successfully.'
        );
    }

    public function updateKeyResult(Request $request, Goal $goal, GoalKeyResult $goalKeyResult): JsonResponse
    {
        if ((int) $goalKeyResult->goal_id !== (int) $goal->id) {
            abort(404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_value' => ['nullable', 'numeric', 'min:0'],
            'current_value' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['not_started', 'in_progress', 'completed'])],
            'due_date' => ['nullable', 'date'],
        ]);

        $keyResult = $this->goalService->updateKeyResult($request->user(), $goalKeyResult, $validated);

        return $this->success(
            ['key_result' => $this->formatKeyResult($keyResult)],
            'Key result updated successfully.'
        );
    }

    public function deleteKeyResult(Request $request, Goal $goal, GoalKeyResult $goalKeyResult): JsonResponse
    {
        if ((int) $goalKeyResult->goal_id !== (int) $goal->id) {
            abort(404);
        }

        $this->goalService->deleteKeyResult($request->user(), $goalKeyResult);

        return $this->success(null, 'Key result deleted successfully.');
    }

    public function cascade(Request $request, Goal $goal): JsonResponse
    {
        $result = $this->goalService->cascade($request->user(), $goal);

        return $this->success([
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'goals' => $result['goals']->map(fn (Goal $child) => $this->formatGoal($child))->values(),
        ], sprintf(
            '%d goal(s) created.%s',
            $result['created'],
            $result['skipped'] > 0 ? " {$result['skipped']} already existed and were skipped." : ''
        ));
    }

    private function validateGoalPayload(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'completed', 'cancelled'])],
            'visibility' => ['nullable', Rule::in(['private', 'team', 'company'])],
            'level' => ['nullable', Rule::in(['company', 'department', 'individual'])],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'parent_goal_id' => ['nullable', 'integer', 'exists:goals,id'],
            'key_results' => ['nullable', 'array'],
            'key_results.*.id' => ['nullable', 'integer'],
            'key_results.*.title' => ['required_with:key_results', 'string', 'max:255'],
            'key_results.*.description' => ['nullable', 'string'],
            'key_results.*.target_value' => ['nullable', 'numeric', 'min:0'],
            'key_results.*.current_value' => ['nullable', 'numeric', 'min:0'],
            'key_results.*.unit' => ['nullable', 'string', 'max:50'],
            'key_results.*.weight' => ['nullable', 'numeric', 'min:0'],
            'key_results.*.status' => ['nullable', Rule::in(['not_started', 'in_progress', 'completed'])],
            'key_results.*.due_date' => ['nullable', 'date'],
        ]);
    }

    private function formatGoal(Goal $goal, bool $detailed = false): array
    {
        $data = [
            'id' => $goal->id,
            'level' => $goal->level,
            'title' => $goal->title,
            'description' => $goal->description,
            'period_start' => $goal->period_start?->toDateString(),
            'period_end' => $goal->period_end?->toDateString(),
            'status' => $goal->status,
            'visibility' => $goal->visibility,
            'progress' => (float) $goal->progress,
            'employee' => $this->employeeBrief($goal->employee),
            'department' => $this->departmentBrief($goal->department),
            'parent' => $goal->parent ? [
                'id' => $goal->parent->id,
                'title' => $goal->parent->title,
                'level' => $goal->parent->level,
            ] : null,
            'can_cascade' => in_array($goal->level, [Goal::LEVEL_COMPANY, Goal::LEVEL_DEPARTMENT], true),
            'created_at' => $goal->created_at?->toIso8601String(),
            'updated_at' => $goal->updated_at?->toIso8601String(),
        ];

        if ($detailed || $goal->relationLoaded('keyResults')) {
            $data['key_results'] = $goal->keyResults->map(fn ($kr) => $this->formatKeyResult($kr))->values();
        }

        return $data;
    }

    private function formatKeyResult(GoalKeyResult $keyResult): array
    {
        return [
            'id' => $keyResult->id,
            'title' => $keyResult->title,
            'description' => $keyResult->description,
            'target_value' => (float) $keyResult->target_value,
            'current_value' => (float) $keyResult->current_value,
            'unit' => $keyResult->unit,
            'weight' => (float) $keyResult->weight,
            'status' => $keyResult->status,
            'due_date' => $keyResult->due_date?->toDateString(),
            'sort_order' => $keyResult->sort_order,
        ];
    }

    private function employeeBrief(?Employee $employee): ?array
    {
        if (! $employee) {
            return null;
        }

        return [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'full_name' => $employee->full_name,
        ];
    }

    private function departmentBrief(?Department $department): ?array
    {
        if (! $department) {
            return null;
        }

        return [
            'id' => $department->id,
            'name' => $department->name,
        ];
    }
}
