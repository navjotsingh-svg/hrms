<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\CandidateInterview;
use App\Services\HiringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HiringInterviewController extends Controller
{
    use ApiResponse;

    public function __construct(private HiringService $hiringService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['scheduled', 'completed', 'cancelled', 'no_show'])],
            'candidate_id' => ['nullable', 'integer', 'exists:candidates,id'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
        ]);

        $paginator = $this->hiringService->listInterviews($request->user(), $validated);

        return $this->success([
            'interviews' => collect($paginator->items())->map(fn (CandidateInterview $i) => $this->format($i))->values(),
            'pagination' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'candidate_id' => ['required', 'integer', 'exists:candidates,id'],
            'job_id' => ['nullable', 'integer', 'exists:job_postings,id'],
            'title' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'panel_user_ids' => ['nullable', 'array'],
            'panel_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $interview = $this->hiringService->storeInterview($request->user(), $validated);

        return $this->success(['interview' => $this->format($interview)], 'Interview scheduled.', 201);
    }

    public function update(Request $request, CandidateInterview $candidateInterview): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'scheduled_at' => ['sometimes', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['scheduled', 'completed', 'cancelled', 'no_show'])],
            'panel_user_ids' => ['nullable', 'array'],
            'panel_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $interview = $this->hiringService->updateInterview($request->user(), $candidateInterview, $validated);

        return $this->success(['interview' => $this->format($interview)], 'Interview updated.');
    }

    private function format(CandidateInterview $interview): array
    {
        $interview->loadMissing(['candidate', 'job']);

        return [
            'id' => $interview->id,
            'title' => $interview->title,
            'scheduled_at' => $interview->scheduled_at?->toIso8601String(),
            'duration_minutes' => $interview->duration_minutes,
            'location' => $interview->location,
            'meeting_link' => $interview->meeting_link,
            'notes' => $interview->notes,
            'status' => $interview->status,
            'panel_user_ids' => $interview->panel_user_ids,
            'candidate' => $interview->candidate ? [
                'id' => $interview->candidate->id,
                'full_name' => trim($interview->candidate->first_name.' '.$interview->candidate->last_name),
            ] : null,
            'job' => $interview->job ? ['id' => $interview->job->id, 'title' => $interview->job->title] : null,
        ];
    }

    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
