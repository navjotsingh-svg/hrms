<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\Web\AuthSessionController;
use App\Services\UserLandingService;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('auth.login'))->name('login');

Route::post('/auth/session', [AuthSessionController::class, 'store']);
Route::post('/auth/session/logout', [AuthSessionController::class, 'destroy']);

Route::redirect('/login', '/');
Route::redirect('/register', '/');

    Route::middleware(['web.auth', 'log.activity'])->name('web.')->group(function () {
    Route::get('/dashboard', function (UserLandingService $landing) {
        $user = auth()->user();

        if ($user?->company_id && ! $user->isSuperAdmin()) {
            return redirect()->route($landing->routeNameFor($user));
        }

        return view('dashboard');
    })->name('dashboard');

    Route::middleware('company.member')->prefix('home')->name('home.')->group(function () {
        Route::get('/', [\App\Http\Controllers\HomeController::class, 'index'])
            ->middleware('company.permission:home.view')
            ->name('index');
        Route::get('/dashboard', [\App\Http\Controllers\HomeController::class, 'dashboard'])
            ->middleware('company.permission:home.dashboard.view,home.dashboard.manage')
            ->name('dashboard');
        Route::get('/moments', fn () => redirect()->route('web.employee-experience.social-wall'))
            ->middleware('company.permission:home.moments.view,home.moments.post')
            ->name('moments');
    });

    Route::middleware('company.member')->prefix('employee-experience')->name('employee-experience.')->group(function () {
        Route::get('/social-wall', [\App\Http\Controllers\EmployeeExperienceController::class, 'socialWall'])
            ->middleware('company.permission:home.moments.view,home.moments.post,home.moments.comment')
            ->name('social-wall');
        Route::get('/polls-announcements', [\App\Http\Controllers\EmployeeExperienceController::class, 'pollsAnnouncements'])
            ->middleware('company.permission:home.moments.view,home.dashboard.view,home.dashboard.manage')
            ->name('polls-announcements');
        Route::get('/public-praise', [\App\Http\Controllers\EmployeeExperienceController::class, 'publicPraise'])
            ->middleware('company.permission:home.moments.view,home.moments.post,performance.participate')
            ->name('public-praise');
    });

    Route::middleware('company.member')->prefix('helpdesk')->name('helpdesk.')->group(function () {
        Route::middleware('company.permission:helpdesk.apply')->group(function () {
            Route::get('/create', [\App\Http\Controllers\HelpdeskController::class, 'create'])->name('create');
        });
        Route::middleware('company.permission:helpdesk.apply,helpdesk.manage')->group(function () {
            Route::get('/', [\App\Http\Controllers\HelpdeskController::class, 'index'])->name('index');
            Route::get('/{ticket}', [\App\Http\Controllers\HelpdeskController::class, 'show'])
                ->whereNumber('ticket')
                ->name('show');
        });
    });

    Route::middleware('company.member')->prefix('assistant')->name('assistant.')->group(function () {
        Route::redirect('/', '/home')->name('index');
    });

    Route::middleware('company.member')->prefix('documents-letters')->name('documents-letters.')->group(function () {
        Route::middleware('company.permission:documents.view,documents.manage,documents.sign')->group(function () {
            Route::get('/', [\App\Http\Controllers\DocumentLettersController::class, 'index'])->name('index');
            Route::get('/{letter}', [\App\Http\Controllers\DocumentLettersController::class, 'show'])
                ->whereNumber('letter')
                ->name('show');
        });
    });

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
        Route::get('/attendance/holidays', [\App\Http\Controllers\HolidayController::class, 'index'])->name('attendance.holidays');
        Route::get('/attendance/today', [\App\Http\Controllers\AttendanceController::class, 'today'])->name('attendance.today');
        Route::get('/attendance/overview', [\App\Http\Controllers\AttendanceController::class, 'overview'])->name('attendance.overview');
    });

    Route::middleware(['company.member', 'company.permission:attendance.view'])->group(function () {
        Route::get('/attendance/regularize', [\App\Http\Controllers\AttendanceRegularizationController::class, 'index'])
            ->name('attendance.regularize.index');
    });

    Route::middleware('company.member')->prefix('wfh')->name('wfh.')->group(function () {
        Route::middleware('company.permission:wfh.apply')->group(function () {
            Route::get('/apply', [\App\Http\Controllers\WfhController::class, 'create'])->name('apply');
        });
        Route::middleware('company.permission:wfh.apply,wfh.approve')->group(function () {
            Route::get('/', [\App\Http\Controllers\WfhController::class, 'index'])->name('index');
            Route::get('/{wfh}', [\App\Http\Controllers\WfhController::class, 'show'])
                ->whereNumber('wfh')
                ->name('show');
        });
    });

    Route::middleware('company.member')->prefix('asset-requests')->name('asset-requests.')->group(function () {
        Route::middleware('company.permission:assets.apply')->group(function () {
            Route::get('/apply', [\App\Http\Controllers\AssetRequestController::class, 'create'])->name('apply');
        });
        Route::middleware('company.permission:assets.apply,assets.approve')->group(function () {
            Route::get('/', [\App\Http\Controllers\AssetRequestController::class, 'index'])->name('index');
            Route::get('/{assetRequest}', [\App\Http\Controllers\AssetRequestController::class, 'show'])
                ->whereNumber('assetRequest')
                ->name('show');
        });
    });

    Route::middleware('company.member')->prefix('offboarding')->name('offboarding.')->group(function () {
        Route::middleware('company.permission:offboarding.apply')->group(function () {
            Route::get('/apply', [\App\Http\Controllers\OffboardingController::class, 'apply'])->name('apply');
        });
        Route::middleware('company.permission:offboarding.apply,offboarding.approve,offboarding.manage,clearance.review,offboarding.fnf.manage')->group(function () {
            Route::get('/', [\App\Http\Controllers\OffboardingController::class, 'index'])->name('index');
            Route::get('/cases/{exitCase}', [\App\Http\Controllers\OffboardingController::class, 'show'])
                ->whereNumber('exitCase')
                ->name('show');
        });
    });

    Route::middleware('company.member')->prefix('leave')->name('leave.')->group(function () {
        Route::middleware('company.permission:leave.apply')->group(function () {
            Route::get('/apply', [\App\Http\Controllers\LeaveController::class, 'create'])->name('apply');
            Route::get('/balances', [\App\Http\Controllers\LeaveController::class, 'balances'])->name('balances');
        });
        Route::get('/', [\App\Http\Controllers\LeaveController::class, 'index'])->name('index');
        Route::get('/calendar', [\App\Http\Controllers\LeaveController::class, 'calendar'])->name('calendar');
        Route::middleware('company.permission:leave.manage')->group(function () {
        });
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
        Route::redirect('/holidays', '/attendance/holidays')->name('holidays.index');
        Route::get('/holidays/create', [\App\Http\Controllers\HolidayController::class, 'create'])->name('holidays.create');
        Route::get('/holidays/{holiday}/edit', [\App\Http\Controllers\HolidayController::class, 'edit'])
            ->whereNumber('holiday')
            ->name('holidays.edit');
        Route::get('/weekly-off', [\App\Http\Controllers\WeeklyOffController::class, 'index'])->name('weekly-off.index');
        Route::get('/portal-start', [\App\Http\Controllers\PortalStartController::class, 'index'])->name('portal-start.index');
    });

    Route::middleware('company.member')->prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('index');
        Route::get('/leave-balances', [\App\Http\Controllers\AnalyticsController::class, 'leaveBalances'])->name('leave-balances');
        Route::get('/reports/{reportKey}', [\App\Http\Controllers\AnalyticsController::class, 'report'])
            ->name('report');
        Route::get('/{section}', [\App\Http\Controllers\AnalyticsController::class, 'section'])
            ->where('section', 'leave|attendance|people|expense|hiring|performance')
            ->name('section');
    });

    Route::middleware(['company.member', 'company.permission:payroll.manage'])->group(function () {
        Route::get('/payroll', [\App\Http\Controllers\PayrollController::class, 'index'])->name('payroll.index');
        Route::get('/payroll/settings', [\App\Http\Controllers\PayrollSettingsController::class, 'index'])->name('payroll.settings');
    });

    Route::middleware(['company.member', 'company.permission:payroll.view'])->group(function () {
        Route::get('/my-payslips', [\App\Http\Controllers\PayrollController::class, 'myPayslips'])->name('payroll.my-payslips');
    });

    Route::middleware('company.member')->prefix('people')->name('people.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PeopleController::class, 'index'])->name('index');
    });

    Route::middleware(['company.member', 'company.permission:employees.view,employees.manage'])->group(function () {
        Route::get('/org-chart', [\App\Http\Controllers\OrgChartController::class, 'index'])->name('org-chart.index');
    });

    Route::middleware('company.member')->group(function () {
        Route::get('/requests', [\App\Http\Controllers\RequestHubController::class, 'index'])->name('requests.index');
        Route::get('/requests/{category}/{id}', [\App\Http\Controllers\RequestHubController::class, 'show'])
            ->name('requests.show')
            ->where('category', '[a-z_\-]+');
    });

    Route::middleware(['company.member', 'company.permission:projects.manage'])->group(function () {
        Route::get('/projects', [\App\Http\Controllers\ProjectController::class, 'index'])->name('projects.index');
    });

    Route::middleware('company.member')->group(function () {
        Route::get('/timesheets', [\App\Http\Controllers\TimesheetController::class, 'index'])->name('timesheets.index');
    });

    Route::middleware('company.member')->group(function () {
        Route::get('/expenses', [\App\Http\Controllers\ExpenseController::class, 'index'])->name('expenses.index');
    });

    Route::middleware(['company.member', 'company.permission:performance.participate'])->prefix('performance')->name('performance.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PerformanceController::class, 'overview'])->name('overview');
        Route::get('/praise-recognition', [\App\Http\Controllers\PerformanceController::class, 'praiseRecognition'])->name('praise-recognition');
        Route::get('/continuous-feedback', [\App\Http\Controllers\PerformanceController::class, 'continuousFeedback'])->name('continuous-feedback');
        Route::get('/one-on-one', [\App\Http\Controllers\PerformanceController::class, 'oneOnOne'])->name('one-on-one');
        Route::get('/reviews', [\App\Http\Controllers\PerformanceController::class, 'reviews'])->name('reviews');
        Route::get('/calibration', [\App\Http\Controllers\PerformanceController::class, 'calibration'])->name('calibration');
        Route::get('/promotions', [\App\Http\Controllers\PerformanceController::class, 'promotions'])->name('promotions');
        Route::get('/insights', [\App\Http\Controllers\PerformanceController::class, 'insights'])->name('insights');
        Route::get('/compensation', [\App\Http\Controllers\PerformanceController::class, 'compensation'])->name('compensation');
        Route::get('/skills', [\App\Http\Controllers\PerformanceController::class, 'skills'])->name('skills');
        Route::get('/review-cycles', [\App\Http\Controllers\PerformanceController::class, 'reviewCycles'])->name('review-cycles');
        Route::get('/feedback-forms', [\App\Http\Controllers\PerformanceController::class, 'feedbackForms'])->name('feedback-forms');
        Route::get('/question-bank', [\App\Http\Controllers\PerformanceController::class, 'questionBank'])->name('question-bank');
        Route::get('/goals', [\App\Http\Controllers\PerformanceController::class, 'goals'])->name('goals');
        Route::get('/kpi', [\App\Http\Controllers\PerformanceController::class, 'kpi'])->name('kpi');
        Route::get('/pip', [\App\Http\Controllers\PerformanceController::class, 'pip'])->name('pip');
    });

    Route::middleware(['company.member', 'company.permission:hiring.requisition.create'])->prefix('hiring')->name('hiring.')->group(function () {
        Route::get('/', [\App\Http\Controllers\HiringController::class, 'overview'])->name('overview');
        Route::get('/requisitions', [\App\Http\Controllers\HiringController::class, 'requisitions'])->name('requisitions');
        Route::middleware('company.permission:hiring.manage')->group(function () {
            Route::get('/jobs', [\App\Http\Controllers\HiringController::class, 'jobs'])->name('jobs');
            Route::get('/candidates', [\App\Http\Controllers\HiringController::class, 'candidates'])->name('candidates');
            Route::get('/offers', [\App\Http\Controllers\HiringController::class, 'offers'])->name('offers');
            Route::get('/templates', [\App\Http\Controllers\HiringController::class, 'templates'])->name('templates');
        });
        Route::middleware('company.permission:hiring.interview')->group(function () {
            Route::get('/interviews', [\App\Http\Controllers\HiringController::class, 'interviews'])->name('interviews');
        });
        Route::middleware('company.permission:hiring.careers.publish')->group(function () {
            Route::get('/careers', [\App\Http\Controllers\HiringController::class, 'careers'])->name('careers');
        });
    });

    Route::middleware('company.member')->group(function () {
        Route::get('/reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
    });

    Route::get('/activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-logs.index');

    Route::middleware('company.member')->prefix('employees')->name('employees.')->group(function () {
        Route::middleware('company.permission:employees.view')->group(function () {
            Route::get('/', [\App\Http\Controllers\EmployeeController::class, 'index'])->name('index');
            Route::get('/{employee}', [\App\Http\Controllers\EmployeeController::class, 'show'])
                ->whereNumber('employee')
                ->name('show');
        });
        Route::middleware('company.permission:employees.manage')->group(function () {
            Route::get('/create', [\App\Http\Controllers\EmployeeController::class, 'create'])->name('create');
            Route::get('/bulk-import', [\App\Http\Controllers\EmployeeController::class, 'bulkImport'])->name('bulk-import');
            Route::get('/{employee}/edit', [\App\Http\Controllers\EmployeeController::class, 'edit'])
                ->whereNumber('employee')
                ->name('edit');
            Route::get('/{employee}/profile/edit', [\App\Http\Controllers\EmployeeController::class, 'profileEdit'])
                ->whereNumber('employee')
                ->name('profile.edit');
        });
    });
});

Route::get('/careers/{slug}', [\App\Http\Controllers\PublicCareersController::class, 'show'])->name('careers.show');
Route::post('/careers/{slug}/apply', [\App\Http\Controllers\PublicCareersController::class, 'applyGeneral'])->name('careers.apply-general');
Route::get('/careers/{slug}/jobs/{jobPosting}', [\App\Http\Controllers\PublicCareersController::class, 'job'])->name('careers.job');
Route::post('/careers/{slug}/jobs/{jobPosting}/apply', [\App\Http\Controllers\PublicCareersController::class, 'apply'])->name('careers.apply');

require __DIR__.'/auth.php';
