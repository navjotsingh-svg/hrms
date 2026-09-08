<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\PerformanceQuestionBank;
use App\Services\PerformanceQuestionBankService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PerformanceQuestionBankController extends Controller
{
    use ApiResponse;

    public function __construct(private PerformanceQuestionBankService $questionBankService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $questions = $this->questionBankService->listForUser($request->user(), $validated);

        return $this->success([
            'questions' => $questions->map(fn (PerformanceQuestionBank $q) => $this->formatQuestion($q))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $question = $this->questionBankService->store($request->user(), $validated);

        return $this->success(
            ['question' => $this->formatQuestion($question)],
            'Question added to bank successfully.',
            201
        );
    }

    public function update(Request $request, PerformanceQuestionBank $performanceQuestionBank): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $question = $this->questionBankService->update($request->user(), $performanceQuestionBank, $validated);

        return $this->success(
            ['question' => $this->formatQuestion($question)],
            'Question updated successfully.'
        );
    }

    public function destroy(Request $request, PerformanceQuestionBank $performanceQuestionBank): JsonResponse
    {
        $this->questionBankService->delete($request->user(), $performanceQuestionBank);

        return $this->success(null, 'Question deleted successfully.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
            'question' => ['required', 'string', 'max:500'],
            'question_type' => ['nullable', Rule::in(['rating', 'text'])],
            'default_weight' => ['nullable', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function formatQuestion(PerformanceQuestionBank $question): array
    {
        return [
            'id' => $question->id,
            'category' => $question->category,
            'question' => $question->question,
            'question_type' => $question->question_type,
            'default_weight' => (float) $question->default_weight,
            'sort_order' => $question->sort_order,
            'is_active' => (bool) $question->is_active,
            'created_at' => $question->created_at?->toIso8601String(),
        ];
    }
}
