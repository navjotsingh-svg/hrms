<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\RejectExpenseRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Http\Resources\ExpenseTypeResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use App\Services\ExpenseTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ExpenseService $expenseService,
        private ExpenseTypeService $expenseTypeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'pending', 'approved', 'rejected', 'cancelled'])],
            'belongs_to' => ['nullable', Rule::in(['all', 'myself', 'reportees'])],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $expenses = $this->expenseService->listForUser($request->user(), $validated);

        return $this->success([
            'expenses' => ExpenseResource::collection($expenses->items()),
            'pagination' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
                'from' => $expenses->firstItem(),
                'to' => $expenses->lastItem(),
            ],
        ]);
    }

    public function typeOptions(Request $request): JsonResponse
    {
        $types = $this->expenseTypeService->optionsForUser($request->user());

        return $this->success([
            'expense_types' => ExpenseTypeResource::collection($types),
        ]);
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $files = array_filter([$request->file('receipt')]);

        $expense = $this->expenseService->storeIndependent(
            $request->user(),
            $request->validated(),
            $files,
        );

        return $this->success([
            'expense' => new ExpenseResource($expense),
        ], 'Expense created successfully.', 201);
    }

    public function show(Request $request, Expense $expense): JsonResponse
    {
        $expense = $this->expenseService->resolveForUser($request->user(), $expense);

        return $this->success([
            'expense' => new ExpenseResource($expense),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $files = array_filter([$request->file('receipt')]);

        $expense = $this->expenseService->updateExpense(
            $request->user(),
            $expense,
            $request->validated(),
            $files,
        );

        return $this->success([
            'expense' => new ExpenseResource($expense),
        ], 'Expense updated successfully.');
    }

    public function submit(Request $request, Expense $expense): JsonResponse
    {
        $expense = $this->expenseService->submitIndependent($request->user(), $expense);

        return $this->success([
            'expense' => new ExpenseResource($expense),
        ], 'Expense submitted for approval.');
    }

    public function approve(Request $request, Expense $expense): JsonResponse
    {
        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $expense = $this->expenseService->approve($request->user(), $expense, $validated['review_notes'] ?? null);

        return $this->success([
            'expense' => new ExpenseResource($expense),
        ], 'Expense approved.');
    }

    public function reject(RejectExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense = $this->expenseService->reject($request->user(), $expense, $request->validated('notes'));

        return $this->success([
            'expense' => new ExpenseResource($expense),
        ], 'Expense rejected.');
    }

    public function cancel(Request $request, Expense $expense): JsonResponse
    {
        $expense = $this->expenseService->cancel($request->user(), $expense);

        return $this->success([
            'expense' => new ExpenseResource($expense),
        ], 'Expense cancelled.');
    }

    public function markPaid(Request $request, Expense $expense): JsonResponse
    {
        $expense = $this->expenseService->markAsPaid($request->user(), $expense);

        return $this->success([
            'expense' => new ExpenseResource($expense),
        ], 'Expense marked as paid.');
    }

    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'pending', 'approved', 'rejected', 'cancelled'])],
            'belongs_to' => ['nullable', Rule::in(['all', 'myself', 'reportees'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $rows = $this->expenseService->exportRows($request->user(), $validated);

        $filename = 'expenses-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Expense Date',
                'Created On',
                'Expense Type',
                'Amount',
                'Payout Status',
                'Approval Status',
                'Actioned By',
                'Belongs To',
                'Merchant',
                'Reference',
            ]);

            foreach ($rows as $expense) {
                fputcsv($handle, [
                    $expense->expense_date?->format('d M Y'),
                    $expense->created_at?->format('d M Y'),
                    $expense->expenseType?->name,
                    number_format((float) $expense->amount, 2, '.', ''),
                    ucfirst($expense->payout_status),
                    ucfirst($expense->status),
                    $expense->reviewedBy?->name,
                    $expense->employee?->full_name,
                    $expense->merchant,
                    $expense->reference_number,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
