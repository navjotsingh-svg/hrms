<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\Competency;
use App\Models\Employee;
use App\Models\EmployeeCompetency;
use App\Services\CompetencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompetencyController extends Controller
{
    use ApiResponse;

    public function __construct(private CompetencyService $service) {}

    public function indexCompetencies(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'active_only' => ['nullable'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $competencies = $this->service->listCompetencies($request->user(), $validated);

        return $this->success([
            'competencies' => $competencies->getCollection()->map(fn ($item) => $this->formatCompetency($item))->values(),
            'pagination' => $this->pagination($competencies),
        ]);
    }

    public function storeCompetency(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'max_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $competency = $this->service->storeCompetency($request->user(), $validated);

        return $this->success(['competency' => $this->formatCompetency($competency)], 'Competency created successfully.', 201);
    }

    public function updateCompetency(Request $request, Competency $competency): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'max_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $competency = $this->service->updateCompetency($request->user(), $competency, $validated);

        return $this->success(['competency' => $this->formatCompetency($competency)], 'Competency updated successfully.');
    }

    public function indexEmployeeCompetencies(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $records = $this->service->listEmployeeCompetencies($request->user(), $validated);

        return $this->success([
            'employee_competencies' => $records->getCollection()->map(fn ($item) => $this->formatEmployeeCompetency($item))->values(),
            'pagination' => $this->pagination($records),
        ]);
    }

    public function storeEmployeeCompetency(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'current_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'target_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'notes' => ['nullable', 'string'],
        ]);

        $record = $this->service->storeEmployeeCompetency($request->user(), $validated);

        return $this->success(
            ['employee_competency' => $this->formatEmployeeCompetency($record)],
            'Employee competency saved successfully.',
            201
        );
    }

    public function updateEmployeeCompetency(Request $request, EmployeeCompetency $employeeCompetency): JsonResponse
    {
        $validated = $request->validate([
            'current_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'target_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'notes' => ['nullable', 'string'],
        ]);

        $record = $this->service->updateEmployeeCompetency($request->user(), $employeeCompetency, $validated);

        return $this->success(
            ['employee_competency' => $this->formatEmployeeCompetency($record)],
            'Employee competency updated successfully.'
        );
    }

    private function formatCompetency(Competency $competency): array
    {
        return [
            'id' => $competency->id,
            'name' => $competency->name,
            'category' => $competency->category,
            'description' => $competency->description,
            'max_level' => (int) $competency->max_level,
            'is_active' => (bool) $competency->is_active,
        ];
    }

    private function formatEmployeeCompetency(EmployeeCompetency $record): array
    {
        return [
            'id' => $record->id,
            'employee' => $this->employeeBrief($record->employee),
            'competency' => $record->competency ? $this->formatCompetency($record->competency) : null,
            'current_level' => (int) $record->current_level,
            'target_level' => (int) $record->target_level,
            'gap' => max(0, (int) $record->target_level - (int) $record->current_level),
            'notes' => $record->notes,
            'assessed_at' => $record->assessed_at?->toDateString(),
        ];
    }

    private function employeeBrief(?Employee $employee): ?array
    {
        if (! $employee) {
            return null;
        }

        return [
            'id' => $employee->id,
            'full_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
        ];
    }

    private function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
