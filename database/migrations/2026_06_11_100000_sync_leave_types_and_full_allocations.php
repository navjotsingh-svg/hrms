<?php

use App\Models\Company;
use App\Services\LeaveBalanceService;
use App\Services\LeaveTypeService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $leaveTypeService = app(LeaveTypeService::class);
        $leaveBalanceService = app(LeaveBalanceService::class);

        Company::query()->pluck('id')->each(function (int $companyId) use ($leaveTypeService, $leaveBalanceService) {
            $leaveTypeService->ensureDefaultsForCompany($companyId);
            $leaveBalanceService->syncFullAllocationsForCompany($companyId);
        });
    }

    public function down(): void
    {
        // Data migration — no rollback.
    }
};
