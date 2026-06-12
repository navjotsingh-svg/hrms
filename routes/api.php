<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::get('profile/employee', [ProfileController::class, 'showEmployee'])->name('profile.employee.show');
        Route::post('profile/family-members', [ProfileController::class, 'storeFamilyMembers'])->name('profile.family-members.store');
        Route::post('profile/personal-sections', [ProfileController::class, 'storePersonalSection'])->name('profile.personal-sections.store');
        Route::post('profile/compliance-fields', [ProfileController::class, 'storeComplianceField'])->name('profile.compliance-fields.store');
        Route::post('profile/payment-methods', [ProfileController::class, 'storePaymentMethod'])->name('profile.payment-methods.store');
        Route::post('profile/documents', [ProfileController::class, 'storeDocument'])->name('profile.documents.store');
        Route::get('profile/documents/{employeeDocument}/download', [ProfileController::class, 'downloadDocument'])
            ->name('profile.documents.download')
            ->whereNumber('employeeDocument');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

        Route::middleware('super_admin')->group(function () {
            Route::get('companies/suggestions', [CompanyController::class, 'suggestions'])
                ->name('companies.suggestions');
            Route::post('companies/check-field', [CompanyController::class, 'checkField'])
                ->name('companies.check-field');
            Route::patch('companies/{company}/status', [CompanyController::class, 'updateStatus'])
                ->name('companies.status')
                ->whereNumber('company');
            Route::apiResource('companies', CompanyController::class)
                ->names('companies')
                ->whereNumber('company');
        });

        Route::middleware('company.member')->group(function () {
            Route::get('employee-documents/pending', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'pending'])
                ->name('employee-documents.pending');
            Route::patch('employee-documents/{employeeDocument}/approve', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'approve'])
                ->name('employee-documents.approve')
                ->whereNumber('employeeDocument');
            Route::patch('employee-documents/{employeeDocument}/reject', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'reject'])
                ->name('employee-documents.reject')
                ->whereNumber('employeeDocument');
            Route::get('employee-documents/{employeeDocument}/download', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'download'])
                ->name('employee-documents.download')
                ->whereNumber('employeeDocument');
            Route::delete('employee-documents/{employeeDocument}', [\App\Http\Controllers\Api\V1\EmployeeDocumentController::class, 'destroy'])
                ->name('employee-documents.destroy')
                ->whereNumber('employeeDocument');

            Route::get('employee-payment-methods/pending', [\App\Http\Controllers\Api\V1\EmployeePaymentMethodController::class, 'pending'])
                ->name('employee-payment-methods.pending');
            Route::patch('employee-payment-methods/{employeePaymentMethod}/approve', [\App\Http\Controllers\Api\V1\EmployeePaymentMethodController::class, 'approve'])
                ->name('employee-payment-methods.approve')
                ->whereNumber('employeePaymentMethod');
            Route::patch('employee-payment-methods/{employeePaymentMethod}/reject', [\App\Http\Controllers\Api\V1\EmployeePaymentMethodController::class, 'reject'])
                ->name('employee-payment-methods.reject')
                ->whereNumber('employeePaymentMethod');

            Route::get('employee-family-members/pending', [\App\Http\Controllers\Api\V1\EmployeeFamilyMemberController::class, 'pending'])
                ->name('employee-family-members.pending');
            Route::patch('employee-family-members/{employeeFamilyMember}/approve', [\App\Http\Controllers\Api\V1\EmployeeFamilyMemberController::class, 'approve'])
                ->name('employee-family-members.approve')
                ->whereNumber('employeeFamilyMember');
            Route::patch('employee-family-members/{employeeFamilyMember}/reject', [\App\Http\Controllers\Api\V1\EmployeeFamilyMemberController::class, 'reject'])
                ->name('employee-family-members.reject')
                ->whereNumber('employeeFamilyMember');

            Route::get('employee-personal-sections/pending', [\App\Http\Controllers\Api\V1\EmployeePersonalSectionController::class, 'pending'])
                ->name('employee-personal-sections.pending');
            Route::patch('employee-personal-sections/{employeePersonalSection}/approve', [\App\Http\Controllers\Api\V1\EmployeePersonalSectionController::class, 'approve'])
                ->name('employee-personal-sections.approve')
                ->whereNumber('employeePersonalSection');
            Route::patch('employee-personal-sections/{employeePersonalSection}/reject', [\App\Http\Controllers\Api\V1\EmployeePersonalSectionController::class, 'reject'])
                ->name('employee-personal-sections.reject')
                ->whereNumber('employeePersonalSection');

            Route::get('employee-compliance-fields/pending', [\App\Http\Controllers\Api\V1\EmployeeComplianceController::class, 'pending'])
                ->name('employee-compliance-fields.pending');
            Route::patch('employee-compliance-fields/{employeeComplianceField}/approve', [\App\Http\Controllers\Api\V1\EmployeeComplianceController::class, 'approve'])
                ->name('employee-compliance-fields.approve')
                ->whereNumber('employeeComplianceField');
            Route::patch('employee-compliance-fields/{employeeComplianceField}/reject', [\App\Http\Controllers\Api\V1\EmployeeComplianceController::class, 'reject'])
                ->name('employee-compliance-fields.reject')
                ->whereNumber('employeeComplianceField');

            Route::middleware('company.permission:departments.view')->group(function () {
                Route::get('departments', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'index'])
                    ->name('departments.index');
                Route::get('departments/{department}', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'show'])
                    ->name('departments.show')
                    ->whereNumber('department');
            });

            Route::middleware('company.permission:departments.manage')->group(function () {
                Route::post('departments', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'store'])
                    ->name('departments.store');
                Route::put('departments/{department}', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'update'])
                    ->name('departments.update')
                    ->whereNumber('department');
                Route::patch('departments/{department}', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'update'])
                    ->name('departments.patch')
                    ->whereNumber('department');
                Route::delete('departments/{department}', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'destroy'])
                    ->name('departments.destroy')
                    ->whereNumber('department');
                Route::patch('departments/{department}/status', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'updateStatus'])
                    ->name('departments.status')
                    ->whereNumber('department');
            });

            Route::middleware('company.permission:shifts.view')->group(function () {
                Route::get('shifts', [\App\Http\Controllers\Api\V1\ShiftController::class, 'index'])
                    ->name('shifts.index');
                Route::get('shifts/{shift}', [\App\Http\Controllers\Api\V1\ShiftController::class, 'show'])
                    ->name('shifts.show')
                    ->whereNumber('shift');
            });

            Route::middleware('company.permission:shifts.manage')->group(function () {
                Route::post('shifts', [\App\Http\Controllers\Api\V1\ShiftController::class, 'store'])
                    ->name('shifts.store');
                Route::put('shifts/{shift}', [\App\Http\Controllers\Api\V1\ShiftController::class, 'update'])
                    ->name('shifts.update')
                    ->whereNumber('shift');
                Route::patch('shifts/{shift}', [\App\Http\Controllers\Api\V1\ShiftController::class, 'update'])
                    ->name('shifts.patch')
                    ->whereNumber('shift');
                Route::delete('shifts/{shift}', [\App\Http\Controllers\Api\V1\ShiftController::class, 'destroy'])
                    ->name('shifts.destroy')
                    ->whereNumber('shift');
                Route::patch('shifts/{shift}/status', [\App\Http\Controllers\Api\V1\ShiftController::class, 'updateStatus'])
                    ->name('shifts.status')
                    ->whereNumber('shift');
            });

            Route::middleware('company.permission:documents.view')->group(function () {
                Route::get('document-types', [\App\Http\Controllers\Api\V1\DocumentTypeController::class, 'index'])
                    ->name('document-types.index');
                Route::get('document-types/{document_type}', [\App\Http\Controllers\Api\V1\DocumentTypeController::class, 'show'])
                    ->name('document-types.show')
                    ->whereNumber('document_type');
            });

            Route::middleware('company.permission:assets.view')->group(function () {
                Route::get('asset-types', [\App\Http\Controllers\Api\V1\AssetTypeController::class, 'index'])
                    ->name('asset-types.index');
                Route::get('asset-types/{asset_type}', [\App\Http\Controllers\Api\V1\AssetTypeController::class, 'show'])
                    ->name('asset-types.show')
                    ->whereNumber('asset_type');
            });

            Route::middleware('company.permission:assets.manage')->group(function () {
                Route::post('asset-types', [\App\Http\Controllers\Api\V1\AssetTypeController::class, 'store'])
                    ->name('asset-types.store');
                Route::put('asset-types/{asset_type}', [\App\Http\Controllers\Api\V1\AssetTypeController::class, 'update'])
                    ->name('asset-types.update')
                    ->whereNumber('asset_type');
                Route::patch('asset-types/{asset_type}', [\App\Http\Controllers\Api\V1\AssetTypeController::class, 'update'])
                    ->name('asset-types.patch')
                    ->whereNumber('asset_type');
                Route::delete('asset-types/{asset_type}', [\App\Http\Controllers\Api\V1\AssetTypeController::class, 'destroy'])
                    ->name('asset-types.destroy')
                    ->whereNumber('asset_type');
                Route::patch('asset-types/{asset_type}/status', [\App\Http\Controllers\Api\V1\AssetTypeController::class, 'updateStatus'])
                    ->name('asset-types.status')
                    ->whereNumber('asset_type');
            });

            Route::middleware('company.permission:documents.manage')->group(function () {
                Route::post('document-types', [\App\Http\Controllers\Api\V1\DocumentTypeController::class, 'store'])
                    ->name('document-types.store');
                Route::put('document-types/{document_type}', [\App\Http\Controllers\Api\V1\DocumentTypeController::class, 'update'])
                    ->name('document-types.update')
                    ->whereNumber('document_type');
                Route::patch('document-types/{document_type}', [\App\Http\Controllers\Api\V1\DocumentTypeController::class, 'update'])
                    ->name('document-types.patch')
                    ->whereNumber('document_type');
                Route::delete('document-types/{document_type}', [\App\Http\Controllers\Api\V1\DocumentTypeController::class, 'destroy'])
                    ->name('document-types.destroy')
                    ->whereNumber('document_type');
                Route::patch('document-types/{document_type}/status', [\App\Http\Controllers\Api\V1\DocumentTypeController::class, 'updateStatus'])
                    ->name('document-types.status')
                    ->whereNumber('document_type');
            });

            Route::middleware('company.permission:employees.manage')->group(function () {
                Route::post('employees/check-field', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'checkField'])
                    ->name('employees.check-field');
                Route::get('roles', [\App\Http\Controllers\Api\V1\RoleController::class, 'index'])
                    ->name('roles.index');
                Route::get('roles/{role}', [\App\Http\Controllers\Api\V1\RoleController::class, 'show'])
                    ->name('roles.show')
                    ->whereNumber('role');

                Route::post('employees', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'store'])
                    ->name('employees.store');
                Route::put('employees/{employee}', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'update'])
                    ->name('employees.update')
                    ->whereNumber('employee');
                Route::patch('employees/{employee}', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'update'])
                    ->name('employees.patch')
                    ->whereNumber('employee');
                Route::delete('employees/{employee}', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'destroy'])
                    ->name('employees.destroy')
                    ->whereNumber('employee');
                Route::patch('employees/{employee}/status', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'updateStatus'])
                    ->name('employees.status')
                    ->whereNumber('employee');
                Route::post('employees/{employee}/resend-welcome-email', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'resendWelcomeEmail'])
                    ->name('employees.resend-welcome-email')
                    ->whereNumber('employee');
            });

            Route::middleware('company.permission:attendance.view')->group(function () {
                Route::get('attendance/status', [\App\Http\Controllers\Api\V1\AttendanceController::class, 'status'])
                    ->name('attendance.status');
                Route::get('attendance/calendar', [\App\Http\Controllers\Api\V1\AttendanceController::class, 'calendar'])
                    ->name('attendance.calendar');
                Route::get('attendance/day/{date}', [\App\Http\Controllers\Api\V1\AttendanceController::class, 'day'])
                    ->name('attendance.day')
                    ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
                Route::get('attendance/today-overview', [\App\Http\Controllers\Api\V1\AttendanceController::class, 'todayOverview'])
                    ->name('attendance.today-overview');
            });

            Route::post('attendance/punch', [\App\Http\Controllers\Api\V1\AttendanceController::class, 'punch'])
                ->name('attendance.punch');

            Route::middleware('company.permission:attendance.regularize')->group(function () {
                Route::get('attendance-regularizations/eligible-dates', [\App\Http\Controllers\Api\V1\AttendanceRegularizationController::class, 'eligibleDates'])
                    ->name('attendance-regularizations.eligible-dates');
                Route::post('attendance-regularizations', [\App\Http\Controllers\Api\V1\AttendanceRegularizationController::class, 'store'])
                    ->name('attendance-regularizations.store');
            });

            Route::middleware('company.permission:attendance.approve')->group(function () {
                Route::get('attendance-regularizations/pending', [\App\Http\Controllers\Api\V1\AttendanceRegularizationController::class, 'pending'])
                    ->name('attendance-regularizations.pending');
                Route::patch('attendance-regularizations/batch/{batchId}/approve', [\App\Http\Controllers\Api\V1\AttendanceRegularizationController::class, 'approveBatch'])
                    ->name('attendance-regularizations.batch.approve')
                    ->whereUuid('batchId');
                Route::patch('attendance-regularizations/batch/{batchId}/reject', [\App\Http\Controllers\Api\V1\AttendanceRegularizationController::class, 'rejectBatch'])
                    ->name('attendance-regularizations.batch.reject')
                    ->whereUuid('batchId');
                Route::patch('attendance-regularizations/{attendance_regularization}/approve', [\App\Http\Controllers\Api\V1\AttendanceRegularizationController::class, 'approve'])
                    ->name('attendance-regularizations.approve')
                    ->whereNumber('attendance_regularization');
                Route::patch('attendance-regularizations/{attendance_regularization}/reject', [\App\Http\Controllers\Api\V1\AttendanceRegularizationController::class, 'reject'])
                    ->name('attendance-regularizations.reject')
                    ->whereNumber('attendance_regularization');
            });

            Route::middleware('company.member')->group(function () {
                Route::get('attendance-regularizations', [\App\Http\Controllers\Api\V1\AttendanceRegularizationController::class, 'index'])
                    ->name('attendance-regularizations.index');
                Route::get('attendance-regularizations/{attendance_regularization}', [\App\Http\Controllers\Api\V1\AttendanceRegularizationController::class, 'show'])
                    ->name('attendance-regularizations.show')
                    ->whereNumber('attendance_regularization');
                Route::patch('attendance-regularizations/{attendance_regularization}/cancel', [\App\Http\Controllers\Api\V1\AttendanceRegularizationController::class, 'cancel'])
                    ->name('attendance-regularizations.cancel')
                    ->whereNumber('attendance_regularization');
            });

            Route::middleware('company.permission:leave.manage')->group(function () {
                Route::apiResource('leave-types', \App\Http\Controllers\Api\V1\LeaveTypeController::class)
                    ->names('leave-types')
                    ->whereNumber('leave_type');
                Route::patch('leave-balances/{balance}', [\App\Http\Controllers\Api\V1\LeaveBalanceController::class, 'update'])
                    ->name('leave-balances.update')
                    ->whereNumber('balance');
                Route::post('leave-balances/{balance}/grant-comp-off', [\App\Http\Controllers\Api\V1\LeaveBalanceController::class, 'grantCompOff'])
                    ->name('leave-balances.grant-comp-off')
                    ->whereNumber('balance');
            });

            Route::middleware('company.permission:leave.apply')->group(function () {
                Route::get('leave-types/options', [\App\Http\Controllers\Api\V1\LeaveTypeController::class, 'options'])
                    ->name('leave-types.options');
                Route::get('leave-balances/me', [\App\Http\Controllers\Api\V1\LeaveBalanceController::class, 'myBalances'])
                    ->name('leave-balances.me');
                Route::post('leave-requests/preview', [\App\Http\Controllers\Api\V1\LeaveRequestController::class, 'preview'])
                    ->name('leave-requests.preview');
                Route::post('leave-requests', [\App\Http\Controllers\Api\V1\LeaveRequestController::class, 'store'])
                    ->name('leave-requests.store');
                Route::post('leave-requests/{leave_request}/attachments', [\App\Http\Controllers\Api\V1\LeaveRequestController::class, 'uploadAttachments'])
                    ->name('leave-requests.attachments')
                    ->whereNumber('leave_request');
            });

            Route::middleware('company.permission:leave.approve')->group(function () {
                Route::get('leave-requests/pending', [\App\Http\Controllers\Api\V1\LeaveRequestController::class, 'pending'])
                    ->name('leave-requests.pending');
                Route::get('leave-balances/employees/{employee}', [\App\Http\Controllers\Api\V1\LeaveBalanceController::class, 'employeeBalances'])
                    ->name('leave-balances.employee')
                    ->whereNumber('employee');
                Route::patch('leave-requests/{leave_request}/approve', [\App\Http\Controllers\Api\V1\LeaveRequestController::class, 'approve'])
                    ->name('leave-requests.approve')
                    ->whereNumber('leave_request');
                Route::patch('leave-requests/{leave_request}/reject', [\App\Http\Controllers\Api\V1\LeaveRequestController::class, 'reject'])
                    ->name('leave-requests.reject')
                    ->whereNumber('leave_request');
            });

            Route::middleware('company.member')->group(function () {
                Route::get('leave-requests', [\App\Http\Controllers\Api\V1\LeaveRequestController::class, 'index'])
                    ->name('leave-requests.index');
                Route::get('leave-requests/{leave_request}', [\App\Http\Controllers\Api\V1\LeaveRequestController::class, 'show'])
                    ->name('leave-requests.show')
                    ->whereNumber('leave_request');
                Route::patch('leave-requests/{leave_request}/cancel', [\App\Http\Controllers\Api\V1\LeaveRequestController::class, 'cancel'])
                    ->name('leave-requests.cancel')
                    ->whereNumber('leave_request');
            });

            Route::middleware('company.permission:attendance.manage')->group(function () {
                Route::get('weekly-off', [\App\Http\Controllers\Api\V1\WeeklyOffController::class, 'show'])
                    ->name('weekly-off.show');
                Route::put('weekly-off', [\App\Http\Controllers\Api\V1\WeeklyOffController::class, 'update'])
                    ->name('weekly-off.update');
                Route::get('portal-start', [\App\Http\Controllers\Api\V1\PortalStartController::class, 'show'])
                    ->name('portal-start.show');
                Route::put('portal-start', [\App\Http\Controllers\Api\V1\PortalStartController::class, 'update'])
                    ->name('portal-start.update');
                Route::apiResource('holidays', \App\Http\Controllers\Api\V1\HolidayController::class)
                    ->names('holidays')
                    ->whereNumber('holiday');
            });

            Route::middleware('company.member')->group(function () {
                Route::get('people/summary', [\App\Http\Controllers\Api\V1\PeopleController::class, 'summary'])
                    ->name('people.summary');
                Route::get('people/org-chart', [\App\Http\Controllers\Api\V1\PeopleController::class, 'orgChart'])
                    ->name('people.org-chart');
            });

            Route::middleware('company.permission:employees.view')->group(function () {
                Route::get('employees', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'index'])
                    ->name('employees.index');
                Route::get('employees/{employee}/profile', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'showProfile'])
                    ->name('employees.profile.show')
                    ->whereNumber('employee');
                Route::get('employees/{employee}', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'show'])
                    ->name('employees.show')
                    ->whereNumber('employee');
            });

            Route::middleware('company.permission:payroll.view')->group(function () {
                Route::get('payroll-periods', [\App\Http\Controllers\Api\V1\PayrollController::class, 'periods'])
                    ->name('payroll-periods.index');
                Route::get('my-payslips', [\App\Http\Controllers\Api\V1\PayrollController::class, 'myPayslips'])
                    ->name('my-payslips.index');
                Route::get('payslips/{payslip}', [\App\Http\Controllers\Api\V1\PayrollController::class, 'show'])
                    ->name('payslips.show')
                    ->whereNumber('payslip');
                Route::get('payslips/{payslip}/view', [\App\Http\Controllers\Api\V1\PayrollController::class, 'view'])
                    ->name('payslips.view')
                    ->whereNumber('payslip');
                Route::get('payslips/{payslip}/download', [\App\Http\Controllers\Api\V1\PayrollController::class, 'download'])
                    ->name('payslips.download')
                    ->whereNumber('payslip');
            });

            Route::middleware('company.permission:payroll.manage')->group(function () {
                Route::post('payroll-periods/generate', [\App\Http\Controllers\Api\V1\PayrollController::class, 'generate'])
                    ->name('payroll-periods.generate');
                Route::post('payroll-periods/regenerate', [\App\Http\Controllers\Api\V1\PayrollController::class, 'regenerate'])
                    ->name('payroll-periods.regenerate');
                Route::get('payroll-periods/{payrollPeriod}/payslips', [\App\Http\Controllers\Api\V1\PayrollController::class, 'payslips'])
                    ->name('payroll-periods.payslips')
                    ->whereNumber('payrollPeriod');
                Route::get('payroll-periods/{payrollPeriod}/export', [\App\Http\Controllers\Api\V1\PayrollController::class, 'export'])
                    ->name('payroll-periods.export')
                    ->whereNumber('payrollPeriod');
                Route::delete('payroll-periods/{payrollPeriod}', [\App\Http\Controllers\Api\V1\PayrollController::class, 'destroyPeriod'])
                    ->name('payroll-periods.destroy')
                    ->whereNumber('payrollPeriod');
            });

            Route::middleware('company.member')->group(function () {
                Route::put('employees/{employee}/profile/salary', [\App\Http\Controllers\Api\V1\EmployeeProfileController::class, 'updateSalary'])
                    ->name('employees.profile.salary.update')
                    ->whereNumber('employee');
                Route::put('employees/{employee}/profile/assets', [\App\Http\Controllers\Api\V1\EmployeeProfileController::class, 'updateAssets'])
                    ->name('employees.profile.assets.update')
                    ->whereNumber('employee');
                Route::post('employees/{employee}/profile/family-members', [\App\Http\Controllers\Api\V1\EmployeeProfileController::class, 'storeFamilyMembers'])
                    ->name('employees.profile.family-members.store')
                    ->whereNumber('employee');
                Route::post('employees/{employee}/profile/personal-sections', [\App\Http\Controllers\Api\V1\EmployeeProfileController::class, 'storePersonalSection'])
                    ->name('employees.profile.personal-sections.store')
                    ->whereNumber('employee');
                Route::post('employees/{employee}/profile/payment-methods', [\App\Http\Controllers\Api\V1\EmployeeProfileController::class, 'storePaymentMethod'])
                    ->name('employees.profile.payment-methods.store')
                    ->whereNumber('employee');
                Route::post('employees/{employee}/profile/compliance-fields', [\App\Http\Controllers\Api\V1\EmployeeProfileController::class, 'storeComplianceField'])
                    ->name('employees.profile.compliance-fields.store')
                    ->whereNumber('employee');
                Route::post('employees/{employee}/profile/documents', [\App\Http\Controllers\Api\V1\EmployeeProfileController::class, 'storeDocument'])
                    ->name('employees.profile.documents.store')
                    ->whereNumber('employee');
                Route::get('employees/{employee}/profile/documents/{employeeDocument}/download', [\App\Http\Controllers\Api\V1\EmployeeProfileController::class, 'downloadDocument'])
                    ->name('employees.profile.documents.download')
                    ->whereNumber(['employee', 'employeeDocument']);
            });
        });
    });
});
