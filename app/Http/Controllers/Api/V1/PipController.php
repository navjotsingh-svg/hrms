<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\Employee;
use App\Models\PipKeyResult;
use App\Models\PipPlan;
use App\Services\PipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PipController extends Controller
{
    use ApiResponse;

    public function __construct(private PipService $pipService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'active', 'completed', 'failed', 'cancelled'])],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $pips = $this->pipService->listForUser($request->user(), $validated);

        return $this->success([
            'pips' => $pips->getCollection()->map(fn (PipPlan $pip) => $this->formatPip($pip))->values(),
            'pagination' => [
                'current_page' => $pips->currentPage(),
                'last_page' => $pips->lastPage(),
                'per_page' => $pips->perPage(),
                'total' => $pips->total(),
                'from' => $pips->firstItem(),
                'to' => $pips->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePipPayload($request, true);

        $pip = $this->pipService->store($request->user(), $validated);

        return $this->success(
            ['pip' => $this->formatPip($pip, true)],
            'PIP created successfully.',
            201
        );
    }

    public function show(Request $request, PipPlan $pipPlan): JsonResponse
    {
        $pip = $this->pipService->resolvePip($request->user(), $pipPlan);

        return $this->success([
            'pip' => $this->formatPip($pip, true),
        ]);
    }

    public function update(Request $request, PipPlan $pipPlan): JsonResponse
    {
        $validated = $this->validatePipPayload($request, false);

        $pip = $this->pipService->update($request->user(), $pipPlan, $validated);

        return $this->success(
            ['pip' => $this->formatPip($pip, true)],
            'PIP updated successfully.'
        );
    }

    public function updateStatus(Request $request, PipPlan $pipPlan): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'active', 'completed', 'failed', 'cancelled'])],
        ]);

        $pip = $this->pipService->updateStatus($request->user(), $pipPlan, $validated['status']);

        return $this->success(
            ['pip' => $this->formatPip($pip, true)],
            'PIP status updated successfully.'
        );
    }

    public function updateKeyResult(Request $request, PipPlan $pipPlan, PipKeyResult $pipKeyResult): JsonResponse
    {
        if ((int) $pipKeyResult->pip_plan_id !== (int) $pipPlan->id) {
            abort(404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'completed', 'missed'])],
        ]);

        $keyResult = $this->pipService->updateKeyResult($request->user(), $pipKeyResult, $validated);

        return $this->success(
            ['key_result' => $this->formatKeyResult($keyResult)],
            'Key result updated successfully.'
        );
    }

    private function validatePipPayload(Request $request, bool $creating): array
    {
        return $request->validate([
            'employee_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:employees,id'],
            'manager_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'title' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'outcome_notes' => ['nullable', 'string'],
            'key_results' => ['nullable', 'array'],
            'key_results.*.id' => ['nullable', 'integer'],
            'key_results.*.title' => ['required_with:key_results', 'string', 'max:255'],
            'key_results.*.description' => ['nullable', 'string'],
            'key_results.*.target_date' => ['nullable', 'date'],
            'key_results.*.status' => ['nullable', Rule::in(['pending', 'in_progress', 'completed', 'missed'])],
        ]);
    }

    private function formatPip(PipPlan $pip, bool $detailed = false): array
    {
        $data = [
            'id' => $pip->id,
            'title' => $pip->title,
            'reason' => $pip->reason,
            'start_date' => $pip->start_date?->toDateString(),
            'end_date' => $pip->end_date?->toDateString(),
            'status' => $pip->status,
            'outcome_notes' => $pip->outcome_notes,
            'employee' => $this->employeeBrief($pip->employee),
            'manager' => $this->employeeBrief($pip->manager),
            'created_at' => $pip->created_at?->toIso8601String(),
            'updated_at' => $pip->updated_at?->toIso8601String(),
        ];

        if ($detailed || $pip->relationLoaded('keyResults')) {
            $data['key_results'] = $pip->keyResults->map(fn ($kr) => $this->formatKeyResult($kr))->values();
        }

        return $data;
    }

    private function formatKeyResult(PipKeyResult $keyResult): array
    {
        return [
            'id' => $keyResult->id,
            'title' => $keyResult->title,
            'description' => $keyResult->description,
            'target_date' => $keyResult->target_date?->toDateString(),
            'status' => $keyResult->status,
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
}
