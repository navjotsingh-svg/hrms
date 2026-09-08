<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    use ApiResponse;

    public function __construct(private DepartmentService $departmentService) {}

    public function index(Request $request): JsonResponse
    {
        $departments = $this->departmentService->listForCompany(
            $request->user()->company_id,
            $request->only(['search', 'status', 'per_page'])
        );

        return $this->success([
            'departments' => DepartmentResource::collection($departments->items()),
            'pagination' => [
                'current_page' => $departments->currentPage(),
                'last_page' => $departments->lastPage(),
                'per_page' => $departments->perPage(),
                'total' => $departments->total(),
                'from' => $departments->firstItem(),
                'to' => $departments->lastItem(),
            ],
        ]);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = $this->departmentService->create(
            $request->user()->company_id,
            $request->validated()
        );

        return $this->success(
            ['department' => new DepartmentResource($department)],
            'Department created successfully.',
            201
        );
    }

    public function show(Request $request, Department $department): JsonResponse
    {
        $this->ensureCompanyDepartment($request, $department);

        return $this->success([
            'department' => new DepartmentResource($department),
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $this->ensureCompanyDepartment($request, $department);

        $department = $this->departmentService->update($department, $request->validated());

        return $this->success(
            ['department' => new DepartmentResource($department)],
            'Department updated successfully.'
        );
    }

    public function destroy(Request $request, Department $department): JsonResponse
    {
        $this->ensureCompanyDepartment($request, $department);

        $this->departmentService->delete($department);

        return $this->success(null, 'Department deleted successfully.');
    }

    public function updateStatus(Request $request, Department $department): JsonResponse
    {
        $this->ensureCompanyDepartment($request, $department);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $department = $this->departmentService->update($department, $validated);

        return $this->success(
            ['department' => new DepartmentResource($department)],
            'Department status updated successfully.'
        );
    }

    private function ensureCompanyDepartment(Request $request, Department $department): void
    {
        if (! $this->departmentService->belongsToCompany($department, $request->user()->company_id)) {
            abort(404);
        }
    }
}
