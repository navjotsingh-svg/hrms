<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeePaymentMethod;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PayrollService
{
    public function __construct(
        private AttendanceService $attendanceService,
        private PortalStartService $portalStartService,
        private ExpenseService $expenseService,
    ) {}

    public function listPeriods(int $companyId): Collection
    {
        return PayrollPeriod::query()
            ->where('company_id', $companyId)
            ->withCount('payslips')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
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
            $this->expenseService->releasePayrollPeriod($period);
            $period->delete();
        }

        return $this->createPayrollPeriod($companyId, $year, $month, $user);
    }

    private function createPayrollPeriod(int $companyId, int $year, int $month, User $user): PayrollPeriod
    {
        $this->assertPeriodWithinPortalStart($companyId, $year, $month);

        $employees = Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereHas('salary')
            ->with(['salary', 'department', 'departments', 'company'])
            ->orderBy('first_name')
            ->get();

        if ($employees->isEmpty()) {
            throw new UnprocessableEntityHttpException('No active employees with salary details found.');
        }

        return DB::transaction(function () use ($companyId, $year, $month, $user, $employees) {
            $period = PayrollPeriod::create([
                'company_id' => $companyId,
                'year' => $year,
                'month' => $month,
                'type' => 'regular',
                'status' => 'processed',
                'processed_by_user_id' => $user->id,
                'processed_at' => now(),
            ]);

            foreach ($employees as $employee) {
                $payload = $this->buildPayslipPayload($employee, $year, $month);

                Payslip::create([
                    'payroll_period_id' => $period->id,
                    ...$payload,
                ]);

                $this->expenseService->markPaidForPayroll($period, $employee);
            }

            return $period->loadCount('payslips');
        });
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
        $expenseReimbursements = $this->expenseService->pendingReimbursementTotal($employee, $year, $month);
        $deductions = $this->buildDeductions($salary, $earnings);
        $totalEarnings = round(array_sum(array_column($earnings, 'amount')), 2);
        $totalDeductions = round(array_sum(array_column($deductions, 'amount')), 2);
        $displayEarnings = $earnings;

        if ($expenseReimbursements > 0) {
            $displayEarnings[] = [
                'label' => 'Expense Reimbursement',
                'amount' => round($expenseReimbursements, 2),
            ];
        }

        $bank = $this->resolveBankDetails($employee);

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
            'earnings' => $displayEarnings,
            'deductions' => $deductions,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_pay' => round($totalEarnings - $totalDeductions, 2),
            'expense_reimbursements' => $expenseReimbursements,
            'bank_name' => $bank['bank_name'],
            'bank_account_number' => $bank['bank_account_number'],
            'pan_number' => $employee->pan_number,
            'uan' => $employee->uan,
            'pf_number' => $employee->pf_number,
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

    private function buildDeductions($salary, array $earnings): array
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
