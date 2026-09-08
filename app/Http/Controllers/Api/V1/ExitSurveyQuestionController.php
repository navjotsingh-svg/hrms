<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExitSurveyQuestionResource;
use App\Models\ExitSurveyQuestion;
use App\Services\ExitSurveyQuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExitSurveyQuestionController extends Controller
{
    use ApiResponse;

    public function __construct(private ExitSurveyQuestionService $questionService) {}

    public function meta(): JsonResponse
    {
        return $this->success($this->questionService->meta());
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $questions = $this->questionService->listForCompany($request->user()->company_id, $validated);

        return $this->success([
            'questions' => ExitSurveyQuestionResource::collection($questions->items()),
            'pagination' => [
                'current_page' => $questions->currentPage(),
                'last_page' => $questions->lastPage(),
                'per_page' => $questions->perPage(),
                'total' => $questions->total(),
                'from' => $questions->firstItem(),
                'to' => $questions->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'type' => ['required', Rule::in(array_keys(config('offboarding.survey_question_types', [])))],
            'options' => ['nullable'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $question = $this->questionService->create($request->user(), $validated);

        return $this->success([
            'question' => new ExitSurveyQuestionResource($question),
        ], 'Survey question created.', 201);
    }

    public function update(Request $request, ExitSurveyQuestion $exit_survey_question): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['sometimes', 'string', 'max:2000'],
            'type' => ['sometimes', Rule::in(array_keys(config('offboarding.survey_question_types', [])))],
            'options' => ['nullable'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $question = $this->questionService->update($request->user(), $exit_survey_question, $validated);

        return $this->success([
            'question' => new ExitSurveyQuestionResource($question),
        ], 'Survey question updated.');
    }

    public function reseed(Request $request): JsonResponse
    {
        $this->questionService->reseedDefaults($request->user());

        return $this->success(null, 'Exit survey questions reset to defaults.');
    }
}
