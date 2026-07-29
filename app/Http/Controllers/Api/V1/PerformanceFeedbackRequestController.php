<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\Employee;
use App\Models\PerformanceFeedbackRequest;
use App\Services\PerformanceFeedbackRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PerformanceFeedbackRequestController extends Controller
{
    use ApiResponse;

    public function __construct(private PerformanceFeedbackRequestService $requestService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'submitted', 'cancelled'])],
            'subject_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        $requests = $this->requestService->listForAdmin($request->user(), $validated);

        return $this->success([
            'requests' => $requests->map(fn ($item) => $this->formatRequest($item))->values(),
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        $requests = $this->requestService->listMine($request->user());

        return $this->success([
            'requests' => $requests->map(fn ($item) => $this->formatRequest($item))->values(),
        ]);
    }

    public function received(Request $request): JsonResponse
    {
        $requests = $this->requestService->listReceived($request->user());

        return $this->success([
            'requests' => $requests->map(fn ($item) => $this->formatRequest($item))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'feedback_form_id' => ['required', 'integer', 'exists:performance_feedback_forms,id'],
            'subject_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'reviewer_employee_ids' => ['required', 'array', 'min:1'],
            'reviewer_employee_ids.*' => ['integer', 'exists:employees,id'],
            'context_notes' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
        ]);

        $created = $this->requestService->store($request->user(), $validated);

        return $this->success(
            ['requests' => $created->map(fn ($item) => $this->formatRequest($item))->values()],
            'Feedback request(s) created successfully.',
            201
        );
    }

    public function show(Request $request, PerformanceFeedbackRequest $performanceFeedbackRequest): JsonResponse
    {
        $item = $this->requestService->resolveForView($request->user(), $performanceFeedbackRequest);

        return $this->success(['request' => $this->formatRequest($item, true)]);
    }

    public function submit(Request $request, PerformanceFeedbackRequest $performanceFeedbackRequest): JsonResponse
    {
        $validated = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.form_question_id' => ['required', 'integer', 'exists:performance_feedback_form_questions,id'],
            'answers.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'answers.*.response_text' => ['nullable', 'string'],
        ]);

        $item = $this->requestService->submit($request->user(), $performanceFeedbackRequest, $validated);

        return $this->success(
            ['request' => $this->formatRequest($item, true)],
            'Feedback submitted successfully.'
        );
    }

    public function cancel(Request $request, PerformanceFeedbackRequest $performanceFeedbackRequest): JsonResponse
    {
        $item = $this->requestService->cancel($request->user(), $performanceFeedbackRequest);

        return $this->success(
            ['request' => $this->formatRequest($item)],
            'Feedback request cancelled.'
        );
    }

    private function formatRequest(PerformanceFeedbackRequest $item, bool $detailed = false): array
    {
        $data = [
            'id' => $item->id,
            'status' => $item->status,
            'context_notes' => $item->context_notes,
            'due_date' => $item->due_date?->toDateString(),
            'overall_rating' => $item->overall_rating,
            'submitted_at' => $item->submitted_at?->toIso8601String(),
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
            'form' => $item->relationLoaded('feedbackForm') && $item->feedbackForm ? [
                'id' => $item->feedbackForm->id,
                'name' => $item->feedbackForm->name,
                'description' => $item->feedbackForm->description,
            ] : null,
            'subject' => $this->employeeBrief($item->subject),
            'reviewer' => $this->employeeBrief($item->reviewer),
            'requested_by' => $item->relationLoaded('requestedBy') && $item->requestedBy ? [
                'id' => $item->requestedBy->id,
                'name' => $item->requestedBy->name,
            ] : null,
        ];

        if ($detailed) {
            if ($data['form']) {
                $data['form']['questions'] = $item->feedbackForm?->questions?->map(fn ($q) => [
                    'id' => $q->id,
                    'question' => $q->question,
                    'question_type' => $q->question_type,
                    'weight' => (float) $q->weight,
                    'sort_order' => $q->sort_order,
                ])->values() ?? [];
            }

            $data['answers'] = $item->relationLoaded('answers')
                ? $item->answers->map(fn ($answer) => [
                    'id' => $answer->id,
                    'form_question_id' => $answer->form_question_id,
                    'question' => $answer->formQuestion?->question,
                    'question_type' => $answer->formQuestion?->question_type,
                    'rating' => $answer->rating,
                    'response_text' => $answer->response_text,
                ])->values()
                : [];
        }

        return $data;
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
