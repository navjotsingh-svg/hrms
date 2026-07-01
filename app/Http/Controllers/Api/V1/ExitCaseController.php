<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExitCaseResource;
use App\Models\ExitAssetReturnItem;
use App\Models\ExitCase;
use App\Models\ExitClearanceItem;
use App\Services\ExitCaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ExitCaseController extends Controller
{
    use ApiResponse;

    public function __construct(private ExitCaseService $exitCaseService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', Rule::in(['in_progress', 'completed', 'cancelled'])],
            'stage' => ['nullable', Rule::in(['clearance', 'asset_return', 'survey', 'fnf', 'completed'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $cases = $this->exitCaseService->listForUser($request->user(), $validated);

        return $this->success([
            'exit_cases' => ExitCaseResource::collection($cases->items()),
            'pagination' => [
                'current_page' => $cases->currentPage(),
                'last_page' => $cases->lastPage(),
                'per_page' => $cases->perPage(),
                'total' => $cases->total(),
                'from' => $cases->firstItem(),
                'to' => $cases->lastItem(),
            ],
        ]);
    }

    public function show(Request $request, ExitCase $exitCase): JsonResponse
    {
        $exitCase = $this->exitCaseService->showForUser($request->user(), $exitCase);

        return $this->success(['exit_case' => new ExitCaseResource($exitCase)]);
    }

    public function reviewClearance(Request $request, ExitCase $exitCase): JsonResponse
    {
        $this->ensureCompanyCase($request, $exitCase);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['clear', 'reject'])],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'distinct'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $exitCase = $this->exitCaseService->reviewClearanceItems(
            $request->user(),
            $exitCase,
            $validated['item_ids'],
            $validated['action'],
            $validated['notes'] ?? null,
        );

        return $this->success(
            ['exit_case' => new ExitCaseResource($exitCase)],
            $validated['action'] === 'clear' ? 'Clearance updated.' : 'Clearance item rejected.',
        );
    }

    public function reviewClearanceItem(Request $request, ExitCase $exitCase, ExitClearanceItem $item): JsonResponse
    {
        $this->ensureItemBelongsToCase($exitCase, $item);
        $this->ensureCompanyCase($request, $exitCase);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['clear', 'reject'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $exitCase = $this->exitCaseService->reviewClearanceItem(
            $request->user(),
            $exitCase,
            $item,
            $validated['action'],
            $validated['notes'] ?? null,
        );

        return $this->success(['exit_case' => new ExitCaseResource($exitCase)], 'Clearance item updated.');
    }

    public function reviewAssets(Request $request, ExitCase $exitCase): JsonResponse
    {
        $this->ensureCompanyCase($request, $exitCase);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['returned', 'waived'])],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'distinct'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $exitCase = $this->exitCaseService->reviewAssetItems(
            $request->user(),
            $exitCase,
            $validated['item_ids'],
            $validated['action'],
            $validated['notes'] ?? null,
        );

        return $this->success(['exit_case' => new ExitCaseResource($exitCase)], 'Asset return updated.');
    }

    public function reviewAssetItem(Request $request, ExitCase $exitCase, ExitAssetReturnItem $item): JsonResponse
    {
        $this->ensureAssetItemBelongsToCase($exitCase, $item);
        $this->ensureCompanyCase($request, $exitCase);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['returned', 'waived'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $exitCase = $this->exitCaseService->markAssetReturned(
            $request->user(),
            $exitCase,
            $item,
            $validated['action'],
            $validated['notes'] ?? null,
        );

        return $this->success(['exit_case' => new ExitCaseResource($exitCase)], 'Asset return updated.');
    }

    public function submitSurvey(Request $request, ExitCase $exitCase): JsonResponse
    {
        $this->ensureCompanyCase($request, $exitCase);

        $validated = $request->validate([
            'responses' => ['required', 'array', 'min:1'],
        ]);

        $exitCase = $this->exitCaseService->submitSurvey(
            $request->user(),
            $exitCase,
            $validated['responses'],
        );

        return $this->success(['exit_case' => new ExitCaseResource($exitCase)], 'Exit survey submitted.');
    }

    public function saveSettlement(Request $request, ExitCase $exitCase): JsonResponse
    {
        $this->ensureCompanyCase($request, $exitCase);

        $validated = $request->validate([
            'leave_encashment' => ['nullable', 'numeric', 'min:0'],
            'pending_dues' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'settlement_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $exitCase = $this->exitCaseService->saveSettlement($request->user(), $exitCase, $validated);

        return $this->success(['exit_case' => new ExitCaseResource($exitCase)], 'F&F settlement saved.');
    }

    public function approveSettlement(Request $request, ExitCase $exitCase): JsonResponse
    {
        $this->ensureCompanyCase($request, $exitCase);

        $exitCase = $this->exitCaseService->approveSettlement($request->user(), $exitCase);

        return $this->success(['exit_case' => new ExitCaseResource($exitCase)], 'F&F settlement approved.');
    }

    public function markSettlementPaid(Request $request, ExitCase $exitCase): JsonResponse
    {
        $this->ensureCompanyCase($request, $exitCase);

        $exitCase = $this->exitCaseService->markSettlementPaid($request->user(), $exitCase);

        return $this->success(['exit_case' => new ExitCaseResource($exitCase)], 'Offboarding completed. Employee marked inactive.');
    }

    private function ensureCompanyCase(Request $request, ExitCase $exitCase): void
    {
        if ((int) $exitCase->company_id !== (int) $request->user()?->company_id) {
            abort(404);
        }

        if (! $request->user()?->canViewExitCase($exitCase)) {
            throw new AccessDeniedHttpException('You are not allowed to access this exit case.');
        }
    }

    private function ensureItemBelongsToCase(ExitCase $exitCase, ExitClearanceItem $item): void
    {
        if ((int) $item->exit_case_id !== (int) $exitCase->id) {
            abort(404);
        }
    }

    private function ensureAssetItemBelongsToCase(ExitCase $exitCase, ExitAssetReturnItem $item): void
    {
        if ((int) $item->exit_case_id !== (int) $exitCase->id) {
            abort(404);
        }
    }
}
