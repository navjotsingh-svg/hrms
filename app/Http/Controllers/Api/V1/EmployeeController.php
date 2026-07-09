<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\DocumentTypeResource;
use App\Http\Resources\EmployeeProfileResource;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Services\EmployeeAccessService;
use App\Services\EmployeeProfileService;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private EmployeeService $employeeService,
        private EmployeeAccessService $employeeAccessService,
        private EmployeeProfileService $employeeProfileService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $employees = $this->employeeService->listForCompany(
            $user->company_id,
            $request->only(['search', 'department_id', 'status', 'employee_id', 'per_page', 'page']),
            $this->employeeAccessService->visibleEmployeeIds($user),
        );

        return $this->success([
            'employees' => EmployeeResource::collection($employees->items()),
            'pagination' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
                'from' => $employees->firstItem(),
                'to' => $employees->lastItem(),
            ],
            'capabilities' => [
                'can_manage' => $this->employeeAccessService->canManage($user),
                'can_view_all' => $this->employeeAccessService->canViewAll($user),
                'can_view_profile' => $user->canViewEmployeeProfile(),
                'can_review_profile' => $user->canViewEmployeeProfile(),
                'can_assign_admin' => $user->canAssignCompanyAdmin(),
            ],
        ]);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $this->employeeAccessService->assertCanManage($request->user());

        $result = $this->employeeService->create(
            $request->user()->company_id,
            $request->validated()
        );

        return $this->success(
            ['employee' => new EmployeeResource($result['employee'])],
            $result['message'],
            201
        );
    }

    public function show(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureAccessibleEmployee($request, $employee);

        $employee->load(['department', 'departments', 'role', 'manager', 'shift', 'company', 'salary', 'weeklyOffDays', 'leaveTypes']);

        return $this->success([
            'employee' => new EmployeeResource($employee),
            'capabilities' => [
                'can_manage' => $this->employeeAccessService->canManage($request->user()),
                'can_assign_admin' => $request->user()->canAssignCompanyAdmin(),
            ],
        ]);
    }

    public function showProfile(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureAccessibleEmployee($request, $employee);

        $employee = $this->employeeProfileService->loadProfile($employee);
        $user = $request->user();

        return $this->success([
            'employee' => new EmployeeProfileResource($employee),
            'document_types' => DocumentTypeResource::collection(
                $this->employeeProfileService->documentTypesForCompany($employee->company_id)
            ),
            'capabilities' => [
                'can_manage' => $this->employeeAccessService->canManage($user),
                'can_review_profile' => $user->canReviewEmployeeProfile($employee),
                'can_edit_profile_without_approval' => $user->canEditEmployeeProfileWithoutApproval($employee),
                'can_edit_without_approval' => $user->canEditEmployeeProfileWithoutApproval($employee),
                'can_update_contact_info' => $user->canUpdateEmployeeContactInfo(),
                'can_manage_salary' => $user->canEditEmployeeProfileWithoutApproval($employee),
                'can_manage_assets' => $user->canEditEmployeeProfileWithoutApproval($employee),
                'can_assign_admin' => $user->canAssignCompanyAdmin(),
            ],
            'pending_reviews' => $this->employeeProfileService->pendingReviewsForEmployee($user, $employee),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $this->ensureAccessibleEmployee($request, $employee);
        $this->employeeAccessService->assertCanManage($request->user());

        if ($request->filled('manager_id') && $this->employeeAccessService->wouldCreateCycle(
            $employee->id,
            (int) $request->input('manager_id'),
            $request->user()->company_id
        )) {
            return $this->error('Invalid manager assignment. An employee cannot report to themselves or their subordinate.', null, 422);
        }

        $employee = $this->employeeService->update($employee, $request->validated());

        return $this->success(
            ['employee' => new EmployeeResource($employee)],
            'Employee updated successfully.'
        );
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureAccessibleEmployee($request, $employee);
        $this->employeeAccessService->assertCanManage($request->user());

        $this->employeeService->delete($employee);

        return $this->success(null, 'Employee deleted successfully.');
    }

    public function resendWelcomeEmail(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureAccessibleEmployee($request, $employee);
        $this->employeeAccessService->assertCanManage($request->user());

        if (! $employee->user_id) {
            return $this->error('This employee does not have portal access.', null, 422);
        }

        try {
            $message = $this->employeeService->resendWelcomeEmail($employee);
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), null, 422);
        } catch (\RuntimeException $exception) {
            return $this->error($exception->getMessage(), null, 500);
        }

        return $this->success(null, $message);
    }

    public function updateStatus(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureAccessibleEmployee($request, $employee);
        $this->employeeAccessService->assertCanManage($request->user());

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $employee = $this->employeeService->updateStatus(
            $employee,
            $validated['status'],
            $request->user(),
        );

        $message = $validated['status'] === 'inactive'
            ? 'Employee deactivated. Portal access was disabled.'
            : 'Employee status updated successfully.';

        return $this->success(
            ['employee' => new EmployeeResource($employee)],
            $message,
        );
    }

    public function updatePortalAccess(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureAccessibleEmployee($request, $employee);
        $this->employeeAccessService->assertCanManage($request->user());

        $validated = $request->validate([
            'portal_access' => ['required', 'boolean'],
        ]);

        $result = $this->employeeService->updatePortalAccess(
            $employee,
            (bool) $validated['portal_access'],
            $request->user(),
        );

        return $this->success(
            ['employee' => new EmployeeResource($result['employee'])],
            $result['message'],
        );
    }

    public function assignCompanyAdmin(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureAccessibleEmployee($request, $employee);

        if (! $request->user()->canAssignCompanyAdmin()) {
            abort(403, 'You are not allowed to assign company administrator access.');
        }

        $result = $this->employeeService->assignCompanyAdmin($employee);

        return $this->success(
            ['employee' => new EmployeeResource($result['employee'])],
            $result['message'],
        );
    }

    public function removeCompanyAdmin(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureAccessibleEmployee($request, $employee);

        if (! $request->user()->canAssignCompanyAdmin()) {
            abort(403, 'You are not allowed to remove company administrator access.');
        }

        $result = $this->employeeService->removeCompanyAdmin($employee, $request->user());

        return $this->success(
            ['employee' => new EmployeeResource($result['employee'])],
            $result['message'],
        );
    }

    public function checkField(Request $request): JsonResponse
    {
        $this->employeeAccessService->assertCanManage($request->user());

        $validated = $request->validate([
            'field' => ['required', Rule::in(['email', 'phone', 'employee_code'])],
            'value' => ['required', 'string', 'max:255'],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
        ]);

        $companyId = $request->user()->company_id;
        $field = $validated['field'];
        $value = trim($validated['value']);
        $employeeId = $validated['employee_id'] ?? null;

        if ($field === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $this->success([
                'valid' => false,
                'message' => 'Please enter a valid email address.',
            ]);
        }

        if ($field === 'phone' && ! preg_match('/^[0-9]{10}$/', $value)) {
            return $this->success([
                'valid' => false,
                'message' => 'Mobile number must be exactly 10 digits.',
            ]);
        }

        if ($field === 'employee_code' && $value === '') {
            return $this->success([
                'valid' => false,
                'message' => 'Employee code is required.',
            ]);
        }

        $employeeQuery = Employee::query()
            ->where('company_id', $companyId)
            ->where($field, $value);

        if ($employeeId) {
            $employeeQuery->where('id', '!=', $employeeId);
        }

        if ($employeeQuery->exists()) {
            $messages = [
                'email' => 'This email is already used by another employee.',
                'phone' => 'This mobile number is already used by another employee.',
                'employee_code' => 'This employee code is already in use.',
            ];

            return $this->success([
                'valid' => false,
                'message' => $messages[$field],
            ]);
        }

        if ($field === 'email') {
            $userQuery = \App\Models\User::query()->where('email', $value);

            if ($employeeId) {
                $userId = Employee::query()->whereKey($employeeId)->value('user_id');
                $userQuery->when($userId, fn ($query) => $query->where('id', '!=', $userId));
            }

            if ($userQuery->exists()) {
                return $this->success([
                    'valid' => false,
                    'message' => 'This email is already registered for login.',
                ]);
            }
        }

        return $this->success([
            'valid' => true,
            'message' => '',
        ]);
    }

    private function ensureAccessibleEmployee(Request $request, Employee $employee): void
    {
        if (! $this->employeeService->belongsToCompany($employee, $request->user()->company_id)) {
            abort(404);
        }

        $this->employeeAccessService->assertCanView($request->user(), $employee);
    }
}
