<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\PerformanceFeedbackForm;
use App\Services\PerformanceFeedbackFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PerformanceFeedbackFormController extends Controller
{
    use ApiResponse;

    public function __construct(private PerformanceFeedbackFormService $formService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'active', 'archived'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $forms = $this->formService->listForUser($request->user(), $validated);

        return $this->success([
            'forms' => $forms->map(fn (PerformanceFeedbackForm $form) => $this->formatForm($form))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $form = $this->formService->store($request->user(), $validated);

        return $this->success(
            ['form' => $this->formatForm($form, true)],
            'Feedback form created successfully.',
            201
        );
    }

    public function show(Request $request, PerformanceFeedbackForm $performanceFeedbackForm): JsonResponse
    {
        $form = $this->formService->resolve($request->user(), $performanceFeedbackForm);

        return $this->success(['form' => $this->formatForm($form, true)]);
    }

    public function update(Request $request, PerformanceFeedbackForm $performanceFeedbackForm): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $form = $this->formService->update($request->user(), $performanceFeedbackForm, $validated);

        return $this->success(
            ['form' => $this->formatForm($form, true)],
            'Feedback form updated successfully.'
        );
    }

    public function destroy(Request $request, PerformanceFeedbackForm $performanceFeedbackForm): JsonResponse
    {
        $this->formService->delete($request->user(), $performanceFeedbackForm);

        return $this->success(null, 'Feedback form deleted successfully.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'archived'])],
            'questions' => ['nullable', 'array'],
            'questions.*.question_bank_id' => ['nullable', 'integer', 'exists:performance_question_bank,id'],
            'questions.*.question' => ['required_with:questions', 'string', 'max:500'],
            'questions.*.question_type' => ['nullable', Rule::in(['rating', 'text'])],
            'questions.*.weight' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function formatForm(PerformanceFeedbackForm $form, bool $detailed = false): array
    {
        $data = [
            'id' => $form->id,
            'name' => $form->name,
            'description' => $form->description,
            'status' => $form->status,
            'questions_count' => $form->questions_count ?? null,
            'created_at' => $form->created_at?->toIso8601String(),
            'updated_at' => $form->updated_at?->toIso8601String(),
        ];

        if ($detailed || $form->relationLoaded('questions')) {
            $data['questions'] = $form->questions->map(fn ($q) => [
                'id' => $q->id,
                'question_bank_id' => $q->question_bank_id,
                'question' => $q->question,
                'question_type' => $q->question_type,
                'weight' => (float) $q->weight,
                'sort_order' => $q->sort_order,
            ])->values();
        }

        return $data;
    }
}
