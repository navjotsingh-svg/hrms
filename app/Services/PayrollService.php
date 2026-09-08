<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeePaymentMethod;
use App\Models\ExitCase;
use App\Models\FullAndFinalSettlement;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PayrollService
{
    public function __construct(
        private AttendanceService $attendanceService,
        private PortalStartService $portalStartService,
        private EmployeeService $employeeService,
        private WorkflowNotificationService $workflowNotificationService,
        private IncomeTaxService $incomeTaxService,
    ) {}

    public function listPeriods(int $companyId): Collection
    {
        return PayrollPeriod::query()
            ->where('company_id', $companyId)
            ->with(['processedBy', 'paidBy', 'employee'])
            ->withCount('payslips')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id')
            ->get();
    }

    /** @return SupportCollection<int, array<string, mixed>> */
    public function listEligibleOffboardEmployees(int $companyId): SupportCollection
    {
        $blockedExitCaseIds = PayrollPeriod::query()
            ->where('company_id', $companyId)
            ->where('type', PayrollPeriod::TYPE_OFFBOARD)
            ->whereNotNull('exit_case_id')
            ->pluck('exit_case_id');

        $blockedEmployeeIds = PayrollPeriod::query()
            ->where('company_id', $companyId)
            ->where('type', PayrollPeriod::TYPE_OFFBOARD)
            ->pluck('employee_id')
            ->filter()
            ->unique()
            ->values();

        $fromExitCases = ExitCase::query()
            ->with(['employee.salary', 'fullAndFinalSettlement'])
            ->where('company_id', $companyId)
            ->whereIn('status', [ExitCase::STATUS_IN_PROGRESS, ExitCase::STATUS_COMPLETED])
            ->where(function ($query) {
                $query->whereNotNull('last_working_date')
                    ->orWhereHas('employee', fn ($employee) => $employee->whereNotNull('last_working_date'));
            })
            ->when($blockedExitCaseIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $blockedExitCaseIds))
            ->whereHas('employee', function ($query) {
                $query->where('is_paid_employee', true)->whereHas('salary');
            })
            ->orderByDesc('last_working_date')
            ->get()
            ->map(function (ExitCase $exitCase) {
                $lastWorkingDate = $exitCase->last_working_date ?? $exitCase->employee?->last_working_date;

                return $this->formatEligibleOffboardEmployee(
                    (int) $exitCase->employee_id,
                    $exitCase->employee?->full_name,
                    $exitCase->employee?->employee_code,
                    $lastWorkingDate,
                    $exitCase->id,
                    $exitCase->fullAndFinalSettlement?->status,
                    $exitCase->fullAndFinalSettlement ? (float) $exitCase->fullAndFinalSettlement->net_payable : null,
                );
            });

        $inactiveWithoutCase = Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'inactive')
            ->where('is_paid_employee', true)
            ->whereHas('salary')
            ->when($blockedEmployeeIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $blockedEmployeeIds))
            ->whereDoesntHave('exitCases', function ($query) {
                $query->whereIn('status', [ExitCase::STATUS_IN_PROGRESS, ExitCase::STATUS_COMPLETED]);
            })
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Employee $employee) => $this->formatEligibleOffboardEmployee(
                (int) $employee->id,
                $employee->full_name,
                $employee->employee_code,
                $employee->last_working_date ?? $employee->updated_at,
                null,
                null,
                null,
            ));

        return $fromExitCases
            ->concat($inactiveWithoutCase)
            ->unique('employee_id')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatEligibleOffboardEmployee(
        int $employeeId,
        ?string $employeeName,
        ?string $employeeCode,
        mixed $lastWorkingDate,
        ?int $exitCaseId,
        ?string $fnfStatus,
        ?float $netPayable,
    ): array {
        $date = $lastWorkingDate
            ? Carbon::parse($lastWorkingDate)
            : null;

        return [
            'exit_case_id' => $exitCaseId,
            'employee_id' => $employeeId,
            'employee_name' => $employeeName,
            'employee_code' => $employeeCode,
            'last_working_date' => $date?->toDateString(),
            'last_working_date_label' => $date?->format('d M Y'),
            'fnf_status' => $fnfStatus,
            'net_payable' => $netPayable,
        ];
    }

    public function generateOffboard(int $companyId, int $employeeId, User $user): PayrollPeriod
    {
        $employee = Employee::query()
            ->where('company_id', $companyId)
            ->where('id', $employeeId)
            ->with(['salary', 'department', 'departments', 'company'])
            ->first();

        if (! $employee) {
            throw new NotFoundHttpException('Employee not found.');
        }

        if (! $employee->is_paid_employee || ! $employee->salary) {
            throw new UnprocessableEntityHttpException('This employee does not have salary details configured for payroll.');
        }

        $exitCase = ExitCase::query()
            ->with('fullAndFinalSettlement')
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereIn('status', [ExitCase::STATUS_IN_PROGRESS, ExitCase::STATUS_COMPLETED])
            ->latest('id')
            ->first();

        $lastWorkingDate = $exitCase?->last_working_date
            ? Carbon::parse($exitCase->last_working_date)
            : $this->resolveEffectiveLastWorkingDate($employee);

        if (! $lastWorkingDate) {
            throw new UnprocessableEntityHttpException('This employee does not have a last working date from offboarding.');
        }

        $existing = PayrollPeriod::query()
            ->where('company_id', $companyId)
            ->where('type', PayrollPeriod::TYPE_OFFBOARD)
            ->where(function ($query) use ($exitCase, $employeeId) {
                $query->where('employee_id', $employeeId);

                if ($exitCase) {
                    $query->orWhere('exit_case_id', $exitCase->id);
                }
            })
            ->first();

        if ($existing) {
            throw new UnprocessableEntityHttpException('Offboard payroll has already been generated for this employee. Select the Offboard period below to view or export the payslip.');
        }

        if (! $employee->last_working_date) {
            $employee->last_working_date = $lastWorkingDate->toDateString();
            $employee->save();
        }

        $year = (int) $lastWorkingDate->year;
        $month = (int) $lastWorkingDate->month;

        $this->assertPeriodWithinPortalStart($companyId, $year, $month);

        try {
            return DB::transaction(function () use ($companyId, $year, $month, $user, $employee, $exitCase) {
                $period = PayrollPeriod::create([
                    'company_id' => $companyId,
                    'year' => $year,
                    'month' => $month,
                    'type' => PayrollPeriod::TYPE_OFFBOARD,
                    'employee_id' => $employee->id,
                    'exit_case_id' => $exitCase?->id,
                    'status' => PayrollPeriod::STATUS_PROCESSED,
                    'processed_by_user_id' => $user->id,
                    'processed_at' => now(),
                ]);

                $payload = $exitCase
                    ? $this->buildOffboardPayslipPayload($employee, $exitCase, $year, $month)
                    : $this->buildPayslipPayload($employee, $year, $month);

                Payslip::create([
                    'payroll_period_id' => $period->id,
                    ...$payload,
                ]);

                if ($exitCase?->fullAndFinalSettlement) {
                    $exitCase->fullAndFinalSettlement->update([
                        'payroll_period_id' => $period->id,
                    ]);
                }

                return $period->load(['processedBy', 'employee'])->loadCount('payslips');
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new UnprocessableEntityHttpException(
                'Offboard payroll has already been generated for this employee. Select the Offboard period below to view or export the payslip.',
                $exception
            );
        }
    }

    public function listPayslipsForPeriod(PayrollPeriod $period, ?int $employeeId = null): Collection
    {
        return Payslip::query()
            ->where('payroll_period_id', $period->id)
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->orderBy('employee_name')
            ->get();
    }

    public function listPayslipsForEmployee(int $companyId, int $employeeId): Collection
    {
        return Payslip::query()
            ->select('payslips.*')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payslips.payroll_period_id')
            ->where('payslips.company_id', $companyId)
            ->where('payslips.employee_id', $employeeId)
            ->with('payrollPeriod')
            ->orderByDesc('payroll_periods.year')
            ->orderByDesc('payroll_periods.month')
            ->get();
    }

    public function generate(int $companyId, int $year, int $month, User $user): PayrollPeriod
    {
        if ($month < 1 || $month > 12) {
            throw new UnprocessableEntityHttpException('Invalid payroll month.');
        }

        $existing = PayrollPeriod::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->where('month', $month)
            ->where('type', 'regular')
            ->first();

        if ($existing) {
            throw new UnprocessableEntityHttpException('Payroll for this period has already been generated. Use regenerate to recalculate.');
        }

        return $this->createPayrollPeriod($companyId, $year, $month, $user);
    }

    public function regenerate(int $companyId, int $year, int $month, User $user): PayrollPeriod
    {
        if ($month < 1 || $month > 12) {
            throw new UnprocessableEntityHttpException('Invalid payroll month.');
        }

        $this->assertPeriodWithinPortalStart($companyId, $year, $month);

        $periods = PayrollPeriod::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->where('month', $month)
            ->where('type', 'regular')
            ->get();

        foreach ($periods as $period) {
            if ($period->isPaid()) {
                throw new UnprocessableEntityHttpException(
                    'Paid payroll cannot be regenerated. Reopen the period before recalculating.'
                );
            }

            $period->delete();
        }

        return $this->createPayrollPeriod($companyId, $year, $month, $user);
    }

    private function createPayrollPeriod(int $companyId, int $year, int $month, User $user): PayrollPeriod
    {
        $this->assertPeriodWithinPortalStart($companyId, $year, $month);

        $employees = $this->employeesEligibleForRegularPayroll($companyId, $year, $month);

        if ($employees->isEmpty()) {
            throw new UnprocessableEntityHttpException('No paid employees were employed during this month.');
        }

        return DB::transaction(function () use ($companyId, $year, $month, $user, $employees) {
            $period = PayrollPeriod::create([
                'company_id' => $companyId,
                'year' => $year,
                'month' => $month,
                'type' => 'regular',
                'status' => PayrollPeriod::STATUS_PROCESSED,
                'processed_by_user_id' => $user->id,
                'processed_at' => now(),
            ]);

            foreach ($employees as $employee) {
                $payload = $this->buildPayslipPayload($employee, $year, $month);

                Payslip::create([
                    'payroll_period_id' => $period->id,
                    ...$payload,
                ]);
            }

            return $period->loadCount('payslips');
        });
    }

    /**
     * Regular payroll includes anyone who actually worked that month:
     * still-active staff, later offboarded staff, and excludes people
     * who had not joined yet or whose last working date is that month.
     *
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    private function employeesEligibleForRegularPayroll(int $companyId, int $year, int $month): SupportCollection
    {
        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth();

        return Employee::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['active', 'inactive'])
            ->where('is_paid_employee', true)
            ->whereHas('salary')
            ->with(['salary', 'department', 'departments', 'company'])
            ->orderedByName()
            ->get()
            ->filter(fn (Employee $employee) => $this->isEligibleForRegularPayroll($employee, $periodStart, $periodEnd))
            ->values();
    }

    private function isEligibleForRegularPayroll(Employee $employee, Carbon $periodStart, Carbon $periodEnd): bool
    {
        if ($employee->joining_date && $employee->joining_date->gt($periodEnd)) {
            return false;
        }

        $payoutFrom = $employee->salary?->salary_payout_from
            ?? $employee->salary?->salary_effective_from;

        if ($payoutFrom && Carbon::parse($payoutFrom)->gt($periodEnd)) {
            return false;
        }

        $lastWorkingDate = $this->resolveEffectiveLastWorkingDate($employee);

        if ($lastWorkingDate && $lastWorkingDate->lt($periodStart)) {
            return false;
        }

        if (
            $lastWorkingDate
            && (int) $lastWorkingDate->year === (int) $periodStart->year
            && (int) $lastWorkingDate->month === (int) $periodStart->month
        ) {
            return false;
        }

        return true;
    }

    private function resolveEffectiveLastWorkingDate(Employee $employee): ?Carbon
    {
        if ($employee->last_working_date) {
            return Carbon::parse($employee->last_working_date)->startOfDay();
        }

        if ($employee->status === 'inactive' && $employee->updated_at) {
            return Carbon::parse($employee->updated_at)->startOfDay();
        }

        return null;
    }

    /**
     * Months that ended before the attendance portal started have no
     * attendance data, so payroll would silently pay full salaries.
     */
    private function assertPeriodWithinPortalStart(int $companyId, int $year, int $month): void
    {
        $portalStart = $this->portalStartService->portalStartDate($companyId);

        if (! $portalStart) {
            return;
        }

        $periodEnd = \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        if ($periodEnd < $portalStart) {
            $label = \Carbon\Carbon::create($year, $month, 1)->format('F Y');
            $startLabel = \Carbon\Carbon::parse($portalStart)->format('d M Y');

            throw new UnprocessableEntityHttpException(
                "Payroll cannot be generated for {$label}. Attendance tracking started on {$startLabel}."
            );
        }
    }

    public function resolvePayslipForUser(User $user, Payslip $payslip): Payslip
    {
        if ((int) $payslip->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Payslip not found.');
        }

        if ($user->canManagePayroll()) {
            return $payslip->load(['payrollPeriod', 'company', 'employee']);
        }

        if (! $user->canViewPayroll()) {
            throw new AccessDeniedHttpException('You are not allowed to view payslips.');
        }

        if (! $user->employee || (int) $user->employee->id !== (int) $payslip->employee_id) {
            throw new AccessDeniedHttpException('You can only view your own payslip.');
        }

        return $payslip->load(['payrollPeriod', 'company', 'employee']);
    }

    public function resolvePeriodForUser(User $user, PayrollPeriod $period): PayrollPeriod
    {
        if ((int) $period->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Payroll period not found.');
        }

        if (! $user->canManagePayroll()) {
            throw new AccessDeniedHttpException('You are not allowed to manage payroll.');
        }

        return $period;
    }

    public function markAsPaid(User $user, PayrollPeriod $period): PayrollPeriod
    {
        $period = $this->resolvePeriodForUser($user, $period);

        if ($period->isPaid()) {
            throw new UnprocessableEntityHttpException('This payroll period is already marked as paid.');
        }

        if ($period->payslips()->count() === 0) {
            throw new UnprocessableEntityHttpException('Cannot mark an empty payroll period as paid.');
        }

        $period->update([
            'status' => PayrollPeriod::STATUS_PAID,
            'paid_by_user_id' => $user->id,
            'paid_at' => now(),
        ]);

        if ($period->type === PayrollPeriod::TYPE_OFFBOARD) {
            $this->finalizeOffboardPayroll($user, $period);
        }

        return $period->fresh()->load(['processedBy', 'paidBy', 'employee'])->loadCount('payslips');
    }

    private function buildOffboardPayslipPayload(Employee $employee, ExitCase $exitCase, int $year, int $month): array
    {
        $payload = $this->buildPayslipPayload($employee, $year, $month);
        $settlement = $exitCase->fullAndFinalSettlement;

        if (! $settlement) {
            return $payload;
        }

        $earnings = $payload['earnings'] ?? [];
        $deductions = $payload['deductions'] ?? [];

        if ((float) $settlement->leave_encashment > 0) {
            $earnings[] = [
                'label' => 'Leave Encashment',
                'amount' => round((float) $settlement->leave_encashment, 2),
            ];
        }

        if ((float) $settlement->pending_dues > 0) {
            $earnings[] = [
                'label' => 'Pending Dues',
                'amount' => round((float) $settlement->pending_dues, 2),
            ];
        }

        if ((float) $settlement->deductions > 0) {
            $deductions[] = [
                'label' => 'Settlement Deductions',
                'amount' => round((float) $settlement->deductions, 2),
            ];
        }

        $totalEarnings = round(array_sum(array_column($earnings, 'amount')), 2);
        $totalDeductions = round(array_sum(array_column($deductions, 'amount')), 2);

        $payload['earnings'] = $earnings;
        $payload['deductions'] = $deductions;
        $payload['total_earnings'] = $totalEarnings;
        $payload['total_deductions'] = $totalDeductions;
        $payload['net_pay'] = round($totalEarnings - $totalDeductions, 2);

        return $payload;
    }

    private function finalizeOffboardPayroll(User $user, PayrollPeriod $period): void
    {
        $period->loadMissing(['exitCase.fullAndFinalSettlement', 'employee']);
        $exitCase = $period->exitCase;

        if (! $exitCase) {
            return;
        }

        DB::transaction(function () use ($user, $exitCase, $period) {
            $settlement = $exitCase->fullAndFinalSettlement;

            if ($settlement && $settlement->status !== FullAndFinalSettlement::STATUS_PAID) {
                $settlement->update([
                    'status' => FullAndFinalSettlement::STATUS_PAID,
                    'payroll_period_id' => $period->id,
                    'processed_by_user_id' => $user->id,
                    'processed_at' => now(),
                ]);
            }

            if ($exitCase->status !== ExitCase::STATUS_COMPLETED) {
                $exitCase->update([
                    'stage' => ExitCase::STAGE_COMPLETED,
                    'status' => ExitCase::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
            }
        });

        if ($period->employee && $period->employee->status === 'active') {
            $this->employeeService->updateStatus($period->employee, 'inactive', $user);
        }

        $exitCase->refresh()->load(['employee', 'resignationRequest']);

        if ($exitCase->status === ExitCase::STATUS_COMPLETED) {
            $this->workflowNotificationService->notifyOffboardingCompleted($exitCase, $user);
        }
    }

    private function buildPayslipPayload(Employee $employee, int $year, int $month): array
    {
        $salary = $employee->salary;
        $attendance = $this->attendanceService->payrollAttendanceMetrics($employee, $year, $month);
        $payableDays = (float) $attendance['payable_days'];
        $lopDays = (float) $attendance['lop_days'];
        $paidDays = (float) $attendance['paid_days'];
        $monthDays = (float) ($attendance['month_days'] ?? 0);

        // Daily rate is always monthly gross / calendar days of the month, so a
        // mid-month run (or mid-month joiner/leaver) pays exactly the days
        // worked instead of inflating the per-day value.
        $factor = $monthDays > 0 ? $paidDays / $monthDays : 0;

        $earnings = $this->buildEarnings($salary, $factor);
        $deductions = $this->buildDeductions($employee, $salary, $earnings, $factor);
        $totalEarnings = round(array_sum(array_column($earnings, 'amount')), 2);
        $totalDeductions = round(array_sum(array_column($deductions, 'amount')), 2);

        $bank = $this->resolveBankDetails($employee);

        $company = $employee->relationLoaded('company')
            ? $employee->company
            : $employee->load('company')->company;
        $incomeTaxApplicable = (bool) $company?->income_tax_applicable;
        $taxRegime = $incomeTaxApplicable
            ? ($employee->tax_regime ?? Employee::TAX_REGIME_NEW)
            : null;

        $departmentName = $employee->departments->pluck('name')->filter()->implode(', ');
        if ($departmentName === '') {
            $departmentName = $employee->department?->name;
        }

        return [
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => $employee->full_name,
            'designation' => $employee->designation,
            'department_name' => $departmentName,
            'location' => $employee->city ?: $employee->company?->city,
            'joining_date' => $employee->joining_date,
            'payable_days' => $payableDays,
            'lop_days' => $lopDays,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_pay' => round($totalEarnings - $totalDeductions, 2),
            'expense_reimbursements' => 0,
            'bank_name' => $bank['bank_name'],
            'bank_account_number' => $bank['bank_account_number'],
            'pan_number' => $employee->pan_number,
            'uan' => $employee->uan,
            'pf_number' => $employee->pf_number,
            'income_tax_applicable' => $incomeTaxApplicable,
            'tax_regime' => $taxRegime,
        ];
    }

    /**
     * Earnings are derived from attendance-paid days first, then split by salary shares.
     *
     * 1. Take the full-month components exactly as stored on the salary master.
     * 2. Prorate total monthly gross by paid_days / payable_days.
     * 3. Allocate that prorated earning base to each component by its share of monthly gross.
     */
    private function buildEarnings($salary, float $factor): array
    {
        $components = collect($this->resolveMonthlySalaryComponents($salary))
            ->filter(fn (array $item) => $item['amount'] > 0)
            ->values();

        $monthlyGross = round((float) $components->sum('amount'), 2);

        if ($monthlyGross <= 0 || $factor <= 0) {
            return [];
        }

        $proratedEarningsBase = round($monthlyGross * $factor, 2);
        $earnings = [];
        $allocated = 0.0;

        foreach ($components as $component) {
            $share = $component['amount'] / $monthlyGross;
            $amount = round($proratedEarningsBase * $share, 2);

            $earnings[] = [
                'label' => $component['label'],
                'amount' => $amount,
            ];
            $allocated += $amount;
        }

        $roundingDiff = round($proratedEarningsBase - $allocated, 2);

        if ($roundingDiff !== 0.0 && $earnings !== []) {
            $lastIndex = count($earnings) - 1;
            $earnings[$lastIndex]['amount'] = round($earnings[$lastIndex]['amount'] + $roundingDiff, 2);
        }

        return $earnings;
    }

    /**
     * Uses the monthly amounts stored on the salary master (the same numbers
     * shown on the salary screen) instead of re-deriving HRA/Special from
     * percentages, so the payslip always matches the configured salary.
     */
    private function resolveMonthlySalaryComponents($salary): array
    {
        return [
            ['label' => 'Basic', 'amount' => (float) $salary->basic_salary],
            ['label' => 'HRA', 'amount' => (float) $salary->hra],
            ['label' => 'Special Allowance', 'amount' => (float) $salary->special_allowance],
            ['label' => 'Conveyance', 'amount' => (float) $salary->conveyance_allowance],
            ['label' => 'Medical', 'amount' => (float) $salary->medical_allowance],
            ['label' => 'Other Allowance', 'amount' => (float) $salary->other_allowance],
        ];
    }

    private function buildDeductions(Employee $employee, $salary, array $earnings, float $attendanceFactor = 1.0): array
    {
        $deductions = [];
        $basic = collect($earnings)->firstWhere('label', 'Basic')['amount'] ?? 0;

        if ($salary->pf_applicable && $basic > 0) {
            $deductions[] = [
                'label' => 'Provident Fund',
                'amount' => round(min($basic * 0.12, 1800), 2),
            ];
        }

        if ($salary->professional_tax_applicable) {
            $deductions[] = [
                'label' => 'Professional Tax',
                'amount' => 200,
            ];
        }

        $company = $employee->relationLoaded('company')
            ? $employee->company
            : $employee->load('company')->company;

        if ($company?->income_tax_applicable) {
            $monthlyGross = round(array_sum(array_column($earnings, 'amount')), 2);
            $monthlyPf = collect($deductions)->firstWhere('label', 'Provident Fund')['amount'] ?? 0.0;
            $regime = $employee->tax_regime ?? Employee::TAX_REGIME_NEW;
            $tds = $this->incomeTaxService->monthlyTds(
                $monthlyGross,
                (float) $salary->annual_ctc,
                (float) $monthlyPf,
                $regime,
                $attendanceFactor,
            );

            if ($tds > 0) {
                $regimeLabel = $this->incomeTaxService->regimeLabel($regime);
                $deductions[] = [
                    'label' => "Income Tax (TDS - {$regimeLabel} Regime)",
                    'amount' => $tds,
                ];
            }
        }

        return $deductions;
    }

    private function resolveBankDetails(Employee $employee): array
    {
        $paymentMethod = EmployeePaymentMethod::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('payment_mode', 'bank_transfer')
            ->latest('reviewed_at')
            ->first();

        if ($paymentMethod) {
            return [
                'bank_name' => $paymentMethod->bank_name,
                'bank_account_number' => $paymentMethod->account_number,
            ];
        }

        $salary = $employee->salary;

        return [
            'bank_name' => $salary?->bank_name,
            'bank_account_number' => $salary?->account_number,
        ];
    }
}
