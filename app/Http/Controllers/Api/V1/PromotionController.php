<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\Employee;
use App\Models\PromotionNomination;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    use ApiResponse;

    public function __construct(private PromotionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'nominated', 'approved', 'rejected', 'cancelled'])],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $nominations = $this->service->listForUser($request->user(), $validated);

        return $this->success([
            'nominations' => $nominations->getCollection()->map(fn ($item) => $this->formatNomination($item))->values(),
            'pagination' => $this->pagination($nominations),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'current_designation' => ['nullable', 'string', 'max:255'],
            'proposed_designation' => ['required', 'string', 'max:255'],
            'justification' => ['nullable', 'string'],
            'review_cycle_id' => ['nullable', 'integer', 'exists:performance_review_cycles,id'],
            'effective_date' => ['nullable', 'date'],
        ]);

        $nomination = $this->service->store($request->user(), $validated);

        return $this->success(
            ['nomination' => $this->formatNomination($nomination)],
            'Promotion nomination created successfully.',
            201
        );
    }

    public function show(Request $request, PromotionNomination $promotionNomination): JsonResponse
    {
        $nomination = $this->service->resolveNomination($request->user(), $promotionNomination);

        return $this->success([
            'nomination' => $this->formatNomination($nomination),
        ]);
    }

    public function update(Request $request, PromotionNomination $promotionNomination): JsonResponse
    {
        $validated = $request->validate([
            'proposed_designation' => ['required', 'string', 'max:255'],
            'justification' => ['nullable', 'string'],
            'review_cycle_id' => ['nullable', 'integer', 'exists:performance_review_cycles,id'],
            'effective_date' => ['nullable', 'date'],
        ]);

        $nomination = $this->service->update($request->user(), $promotionNomination, $validated);

        return $this->success(
            ['nomination' => $this->formatNomination($nomination)],
            'Promotion nomination updated successfully.'
        );
    }

    public function updateStatus(Request $request, PromotionNomination $promotionNomination): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['nominated', 'approved', 'rejected', 'cancelled'])],
        ]);

        $nomination = $this->service->updateStatus($request->user(), $promotionNomination, $validated['status']);

        return $this->success(
            ['nomination' => $this->formatNomination($nomination)],
            'Promotion status updated successfully.'
        );
    }

    private function formatNomination(PromotionNomination $nomination): array
    {
        return [
            'id' => $nomination->id,
            'employee' => $this->employeeBrief($nomination->employee),
            'current_designation' => $nomination->current_designation,
            'proposed_designation' => $nomination->proposed_designation,
            'justification' => $nomination->justification,
            'effective_date' => $nomination->effective_date?->toDateString(),
            'status' => $nomination->status,
            'review_cycle' => $nomination->reviewCycle ? [
                'id' => $nomination->reviewCycle->id,
                'name' => $nomination->reviewCycle->name,
            ] : null,
            'approved_at' => $nomination->approved_at?->toIso8601String(),
            'created_at' => $nomination->created_at?->toIso8601String(),
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
            'designation' => $employee->designation,
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
