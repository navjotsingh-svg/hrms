<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\Web\AuthSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('auth.login'))->name('login');

Route::post('/auth/session', [AuthSessionController::class, 'store']);
Route::post('/auth/session/logout', [AuthSessionController::class, 'destroy']);

Route::redirect('/login', '/');
Route::redirect('/register', '/');

Route::middleware('web.auth')->name('web.')->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    Route::get('/profile', fn () => view('profile.edit'))->name('profile');
    Route::get('/profile/change-password', fn () => view('profile.change-password'))->name('profile.change-password');

    Route::middleware('super_admin')->prefix('companies')->name('companies.')->group(function () {
        Route::get('/', [CompanyController::class, 'index'])->name('index');
        Route::get('/create', [CompanyController::class, 'create'])->name('create');
        Route::get('/{company}', [CompanyController::class, 'show'])
            ->whereNumber('company')
            ->name('show');
        Route::get('/{company}/edit', [CompanyController::class, 'edit'])
            ->whereNumber('company')
            ->name('edit');
    });

    Route::middleware('company.member')->prefix('masters')->name('masters.')->group(function () {
        Route::middleware('company.permission:departments.view')->group(function () {
            Route::get('/departments', [\App\Http\Controllers\DepartmentController::class, 'index'])->name('departments.index');
        });
        Route::middleware('company.permission:departments.manage')->group(function () {
            Route::get('/departments/create', [\App\Http\Controllers\DepartmentController::class, 'create'])->name('departments.create');
            Route::get('/departments/{department}/edit', [\App\Http\Controllers\DepartmentController::class, 'edit'])
                ->whereNumber('department')
                ->name('departments.edit');
        });

        Route::middleware('company.permission:documents.view')->group(function () {
            Route::get('/documents', [\App\Http\Controllers\DocumentTypeController::class, 'index'])->name('documents.index');
        });
        Route::middleware('company.permission:documents.manage')->group(function () {
            Route::get('/documents/create', [\App\Http\Controllers\DocumentTypeController::class, 'create'])->name('documents.create');
            Route::get('/documents/{documentType}/edit', [\App\Http\Controllers\DocumentTypeController::class, 'edit'])
                ->whereNumber('documentType')
                ->name('documents.edit');
        });

        Route::middleware('company.permission:assets.view')->group(function () {
            Route::get('/assets', [\App\Http\Controllers\AssetTypeController::class, 'index'])->name('assets.index');
        });
        Route::middleware('company.permission:assets.manage')->group(function () {
            Route::get('/assets/create', [\App\Http\Controllers\AssetTypeController::class, 'create'])->name('assets.create');
            Route::get('/assets/{assetType}/edit', [\App\Http\Controllers\AssetTypeController::class, 'edit'])
                ->whereNumber('assetType')
                ->name('assets.edit');
        });

        Route::middleware('company.permission:shifts.view')->group(function () {
            Route::get('/shifts', [\App\Http\Controllers\ShiftController::class, 'index'])->name('shifts.index');
        });
        Route::middleware('company.permission:shifts.manage')->group(function () {
            Route::get('/shifts/create', [\App\Http\Controllers\ShiftController::class, 'create'])->name('shifts.create');
            Route::get('/shifts/{shift}/edit', [\App\Http\Controllers\ShiftController::class, 'edit'])
                ->whereNumber('shift')
                ->name('shifts.edit');
        });

        Route::middleware('company.admin')->group(function () {
            Route::get('/roles', [\App\Http\Controllers\RoleController::class, 'index'])->name('roles.index');
            Route::get('/roles/{role}', [\App\Http\Controllers\RoleController::class, 'show'])
                ->whereNumber('role')
                ->name('roles.show');
        });
    });

    Route::middleware(['company.member', 'company.permission:attendance.view'])->group(function () {
        Route::get('/attendance', [\App\Http\Controllers\AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/today', [\App\Http\Controllers\AttendanceController::class, 'today'])->name('attendance.today');
    });

    Route::middleware(['company.member', 'company.permission:attendance.view'])->group(function () {
        Route::get('/attendance/regularize', [\App\Http\Controllers\AttendanceRegularizationController::class, 'index'])
            ->name('attendance.regularize.index');
    });

    Route::middleware('company.member')->prefix('leave')->name('leave.')->group(function () {
        Route::middleware('company.permission:leave.apply')->group(function () {
            Route::get('/apply', [\App\Http\Controllers\LeaveController::class, 'create'])->name('apply');
            Route::get('/balances', [\App\Http\Controllers\LeaveController::class, 'balances'])->name('balances');
        });
        Route::get('/', [\App\Http\Controllers\LeaveController::class, 'index'])->name('index');
        Route::get('/{leave}', [\App\Http\Controllers\LeaveController::class, 'show'])
            ->whereNumber('leave')
            ->name('show');
    });

    Route::middleware(['company.member', 'company.permission:leave.manage'])->group(function () {
        Route::get('/leave/manage-balances', [\App\Http\Controllers\LeaveController::class, 'manageBalances'])->name('leave.manage-balances');
    });

    Route::middleware(['company.member', 'company.permission:leave.manage'])->prefix('masters/leave-types')->name('masters.leave-types.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LeaveTypeController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\LeaveTypeController::class, 'create'])->name('create');
        Route::get('/{leaveType}/edit', [\App\Http\Controllers\LeaveTypeController::class, 'edit'])
            ->whereNumber('leaveType')
            ->name('edit');
    });

    Route::middleware(['company.member', 'company.permission:attendance.manage'])->prefix('masters/attendance')->name('masters.attendance.')->group(function () {
        Route::get('/holidays', [\App\Http\Controllers\HolidayController::class, 'index'])->name('holidays.index');
        Route::get('/holidays/create', [\App\Http\Controllers\HolidayController::class, 'create'])->name('holidays.create');
        Route::get('/holidays/{holiday}/edit', [\App\Http\Controllers\HolidayController::class, 'edit'])
            ->whereNumber('holiday')
            ->name('holidays.edit');
        Route::get('/weekly-off', [\App\Http\Controllers\WeeklyOffController::class, 'index'])->name('weekly-off.index');
        Route::get('/portal-start', [\App\Http\Controllers\PortalStartController::class, 'index'])->name('portal-start.index');
    });

    Route::middleware(['company.member', 'company.permission:payroll.manage'])->group(function () {
        Route::get('/payroll', [\App\Http\Controllers\PayrollController::class, 'index'])->name('payroll.index');
    });

    Route::middleware(['company.member', 'company.permission:payroll.view'])->group(function () {
        Route::get('/my-payslips', [\App\Http\Controllers\PayrollController::class, 'myPayslips'])->name('payroll.my-payslips');
    });

    Route::middleware('company.member')->prefix('people')->name('people.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PeopleController::class, 'index'])->name('index');
    });

    Route::middleware('company.member')->prefix('employees')->name('employees.')->group(function () {
        Route::middleware('company.permission:employees.view')->group(function () {
            Route::get('/', [\App\Http\Controllers\EmployeeController::class, 'index'])->name('index');
            Route::get('/{employee}', [\App\Http\Controllers\EmployeeController::class, 'show'])
                ->whereNumber('employee')
                ->name('show');
            Route::get('/{employee}/profile/edit', [\App\Http\Controllers\EmployeeController::class, 'profileEdit'])
                ->whereNumber('employee')
                ->name('profile.edit');
        });
        Route::middleware('company.permission:employees.manage')->group(function () {
            Route::get('/create', [\App\Http\Controllers\EmployeeController::class, 'create'])->name('create');
            Route::get('/{employee}/edit', [\App\Http\Controllers\EmployeeController::class, 'edit'])
                ->whereNumber('employee')
                ->name('edit');
        });
    });
});

require __DIR__.'/auth.php';
