<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\Employee;
use App\Models\PerformanceReviewCycle;
use App\Services\PerformanceReviewCycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PerformanceReviewCycleController extends Controller
{
    use ApiResponse;

    public function __construct(private PerformanceReviewCycleService $cycleService) {}

    public function index(Request $request): JsonResponse
    {
        $cycles = $this->cycleService->listForUser($request->user());

        return $this->success([
            'cycles' => $cycles->map(fn (PerformanceReviewCycle $cycle) => $this->formatCycle($cycle))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateCyclePayload($request);

        $cycle = $this->cycleService->store($request->user(), $validated);

        return $this->success(
            ['cycle' => $this->formatCycle($cycle, true)],
            'Review cycle created successfully.',
            201
        );
    }

    public function show(Request $request, PerformanceReviewCycle $performanceReviewCycle): JsonResponse
    {
        $cycle = $this->cycleService->resolveCycle($request->user(), $performanceReviewCycle);
        $cycle->load(['questions', 'pairs.reviewee', 'pairs.reviewer']);

        return $this->success([
            'cycle' => $this->formatCycle($cycle, true),
        ]);
    }

    public function update(Request $request, PerformanceReviewCycle $performanceReviewCycle): JsonResponse
    {
        $validated = $this->validateCyclePayload($request);

        $cycle = $this->cycleService->update(
            $request->user(),
            $performanceReviewCycle,
            $validated
        );

        return $this->success(
            ['cycle' => $this->formatCycle($cycle, true)],
            'Review cycle updated successfully.'
        );
    }

    public function activate(Request $request, PerformanceReviewCycle $performanceReviewCycle): JsonResponse
    {
        $cycle = $this->cycleService->activate($request->user(), $performanceReviewCycle);

        return $this->success(
            ['cycle' => $this->formatCycle($cycle)],
            'Review cycle activated successfully.'
        );
    }

    public function close(Request $request, PerformanceReviewCycle $performanceReviewCycle): JsonResponse
    {
        $cycle = $this->cycleService->close($request->user(), $performanceReviewCycle);

        return $this->success(
            ['cycle' => $this->formatCycle($cycle)],
            'Review cycle closed successfully.'
        );
    }

    public function toggleReviewsOpen(Request $request, PerformanceReviewCycle $performanceReviewCycle): JsonResponse
    {
        $validated = $request->validate([
            'reviews_open' => ['required', 'boolean'],
        ]);

        $cycle = $this->cycleService->toggleReviewsOpen(
            $request->user(),
            $performanceReviewCycle,
            (bool) $validated['reviews_open']
        );

        return $this->success(
            ['cycle' => $this->formatCycle($cycle)],
            'Review cycle updated successfully.'
        );
    }

    public function progress(Request $request, PerformanceReviewCycle $performanceReviewCycle): JsonResponse
    {
        $progress = $this->cycleService->progress($request->user(), $performanceReviewCycle);

        return $this->success(['progress' => $progress]);
    }

    public function sendReminders(Request $request, PerformanceReviewCycle $performanceReviewCycle): JsonResponse
    {
        $sent = $this->cycleService->sendReminders($request->user(), $performanceReviewCycle);

        return $this->success(
            ['sent' => $sent],
            "{$sent} reminder(s) sent successfully."
        );
    }

    public function myReviews(Request $request): JsonResponse
    {
        $reviews = $this->cycleService->myReviews($request->user());

        return $this->success([
            'reviews' => $reviews->map(fn ($review) => $this->formatReviewSummary($review))->values(),
        ]);
    }

    private function validateCyclePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'questions' => ['nullable', 'array'],
            'questions.*.question' => ['required_with:questions', 'string', 'max:500'],
            'questions.*.weight' => ['nullable', 'numeric', 'min:0'],
            'pairs' => ['nullable', 'array'],
            'pairs.*.reviewee_employee_id' => ['required_with:pairs', 'integer', 'exists:employees,id'],
            'pairs.*.reviewer_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'pairs.*.relationship' => ['nullable', Rule::in(['self', 'manager', 'peer', 'hr'])],
        ]);
    }

    private function formatCycle(PerformanceReviewCycle $cycle, bool $detailed = false): array
    {
        $data = [
            'id' => $cycle->id,
            'name' => $cycle->name,
            'description' => $cycle->description,
            'period_start' => $cycle->period_start?->toDateString(),
            'period_end' => $cycle->period_end?->toDateString(),
            'status' => $cycle->status,
            'reviews_open' => (bool) $cycle->reviews_open,
            'reviews_count' => $cycle->reviews_count ?? null,
            'pairs_count' => $cycle->pairs_count ?? null,
            'created_at' => $cycle->created_at?->toIso8601String(),
            'updated_at' => $cycle->updated_at?->toIso8601String(),
        ];

        if (! $detailed) {
            return $data;
        }

        $data['questions'] = $cycle->relationLoaded('questions')
            ? $cycle->questions->map(fn ($question) => [
                'id' => $question->id,
                'question' => $question->question,
                'weight' => (float) $question->weight,
                'sort_order' => $question->sort_order,
            ])->values()
            : [];

        $data['pairs'] = $cycle->relationLoaded('pairs')
            ? $cycle->pairs->map(fn ($pair) => [
                'id' => $pair->id,
                'reviewee' => $this->employeeBrief($pair->reviewee),
                'reviewer' => $this->employeeBrief($pair->reviewer),
                'relationship' => $pair->relationship,
            ])->values()
            : [];

        return $data;
    }

    private function formatReviewSummary($review): array
    {
        return [
            'id' => $review->id,
            'status' => $review->status,
            'overall_rating' => $review->overall_rating,
            'submitted_at' => $review->submitted_at?->toIso8601String(),
            'cycle' => $review->cycle ? [
                'id' => $review->cycle->id,
                'name' => $review->cycle->name,
                'status' => $review->cycle->status,
                'reviews_open' => (bool) $review->cycle->reviews_open,
            ] : null,
            'reviewee' => $this->employeeBrief($review->reviewee),
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
