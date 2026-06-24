<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    use ApiResponse;

    public function __construct(private ProjectService $projectService) {}

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->canManageProjects()) {
            abort(403);
        }

        $projects = $this->projectService->listForCompany(
            $request->user()->company_id,
            $request->only(['search', 'status', 'per_page'])
        );

        return $this->success([
            'projects' => ProjectResource::collection($projects->items()),
            'pagination' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
                'from' => $projects->firstItem(),
                'to' => $projects->lastItem(),
            ],
        ]);
    }

    public function assigned(Request $request): JsonResponse
    {
        if (! $request->user()->canViewProjects()) {
            abort(403);
        }

        $employee = $request->user()->employee;

        if (! $employee) {
            return $this->success(['projects' => []]);
        }

        $projects = $this->projectService->assignedToEmployee(
            $request->user()->company_id,
            (int) $employee->id,
        );

        return $this->success([
            'projects' => ProjectResource::collection($projects),
        ]);
    }

    public function employeeOptions(Request $request): JsonResponse
    {
        if (! $request->user()->canManageProjects()) {
            abort(403);
        }

        $context = $this->projectService->assigneeContext($request->user());
        $mapEmployee = fn ($employee) => [
            'id' => $employee->id,
            'full_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
        ];

        return $this->success([
            'employees' => $context['employees']->map($mapEmployee)->values(),
            'auto_assign' => $context['auto_assign']->map($mapEmployee)->values(),
            'assigner_role' => $context['assigner_role'],
        ]);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->projectService->create($request->user(), $request->validated());

        return $this->success(
            ['project' => new ProjectResource($project)],
            'Project created successfully.',
            201
        );
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->ensureAccessibleProject($request, $project);

        $project->load(['employees', 'createdBy.employee']);

        return $this->success([
            'project' => new ProjectResource($project),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->ensureCompanyProject($request, $project);

        $project = $this->projectService->update($request->user(), $project, $request->validated());

        return $this->success(
            ['project' => new ProjectResource($project)],
            'Project updated successfully.'
        );
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->ensureCompanyProject($request, $project);

        $this->projectService->delete($project);

        return $this->success(null, 'Project deleted successfully.');
    }

    private function ensureCompanyProject(Request $request, Project $project): void
    {
        if (! $this->projectService->belongsToCompany($project, $request->user()->company_id)) {
            abort(404);
        }
    }

    private function ensureAccessibleProject(Request $request, Project $project): void
    {
        $this->ensureCompanyProject($request, $project);

        $user = $request->user();

        if ($user->canManageProjects()) {
            return;
        }

        $employeeId = $user->employee?->id;

        if (! $employeeId || ! $project->employees()->where('employees.id', $employeeId)->exists()) {
            abort(403);
        }
    }
}
