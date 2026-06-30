<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\UpdateCompanyPayrollSettingsRequest;
use App\Services\CompanyPayrollSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyPayrollSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private CompanyPayrollSettingsService $companyPayrollSettingsService) {}

    public function show(Request $request): JsonResponse
    {
        return $this->success(
            $this->companyPayrollSettingsService->getForCompany((int) $request->user()->company_id)
        );
    }

    public function update(UpdateCompanyPayrollSettingsRequest $request): JsonResponse
    {
        $settings = $this->companyPayrollSettingsService->updateForCompany(
            (int) $request->user()->company_id,
            $request->validated(),
        );

        return $this->success($settings, 'Company payroll settings updated successfully.');
    }
}
