<?php

namespace App\Http\Controllers;

use App\Services\CompanyPayrollSettingsService;
use Illuminate\Http\Request;

class PayrollSettingsController extends Controller
{
    public function __construct(private CompanyPayrollSettingsService $companyPayrollSettingsService) {}

    public function index(Request $request)
    {
        $settings = $this->companyPayrollSettingsService->getForCompany((int) $request->user()->company_id);

        return view('payroll.settings', compact('settings'));
    }
}
