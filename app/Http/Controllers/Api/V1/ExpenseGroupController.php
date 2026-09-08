<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\RejectExpenseRequest;
use App\Http\Requests\StoreExpenseGroupRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Resources\ExpenseGroupResource;
use App\Http\Resources\ExpenseResource;
use App\Models\ExpenseGroup;
use App\Services\ExpenseGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseGroupController extends Controller
{
    use ApiResponse;

    public function __construct(private ExpenseGroupService $expenseGroupService) {}

    public function draftOptions(Request $request): JsonResponse
    {
        $groups = $this->expenseGroupService->draftOptionsForUser($request->user());

        return $this->success([
            'expense_groups' => $groups->map(fn (ExpenseGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
            ])->values(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'pending', 'approved', 'rejected', 'cancelled'])],
            'belongs_to' => ['nullable', Rule::in(['all', 'myself', 'reportees'])],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $groups = $this->expenseGroupService->listForUser($request->user(), $validated);

        return $this->success([
            'expense_groups' => ExpenseGroupResource::collection($groups->items()),
            'pagination' => [
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'per_page' => $groups->perPage(),
                'total' => $groups->total(),
                'from' => $groups->firstItem(),
                'to' => $groups->lastItem(),
            ],
        ]);
    }

    public function store(StoreExpenseGroupRequest $request): JsonResponse
    {
        $group = $this->expenseGroupService->store($request->user(), $request->validated());

        return $this->success([
            'expense_group' => new ExpenseGroupResource($group),
        ], 'Expense group created.', 201);
    }

    public function update(StoreExpenseGroupRequest $request, ExpenseGroup $expenseGroup): JsonResponse
    {
        $group = $this->expenseGroupService->update($request->user(), $expenseGroup, $request->validated());

        return $this->success([
            'expense_group' => new ExpenseGroupResource($group),
        ], 'Expense group updated.');
    }

    public function show(Request $request, ExpenseGroup $expenseGroup): JsonResponse
    {
        $group = $this->expenseGroupService->resolveForUser($request->user(), $expenseGroup);

        return $this->success([
            'expense_group' => new ExpenseGroupResource($group),
        ]);
    }

    public function addExpense(StoreExpenseRequest $request, ExpenseGroup $expenseGroup): JsonResponse
    {
        $files = array_filter([$request->file('receipt')]);

        $expense = $this->expenseGroupService->addExpense(
            $request->user(),
            $expenseGroup,
            $request->validated(),
            $files,
        );

        return $this->success([
            'expense' => new ExpenseResource($expense),
        ], 'Expense added to group.', 201);
    }

    public function submit(Request $request, ExpenseGroup $expenseGroup): JsonResponse
    {
        $group = $this->expenseGroupService->submit($request->user(), $expenseGroup);

        return $this->success([
            'expense_group' => new ExpenseGroupResource($group),
        ], 'Expense group submitted for approval.');
    }

    public function approve(Request $request, ExpenseGroup $expenseGroup): JsonResponse
    {
        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $group = $this->expenseGroupService->approve(
            $request->user(),
            $expenseGroup,
            $validated['review_notes'] ?? null,
        );

        return $this->success([
            'expense_group' => new ExpenseGroupResource($group),
        ], 'Expense group approved.');
    }

    public function reject(RejectExpenseRequest $request, ExpenseGroup $expenseGroup): JsonResponse
    {
        $group = $this->expenseGroupService->reject(
            $request->user(),
            $expenseGroup,
            $request->validated('notes'),
        );

        return $this->success([
            'expense_group' => new ExpenseGroupResource($group),
        ], 'Expense group rejected.');
    }

    public function cancel(Request $request, ExpenseGroup $expenseGroup): JsonResponse
    {
        $group = $this->expenseGroupService->cancel($request->user(), $expenseGroup);

        return $this->success([
            'expense_group' => new ExpenseGroupResource($group),
        ], 'Expense group cancelled.');
    }
}
