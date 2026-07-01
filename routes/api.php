<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware(['auth:sanctum', 'log.activity'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('activity-logs', [\App\Http\Controllers\Api\V1\ActivityLogController::class, 'index'])
            ->name('activity-logs.index');
        Route::get('activity-logs/dates', [\App\Http\Controllers\Api\V1\ActivityLogController::class, 'dates'])
            ->name('activity-logs.dates');
        Route::get('activity-logs/employees/{employee}/timeline', [\App\Http\Controllers\Api\V1\ActivityLogController::class, 'timeline'])
            ->name('activity-logs.employee-timeline')
            ->whereNumber('employee');

        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::get('profile/employee', [ProfileController::class, 'showEmployee'])->name('profile.employee.show');
        Route::post('profile/family-members', [ProfileController::class, 'storeFamilyMembers'])->name('profile.family-members.store');
        Route::post('profile/personal-sections', [ProfileController::class, 'storePersonalSection'])->name('profile.personal-sections.store');
        Route::post('profile/compliance-fields', [ProfileController::class, 'storeComplianceField'])->name('profile.compliance-fields.store');
        Route::post('profile/payment-methods', [ProfileController::class, 'storePaymentMethod'])->name('profile.payment-methods.store');
        Route::post('profile/photo', [ProfileController::class, 'storeProfilePhoto'])->name('profile.photo.store');
        Route::get('profile/payment-method-proofs/{employeePaymentMethodProof}/download', [ProfileController::class, 'downloadPaymentMethodProof'])
            ->name('profile.payment-method-proofs.download')
            ->whereNumber('employeePaymentMethodProof');
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
            Route::middleware('company.permission:home.moments.view')->group(function () {
                Route::get('home/moments/summary', [\App\Http\Controllers\Api\V1\MomentController::class, 'summary'])
                    ->name('home.moments.summary');
                Route::patch('home/moments/mark-seen', [\App\Http\Controllers\Api\V1\MomentController::class, 'markSeen'])
                    ->name('home.moments.mark-seen');
                Route::get('home/moments', [\App\Http\Controllers\Api\V1\MomentController::class, 'index'])
                    ->name('home.moments.index');
                Route::get('home/moments/{moment}/comments', [\App\Http\Controllers\Api\V1\MomentController::class, 'comments'])
                    ->name('home.moments.comments.index')
                    ->whereNumber('moment');
                Route::post('home/moments/{moment}/react', [\App\Http\Controllers\Api\V1\MomentController::class, 'react'])
                    ->name('home.moments.react')
                    ->whereNumber('moment');
                Route::post('home/moments/{moment}/comments', [\App\Http\Controllers\Api\V1\MomentController::class, 'storeComment'])
                    ->name('home.moments.comments.store')
                    ->whereNumber('moment');
            });

            Route::middleware('company.permission:home.moments.post')->group(function () {
                Route::post('home/moments', [\App\Http\Controllers\Api\V1\MomentController::class, 'store'])
                    ->name('home.moments.store');
                Route::put('home/moments/templates', [\App\Http\Controllers\Api\V1\MomentController::class, 'updateTemplates'])
                    ->name('home.moments.templates.update');
            });

            Route::middleware('company.permission:home.dashboard.view')->group(function () {
                Route::get('home/dashboard', [\App\Http\Controllers\Api\V1\HomeDashboardController::class, 'index'])
                    ->name('home.dashboard.index');
            });

            Route::middleware('company.permission:home.dashboard.manage')->group(function () {
                Route::put('home/dashboard/widgets', [\App\Http\Controllers\Api\V1\HomeDashboardController::class, 'syncWidgets'])
                    ->name('home.dashboard.widgets.sync');
            });

            Route::get('notifications/summary', [\App\Http\Controllers\Api\V1\NotificationController::class, 'summary'])
                ->name('notifications.summary');
            Route::get('notifications', [\App\Http\Controllers\Api\V1\NotificationController::class, 'index'])
                ->name('notifications.index');
            Route::post('notifications/read-all', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAllRead'])
                ->name('notifications.read-all');
            Route::post('notifications/{notification}/read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markRead'])
                ->name('notifications.read')
                ->whereNumber('notification');

            Route::get('request-hub/summary', [\App\Http\Controllers\Api\V1\RequestHubController::class, 'summary'])
                ->name('request-hub.summary');
            Route::get('request-hub/{category}/{entityId}', [\App\Http\Controllers\Api\V1\RequestHubController::class, 'show'])
                ->name('request-hub.show')
                ->where('category', '[a-z_]+')
                ->where('entityId', '[0-9]+');
            Route::get('request-hub/pending', [\App\Http\Controllers\Api\V1\RequestHubController::class, 'pending'])
                ->name('request-hub.pending');
            Route::post('request-hub/bulk-review', [\App\Http\Controllers\Api\V1\RequestHubController::class, 'bulkReview'])
                ->name('request-hub.bulk-review');
            Route::get('request-hub/mine', [\App\Http\Controllers\Api\V1\RequestHubController::class, 'mine'])
                ->name('request-hub.mine');
            Route::get('request-hub/team', [\App\Http\Controllers\Api\V1\RequestHubController::class, 'team'])
                ->name('request-hub.team');

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
            Route::get('employee-payment-method-proofs/{employeePaymentMethodProof}/download', [\App\Http\Controllers\Api\V1\EmployeePaymentMethodController::class, 'downloadProof'])
                ->name('employee-payment-method-proofs.download')
                ->whereNumber('employeePaymentMethodProof');

            Route::get('employee-profile-photos/pending', [\App\Http\Controllers\Api\V1\EmployeeProfilePhotoController::class, 'pending'])
                ->name('employee-profile-photos.pending');
            Route::get('employee-profile-photos/{employeeProfilePhoto}/download', [\App\Http\Controllers\Api\V1\EmployeeProfilePhotoController::class, 'download'])
                ->name('employee-profile-photos.download')
                ->whereNumber('employeeProfilePhoto');
            Route::patch('employee-profile-photos/{employeeProfilePhoto}/approve', [\App\Http\Controllers\Api\V1\EmployeeProfilePhotoController::class, 'approve'])
                ->name('employee-profile-photos.approve')
                ->whereNumber('employeeProfilePhoto');
            Route::patch('employee-profile-photos/{employeeProfilePhoto}/reject', [\App\Http\Controllers\Api\V1\EmployeeProfilePhotoController::class, 'reject'])
                ->name('employee-profile-photos.reject')
                ->whereNumber('employeeProfilePhoto');

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

            Route::middleware('company.permission:departments.view,employees.manage')->group(function () {
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

            Route::middleware('company.permission:shifts.view,employees.manage')->group(function () {
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

            Route::middleware('company.permission:projects.view')->group(function () {
                Route::get('projects/assigned', [\App\Http\Controllers\Api\V1\ProjectController::class, 'assigned'])
                    ->name('projects.assigned');
                Route::get('projects/{project}', [\App\Http\Controllers\Api\V1\ProjectController::class, 'show'])
                    ->name('projects.show')
                    ->whereNumber('project');
            });

            Route::middleware('company.permission:projects.manage')->group(function () {
                Route::get('projects/employee-options', [\App\Http\Controllers\Api\V1\ProjectController::class, 'employeeOptions'])
                    ->name('projects.employee-options');
                Route::get('projects', [\App\Http\Controllers\Api\V1\ProjectController::class, 'index'])
                    ->name('projects.index');
                Route::post('projects', [\App\Http\Controllers\Api\V1\ProjectController::class, 'store'])
                    ->name('projects.store');
                Route::put('projects/{project}', [\App\Http\Controllers\Api\V1\ProjectController::class, 'update'])
                    ->name('projects.update')
                    ->whereNumber('project');
                Route::patch('projects/{project}', [\App\Http\Controllers\Api\V1\ProjectController::class, 'update'])
                    ->name('projects.patch')
                    ->whereNumber('project');
                Route::delete('projects/{project}', [\App\Http\Controllers\Api\V1\ProjectController::class, 'destroy'])
                    ->name('projects.destroy')
                    ->whereNumber('project');
            });

            Route::middleware('company.member')->group(function () {
                Route::get('timesheets/team-employees', [\App\Http\Controllers\Api\V1\TimesheetController::class, 'teamEmployees'])
                    ->name('timesheets.team-employees');
                Route::get('timesheets/comments', [\App\Http\Controllers\Api\V1\TimesheetController::class, 'comments'])
                    ->name('timesheets.comments');
                Route::post('timesheets/comments', [\App\Http\Controllers\Api\V1\TimesheetController::class, 'storeComment'])
                    ->name('timesheets.comments.store');
                Route::get('timesheets/project-options', [\App\Http\Controllers\Api\V1\TimesheetController::class, 'projectOptions'])
                    ->name('timesheets.project-options');
                Route::get('timesheets/recent', [\App\Http\Controllers\Api\V1\TimesheetController::class, 'recent'])
                    ->name('timesheets.recent');
                Route::get('timesheets', [\App\Http\Controllers\Api\V1\TimesheetController::class, 'index'])
                    ->name('timesheets.index');
                Route::post('timesheets', [\App\Http\Controllers\Api\V1\TimesheetController::class, 'store'])
                    ->name('timesheets.store');
            });

            Route::middleware('company.permission:expenses.apply')->group(function () {
                Route::get('expense-types/options', [\App\Http\Controllers\Api\V1\ExpenseController::class, 'typeOptions'])
                    ->name('expense-types.options');
                Route::get('expenses/export', [\App\Http\Controllers\Api\V1\ExpenseController::class, 'export'])
                    ->name('expenses.export');
                Route::post('expenses', [\App\Http\Controllers\Api\V1\ExpenseController::class, 'store'])
                    ->name('expenses.store');
                Route::put('expenses/{expense}', [\App\Http\Controllers\Api\V1\ExpenseController::class, 'update'])
                    ->name('expenses.update')
                    ->whereNumber('expense');
                Route::patch('expenses/{expense}/submit', [\App\Http\Controllers\Api\V1\ExpenseController::class, 'submit'])
                    ->name('expenses.submit')
                    ->whereNumber('expense');
                Route::patch('expenses/{expense}/cancel', [\App\Http\Controllers\Api\V1\ExpenseController::class, 'cancel'])
                    ->name('expenses.cancel')
                    ->whereNumber('expense');
                Route::get('expense-groups/draft-options', [\App\Http\Controllers\Api\V1\ExpenseGroupController::class, 'draftOptions'])
                    ->name('expense-groups.draft-options');
                Route::post('expense-groups', [\App\Http\Controllers\Api\V1\ExpenseGroupController::class, 'store'])
                    ->name('expense-groups.store');
                Route::put('expense-groups/{expenseGroup}', [\App\Http\Controllers\Api\V1\ExpenseGroupController::class, 'update'])
                    ->name('expense-groups.update')
                    ->whereNumber('expenseGroup');
                Route::post('expense-groups/{expenseGroup}/expenses', [\App\Http\Controllers\Api\V1\ExpenseGroupController::class, 'addExpense'])
                    ->name('expense-groups.expenses.store')
                    ->whereNumber('expenseGroup');
                Route::patch('expense-groups/{expenseGroup}/submit', [\App\Http\Controllers\Api\V1\ExpenseGroupController::class, 'submit'])
                    ->name('expense-groups.submit')
                    ->whereNumber('expenseGroup');
                Route::patch('expense-groups/{expenseGroup}/cancel', [\App\Http\Controllers\Api\V1\ExpenseGroupController::class, 'cancel'])
                    ->name('expense-groups.cancel')
                    ->whereNumber('expenseGroup');
            });

            Route::middleware('company.member')->group(function () {
                Route::patch('expenses/{expense}/approve', [\App\Http\Controllers\Api\V1\ExpenseController::class, 'approve'])
                    ->name('expenses.approve')
                    ->whereNumber('expense');
                Route::patch('expenses/{expense}/reject', [\App\Http\Controllers\Api\V1\ExpenseController::class, 'reject'])
                    ->name('expenses.reject')
                    ->whereNumber('expense');
                Route::patch('expense-groups/{expenseGroup}/approve', [\App\Http\Controllers\Api\V1\ExpenseGroupController::class, 'approve'])
                    ->name('expense-groups.approve')
                    ->whereNumber('expenseGroup');
                Route::patch('expense-groups/{expenseGroup}/reject', [\App\Http\Controllers\Api\V1\ExpenseGroupController::class, 'reject'])
                    ->name('expense-groups.reject')
                    ->whereNumber('expenseGroup');
            });

            Route::middleware('company.member')->group(function () {
                Route::get('expenses', [\App\Http\Controllers\Api\V1\ExpenseController::class, 'index'])
                    ->name('expenses.index');
                Route::get('expenses/{expense}', [\App\Http\Controllers\Api\V1\ExpenseController::class, 'show'])
                    ->name('expenses.show')
                    ->whereNumber('expense');
                Route::get('expense-groups', [\App\Http\Controllers\Api\V1\ExpenseGroupController::class, 'index'])
                    ->name('expense-groups.index');
                Route::get('expense-groups/{expenseGroup}', [\App\Http\Controllers\Api\V1\ExpenseGroupController::class, 'show'])
                    ->name('expense-groups.show')
                    ->whereNumber('expenseGroup');
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
                Route::patch('employees/{employee}/portal-access', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'updatePortalAccess'])
                    ->name('employees.portal-access')
                    ->whereNumber('employee');
                Route::post('employees/{employee}/resend-welcome-email', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'resendWelcomeEmail'])
                    ->name('employees.resend-welcome-email')
                    ->whereNumber('employee');
                Route::patch('employees/{employee}/make-admin', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'assignCompanyAdmin'])
                    ->middleware('company.permission:employees.assign_admin')
                    ->name('employees.make-admin')
                    ->whereNumber('employee');
                Route::patch('employees/{employee}/remove-admin', [\App\Http\Controllers\Api\V1\EmployeeController::class, 'removeCompanyAdmin'])
                    ->middleware('company.permission:employees.assign_admin')
                    ->name('employees.remove-admin')
                    ->whereNumber('employee');
            });

            Route::middleware('company.permission:roles.manage,roles.view,employees.manage')->group(function () {
                Route::get('roles', [\App\Http\Controllers\Api\V1\RoleController::class, 'index'])
                    ->name('roles.index');
                Route::get('roles/{role}', [\App\Http\Controllers\Api\V1\RoleController::class, 'show'])
                    ->name('roles.show')
                    ->whereNumber('role');
            });

            Route::middleware('company.permission:roles.manage')->group(function () {
                Route::get('permissions/catalog', [\App\Http\Controllers\Api\V1\RoleController::class, 'permissionCatalog'])
                    ->name('permissions.catalog');
                Route::post('roles', [\App\Http\Controllers\Api\V1\RoleController::class, 'store'])
                    ->name('roles.store');
                Route::put('roles/{role}', [\App\Http\Controllers\Api\V1\RoleController::class, 'update'])
                    ->name('roles.update')
                    ->whereNumber('role');
                Route::patch('roles/{role}', [\App\Http\Controllers\Api\V1\RoleController::class, 'update'])
                    ->name('roles.patch')
                    ->whereNumber('role');
                Route::patch('roles/{role}/permissions', [\App\Http\Controllers\Api\V1\RoleController::class, 'syncPermissions'])
                    ->name('roles.permissions.sync')
                    ->whereNumber('role');
                Route::delete('roles/{role}/permissions', [\App\Http\Controllers\Api\V1\RoleController::class, 'resetPermissions'])
                    ->name('roles.permissions.reset')
                    ->whereNumber('role');
                Route::delete('roles/{role}', [\App\Http\Controllers\Api\V1\RoleController::class, 'destroy'])
                    ->name('roles.destroy')
                    ->whereNumber('role');
            });

            Route::middleware('company.permission:attendance.view')->group(function () {
                Route::get('holidays', [\App\Http\Controllers\Api\V1\HolidayController::class, 'index'])
                    ->name('holidays.index');
                Route::get('holidays/{holiday}', [\App\Http\Controllers\Api\V1\HolidayController::class, 'show'])
                    ->name('holidays.show')
                    ->whereNumber('holiday');
                Route::get('attendance/status', [\App\Http\Controllers\Api\V1\AttendanceController::class, 'status'])
                    ->name('attendance.status');
                Route::get('attendance/calendar', [\App\Http\Controllers\Api\V1\AttendanceController::class, 'calendar'])
                    ->name('attendance.calendar');
                Route::get('attendance/day/{date}', [\App\Http\Controllers\Api\V1\AttendanceController::class, 'day'])
                    ->name('attendance.day')
                    ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
                Route::get('attendance/today-overview', [\App\Http\Controllers\Api\V1\AttendanceController::class, 'todayOverview'])
                    ->name('attendance.today-overview');
                Route::get('attendance/month-matrix', [\App\Http\Controllers\Api\V1\AttendanceController::class, 'monthMatrix'])
                    ->name('attendance.month-matrix');
            });

            Route::post('attendance/punch', [\App\Http\Controllers\Api\V1\AttendanceController::class, 'punch'])
                ->name('attendance.punch');
            Route::post('attendance/face-reference', [\App\Http\Controllers\Api\V1\AttendanceSettingsController::class, 'syncFaceReference'])
                ->name('attendance.face-reference.sync');
            Route::get('attendance/current-ip', [\App\Http\Controllers\Api\V1\AttendanceSettingsController::class, 'currentIp'])
                ->name('attendance.current-ip');

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
                Route::get('attendance-regularizations/summary', [\App\Http\Controllers\Api\V1\AttendanceRegularizationController::class, 'summary'])
                    ->name('attendance-regularizations.summary');
                Route::get('attendance-regularizations', [\App\Http\Controllers\Api\V1\AttendanceRegularizationController::class, 'index'])
                    ->name('attendance-regularizations.index');
                Route::get('attendance-regularizations/batch/{batchId}', [\App\Http\Controllers\Api\V1\AttendanceRegularizationController::class, 'showBatch'])
                    ->name('attendance-regularizations.batch.show')
                    ->whereUuid('batchId');
                Route::get('attendance-regularizations/{attendance_regularization}', [\App\Http\Controllers\Api\V1\AttendanceRegularizationController::class, 'show'])
                    ->name('attendance-regularizations.show')
                    ->whereNumber('attendance_regularization');
                Route::patch('attendance-regularizations/{attendance_regularization}/cancel', [\App\Http\Controllers\Api\V1\AttendanceRegularizationController::class, 'cancel'])
                    ->name('attendance-regularizations.cancel')
                    ->whereNumber('attendance_regularization');
            });

            Route::middleware('company.member')->group(function () {
                Route::get('leave-calendar', [\App\Http\Controllers\Api\V1\LeaveCalendarController::class, 'show'])
                    ->name('leave-calendar.show');
            });

            Route::middleware('company.member')->group(function () {
                Route::get('analytics/catalog', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'catalog'])
                    ->name('analytics.catalog');
                Route::get('analytics/options', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'options'])
                    ->name('analytics.options');
                Route::get('analytics/reports/{reportKey}', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'show'])
                    ->name('analytics.reports.show');
                Route::get('analytics/reports/{reportKey}/export', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'export'])
                    ->name('analytics.reports.export');
                Route::get('analytics/leave-balances', [\App\Http\Controllers\Api\V1\LeaveBalanceAnalyticsController::class, 'index'])
                    ->name('analytics.leave-balances.index');
                Route::get('analytics/leave-balances/export', [\App\Http\Controllers\Api\V1\LeaveBalanceAnalyticsController::class, 'export'])
                    ->name('analytics.leave-balances.export');
                Route::get('analytics/leave-balances/employees/{employee}/policies/{leaveType}', [\App\Http\Controllers\Api\V1\LeaveBalanceAnalyticsController::class, 'detail'])
                    ->name('analytics.leave-balances.detail')
                    ->whereNumber(['employee', 'leaveType']);
            });

            Route::middleware('company.permission:leave.manage,employees.manage')->group(function () {
                Route::get('leave-types', [\App\Http\Controllers\Api\V1\LeaveTypeController::class, 'index'])
                    ->name('leave-types.index');
            });

            Route::middleware('company.permission:leave.manage')->group(function () {
                Route::apiResource('leave-types', \App\Http\Controllers\Api\V1\LeaveTypeController::class)
                    ->except(['index'])
                    ->names('leave-types')
                    ->whereNumber('leave_type');
                Route::patch('leave-balances/{balance}', [\App\Http\Controllers\Api\V1\LeaveBalanceController::class, 'update'])
                    ->name('leave-balances.update')
                    ->whereNumber('balance');
                Route::get('leave-balances/overview', [\App\Http\Controllers\Api\V1\LeaveBalanceController::class, 'overview'])
                    ->name('leave-balances.overview');
                Route::get('leave-balances/employees/{employee}', [\App\Http\Controllers\Api\V1\LeaveBalanceController::class, 'employeeBalances'])
                    ->name('leave-balances.employee')
                    ->whereNumber('employee');
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

            Route::middleware('company.permission:wfh.apply')->group(function () {
                Route::post('wfh-requests/preview', [\App\Http\Controllers\Api\V1\WfhController::class, 'preview'])
                    ->name('wfh-requests.preview');
                Route::post('wfh-requests', [\App\Http\Controllers\Api\V1\WfhController::class, 'store'])
                    ->name('wfh-requests.store');
                Route::post('wfh-requests/{wfh_request}/attachments', [\App\Http\Controllers\Api\V1\WfhController::class, 'uploadAttachments'])
                    ->name('wfh-requests.attachments')
                    ->whereNumber('wfh_request');
            });

            Route::middleware('company.permission:helpdesk.apply')->group(function () {
                Route::post('helpdesk-tickets', [\App\Http\Controllers\Api\V1\HelpdeskTicketController::class, 'store'])
                    ->name('helpdesk-tickets.store');
            });

            Route::middleware('company.permission:helpdesk.apply,helpdesk.manage')->group(function () {
                Route::get('helpdesk-tickets/meta', [\App\Http\Controllers\Api\V1\HelpdeskTicketController::class, 'meta'])
                    ->name('helpdesk-tickets.meta');
                Route::get('helpdesk-tickets/summary', [\App\Http\Controllers\Api\V1\HelpdeskTicketController::class, 'summary'])
                    ->name('helpdesk-tickets.summary');
                Route::get('helpdesk-tickets', [\App\Http\Controllers\Api\V1\HelpdeskTicketController::class, 'index'])
                    ->name('helpdesk-tickets.index');
                Route::get('helpdesk-tickets/{helpdesk_ticket}', [\App\Http\Controllers\Api\V1\HelpdeskTicketController::class, 'show'])
                    ->name('helpdesk-tickets.show')
                    ->whereNumber('helpdesk_ticket');
                Route::post('helpdesk-tickets/{helpdesk_ticket}/comments', [\App\Http\Controllers\Api\V1\HelpdeskTicketController::class, 'addComment'])
                    ->name('helpdesk-tickets.comments.store')
                    ->whereNumber('helpdesk_ticket');
            });

            Route::middleware('company.permission:helpdesk.manage')->group(function () {
                Route::post('helpdesk-categories', [\App\Http\Controllers\Api\V1\HelpdeskCategoryController::class, 'store'])
                    ->name('helpdesk-categories.store');
                Route::patch('helpdesk-tickets/{helpdesk_ticket}/status', [\App\Http\Controllers\Api\V1\HelpdeskTicketController::class, 'updateStatus'])
                    ->name('helpdesk-tickets.status')
                    ->whereNumber('helpdesk_ticket');
            });

            Route::middleware('company.permission:documents.manage')->group(function () {
                Route::get('document-letter-templates/meta', [\App\Http\Controllers\Api\V1\DocumentLetterTemplateController::class, 'meta'])
                    ->name('document-letter-templates.meta');
                Route::get('document-letter-templates', [\App\Http\Controllers\Api\V1\DocumentLetterTemplateController::class, 'index'])
                    ->name('document-letter-templates.index');
                Route::post('document-letter-templates', [\App\Http\Controllers\Api\V1\DocumentLetterTemplateController::class, 'store'])
                    ->name('document-letter-templates.store');
                Route::put('document-letter-templates/{document_letter_template}', [\App\Http\Controllers\Api\V1\DocumentLetterTemplateController::class, 'update'])
                    ->name('document-letter-templates.update')
                    ->whereNumber('document_letter_template');
                Route::get('document-letter-templates/{document_letter_template}', [\App\Http\Controllers\Api\V1\DocumentLetterTemplateController::class, 'show'])
                    ->name('document-letter-templates.show')
                    ->whereNumber('document_letter_template');
                Route::post('document-letter-templates/{document_letter_template}/preview', [\App\Http\Controllers\Api\V1\DocumentLetterTemplateController::class, 'preview'])
                    ->name('document-letter-templates.preview')
                    ->whereNumber('document_letter_template');
                Route::post('document-letters', [\App\Http\Controllers\Api\V1\DocumentLetterController::class, 'store'])
                    ->name('document-letters.store');
                Route::patch('document-letters/{document_letter}/issue', [\App\Http\Controllers\Api\V1\DocumentLetterController::class, 'issue'])
                    ->name('document-letters.issue')
                    ->whereNumber('document_letter');
                Route::patch('document-letters/{document_letter}/cancel', [\App\Http\Controllers\Api\V1\DocumentLetterController::class, 'cancel'])
                    ->name('document-letters.cancel')
                    ->whereNumber('document_letter');
            });

            Route::middleware('company.permission:documents.view,documents.manage,documents.sign')->group(function () {
                Route::get('document-letters/summary', [\App\Http\Controllers\Api\V1\DocumentLetterController::class, 'summary'])
                    ->name('document-letters.summary');
                Route::get('document-letters', [\App\Http\Controllers\Api\V1\DocumentLetterController::class, 'index'])
                    ->name('document-letters.index');
                Route::get('document-letters/{document_letter}', [\App\Http\Controllers\Api\V1\DocumentLetterController::class, 'show'])
                    ->name('document-letters.show')
                    ->whereNumber('document_letter');
            });

            Route::middleware('company.permission:documents.sign')->group(function () {
                Route::post('document-letters/{document_letter}/sign', [\App\Http\Controllers\Api\V1\DocumentLetterController::class, 'sign'])
                    ->name('document-letters.sign')
                    ->whereNumber('document_letter');
                Route::patch('document-letters/{document_letter}/decline', [\App\Http\Controllers\Api\V1\DocumentLetterController::class, 'decline'])
                    ->name('document-letters.decline')
                    ->whereNumber('document_letter');
            });

            Route::middleware('company.permission:assets.apply')->group(function () {
                Route::get('asset-types/options', [\App\Http\Controllers\Api\V1\AssetRequestController::class, 'options'])
                    ->name('asset-types.options');
                Route::post('asset-requests', [\App\Http\Controllers\Api\V1\AssetRequestController::class, 'store'])
                    ->name('asset-requests.store');
            });

            Route::middleware('company.permission:leave.approve')->group(function () {
                Route::get('leave-requests/pending', [\App\Http\Controllers\Api\V1\LeaveRequestController::class, 'pending'])
                    ->name('leave-requests.pending');
                Route::patch('leave-requests/{leave_request}/approve', [\App\Http\Controllers\Api\V1\LeaveRequestController::class, 'approve'])
                    ->name('leave-requests.approve')
                    ->whereNumber('leave_request');
                Route::patch('leave-requests/{leave_request}/reject', [\App\Http\Controllers\Api\V1\LeaveRequestController::class, 'reject'])
                    ->name('leave-requests.reject')
                    ->whereNumber('leave_request');
            });

            Route::middleware('company.permission:wfh.approve')->group(function () {
                Route::get('wfh-requests/pending', [\App\Http\Controllers\Api\V1\WfhController::class, 'pending'])
                    ->name('wfh-requests.pending');
                Route::patch('wfh-requests/{wfh_request}/approve', [\App\Http\Controllers\Api\V1\WfhController::class, 'approve'])
                    ->name('wfh-requests.approve')
                    ->whereNumber('wfh_request');
                Route::patch('wfh-requests/{wfh_request}/reject', [\App\Http\Controllers\Api\V1\WfhController::class, 'reject'])
                    ->name('wfh-requests.reject')
                    ->whereNumber('wfh_request');
            });

            Route::middleware('company.permission:assets.approve')->group(function () {
                Route::get('asset-requests/pending', [\App\Http\Controllers\Api\V1\AssetRequestController::class, 'pending'])
                    ->name('asset-requests.pending');
                Route::patch('asset-requests/{asset_request}/approve', [\App\Http\Controllers\Api\V1\AssetRequestController::class, 'approve'])
                    ->name('asset-requests.approve')
                    ->whereNumber('asset_request');
                Route::patch('asset-requests/{asset_request}/reject', [\App\Http\Controllers\Api\V1\AssetRequestController::class, 'reject'])
                    ->name('asset-requests.reject')
                    ->whereNumber('asset_request');
                Route::post('asset-requests/{asset_request}/items/review', [\App\Http\Controllers\Api\V1\AssetRequestController::class, 'reviewItems'])
                    ->name('asset-requests.items.review')
                    ->whereNumber('asset_request');
                Route::patch('asset-requests/{asset_request}/items/{item}/approve', [\App\Http\Controllers\Api\V1\AssetRequestController::class, 'approveItem'])
                    ->name('asset-requests.items.approve')
                    ->whereNumber(['asset_request', 'item']);
                Route::patch('asset-requests/{asset_request}/items/{item}/reject', [\App\Http\Controllers\Api\V1\AssetRequestController::class, 'rejectItem'])
                    ->name('asset-requests.items.reject')
                    ->whereNumber(['asset_request', 'item']);
            });

            Route::middleware('company.permission:offboarding.apply')->group(function () {
                Route::post('resignation-requests', [\App\Http\Controllers\Api\V1\ResignationController::class, 'store'])
                    ->name('resignation-requests.store');
            });

            Route::middleware('company.permission:offboarding.approve,offboarding.manage,clearance.review,offboarding.fnf.manage')->group(function () {
                Route::get('resignation-requests/pending', [\App\Http\Controllers\Api\V1\ResignationController::class, 'pending'])
                    ->name('resignation-requests.pending');
                Route::patch('resignation-requests/{resignation_request}/approve', [\App\Http\Controllers\Api\V1\ResignationController::class, 'approve'])
                    ->name('resignation-requests.approve')
                    ->whereNumber('resignation_request');
                Route::patch('resignation-requests/{resignation_request}/reject', [\App\Http\Controllers\Api\V1\ResignationController::class, 'reject'])
                    ->name('resignation-requests.reject')
                    ->whereNumber('resignation_request');
                Route::post('exit-cases/{exit_case}/clearance/review', [\App\Http\Controllers\Api\V1\ExitCaseController::class, 'reviewClearance'])
                    ->name('exit-cases.clearance.review')
                    ->whereNumber('exit_case');
                Route::patch('exit-cases/{exit_case}/clearance-items/{item}', [\App\Http\Controllers\Api\V1\ExitCaseController::class, 'reviewClearanceItem'])
                    ->name('exit-cases.clearance-items.review')
                    ->whereNumber(['exit_case', 'item']);
                Route::post('exit-cases/{exit_case}/assets/review', [\App\Http\Controllers\Api\V1\ExitCaseController::class, 'reviewAssets'])
                    ->name('exit-cases.assets.review')
                    ->whereNumber('exit_case');
                Route::patch('exit-cases/{exit_case}/asset-items/{item}', [\App\Http\Controllers\Api\V1\ExitCaseController::class, 'reviewAssetItem'])
                    ->name('exit-cases.asset-items.review')
                    ->whereNumber(['exit_case', 'item']);
                Route::patch('exit-cases/{exit_case}/settlement', [\App\Http\Controllers\Api\V1\ExitCaseController::class, 'saveSettlement'])
                    ->name('exit-cases.settlement.save')
                    ->whereNumber('exit_case');
                Route::patch('exit-cases/{exit_case}/settlement/approve', [\App\Http\Controllers\Api\V1\ExitCaseController::class, 'approveSettlement'])
                    ->name('exit-cases.settlement.approve')
                    ->whereNumber('exit_case');
                Route::patch('exit-cases/{exit_case}/settlement/paid', [\App\Http\Controllers\Api\V1\ExitCaseController::class, 'markSettlementPaid'])
                    ->name('exit-cases.settlement.paid')
                    ->whereNumber('exit_case');
                Route::get('exit-survey-questions/meta', [\App\Http\Controllers\Api\V1\ExitSurveyQuestionController::class, 'meta'])
                    ->name('exit-survey-questions.meta');
                Route::get('exit-survey-questions', [\App\Http\Controllers\Api\V1\ExitSurveyQuestionController::class, 'index'])
                    ->name('exit-survey-questions.index');
                Route::post('exit-survey-questions', [\App\Http\Controllers\Api\V1\ExitSurveyQuestionController::class, 'store'])
                    ->name('exit-survey-questions.store');
                Route::put('exit-survey-questions/{exit_survey_question}', [\App\Http\Controllers\Api\V1\ExitSurveyQuestionController::class, 'update'])
                    ->name('exit-survey-questions.update')
                    ->whereNumber('exit_survey_question');
                Route::post('exit-survey-questions/reseed', [\App\Http\Controllers\Api\V1\ExitSurveyQuestionController::class, 'reseed'])
                    ->name('exit-survey-questions.reseed');
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
                Route::get('wfh-requests', [\App\Http\Controllers\Api\V1\WfhController::class, 'index'])
                    ->name('wfh-requests.index');
                Route::get('wfh-requests/{wfh_request}', [\App\Http\Controllers\Api\V1\WfhController::class, 'show'])
                    ->name('wfh-requests.show')
                    ->whereNumber('wfh_request');
                Route::patch('wfh-requests/{wfh_request}/cancel', [\App\Http\Controllers\Api\V1\WfhController::class, 'cancel'])
                    ->name('wfh-requests.cancel')
                    ->whereNumber('wfh_request');
                Route::get('asset-requests', [\App\Http\Controllers\Api\V1\AssetRequestController::class, 'index'])
                    ->name('asset-requests.index');
                Route::get('asset-requests/{asset_request}', [\App\Http\Controllers\Api\V1\AssetRequestController::class, 'show'])
                    ->name('asset-requests.show')
                    ->whereNumber('asset_request');
                Route::patch('asset-requests/{asset_request}/cancel', [\App\Http\Controllers\Api\V1\AssetRequestController::class, 'cancel'])
                    ->name('asset-requests.cancel')
                    ->whereNumber('asset_request');
                Route::get('resignation-requests', [\App\Http\Controllers\Api\V1\ResignationController::class, 'index'])
                    ->name('resignation-requests.index');
                Route::get('resignation-requests/{resignation_request}', [\App\Http\Controllers\Api\V1\ResignationController::class, 'show'])
                    ->name('resignation-requests.show')
                    ->whereNumber('resignation_request');
                Route::patch('resignation-requests/{resignation_request}/cancel', [\App\Http\Controllers\Api\V1\ResignationController::class, 'cancel'])
                    ->name('resignation-requests.cancel')
                    ->whereNumber('resignation_request');
                Route::get('exit-cases', [\App\Http\Controllers\Api\V1\ExitCaseController::class, 'index'])
                    ->name('exit-cases.index');
                Route::get('exit-cases/{exit_case}', [\App\Http\Controllers\Api\V1\ExitCaseController::class, 'show'])
                    ->name('exit-cases.show')
                    ->whereNumber('exit_case');
                Route::post('exit-cases/{exit_case}/survey', [\App\Http\Controllers\Api\V1\ExitCaseController::class, 'submitSurvey'])
                    ->name('exit-cases.survey.submit')
                    ->whereNumber('exit_case');
            });

            Route::middleware('company.permission:attendance.manage,employees.manage')->group(function () {
                Route::get('weekly-off', [\App\Http\Controllers\Api\V1\WeeklyOffController::class, 'show'])
                    ->name('weekly-off.show');
            });

            Route::middleware('company.permission:attendance.manage')->group(function () {
                Route::put('weekly-off', [\App\Http\Controllers\Api\V1\WeeklyOffController::class, 'update'])
                    ->name('weekly-off.update');
                Route::get('portal-start', [\App\Http\Controllers\Api\V1\PortalStartController::class, 'show'])
                    ->name('portal-start.show');
                Route::put('portal-start', [\App\Http\Controllers\Api\V1\PortalStartController::class, 'update'])
                    ->name('portal-start.update');
                Route::get('attendance/network-settings', [\App\Http\Controllers\Api\V1\AttendanceSettingsController::class, 'showNetwork'])
                    ->name('attendance.network-settings.show');
                Route::put('attendance/network-settings', [\App\Http\Controllers\Api\V1\AttendanceSettingsController::class, 'updateNetwork'])
                    ->name('attendance.network-settings.update');
                Route::put('attendance/face-settings', [\App\Http\Controllers\Api\V1\AttendanceSettingsController::class, 'updateFace'])
                    ->name('attendance.face-settings.update');
                Route::post('holidays', [\App\Http\Controllers\Api\V1\HolidayController::class, 'store'])
                    ->name('holidays.store');
                Route::put('holidays/{holiday}', [\App\Http\Controllers\Api\V1\HolidayController::class, 'update'])
                    ->name('holidays.update')
                    ->whereNumber('holiday');
                Route::patch('holidays/{holiday}', [\App\Http\Controllers\Api\V1\HolidayController::class, 'update'])
                    ->name('holidays.patch')
                    ->whereNumber('holiday');
                Route::delete('holidays/{holiday}', [\App\Http\Controllers\Api\V1\HolidayController::class, 'destroy'])
                    ->name('holidays.destroy')
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
                Route::post('payroll-periods/{payrollPeriod}/mark-paid', [\App\Http\Controllers\Api\V1\PayrollController::class, 'markPaid'])
                    ->name('payroll-periods.mark-paid')
                    ->whereNumber('payrollPeriod');
                Route::get('payroll-periods/{payrollPeriod}/payslips', [\App\Http\Controllers\Api\V1\PayrollController::class, 'payslips'])
                    ->name('payroll-periods.payslips')
                    ->whereNumber('payrollPeriod');
                Route::get('payroll-periods/{payrollPeriod}/export', [\App\Http\Controllers\Api\V1\PayrollController::class, 'export'])
                    ->name('payroll-periods.export')
                    ->whereNumber('payrollPeriod');
                Route::put('payroll-settings', [\App\Http\Controllers\Api\V1\CompanyPayrollSettingsController::class, 'update'])
                    ->name('payroll-settings.update');
            });

            Route::middleware('company.member')->group(function () {
                Route::get('payroll-settings', [\App\Http\Controllers\Api\V1\CompanyPayrollSettingsController::class, 'show'])
                    ->name('payroll-settings.show');
            });

            Route::middleware('company.permission:performance.participate')->group(function () {
                Route::get('performance/overview', [\App\Http\Controllers\Api\V1\PerformanceOverviewController::class, 'show'])
                    ->name('performance.overview');
            });

            Route::middleware('company.permission:performance.manage')->group(function () {
                Route::get('performance-question-bank', [\App\Http\Controllers\Api\V1\PerformanceQuestionBankController::class, 'index'])
                    ->name('performance-question-bank.index');
                Route::post('performance-question-bank', [\App\Http\Controllers\Api\V1\PerformanceQuestionBankController::class, 'store'])
                    ->name('performance-question-bank.store');
                Route::put('performance-question-bank/{performanceQuestionBank}', [\App\Http\Controllers\Api\V1\PerformanceQuestionBankController::class, 'update'])
                    ->name('performance-question-bank.update')
                    ->whereNumber('performanceQuestionBank');
                Route::delete('performance-question-bank/{performanceQuestionBank}', [\App\Http\Controllers\Api\V1\PerformanceQuestionBankController::class, 'destroy'])
                    ->name('performance-question-bank.destroy')
                    ->whereNumber('performanceQuestionBank');

                Route::get('performance-feedback-forms', [\App\Http\Controllers\Api\V1\PerformanceFeedbackFormController::class, 'index'])
                    ->name('performance-feedback-forms.index');
                Route::post('performance-feedback-forms', [\App\Http\Controllers\Api\V1\PerformanceFeedbackFormController::class, 'store'])
                    ->name('performance-feedback-forms.store');
                Route::get('performance-feedback-forms/{performanceFeedbackForm}', [\App\Http\Controllers\Api\V1\PerformanceFeedbackFormController::class, 'show'])
                    ->name('performance-feedback-forms.show')
                    ->whereNumber('performanceFeedbackForm');
                Route::put('performance-feedback-forms/{performanceFeedbackForm}', [\App\Http\Controllers\Api\V1\PerformanceFeedbackFormController::class, 'update'])
                    ->name('performance-feedback-forms.update')
                    ->whereNumber('performanceFeedbackForm');
                Route::delete('performance-feedback-forms/{performanceFeedbackForm}', [\App\Http\Controllers\Api\V1\PerformanceFeedbackFormController::class, 'destroy'])
                    ->name('performance-feedback-forms.destroy')
                    ->whereNumber('performanceFeedbackForm');

                Route::get('performance-kpis', [\App\Http\Controllers\Api\V1\PerformanceKpiController::class, 'index'])
                    ->name('performance-kpis.index');
                Route::post('performance-kpis', [\App\Http\Controllers\Api\V1\PerformanceKpiController::class, 'store'])
                    ->name('performance-kpis.store');
                Route::get('performance-kpis/{performanceKpi}', [\App\Http\Controllers\Api\V1\PerformanceKpiController::class, 'show'])
                    ->name('performance-kpis.show')
                    ->whereNumber('performanceKpi');
                Route::put('performance-kpis/{performanceKpi}', [\App\Http\Controllers\Api\V1\PerformanceKpiController::class, 'update'])
                    ->name('performance-kpis.update')
                    ->whereNumber('performanceKpi');
                Route::delete('performance-kpis/{performanceKpi}', [\App\Http\Controllers\Api\V1\PerformanceKpiController::class, 'destroy'])
                    ->name('performance-kpis.destroy')
                    ->whereNumber('performanceKpi');

                Route::get('performance-review-cycles', [\App\Http\Controllers\Api\V1\PerformanceReviewCycleController::class, 'index'])
                    ->name('performance-review-cycles.index');
                Route::post('performance-review-cycles', [\App\Http\Controllers\Api\V1\PerformanceReviewCycleController::class, 'store'])
                    ->name('performance-review-cycles.store');
                Route::get('performance-review-cycles/{performanceReviewCycle}', [\App\Http\Controllers\Api\V1\PerformanceReviewCycleController::class, 'show'])
                    ->name('performance-review-cycles.show')
                    ->whereNumber('performanceReviewCycle');
                Route::put('performance-review-cycles/{performanceReviewCycle}', [\App\Http\Controllers\Api\V1\PerformanceReviewCycleController::class, 'update'])
                    ->name('performance-review-cycles.update')
                    ->whereNumber('performanceReviewCycle');
                Route::patch('performance-review-cycles/{performanceReviewCycle}', [\App\Http\Controllers\Api\V1\PerformanceReviewCycleController::class, 'update'])
                    ->name('performance-review-cycles.patch')
                    ->whereNumber('performanceReviewCycle');
                Route::patch('performance-review-cycles/{performanceReviewCycle}/activate', [\App\Http\Controllers\Api\V1\PerformanceReviewCycleController::class, 'activate'])
                    ->name('performance-review-cycles.activate')
                    ->whereNumber('performanceReviewCycle');
                Route::patch('performance-review-cycles/{performanceReviewCycle}/close', [\App\Http\Controllers\Api\V1\PerformanceReviewCycleController::class, 'close'])
                    ->name('performance-review-cycles.close')
                    ->whereNumber('performanceReviewCycle');
                Route::patch('performance-review-cycles/{performanceReviewCycle}/reviews-open', [\App\Http\Controllers\Api\V1\PerformanceReviewCycleController::class, 'toggleReviewsOpen'])
                    ->name('performance-review-cycles.reviews-open')
                    ->whereNumber('performanceReviewCycle');
                Route::get('performance-review-cycles/{performanceReviewCycle}/progress', [\App\Http\Controllers\Api\V1\PerformanceReviewCycleController::class, 'progress'])
                    ->name('performance-review-cycles.progress')
                    ->whereNumber('performanceReviewCycle');
                Route::post('performance-review-cycles/{performanceReviewCycle}/reminders', [\App\Http\Controllers\Api\V1\PerformanceReviewCycleController::class, 'sendReminders'])
                    ->name('performance-review-cycles.reminders')
                    ->whereNumber('performanceReviewCycle');
            });

            Route::middleware('company.permission:performance.review')->group(function () {
                Route::get('performance-reviews/mine', [\App\Http\Controllers\Api\V1\PerformanceReviewCycleController::class, 'myReviews'])
                    ->name('performance-reviews.mine');
                Route::get('performance-reviews/{performanceReview}', [\App\Http\Controllers\Api\V1\PerformanceReviewController::class, 'show'])
                    ->name('performance-reviews.show')
                    ->whereNumber('performanceReview');
                Route::post('performance-reviews/{performanceReview}/submit', [\App\Http\Controllers\Api\V1\PerformanceReviewController::class, 'submit'])
                    ->name('performance-reviews.submit')
                    ->whereNumber('performanceReview');
            });

            Route::middleware('company.permission:performance.participate')->group(function () {
                Route::get('goals', [\App\Http\Controllers\Api\V1\GoalController::class, 'index'])
                    ->name('goals.index');
                Route::post('goals', [\App\Http\Controllers\Api\V1\GoalController::class, 'store'])
                    ->name('goals.store');
                Route::get('goals/{goal}', [\App\Http\Controllers\Api\V1\GoalController::class, 'show'])
                    ->name('goals.show')
                    ->whereNumber('goal');
                Route::put('goals/{goal}', [\App\Http\Controllers\Api\V1\GoalController::class, 'update'])
                    ->name('goals.update')
                    ->whereNumber('goal');
                Route::patch('goals/{goal}', [\App\Http\Controllers\Api\V1\GoalController::class, 'update'])
                    ->name('goals.patch')
                    ->whereNumber('goal');
                Route::patch('goals/{goal}/key-results/{goalKeyResult}', [\App\Http\Controllers\Api\V1\GoalController::class, 'updateKeyResult'])
                    ->name('goals.key-results.update')
                    ->whereNumber(['goal', 'goalKeyResult']);
                Route::delete('goals/{goal}/key-results/{goalKeyResult}', [\App\Http\Controllers\Api\V1\GoalController::class, 'deleteKeyResult'])
                    ->name('goals.key-results.destroy')
                    ->whereNumber(['goal', 'goalKeyResult']);
            });

            Route::middleware('company.permission:pip.manage')->group(function () {
                Route::get('pips', [\App\Http\Controllers\Api\V1\PipController::class, 'index'])
                    ->name('pips.index');
                Route::post('pips', [\App\Http\Controllers\Api\V1\PipController::class, 'store'])
                    ->name('pips.store');
                Route::get('pips/{pipPlan}', [\App\Http\Controllers\Api\V1\PipController::class, 'show'])
                    ->name('pips.show')
                    ->whereNumber('pipPlan');
                Route::put('pips/{pipPlan}', [\App\Http\Controllers\Api\V1\PipController::class, 'update'])
                    ->name('pips.update')
                    ->whereNumber('pipPlan');
                Route::patch('pips/{pipPlan}', [\App\Http\Controllers\Api\V1\PipController::class, 'update'])
                    ->name('pips.patch')
                    ->whereNumber('pipPlan');
                Route::patch('pips/{pipPlan}/status', [\App\Http\Controllers\Api\V1\PipController::class, 'updateStatus'])
                    ->name('pips.status')
                    ->whereNumber('pipPlan');
                Route::patch('pips/{pipPlan}/key-results/{pipKeyResult}', [\App\Http\Controllers\Api\V1\PipController::class, 'updateKeyResult'])
                    ->name('pips.key-results.update')
                    ->whereNumber(['pipPlan', 'pipKeyResult']);
            });

            Route::middleware('company.permission:hiring.requisition.create')->group(function () {
                Route::get('hiring/overview', [\App\Http\Controllers\Api\V1\HiringOverviewController::class, 'show'])
                    ->name('hiring.overview');
                Route::get('job-requisitions', [\App\Http\Controllers\Api\V1\JobRequisitionController::class, 'index'])
                    ->name('job-requisitions.index');
                Route::post('job-requisitions', [\App\Http\Controllers\Api\V1\JobRequisitionController::class, 'store'])
                    ->name('job-requisitions.store');
                Route::put('job-requisitions/{jobRequisition}', [\App\Http\Controllers\Api\V1\JobRequisitionController::class, 'update'])
                    ->name('job-requisitions.update')
                    ->whereNumber('jobRequisition');
                Route::patch('job-requisitions/{jobRequisition}/submit', [\App\Http\Controllers\Api\V1\JobRequisitionController::class, 'submit'])
                    ->name('job-requisitions.submit')
                    ->whereNumber('jobRequisition');
            });

            Route::middleware('company.permission:hiring.requisition.approve')->group(function () {
                Route::patch('job-requisitions/{jobRequisition}/approve', [\App\Http\Controllers\Api\V1\JobRequisitionController::class, 'approve'])
                    ->name('job-requisitions.approve')
                    ->whereNumber('jobRequisition');
                Route::patch('job-requisitions/{jobRequisition}/reject', [\App\Http\Controllers\Api\V1\JobRequisitionController::class, 'reject'])
                    ->name('job-requisitions.reject')
                    ->whereNumber('jobRequisition');
            });

            Route::middleware('company.permission:hiring.manage')->group(function () {
                Route::get('hiring-jobs', [\App\Http\Controllers\Api\V1\HiringJobController::class, 'index'])
                    ->name('hiring-jobs.index');
                Route::post('hiring-jobs', [\App\Http\Controllers\Api\V1\HiringJobController::class, 'store'])
                    ->name('hiring-jobs.store');
                Route::put('hiring-jobs/{jobPosting}', [\App\Http\Controllers\Api\V1\HiringJobController::class, 'update'])
                    ->name('hiring-jobs.update')
                    ->whereNumber('jobPosting');
                Route::patch('hiring-jobs/{jobPosting}/publish', [\App\Http\Controllers\Api\V1\HiringJobController::class, 'publish'])
                    ->name('hiring-jobs.publish')
                    ->whereNumber('jobPosting');
                Route::patch('hiring-jobs/{jobPosting}/close', [\App\Http\Controllers\Api\V1\HiringJobController::class, 'close'])
                    ->name('hiring-jobs.close')
                    ->whereNumber('jobPosting');

                Route::get('hiring-candidates', [\App\Http\Controllers\Api\V1\HiringCandidateController::class, 'index'])
                    ->name('hiring-candidates.index');
                Route::get('hiring-candidates/{candidate}', [\App\Http\Controllers\Api\V1\HiringCandidateController::class, 'show'])
                    ->name('hiring-candidates.show')
                    ->whereNumber('candidate');
                Route::post('hiring-candidates', [\App\Http\Controllers\Api\V1\HiringCandidateController::class, 'store'])
                    ->name('hiring-candidates.store');
                Route::patch('hiring-candidates/{candidate}/stage', [\App\Http\Controllers\Api\V1\HiringCandidateController::class, 'updateStage'])
                    ->name('hiring-candidates.stage')
                    ->whereNumber('candidate');

                Route::get('hiring-offers', [\App\Http\Controllers\Api\V1\HiringOfferController::class, 'index'])
                    ->name('hiring-offers.index');
                Route::post('hiring-offers', [\App\Http\Controllers\Api\V1\HiringOfferController::class, 'store'])
                    ->name('hiring-offers.store');
                Route::patch('hiring-offers/{hiringOffer}/send', [\App\Http\Controllers\Api\V1\HiringOfferController::class, 'send'])
                    ->name('hiring-offers.send')
                    ->whereNumber('hiringOffer');

                Route::get('hiring-templates', [\App\Http\Controllers\Api\V1\HiringOfferController::class, 'templates'])
                    ->name('hiring-templates.index');
                Route::post('hiring-templates', [\App\Http\Controllers\Api\V1\HiringOfferController::class, 'storeTemplate'])
                    ->name('hiring-templates.store');
                Route::put('hiring-templates/{hiringTemplate}', [\App\Http\Controllers\Api\V1\HiringOfferController::class, 'updateTemplate'])
                    ->name('hiring-templates.update')
                    ->whereNumber('hiringTemplate');
            });

            Route::middleware('company.permission:hiring.interview')->group(function () {
                Route::get('hiring-interviews', [\App\Http\Controllers\Api\V1\HiringInterviewController::class, 'index'])
                    ->name('hiring-interviews.index');
                Route::post('hiring-interviews', [\App\Http\Controllers\Api\V1\HiringInterviewController::class, 'store'])
                    ->name('hiring-interviews.store');
                Route::put('hiring-interviews/{candidateInterview}', [\App\Http\Controllers\Api\V1\HiringInterviewController::class, 'update'])
                    ->name('hiring-interviews.update')
                    ->whereNumber('candidateInterview');
            });

            Route::middleware('company.permission:hiring.careers.publish')->group(function () {
                Route::get('hiring/careers-page', [\App\Http\Controllers\Api\V1\HiringCareersController::class, 'show'])
                    ->name('hiring.careers-page.show');
                Route::post('hiring/careers-page', [\App\Http\Controllers\Api\V1\HiringCareersController::class, 'update'])
                    ->name('hiring.careers-page.update');
            });

            Route::middleware('company.member')->group(function () {
                Route::get('reports/catalog', [\App\Http\Controllers\Api\V1\ReportController::class, 'catalog'])
                    ->name('reports.catalog');
                Route::get('reports/options', [\App\Http\Controllers\Api\V1\ReportController::class, 'options'])
                    ->name('reports.options');
                Route::get('reports/{type}', [\App\Http\Controllers\Api\V1\ReportController::class, 'show'])
                    ->name('reports.show')
                    ->where('type', '[a-z\-]+');
                Route::get('reports/{type}/export', [\App\Http\Controllers\Api\V1\ReportController::class, 'export'])
                    ->name('reports.export')
                    ->where('type', '[a-z\-]+');

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
                Route::post('employees/{employee}/profile/photo', [\App\Http\Controllers\Api\V1\EmployeeProfileController::class, 'storeProfilePhoto'])
                    ->name('employees.profile.photo.store')
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
                Route::get('employees/{employee}/profile/payment-method-proofs/{employeePaymentMethodProof}/download', [\App\Http\Controllers\Api\V1\EmployeeProfileController::class, 'downloadPaymentMethodProof'])
                    ->name('employees.profile.payment-method-proofs.download')
                    ->whereNumber(['employee', 'employeePaymentMethodProof']);
            });
        });
    });
});
