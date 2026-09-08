<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Services\PerformanceReviewCycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceReviewController extends Controller
{
    use ApiResponse;

    public function __construct(private PerformanceReviewCycleService $cycleService) {}

    public function show(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $review = $this->cycleService->resolveReview($request->user(), $performanceReview);

        return $this->success([
            'review' => $this->formatReview($review),
        ]);
    }

    public function submit(Request $request, PerformanceReview $performanceReview): JsonResponse
    {
        $validated = $request->validate([
            'summary_notes' => ['nullable', 'string'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'exists:performance_review_questions,id'],
            'answers.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'answers.*.comment' => ['nullable', 'string'],
        ]);

        $review = $this->cycleService->submitReview(
            $request->user(),
            $performanceReview,
            $validated
        );

        return $this->success(
            ['review' => $this->formatReview($review)],
            'Review submitted successfully.'
        );
    }

    private function formatReview(PerformanceReview $review): array
    {
        return [
            'id' => $review->id,
            'status' => $review->status,
            'overall_rating' => $review->overall_rating,
            'summary_notes' => $review->summary_notes,
            'submitted_at' => $review->submitted_at?->toIso8601String(),
            'cycle' => $review->cycle ? [
                'id' => $review->cycle->id,
                'name' => $review->cycle->name,
                'status' => $review->cycle->status,
                'reviews_open' => (bool) $review->cycle->reviews_open,
                'questions' => $review->cycle->relationLoaded('questions')
                    ? $review->cycle->questions->map(fn ($question) => [
                        'id' => $question->id,
                        'question' => $question->question,
                        'weight' => (float) $question->weight,
                        'sort_order' => $question->sort_order,
                    ])->values()
                    : [],
            ] : null,
            'reviewee' => $this->employeeBrief($review->reviewee),
            'answers' => $review->relationLoaded('answers')
                ? $review->answers->map(fn ($answer) => [
                    'id' => $answer->id,
                    'question_id' => $answer->question_id,
                    'question' => $answer->question?->question,
                    'rating' => $answer->rating,
                    'comment' => $answer->comment,
                ])->values()
                : [],
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
