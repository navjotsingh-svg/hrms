<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\JobPosting;
use App\Services\HiringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HiringJobController extends Controller
{
    use ApiResponse;

    public function __construct(private HiringService $hiringService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'open', 'closed'])],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
        ]);

        $paginator = $this->hiringService->listJobs($request->user(), $validated);

        return $this->success([
            'jobs' => collect($paginator->items())->map(fn (JobPosting $job) => $this->format($job))->values(),
            'pagination' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $job = $this->hiringService->storeJob($request->user(), $validated);

        return $this->success(['job' => $this->format($job)], 'Job created.', 201);
    }

    public function update(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $validated = $this->validatePayload($request, true);

        $job = $this->hiringService->updateJob($request->user(), $jobPosting, $validated);

        return $this->success(['job' => $this->format($job)], 'Job updated.');
    }

    public function publish(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $job = $this->hiringService->publishJob($request->user(), $jobPosting);

        return $this->success(['job' => $this->format($job)], 'Job published.');
    }

    public function close(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $job = $this->hiringService->closeJob($request->user(), $jobPosting);

        return $this->success(['job' => $this->format($job)], 'Job closed.');
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'requisition_id' => ['nullable', 'integer', 'exists:job_requisitions,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'hiring_manager_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description_html' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:30'],
            'experience_min' => ['nullable', 'integer', 'min:0'],
            'salary_min' => ['nullable', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function format(JobPosting $job): array
    {
        $job->loadMissing(['department', 'hiringManager', 'requisition']);

        return [
            'id' => $job->id,
            'title' => $job->title,
            'slug' => $job->slug,
            'description_html' => $job->description_html,
            'location' => $job->location,
            'employment_type' => $job->employment_type,
            'experience_min' => $job->experience_min,
            'salary_min' => $job->salary_min,
            'salary_max' => $job->salary_max,
            'status' => $job->status,
            'published_at' => $job->published_at?->toIso8601String(),
            'department' => $job->department ? ['id' => $job->department->id, 'name' => $job->department->name] : null,
            'hiring_manager' => $job->hiringManager ? ['id' => $job->hiringManager->id, 'full_name' => $job->hiringManager->full_name] : null,
            'requisition_id' => $job->requisition_id,
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
