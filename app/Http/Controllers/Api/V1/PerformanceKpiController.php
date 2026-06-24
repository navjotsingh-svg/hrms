<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\Employee;
use App\Models\PerformanceKpi;
use App\Services\PerformanceKpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PerformanceKpiController extends Controller
{
    use ApiResponse;

    public function __construct(private PerformanceKpiService $kpiService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'completed', 'cancelled'])],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $kpis = $this->kpiService->listForUser($request->user(), $validated);

        return $this->success([
            'kpis' => $kpis->getCollection()->map(fn (PerformanceKpi $kpi) => $this->formatKpi($kpi))->values(),
            'pagination' => [
                'current_page' => $kpis->currentPage(),
                'last_page' => $kpis->lastPage(),
                'per_page' => $kpis->perPage(),
                'total' => $kpis->total(),
                'from' => $kpis->firstItem(),
                'to' => $kpis->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request, true);

        $kpi = $this->kpiService->store($request->user(), $validated);

        return $this->success(
            ['kpi' => $this->formatKpi($kpi)],
            'KPI created successfully.',
            201
        );
    }

    public function show(Request $request, PerformanceKpi $performanceKpi): JsonResponse
    {
        $kpi = $this->kpiService->resolve($request->user(), $performanceKpi);

        return $this->success(['kpi' => $this->formatKpi($kpi)]);
    }

    public function update(Request $request, PerformanceKpi $performanceKpi): JsonResponse
    {
        $validated = $this->validatePayload($request, false);

        $kpi = $this->kpiService->update($request->user(), $performanceKpi, $validated);

        return $this->success(
            ['kpi' => $this->formatKpi($kpi)],
            'KPI updated successfully.'
        );
    }

    public function destroy(Request $request, PerformanceKpi $performanceKpi): JsonResponse
    {
        $this->kpiService->delete($request->user(), $performanceKpi);

        return $this->success(null, 'KPI deleted successfully.');
    }

    private function validatePayload(Request $request, bool $creating): array
    {
        return $request->validate([
            'employee_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:employees,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_value' => ['nullable', 'numeric', 'min:0'],
            'current_value' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'frequency' => ['nullable', Rule::in(['monthly', 'quarterly', 'annual'])],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'status' => ['nullable', Rule::in(['active', 'completed', 'cancelled'])],
        ]);
    }

    private function formatKpi(PerformanceKpi $kpi): array
    {
        return [
            'id' => $kpi->id,
            'title' => $kpi->title,
            'description' => $kpi->description,
            'target_value' => (float) $kpi->target_value,
            'current_value' => (float) $kpi->current_value,
            'unit' => $kpi->unit,
            'frequency' => $kpi->frequency,
            'period_start' => $kpi->period_start?->toDateString(),
            'period_end' => $kpi->period_end?->toDateString(),
            'status' => $kpi->status,
            'progress_percent' => $kpi->progressPercent(),
            'employee' => $this->employeeBrief($kpi->employee),
            'created_at' => $kpi->created_at?->toIso8601String(),
            'updated_at' => $kpi->updated_at?->toIso8601String(),
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
}
