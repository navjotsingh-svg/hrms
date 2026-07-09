<?php

namespace App\Services;

use App\Mail\EmployeeWelcomeMail;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\EmployeeSalaryRevision;
use App\Models\EmployeeWeeklyOffDay;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EmployeeService
{
    private const SALARY_KEYS = [
        'annual_ctc',
        'salary_effective_from',
        'salary_payout_from',
    ];

    public function __construct(
        private EmployeeAccessService $employeeAccessService,
        private ActivityLogService $activityLogService,
        private CompanyPayrollSettingsService $companyPayrollSettingsService,
        private CompanyAdminEmployeeService $companyAdminEmployeeService,
    ) {}

    public function listForCompany(int $companyId, array $filters = [], ?array $visibleEmployeeIds = null): LengthAwarePaginator
    {
        $query = Employee::query()
            ->where('company_id', $companyId)
            ->with(['department', 'departments', 'role', 'manager', 'shift'])
            ->orderedByName();

        if ($visibleEmployeeIds !== null) {
            if ($visibleEmployeeIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $visibleEmployeeIds);
            }
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['department_id'])) {
            $query->where(function ($builder) use ($filters) {
                $builder
                    ->where('department_id', $filters['department_id'])
                    ->orWhereHas('departments', fn ($relation) => $relation->where('departments.id', $filters['department_id']));
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('id', (int) $filters['employee_id']);
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, (int) ($filters['per_page'] ?? 10));

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function create(int $companyId, array $data): array
    {
        $givePortalAccess = (bool) ($data['give_portal_access'] ?? false);
        unset($data['give_portal_access']);

        $salaryData = $this->extractSalaryData($data, $companyId);
        $departmentIds = $this->extractDepartmentIds($data);
        $weeklyOffData = $this->extractWeeklyOffData($data);
        $leaveTypeIds = $this->extractLeaveTypeIds($data);
        $this->normalizeProbationData($data);
        $this->normalizePaidEmployeeData($data);
        $plainPassword = null;
        $employeeCode = $data['employee_code'];
        $isPaidEmployee = (bool) ($data['is_paid_employee'] ?? true);

        if (! $isPaidEmployee) {
            $salaryData = null;
        }

        $employee = DB::transaction(function () use ($companyId, $data, $givePortalAccess, $salaryData, $departmentIds, $weeklyOffData, $leaveTypeIds, &$plainPassword, $employeeCode) {
            $userId = null;

            if ($givePortalAccess) {
                $plainPassword = Str::password(12, symbols: false);
                $fullName = trim("{$data['first_name']} ".($data['last_name'] ?? ''));

                $user = User::create([
                    'company_id' => $companyId,
                    'role_id' => $data['role_id'],
                    'name' => $fullName,
                    'email' => $data['email'],
                    'password' => $plainPassword,
                    'email_verified_at' => now(),
                ]);

                $userId = $user->id;
            }

            $employee = Employee::create([
                ...$data,
                'company_id' => $companyId,
                'user_id' => $userId,
                'employee_code' => $employeeCode,
                'portal_access_date' => $givePortalAccess ? now()->toDateString() : null,
            ]);

            $this->syncSalary($employee, $salaryData);
            $this->syncDepartments($employee, $departmentIds);
            $this->syncWeeklyOffDays($employee, $weeklyOffData);
            $this->syncLeaveTypes($employee, $leaveTypeIds);

            return $employee;
        });

        $employee->load(['company', 'department', 'departments', 'role', 'manager', 'shift', 'salary', 'weeklyOffDays', 'leaveTypes']);
        app(LeaveBalanceService::class)->ensureBalancesForEmployee($employee, (int) now()->format('Y'));
        $message = 'Employee created successfully.';

        if ($givePortalAccess && $plainPassword) {
            try {
                Mail::to($employee->email)->send(new EmployeeWelcomeMail($employee, $plainPassword));
                $message = 'Employee created and welcome email sent with login credentials.';
            } catch (\Throwable $exception) {
                report($exception);
                $message = 'Employee created but welcome email could not be sent. Please share credentials manually.';
            }
        } elseif (! $givePortalAccess) {
            $message = 'Employee created without portal access. You can grant access later.';
        }

        $this->logEmployeeCreated($employee);

        return [
            'employee' => $employee,
            'message' => $message,
        ];
    }

    private function logEmployeeCreated(Employee $employee): void
    {
        $actor = request()?->user();

        if ($actor) {
            $this->activityLogService->logChange(
                $actor,
                'employees',
                'created',
                $employee,
                (int) $employee->id,
                'Employee profile created.',
                [],
                [
                    'employee_code' => $employee->employee_code,
                    'name' => $employee->full_name,
                    'email' => $employee->email,
                    'status' => $employee->status,
                ],
                request(),
            );
        }
    }

    public function update(Employee $employee, array $data): Employee
    {
        $givePortalAccess = array_key_exists('give_portal_access', $data)
            ? (bool) $data['give_portal_access']
            : null;
        unset($data['give_portal_access']);

        $salaryData = $this->extractSalaryData($data, (int) $employee->company_id);
        $salaryRevisionNotes = $this->extractSalaryRevisionNotes($data);
        $departmentIds = $this->extractDepartmentIds($data);
        $weeklyOffData = $this->extractWeeklyOffData($data);
        $leaveTypeIds = $this->extractLeaveTypeIds($data);
        $this->normalizeProbationData($data);
        $this->normalizePaidEmployeeData($data);
        $plainPassword = null;
        $trackedFields = [
            'first_name', 'last_name', 'email', 'employee_code', 'department_id', 'role_id',
            'status', 'employment_type', 'is_paid_employee', 'designation', 'manager_id', 'shift_id', 'phone', 'date_of_joining', 'probation_status',
        ];
        $before = $employee->only($trackedFields);
        $actor = request()?->user();
        $salaryWasInitialSync = false;
        $isPaidEmployee = (bool) ($data['is_paid_employee'] ?? $employee->isPaidEmployee());

        if (! $isPaidEmployee) {
            $salaryData = null;
        }

        DB::transaction(function () use ($employee, $data, $givePortalAccess, $salaryData, $salaryRevisionNotes, $departmentIds, $weeklyOffData, $leaveTypeIds, $actor, &$plainPassword, &$salaryWasInitialSync) {
            $employee->update($data);

            if ($salaryData !== null) {
                $existingSalary = EmployeeSalary::query()->where('employee_id', $employee->id)->first();

                if ($existingSalary) {
                    $this->reviseSalary(
                        $employee,
                        $salaryData,
                        $actor,
                        $salaryRevisionNotes ?? 'Updated from employee record',
                    );
                } else {
                    $this->syncSalary($employee, $salaryData);
                    $salaryWasInitialSync = true;
                }
            }

            $this->syncDepartments($employee, $departmentIds);
            $this->syncWeeklyOffDays($employee, $weeklyOffData);
            $this->syncLeaveTypes($employee, $leaveTypeIds);

            if ($givePortalAccess === true && ! $employee->user_id) {
                $plainPassword = Str::password(12, symbols: false);
                $fullName = trim("{$employee->first_name} ".($employee->last_name ?? ''));

                $user = User::create([
                    'company_id' => $employee->company_id,
                    'role_id' => $employee->role_id,
                    'name' => $fullName,
                    'email' => $employee->email,
                    'password' => $plainPassword,
                    'email_verified_at' => now(),
                ]);

                $employee->update([
                    'user_id' => $user->id,
                    'portal_access_date' => now()->toDateString(),
                ]);
            }

            if ($employee->user_id && ! $employee->portal_access_date) {
                $employee->loadMissing('user');
                $employee->update([
                    'portal_access_date' => $employee->user?->created_at?->toDateString()
                        ?? $employee->created_at?->toDateString()
                        ?? now()->toDateString(),
                ]);
            }

            if ($employee->user) {
                $fullName = trim("{$employee->first_name} ".($employee->last_name ?? ''));

                $employee->user->update([
                    'name' => $fullName,
                    'email' => $employee->email,
                    'role_id' => $employee->role_id,
                ]);
            }

            if ($employee->status === 'inactive' && $employee->user_id) {
                $this->blockPortalLogin($employee);
            }
        });

        $employee = $employee->fresh()->load(['department', 'departments', 'role', 'manager', 'shift', 'company', 'salary', 'weeklyOffDays', 'leaveTypes']);

        $actor = request()?->user();

        if ($actor) {
            $after = $employee->only($trackedFields);
            $oldValues = [];
            $newValues = [];

            foreach ($trackedFields as $field) {
                $previous = $before[$field] ?? null;
                $current = $after[$field] ?? null;

                if ((string) $previous !== (string) $current) {
                    $oldValues[$field] = $previous;
                    $newValues[$field] = $current;
                }
            }

            if ($oldValues !== []) {
                $this->activityLogService->logChange(
                    $actor,
                    'employees',
                    'updated',
                    $employee,
                    (int) $employee->id,
                    'Employee profile updated.',
                    $oldValues,
                    $newValues,
                    request(),
                );
            }

            if ($salaryWasInitialSync) {
                $this->activityLogService->logChange(
                    $actor,
                    'employees',
                    'salary.updated',
                    $employee,
                    (int) $employee->id,
                    'Employee salary details updated.',
                    [],
                    $this->sanitizeSalaryForLog($salaryData),
                    request(),
                );
            }

            if ($givePortalAccess === true && $employee->user_id) {
                $this->activityLogService->logChange(
                    $actor,
                    'employees',
                    'portal_access.granted',
                    $employee,
                    (int) $employee->id,
                    'Portal access granted to employee.',
                    [],
                    ['portal_access_date' => $employee->portal_access_date],
                    request(),
                );
            }
        }

        if ($plainPassword) {
            try {
                Mail::to($employee->email)->send(new EmployeeWelcomeMail($employee, $plainPassword));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $employee;
    }

    /** @return array{employee: Employee, message: string, granted_portal_access: bool} */
    public function assignCompanyAdmin(Employee $employee): array
    {
        $adminRoleId = Role::idFor(Role::SLUG_COMPANY_ADMIN);

        if (! $adminRoleId) {
            throw new \RuntimeException('Company administrator role is not configured.');
        }

        $employee->loadMissing(['user', 'role', 'company']);

        if ((int) $employee->role_id === (int) $adminRoleId) {
            return [
                'employee' => $employee,
                'message' => 'This employee is already a company administrator.',
                'granted_portal_access' => false,
            ];
        }

        $plainPassword = null;
        $grantedPortalAccess = false;
        $previousRoleId = $employee->role_id;

        DB::transaction(function () use ($employee, $adminRoleId, &$plainPassword, &$grantedPortalAccess) {
            if (! $employee->user_id) {
                $plainPassword = Str::password(12, symbols: false);
                $fullName = trim("{$employee->first_name} ".($employee->last_name ?? ''));

                $user = User::create([
                    'company_id' => $employee->company_id,
                    'role_id' => $adminRoleId,
                    'name' => $fullName,
                    'email' => $employee->email,
                    'password' => $plainPassword,
                    'email_verified_at' => now(),
                ]);

                $employee->update([
                    'user_id' => $user->id,
                    'role_id' => $adminRoleId,
                    'portal_access_date' => now()->toDateString(),
                    'is_paid_employee' => true,
                ]);

                $grantedPortalAccess = true;

                return;
            }

            $employee->update([
                'role_id' => $adminRoleId,
                'is_paid_employee' => true,
            ]);
            $employee->user?->update(['role_id' => $adminRoleId]);
        });

        $employee = $employee->fresh()->load(['department', 'departments', 'role', 'manager', 'shift', 'company', 'user']);

        if ($employee->user) {
            $synced = $this->companyAdminEmployeeService->ensureForAdmin($employee->user);

            if ($synced) {
                $employee = $synced->load(['department', 'departments', 'role', 'manager', 'shift', 'company', 'user']);
            }
        }

        if ($grantedPortalAccess && $plainPassword) {
            try {
                Mail::to($employee->email)->send(new EmployeeWelcomeMail($employee, $plainPassword));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $actor = request()?->user();

        if ($actor) {
            $this->activityLogService->logChange(
                $actor,
                'employees',
                'admin.assigned',
                $employee,
                (int) $employee->id,
                'Employee promoted to company administrator.',
                ['role_id' => $previousRoleId],
                ['role_id' => $adminRoleId],
                request(),
            );
        }

        $message = $grantedPortalAccess
            ? 'Employee is now a company administrator. Portal access was enabled and welcome email sent.'
            : 'Employee is now a company administrator.';

        return [
            'employee' => $employee,
            'message' => $message,
            'granted_portal_access' => $grantedPortalAccess,
        ];
    }

    /** @return array{employee: Employee, message: string} */
    public function removeCompanyAdmin(Employee $employee, User $actor): array
    {
        $adminRoleId = Role::idFor(Role::SLUG_COMPANY_ADMIN);
        $employeeRoleId = Role::idFor(Role::SLUG_EMPLOYEE);

        if (! $adminRoleId || ! $employeeRoleId) {
            throw new \RuntimeException('Required roles are not configured.');
        }

        $employee->loadMissing(['user', 'role']);

        if ((int) $employee->role_id !== (int) $adminRoleId) {
            return [
                'employee' => $employee->load(['department', 'departments', 'role', 'manager', 'shift']),
                'message' => 'This employee is not a company administrator.',
            ];
        }

        $actor->loadMissing('employee');

        if ($actor->employee && (int) $actor->employee->id === (int) $employee->id) {
            throw new AccessDeniedHttpException('You cannot remove your own administrator access.');
        }

        $adminCount = User::query()
            ->where('company_id', $employee->company_id)
            ->where('role_id', $adminRoleId)
            ->count();

        if ($adminCount <= 1) {
            throw new AccessDeniedHttpException('At least one company administrator is required.');
        }

        DB::transaction(function () use ($employee, $employeeRoleId) {
            $employee->update(['role_id' => $employeeRoleId]);
            $employee->user?->update(['role_id' => $employeeRoleId]);
        });

        $employee = $employee->fresh()->load(['department', 'departments', 'role', 'manager', 'shift', 'company']);

        $this->activityLogService->logChange(
            $actor,
            'employees',
            'admin.removed',
            $employee,
            (int) $employee->id,
            'Company administrator access removed from employee.',
            ['role_id' => $adminRoleId],
            ['role_id' => $employeeRoleId],
            request(),
        );

        return [
            'employee' => $employee,
            'message' => 'Company administrator access removed. Employee role restored.',
        ];
    }

    public function resendWelcomeEmail(Employee $employee): string
    {
        if (! $employee->user_id) {
            throw new \InvalidArgumentException('This employee does not have portal access.');
        }

        $employee->loadMissing(['user', 'company']);

        if (! $employee->user) {
            throw new \InvalidArgumentException('This employee does not have portal access.');
        }

        $plainPassword = Str::password(12, symbols: false);

        $employee->user->update([
            'password' => $plainPassword,
        ]);

        $employee->user->tokens()->delete();

        try {
            Mail::to($employee->email)->send(new EmployeeWelcomeMail($employee, $plainPassword));

            return 'Welcome email sent with a new login password.';
        } catch (\Throwable $exception) {
            report($exception);

            throw new \RuntimeException('Welcome email could not be sent. Please try again or share credentials manually.');
        }
    }

    /** @return array{employee: Employee, message: string, plain_password: ?string} */
    public function grantPortalAccess(Employee $employee, ?User $actor = null): array
    {
        if ($employee->status !== 'active') {
            throw ValidationException::withMessages([
                'portal_access' => ['Portal access can only be enabled for active employees.'],
            ]);
        }

        $employee->loadMissing(['user', 'role', 'company']);

        if ($employee->user_id) {
            return [
                'employee' => $employee,
                'message' => 'Employee already has portal access.',
                'plain_password' => null,
            ];
        }

        $plainPassword = Str::password(12, symbols: false);
        $fullName = trim("{$employee->first_name} ".($employee->last_name ?? ''));

        DB::transaction(function () use ($employee, $plainPassword, $fullName, $actor) {
            $user = User::create([
                'company_id' => $employee->company_id,
                'role_id' => $employee->role_id,
                'name' => $fullName,
                'email' => $employee->email,
                'password' => $plainPassword,
                'email_verified_at' => now(),
            ]);

            $employee->update([
                'user_id' => $user->id,
                'portal_access_date' => now()->toDateString(),
            ]);

            if ($actor) {
                $this->activityLogService->logChange(
                    $actor,
                    'employees',
                    'portal_access.granted',
                    $employee,
                    (int) $employee->id,
                    'Portal access granted to employee.',
                    [],
                    ['portal_access_date' => $employee->portal_access_date],
                    request(),
                );
            }
        });

        $employee = $employee->fresh()->load(['department', 'departments', 'role', 'manager', 'shift', 'company']);

        try {
            Mail::to($employee->email)->send(new EmployeeWelcomeMail($employee, $plainPassword));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return [
            'employee' => $employee,
            'message' => 'Portal access enabled. Welcome email sent with login credentials.',
            'plain_password' => $plainPassword,
        ];
    }

    public function revokePortalAccess(Employee $employee, ?User $actor = null, bool $logChange = true): Employee
    {
        $employee->loadMissing('user');

        if (! $employee->user_id && ! $employee->portal_access_date) {
            return $employee->fresh()->load(['department', 'departments', 'role', 'manager', 'shift', 'company']);
        }

        DB::transaction(function () use ($employee, $actor, $logChange) {
            $this->blockPortalLogin($employee, unlinkUser: true);

            if ($logChange && $actor) {
                $this->activityLogService->logChange(
                    $actor,
                    'employees',
                    'portal_access.revoked',
                    $employee,
                    (int) $employee->id,
                    'Portal access disabled for employee.',
                    [],
                    [],
                    request(),
                );
            }
        });

        return $employee->fresh()->load(['department', 'departments', 'role', 'manager', 'shift', 'company']);
    }

    /**
     * Revoke active sessions without deleting the user record.
     * Offboarded/inactive employees keep their user link for audit history.
     */
    private function blockPortalLogin(Employee $employee, bool $unlinkUser = false): void
    {
        $employee->loadMissing('user');
        $user = $employee->user;

        if ($unlinkUser) {
            $employee->update([
                'user_id' => null,
                'portal_access_date' => null,
            ]);
        }

        $user?->tokens()->delete();
    }

    /** @return array{employee: Employee, message: string} */
    public function updatePortalAccess(Employee $employee, bool $enabled, ?User $actor = null): array
    {
        if ($enabled) {
            $result = $this->grantPortalAccess($employee, $actor);

            return [
                'employee' => $result['employee'],
                'message' => $result['message'],
            ];
        }

        $employee = $this->revokePortalAccess($employee, $actor);

        return [
            'employee' => $employee,
            'message' => 'Portal access disabled. The employee can no longer sign in.',
        ];
    }

    public function updateStatus(Employee $employee, string $status, ?User $actor = null): Employee
    {
        DB::transaction(function () use ($employee, $status, $actor) {
            $previousStatus = $employee->status;

            $employee->update(['status' => $status]);

            if ($status === 'inactive' && $employee->user_id) {
                $this->blockPortalLogin($employee);

                if ($actor) {
                    $this->activityLogService->logChange(
                        $actor,
                        'employees',
                        'status.inactive',
                        $employee,
                        (int) $employee->id,
                        'Employee deactivated. Portal login disabled; user account retained for records.',
                        ['status' => $previousStatus],
                        ['status' => 'inactive'],
                        request(),
                    );
                }
            }
        });

        return $employee->fresh()->load(['department', 'departments', 'role', 'manager', 'shift', 'company']);
    }

    public function delete(Employee $employee): void
    {
        DB::transaction(function () use ($employee) {
            $user = $employee->user;
            $employee->delete();

            if ($user) {
                $user->tokens()->delete();
                $user->delete();
            }
        });
    }

    public function belongsToCompany(Employee $employee, int $companyId): bool
    {
        return (int) $employee->company_id === $companyId;
    }

    public function generateEmployeeCode(int $companyId): string
    {
        $latestCode = Employee::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->value('employee_code');

        $nextNumber = 1;

        if ($latestCode && preg_match('/(\d+)$/', $latestCode, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return 'EMP'.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function extractSalaryData(array &$data, int $companyId): ?array
    {
        $salary = [];

        foreach (self::SALARY_KEYS as $key) {
            if (array_key_exists($key, $data)) {
                $salary[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        return $salary !== [] ? $this->normalizeSalaryData($salary, $companyId) : null;
    }

    private function extractSalaryRevisionNotes(array &$data): ?string
    {
        if (! array_key_exists('salary_revision_notes', $data)) {
            return null;
        }

        $notes = trim((string) ($data['salary_revision_notes'] ?? ''));
        unset($data['salary_revision_notes']);

        return $notes !== '' ? $notes : null;
    }

    private function salarySnapshotMatches(EmployeeSalary $existing, array $normalized): bool
    {
        foreach (self::SALARY_KEYS as $key) {
            $existingValue = $existing->{$key};
            $newValue = $normalized[$key] ?? null;

            if (in_array($key, ['pf_applicable', 'esi_applicable', 'professional_tax_applicable'], true)) {
                if ((bool) $existingValue !== (bool) $newValue) {
                    return false;
                }

                continue;
            }

            if ($key === 'salary_effective_from' || $key === 'salary_payout_from') {
                $existingDate = $existingValue?->format('Y-m-d');
                $newDate = $newValue ? \Carbon\Carbon::parse($newValue)->format('Y-m-d') : null;

                if ($existingDate !== $newDate) {
                    return false;
                }

                continue;
            }

            if (round((float) $existingValue, 2) !== round((float) $newValue, 2)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function salarySnapshotForLog(EmployeeSalary $salary): array
    {
        return $this->sanitizeSalaryForLog([
            'annual_ctc' => (float) $salary->annual_ctc,
            'basic_salary' => (float) $salary->basic_salary,
            'hra_percent' => (float) $salary->hra_percent,
            'special_allowance_percent' => (float) $salary->special_allowance_percent,
            'conveyance_allowance' => (float) $salary->conveyance_allowance,
            'medical_allowance' => (float) $salary->medical_allowance,
            'other_allowance' => (float) $salary->other_allowance,
            'pf_applicable' => (bool) $salary->pf_applicable,
            'esi_applicable' => (bool) $salary->esi_applicable,
            'professional_tax_applicable' => (bool) $salary->professional_tax_applicable,
            'salary_effective_from' => $salary->salary_effective_from?->format('Y-m-d'),
            'salary_payout_from' => $salary->salary_payout_from?->format('Y-m-d'),
            'monthly_gross' => $salary->monthly_gross,
        ]);
    }

    private function normalizeSalaryData(array $salary, int $companyId): array
    {
        $settings = $this->companyPayrollSettingsService->getForCompany($companyId);
        $annualCtc = (float) ($salary['annual_ctc'] ?? 0);
        $monthlyCtc = $annualCtc > 0 ? $annualCtc / 12 : 0;
        $basicPercent = (float) ($settings['basic_salary_percent'] ?? 50);
        $hraPercent = (float) ($settings['hra_percent'] ?? 40);
        $specialPercent = (float) ($settings['special_allowance_percent'] ?? 0);

        $salary['basic_salary'] = round($monthlyCtc * $basicPercent / 100, 2);
        $salary['hra_percent'] = $hraPercent;
        $salary['special_allowance_percent'] = $specialPercent;
        $salary['hra'] = round($monthlyCtc * $hraPercent / 100, 2);
        $salary['special_allowance'] = round($monthlyCtc * $specialPercent / 100, 2);
        $salary['conveyance_allowance'] = (float) ($settings['conveyance_allowance'] ?? 0);
        $salary['medical_allowance'] = (float) ($settings['medical_allowance'] ?? 0);
        $salary['other_allowance'] = (float) ($settings['other_allowance'] ?? 0);
        $salary['pf_applicable'] = $settings['pf_applicable'];
        $salary['esi_applicable'] = $settings['esi_applicable'];
        $salary['professional_tax_applicable'] = $settings['professional_tax_applicable'];

        if (empty($salary['salary_payout_from']) && ! empty($salary['salary_effective_from'])) {
            $salary['salary_payout_from'] = $salary['salary_effective_from'];
        }

        return $salary;
    }

    private function extractDepartmentIds(array &$data): ?array
    {
        if (! array_key_exists('department_ids', $data)) {
            return null;
        }

        $departmentIds = array_values(array_unique(array_filter(array_map(
            'intval',
            $data['department_ids'] ?? []
        ))));

        unset($data['department_ids']);
        $data['department_id'] = $departmentIds[0] ?? null;

        return $departmentIds;
    }

    private function syncDepartments(Employee $employee, ?array $departmentIds): void
    {
        if ($departmentIds === null) {
            return;
        }

        $employee->departments()->sync($departmentIds);
    }

    /** @return array{mode: string, weekdays: array<int, int>} */
    private function extractWeeklyOffData(array &$data): array
    {
        $mode = $data['weekly_off_mode'] ?? Employee::WEEKLY_OFF_MODE_COMPANY;
        $weekdays = $data['weekly_off_weekdays'] ?? [];

        unset($data['weekly_off_weekdays']);

        $mode = in_array($mode, [Employee::WEEKLY_OFF_MODE_COMPANY, Employee::WEEKLY_OFF_MODE_CUSTOM], true)
            ? $mode
            : Employee::WEEKLY_OFF_MODE_COMPANY;

        $data['weekly_off_mode'] = $mode;

        return [
            'mode' => $mode,
            'weekdays' => is_array($weekdays)
                ? array_values(array_unique(array_map('intval', $weekdays)))
                : [],
        ];
    }

    /** @param  array{mode: string, weekdays: array<int, int>}  $weeklyOffData */
    private function syncWeeklyOffDays(Employee $employee, array $weeklyOffData): void
    {
        if ($weeklyOffData['mode'] === Employee::WEEKLY_OFF_MODE_COMPANY) {
            $employee->weeklyOffDays()->delete();

            return;
        }

        $weekdays = array_values(array_filter(
            $weeklyOffData['weekdays'],
            fn (int $weekday) => $weekday >= 0 && $weekday <= 6,
        ));

        $employee->weeklyOffDays()->delete();

        foreach ($weekdays as $weekday) {
            EmployeeWeeklyOffDay::create([
                'employee_id' => $employee->id,
                'weekday' => $weekday,
            ]);
        }
    }

    /** @return array<int, int>|null */
    private function extractLeaveTypeIds(array &$data): ?array
    {
        if (! array_key_exists('leave_type_ids', $data)) {
            return null;
        }

        $leaveTypeIds = array_values(array_unique(array_filter(array_map(
            'intval',
            $data['leave_type_ids'] ?? [],
        ))));

        unset($data['leave_type_ids']);

        return $leaveTypeIds;
    }

    /** @param  array<int, int>|null  $leaveTypeIds */
    private function syncLeaveTypes(Employee $employee, ?array $leaveTypeIds): void
    {
        if ($leaveTypeIds === null) {
            return;
        }

        $validIds = LeaveType::query()
            ->where('company_id', $employee->company_id)
            ->where('status', 'active')
            ->whereIn('id', $leaveTypeIds)
            ->pluck('id')
            ->all();

        $syncData = [];

        foreach ($validIds as $leaveTypeId) {
            $syncData[$leaveTypeId] = ['company_id' => $employee->company_id];
        }

        $employee->leaveTypes()->sync($syncData);
    }

    private function normalizePaidEmployeeData(array &$data): void
    {
        if (! array_key_exists('is_paid_employee', $data)) {
            return;
        }

        $data['is_paid_employee'] = filter_var($data['is_paid_employee'], FILTER_VALIDATE_BOOLEAN);
    }

    private function normalizeProbationData(array &$data): void
    {
        $applicable = (bool) ($data['probation_applicable'] ?? false);

        if (! $applicable) {
            $data['probation_applicable'] = false;
            $data['probation_period_months'] = null;
            $data['probation_end_date'] = null;
            $data['probation_status'] = 'not_applicable';

            return;
        }

        $data['probation_applicable'] = true;
        $data['probation_status'] = $data['probation_status'] ?? 'on_probation';
        $data['probation_period_months'] = $data['probation_period_months'] ?? 3;

        if (! empty($data['probation_end_date'])) {
            $endDate = \Carbon\Carbon::parse($data['probation_end_date'])->startOfDay();

            if ($endDate->lt(now()->startOfDay()) && ($data['probation_status'] ?? 'on_probation') === 'on_probation') {
                $data['probation_status'] = 'confirmed';
            }
        }
    }

    public function computeIncrementEffectiveDate(Employee $employee, ?\Carbon\Carbon $currentEffectiveFrom = null): \Carbon\Carbon
    {
        $joiningDate = $employee->joining_date;

        if (! $joiningDate) {
            return now()->startOfDay();
        }

        $joining = \Carbon\Carbon::parse($joiningDate)->startOfDay();
        $reference = ($currentEffectiveFrom ?? $joining)->copy()->startOfDay();
        $candidate = $joining->copy()->addYear();

        while ($candidate->lte($reference)) {
            $candidate->addYear();
        }

        return $candidate;
    }

    public function reviseSalary(
        Employee $employee,
        array $salaryData,
        ?User $revisedBy = null,
        ?string $revisionNotes = null,
        ?string $revisionType = null,
    ): EmployeeSalary {
        $normalized = $this->normalizeSalaryData($salaryData, (int) $employee->company_id);

        return DB::transaction(function () use ($employee, $normalized, $revisedBy, $revisionNotes, $revisionType) {
            $existing = EmployeeSalary::query()->where('employee_id', $employee->id)->first();

            if ($existing && $this->salarySnapshotMatches($existing, $normalized)) {
                return $existing;
            }

            $previousSnapshot = $existing ? $this->salarySnapshotForLog($existing) : [];

            if ($existing) {
                EmployeeSalaryRevision::create([
                    'company_id' => $employee->company_id,
                    'employee_id' => $employee->id,
                    'revised_by_user_id' => $revisedBy?->id,
                    'annual_ctc' => $existing->annual_ctc,
                    'basic_salary' => $existing->basic_salary,
                    'hra_percent' => $existing->hra_percent,
                    'special_allowance_percent' => $existing->special_allowance_percent,
                    'hra' => $existing->hra,
                    'special_allowance' => $existing->special_allowance,
                    'conveyance_allowance' => $existing->conveyance_allowance,
                    'medical_allowance' => $existing->medical_allowance,
                    'other_allowance' => $existing->other_allowance,
                    'pf_applicable' => $existing->pf_applicable,
                    'esi_applicable' => $existing->esi_applicable,
                    'professional_tax_applicable' => $existing->professional_tax_applicable,
                    'salary_effective_from' => $existing->salary_effective_from,
                    'salary_payout_from' => $existing->salary_payout_from,
                    'revision_notes' => $revisionNotes,
                    'revision_type' => $revisionType,
                    'revised_at' => now(),
                ]);
            }

            $updated = $this->persistSalary($employee, $normalized, $existing);

            if ($revisedBy && $existing) {
                $activityAction = $revisionType === 'increment' ? 'salary.incremented' : 'salary.revised';
                $activityMessage = $revisionType === 'increment'
                    ? 'Employee salary incremented.'
                    : 'Employee salary revised.';

                $this->activityLogService->logChange(
                    $revisedBy,
                    'employees',
                    $activityAction,
                    $employee,
                    (int) $employee->id,
                    $activityMessage,
                    $previousSnapshot,
                    $this->sanitizeSalaryForLog($normalized),
                    request(),
                );
            }

            return $updated;
        });
    }

    private function syncSalary(Employee $employee, ?array $salaryData): void
    {
        if ($salaryData === null) {
            return;
        }

        $existing = EmployeeSalary::query()->where('employee_id', $employee->id)->first();
        $this->persistSalary($employee, $this->normalizeSalaryData($salaryData, (int) $employee->company_id), $existing);
    }

    private function persistSalary(Employee $employee, array $salaryData, ?EmployeeSalary $existing): EmployeeSalary
    {
        return EmployeeSalary::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                ...$salaryData,
                'company_id' => $employee->company_id,
                'payment_mode' => $existing?->payment_mode ?? 'bank_transfer',
                'bank_name' => $existing?->bank_name,
                'account_holder_name' => $existing?->account_holder_name,
                'account_number' => $existing?->account_number,
                'ifsc_code' => $existing?->ifsc_code,
            ]
        );
    }

    /** @param  array<string, mixed>  $salaryData */
    private function sanitizeSalaryForLog(array $salaryData): array
    {
        return collect($salaryData)
            ->except(['account_number'])
            ->all();
    }
}
