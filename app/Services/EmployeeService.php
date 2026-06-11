<?php

namespace App\Services;

use App\Mail\EmployeeWelcomeMail;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\EmployeeSalaryRevision;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmployeeService
{
    private const SALARY_KEYS = [
        'annual_ctc',
        'basic_salary',
        'hra_percent',
        'special_allowance_percent',
        'conveyance_allowance',
        'medical_allowance',
        'other_allowance',
        'pf_applicable',
        'esi_applicable',
        'professional_tax_applicable',
        'salary_effective_from',
    ];

    public function __construct(private EmployeeAccessService $employeeAccessService) {}

    public function listForCompany(int $companyId, array $filters = [], ?array $visibleEmployeeIds = null): LengthAwarePaginator
    {
        $query = Employee::query()
            ->where('company_id', $companyId)
            ->with(['department', 'departments', 'role', 'manager', 'shift'])
            ->latest();

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
                    ->orWhere('email', 'like', "%{$search}%")
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

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function create(int $companyId, array $data): array
    {
        $givePortalAccess = (bool) ($data['give_portal_access'] ?? false);
        unset($data['give_portal_access']);

        $salaryData = $this->extractSalaryData($data);
        $departmentIds = $this->extractDepartmentIds($data);
        $this->normalizeProbationData($data);
        $plainPassword = null;
        $employeeCode = $data['employee_code'];

        $employee = DB::transaction(function () use ($companyId, $data, $givePortalAccess, $salaryData, $departmentIds, &$plainPassword, $employeeCode) {
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

            return $employee;
        });

        $employee->load(['company', 'department', 'departments', 'role', 'manager', 'shift', 'salary']);
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

        return [
            'employee' => $employee,
            'message' => $message,
        ];
    }

    public function update(Employee $employee, array $data): Employee
    {
        $givePortalAccess = array_key_exists('give_portal_access', $data)
            ? (bool) $data['give_portal_access']
            : null;
        unset($data['give_portal_access']);

        $salaryData = $this->extractSalaryData($data);
        $departmentIds = $this->extractDepartmentIds($data);
        $this->normalizeProbationData($data);
        $plainPassword = null;

        DB::transaction(function () use ($employee, $data, $givePortalAccess, $salaryData, $departmentIds, &$plainPassword) {
            $employee->update($data);

            if ($salaryData !== null) {
                $this->syncSalary($employee, $salaryData);
            }

            $this->syncDepartments($employee, $departmentIds);

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
        });

        $employee = $employee->fresh()->load(['department', 'departments', 'role', 'manager', 'shift', 'company', 'salary']);

        if ($plainPassword) {
            try {
                Mail::to($employee->email)->send(new EmployeeWelcomeMail($employee, $plainPassword));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $employee;
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

    private function extractSalaryData(array &$data): ?array
    {
        $salary = [];

        foreach (self::SALARY_KEYS as $key) {
            if (array_key_exists($key, $data)) {
                $salary[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        return $salary !== [] ? $this->normalizeSalaryData($salary) : null;
    }

    private function normalizeSalaryData(array $salary): array
    {
        $annualCtc = (float) ($salary['annual_ctc'] ?? 0);
        $monthlyCtc = $annualCtc > 0 ? $annualCtc / 12 : 0;
        $hraPercent = (float) ($salary['hra_percent'] ?? 0);
        $specialPercent = (float) ($salary['special_allowance_percent'] ?? 0);

        $salary['hra_percent'] = $hraPercent;
        $salary['special_allowance_percent'] = $specialPercent;
        $salary['hra'] = round($monthlyCtc * $hraPercent / 100, 2);
        $salary['special_allowance'] = round($monthlyCtc * $specialPercent / 100, 2);

        foreach (['conveyance_allowance', 'medical_allowance', 'other_allowance'] as $field) {
            $salary[$field] = $salary[$field] ?? 0;
        }

        $salary['pf_applicable'] = (bool) ($salary['pf_applicable'] ?? true);
        $salary['esi_applicable'] = (bool) ($salary['esi_applicable'] ?? false);
        $salary['professional_tax_applicable'] = (bool) ($salary['professional_tax_applicable'] ?? true);

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

    public function reviseSalary(Employee $employee, array $salaryData, ?User $revisedBy = null, ?string $revisionNotes = null): EmployeeSalary
    {
        $normalized = $this->normalizeSalaryData($salaryData);

        return DB::transaction(function () use ($employee, $normalized, $revisedBy, $revisionNotes) {
            $existing = EmployeeSalary::query()->where('employee_id', $employee->id)->first();

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
                    'revision_notes' => $revisionNotes,
                    'revised_at' => now(),
                ]);
            }

            return $this->persistSalary($employee, $normalized, $existing);
        });
    }

    private function syncSalary(Employee $employee, ?array $salaryData): void
    {
        if ($salaryData === null) {
            return;
        }

        $existing = EmployeeSalary::query()->where('employee_id', $employee->id)->first();
        $this->persistSalary($employee, $this->normalizeSalaryData($salaryData), $existing);
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
}
