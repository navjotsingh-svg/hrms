<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\Candidate;
use App\Services\HiringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HiringCandidateController extends Controller
{
    use ApiResponse;

    public function __construct(private HiringService $hiringService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stage' => ['nullable', Rule::in(['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'])],
            'job_id' => ['nullable', 'integer', 'exists:job_postings,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
        ]);

        $paginator = $this->hiringService->listCandidates($request->user(), $validated);

        return $this->success([
            'candidates' => collect($paginator->items())->map(fn (Candidate $c) => $this->format($c))->values(),
            'pagination' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_id' => ['nullable', 'integer', 'exists:job_postings,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'source' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'assigned_recruiter_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $candidate = $this->hiringService->storeCandidate($request->user(), $validated);

        return $this->success(['candidate' => $this->format($candidate)], 'Candidate added.', 201);
    }

    public function updateStage(Request $request, Candidate $candidate): JsonResponse
    {
        $validated = $request->validate([
            'stage' => ['required', Rule::in(['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $candidate = $this->hiringService->updateCandidateStage(
            $request->user(),
            $candidate,
            $validated['stage'],
            $validated['notes'] ?? null
        );

        return $this->success(['candidate' => $this->format($candidate)], 'Candidate stage updated.');
    }

    public function show(Request $request, Candidate $candidate): JsonResponse
    {
        $candidate = $this->hiringService->candidateDetail($request->user(), $candidate);

        return $this->success(['candidate' => $this->formatDetail($candidate)]);
    }

    private function formatDetail(Candidate $candidate): array
    {
        return [
            ...$this->format($candidate),
            'resume_url' => $candidate->resume_path ? asset($candidate->resume_path) : null,
            'rejected_at' => $candidate->rejected_at?->toIso8601String(),
            'rejection_reason' => $candidate->rejection_reason,
            'hired_at' => $candidate->hired_at?->toIso8601String(),
            'employee' => $candidate->employee ? [
                'id' => $candidate->employee->id,
                'full_name' => $candidate->employee->full_name,
                'employee_code' => $candidate->employee->employee_code,
            ] : null,
            'stage_logs' => $candidate->stageLogs
                ->map(fn ($log) => [
                    'id' => $log->id,
                    'from_stage' => $log->from_stage,
                    'to_stage' => $log->to_stage,
                    'notes' => $log->notes,
                    'created_at' => $log->created_at?->toIso8601String(),
                    'actor_name' => $log->actor?->name,
                ])
                ->values()
                ->all(),
            'interviews' => $candidate->interviews
                ->map(fn ($interview) => [
                    'id' => $interview->id,
                    'title' => $interview->title,
                    'scheduled_at' => $interview->scheduled_at?->toIso8601String(),
                    'duration_minutes' => $interview->duration_minutes,
                    'location' => $interview->location,
                    'meeting_link' => $interview->meeting_link,
                    'status' => $interview->status,
                    'notes' => $interview->notes,
                    'job' => $interview->job ? ['id' => $interview->job->id, 'title' => $interview->job->title] : null,
                ])
                ->values()
                ->all(),
            'offers' => $candidate->offers
                ->map(fn ($offer) => [
                    'id' => $offer->id,
                    'title' => $offer->title,
                    'offered_ctc' => $offer->offered_ctc,
                    'joining_date' => $offer->joining_date?->format('Y-m-d'),
                    'status' => $offer->status,
                    'sent_at' => $offer->sent_at?->toIso8601String(),
                    'responded_at' => $offer->responded_at?->toIso8601String(),
                    'job' => $offer->job ? ['id' => $offer->job->id, 'title' => $offer->job->title] : null,
                ])
                ->values()
                ->all(),
        ];
    }

    private function format(Candidate $candidate): array
    {
        $candidate->loadMissing(['job', 'assignedRecruiter']);

        return [
            'id' => $candidate->id,
            'first_name' => $candidate->first_name,
            'last_name' => $candidate->last_name,
            'full_name' => trim($candidate->first_name.' '.$candidate->last_name),
            'email' => $candidate->email,
            'phone' => $candidate->phone,
            'source' => $candidate->source,
            'stage' => $candidate->stage,
            'notes' => $candidate->notes,
            'applied_at' => $candidate->applied_at?->toIso8601String(),
            'job' => $candidate->job ? ['id' => $candidate->job->id, 'title' => $candidate->job->title] : null,
            'assigned_recruiter' => $candidate->assignedRecruiter ? ['id' => $candidate->assignedRecruiter->id, 'name' => $candidate->assignedRecruiter->name] : null,
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
