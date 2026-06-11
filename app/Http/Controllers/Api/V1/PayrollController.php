<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\PayrollPeriodExport;
use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\GeneratePayrollRequest;
use App\Http\Resources\PayrollPeriodResource;
use App\Http\Resources\PayslipResource;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Services\PayrollService;
use App\Services\PayslipPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PayrollController extends Controller
{
    use ApiResponse;

    public function __construct(
        private PayrollService $payrollService,
        private PayslipPdfService $payslipPdfService,
    ) {}

    
    public function periods(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->canManagePayroll()) {
            $periods = $this->payrollService->listPeriods($user->company_id);
        } elseif ($user->canViewPayroll() && $user->employee) {
            $periodIds = Payslip::query()
                ->where('employee_id', $user->employee->id)
                ->pluck('payroll_period_id')
                ->unique();

            $periods = PayrollPeriod::query()
                ->whereIn('id', $periodIds)
                ->withCount('payslips')
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->get();
        } else {
            abort(403);
        }

        return $this->success([
            'periods' => PayrollPeriodResource::collection($periods),
            'capabilities' => [
                'can_manage_payroll' => $user->canManagePayroll(),
                'can_view_payroll' => $user->canViewPayroll(),
            ],
        ]);
    }

    public function generate(GeneratePayrollRequest $request): JsonResponse
    {
        if (! $request->user()->canManagePayroll()) {
            abort(403);
        }

        $validated = $request->validated();
        $period = $this->payrollService->generate(
            $request->user()->company_id,
            (int) $validated['year'],
            (int) $validated['month'],
            $request->user()
        );

        return $this->success(
            ['period' => new PayrollPeriodResource($period)],
            'Payroll generated successfully.',
            201
        );
    }

    public function regenerate(GeneratePayrollRequest $request): JsonResponse
    {
        if (! $request->user()->canManagePayroll()) {
            abort(403);
        }

        $validated = $request->validated();
        $period = $this->payrollService->regenerate(
            $request->user()->company_id,
            (int) $validated['year'],
            (int) $validated['month'],
            $request->user()
        );

        return $this->success(
            ['period' => new PayrollPeriodResource($period)],
            'Payroll regenerated successfully with updated attendance and leave data.'
        );
    }

    public function destroyPeriod(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        $this->payrollService->resolvePeriodForUser($request->user(), $payrollPeriod);
        $this->payrollService->deletePeriod($payrollPeriod);

        return $this->success(null, 'Payroll period deleted successfully.');
    }

    public function payslips(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        $this->payrollService->resolvePeriodForUser($request->user(), $payrollPeriod);

        $payslips = $this->payrollService->listPayslipsForPeriod($payrollPeriod);

        return $this->success([
            'payslips' => PayslipResource::collection($payslips),
        ]);
    }

    public function export(Request $request, PayrollPeriod $payrollPeriod)
    {
        $this->payrollService->resolvePeriodForUser($request->user(), $payrollPeriod);

        $payslips = $this->payrollService->listPayslipsForPeriod($payrollPeriod);

        if ($payslips->isEmpty()) {
            throw new NotFoundHttpException('No payslips found for this period.');
        }

        $filename = 'payroll-'.str_replace(' ', '-', strtolower($payrollPeriod->label())).'.xlsx';

        return Excel::download(new PayrollPeriodExport($payrollPeriod, $payslips), $filename);
    }

    public function myPayslips(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->canViewPayroll() || ! $user->employee) {
            abort(403);
        }

        $payslips = $this->payrollService->listPayslipsForEmployee(
            $user->company_id,
            $user->employee->id
        );

        return $this->success([
            'payslips' => PayslipResource::collection($payslips),
        ]);
    }

    public function show(Request $request, Payslip $payslip): JsonResponse
    {
        $payslip = $this->payrollService->resolvePayslipForUser($request->user(), $payslip);

        return $this->success([
            'payslip' => new PayslipResource($payslip),
        ]);
    }

    public function view(Request $request, Payslip $payslip)
    {
        $payslip = $this->payrollService->resolvePayslipForUser($request->user(), $payslip);

        return $this->payslipPdfService->inline($payslip);
    }

    public function download(Request $request, Payslip $payslip)
    {
        $payslip = $this->payrollService->resolvePayslipForUser($request->user(), $payslip);

        return $this->payslipPdfService->download($payslip);
    }
}
