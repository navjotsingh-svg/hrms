<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\CompensationBand;
use App\Models\CompensationRecommendation;
use App\Models\Employee;
use App\Services\CompensationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompensationController extends Controller
{
    use ApiResponse;

    public function __construct(private CompensationService $service) {}

    public function indexBands(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'active_only' => ['nullable'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $bands = $this->service->listBands($request->user(), $validated);

        return $this->success([
            'bands' => $bands->getCollection()->map(fn ($band) => $this->formatBand($band))->values(),
            'pagination' => $this->pagination($bands),
        ]);
    }

    public function storeBand(Request $request): JsonResponse
    {
        $validated = $this->validateBandPayload($request);

        $band = $this->service->storeBand($request->user(), $validated);

        return $this->success(['band' => $this->formatBand($band)], 'Salary band created successfully.', 201);
    }

    public function updateBand(Request $request, CompensationBand $compensationBand): JsonResponse
    {
        $validated = $this->validateBandPayload($request);
        $band = $this->service->updateBand($request->user(), $compensationBand, $validated);

        return $this->success(['band' => $this->formatBand($band)], 'Salary band updated successfully.');
    }

    public function indexRecommendations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'proposed', 'approved', 'applied'])],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $recommendations = $this->service->listRecommendations($request->user(), $validated);

        return $this->success([
            'recommendations' => $recommendations->getCollection()->map(fn ($item) => $this->formatRecommendation($item))->values(),
            'pagination' => $this->pagination($recommendations),
        ]);
    }

    public function storeRecommendation(Request $request): JsonResponse
    {
        $validated = $this->validateRecommendationPayload($request, true);
        $recommendation = $this->service->storeRecommendation($request->user(), $validated);

        return $this->success(
            ['recommendation' => $this->formatRecommendation($recommendation)],
            'Merit recommendation created successfully.',
            201
        );
    }

    public function updateRecommendation(Request $request, CompensationRecommendation $compensationRecommendation): JsonResponse
    {
        $validated = $this->validateRecommendationPayload($request, false);
        $recommendation = $this->service->updateRecommendation($request->user(), $compensationRecommendation, $validated);

        return $this->success(
            ['recommendation' => $this->formatRecommendation($recommendation)],
            'Merit recommendation updated successfully.'
        );
    }

    public function updateRecommendationStatus(Request $request, CompensationRecommendation $compensationRecommendation): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'proposed', 'approved', 'applied'])],
        ]);

        $recommendation = $this->service->updateRecommendationStatus(
            $request->user(),
            $compensationRecommendation,
            $validated['status']
        );

        return $this->success(
            ['recommendation' => $this->formatRecommendation($recommendation)],
            'Merit recommendation status updated successfully.'
        );
    }

    private function validateBandPayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'grade' => ['nullable', 'string', 'max:50'],
            'min_salary' => ['required', 'numeric', 'min:0'],
            'mid_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function validateRecommendationPayload(Request $request, bool $creating): array
    {
        return $request->validate([
            'employee_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:employees,id'],
            'review_cycle_id' => ['nullable', 'integer', 'exists:performance_review_cycles,id'],
            'band_id' => ['nullable', 'integer', 'exists:compensation_bands,id'],
            'current_salary' => ['nullable', 'numeric', 'min:0'],
            'recommended_increase_percent' => ['nullable', 'numeric', 'min:0'],
            'recommended_increase_amount' => ['nullable', 'numeric', 'min:0'],
            'recommended_new_salary' => ['nullable', 'numeric', 'min:0'],
            'merit_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function formatBand(CompensationBand $band): array
    {
        return [
            'id' => $band->id,
            'name' => $band->name,
            'grade' => $band->grade,
            'min_salary' => (float) $band->min_salary,
            'mid_salary' => $band->mid_salary !== null ? (float) $band->mid_salary : null,
            'max_salary' => (float) $band->max_salary,
            'currency' => $band->currency,
            'description' => $band->description,
            'is_active' => (bool) $band->is_active,
        ];
    }

    private function formatRecommendation(CompensationRecommendation $recommendation): array
    {
        return [
            'id' => $recommendation->id,
            'employee' => $this->employeeBrief($recommendation->employee),
            'band' => $recommendation->band ? [
                'id' => $recommendation->band->id,
                'name' => $recommendation->band->name,
            ] : null,
            'review_cycle' => $recommendation->reviewCycle ? [
                'id' => $recommendation->reviewCycle->id,
                'name' => $recommendation->reviewCycle->name,
            ] : null,
            'current_salary' => $recommendation->current_salary !== null ? (float) $recommendation->current_salary : null,
            'recommended_increase_percent' => $recommendation->recommended_increase_percent !== null ? (float) $recommendation->recommended_increase_percent : null,
            'recommended_increase_amount' => $recommendation->recommended_increase_amount !== null ? (float) $recommendation->recommended_increase_amount : null,
            'recommended_new_salary' => $recommendation->recommended_new_salary !== null ? (float) $recommendation->recommended_new_salary : null,
            'merit_rating' => $recommendation->merit_rating !== null ? (float) $recommendation->merit_rating : null,
            'notes' => $recommendation->notes,
            'status' => $recommendation->status,
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
