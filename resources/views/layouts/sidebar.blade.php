@php
    $user = Auth::user();

    $homeKeys = ['home', 'home.dashboard', 'home.moments'];
    $peopleKeys = ['people', 'employees'];
    $attendanceKeys = [
        'attendance', 'attendance.holidays', 'attendance.team', 'attendance.today', 'attendance.regularize',
        'masters.weekly_off', 'masters.portal_start',
    ];
    $leaveKeys = [
        'leave.management', 'leave.calendar', 'leave.apply', 'leave.balances',
        'masters.leave_types', 'masters.leave_balances',
    ];
    $payrollKeys = ['payroll.manage', 'payroll.payslips', 'payroll.settings'];
    $performanceKeys = [
        'performance', 'performance.review_cycles', 'performance.feedback_forms',
        'performance.question_bank', 'performance.goals', 'performance.kpi', 'performance.pip',
    ];
    $hiringKeys = [
        'hiring', 'hiring.requisitions', 'hiring.jobs', 'hiring.candidates',
        'hiring.offers', 'hiring.templates', 'hiring.interviews', 'hiring.careers',
    ];
    $documentsKeys = ['masters.documents', 'masters.assets'];
    $projectsKeys = ['projects', 'timesheets'];
    $analyticsKeys = [
        'analytics.leave', 'analytics.leave_balances', 'analytics.attendance',
        'analytics.people', 'analytics.expense', 'analytics.hiring', 'analytics.performance',
    ];
    $companyKeys = ['masters.departments', 'masters.shifts', 'masters.roles', 'activity_logs'];

    $isHomeOpen = request()->routeIs('web.home.*', 'web.dashboard');
    $isPeopleOpen = request()->routeIs('web.people.*') || request()->routeIs('web.employees.*');
    $isAttendanceOpen = request()->routeIs('web.attendance.*', 'web.masters.attendance.weekly-off.*', 'web.masters.attendance.portal-start.*');
    $isLeaveOpen = request()->routeIs('web.leave.*', 'web.masters.leave-types.*', 'web.masters.attendance.holidays.*');
    $isPayrollOpen = request()->routeIs('web.payroll.*');
    $isPerformanceOpen = request()->routeIs('web.performance.*');
    $isHiringOpen = request()->routeIs('web.hiring.*');
    $isDocumentsOpen = request()->routeIs('web.masters.documents.*', 'web.masters.assets.*');
    $isProjectsOpen = request()->routeIs('web.projects.*', 'web.timesheets.*');
    $isAnalyticsOpen = request()->routeIs('web.analytics.*');
    $analyticsActiveSection = request()->route('section');
    if (request()->routeIs('web.analytics.leave-balances')) {
        $analyticsActiveSection = 'leave';
    } elseif (request()->routeIs('web.analytics.report')) {
        $reportKey = (string) request()->route('reportKey');
        $analyticsActiveSection = match (true) {
            str_starts_with($reportKey, 'leave-') => 'leave',
            str_starts_with($reportKey, 'attendance-'), str_starts_with($reportKey, 'regularization-') => 'attendance',
            str_starts_with($reportKey, 'employee-') => 'people',
            str_starts_with($reportKey, 'expense-') => 'expense',
            str_starts_with($reportKey, 'candidate-') => 'hiring',
            str_starts_with($reportKey, 'review-') => 'performance',
            default => $analyticsActiveSection,
        };
    }
    $isCompanyOpen = request()->routeIs(
        'web.masters.departments.*',
        'web.masters.shifts.*',
        'web.masters.roles.*',
        'web.activity-logs.*'
    );
@endphp
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header d-lg-none">
        @include('layouts.partials.logo')
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close" id="sidebarClose"></button>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column sidebar-menu">
            @if ($user->company_id && ! $user->isSuperAdmin())
                @if ($user->canSeeMenuSection($homeKeys))
                    @component('layouts.partials.sidebar-group', [
                        'id' => 'sidebarHomeMenu',
                        'label' => 'Home',
                        'icon' => 'home',
                        'open' => $isHomeOpen,
                    ])
                        @if ($user->canSeeMenu('home'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.home.index'),
                                'label' => 'Home',
                                'icon' => 'home',
                                'active' => request()->routeIs('web.home.index', 'web.dashboard'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('home.dashboard'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.home.dashboard'),
                                'label' => 'Dashboard',
                                'icon' => 'dashboard',
                                'active' => request()->routeIs('web.home.dashboard'),
                                'badge' => 'new',
                            ])
                        @endif
                        @if ($user->canSeeMenu('home.moments'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.home.moments'),
                                'label' => 'Moments',
                                'icon' => 'moments',
                                'active' => request()->routeIs('web.home.moments'),
                                'badge' => null,
                                'badgeId' => 'sidebarMomentsBadge',
                            ])
                        @endif
                    @endcomponent
                @endif

                @if ($user->canSeeMenuSection($peopleKeys))
                    @component('layouts.partials.sidebar-group', [
                        'id' => 'sidebarPeopleMenu',
                        'label' => 'People',
                        'icon' => 'people',
                        'open' => $isPeopleOpen,
                    ])
                        @if ($user->canSeeMenu('people'))
                            <li class="nav-item">
                                <a
                                    class="nav-link {{ request()->routeIs('web.people.*') && ! request()->has('tab') ? 'active' : '' }}"
                                    href="{{ route('web.people.index') }}"
                                    id="sidebarPeopleSummaryLink"
                                >
                                    <span class="sidebar-icon">@include('layouts.partials.sidebar-icon', ['name' => 'users'])</span>
                                    <span class="sidebar-link-label">Summary</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link sidebar-people-link" href="{{ route('web.people.index') }}#org-chart" id="sidebarPeopleOrgChartLink">
                                    <span class="sidebar-icon">@include('layouts.partials.sidebar-icon', ['name' => 'org-chart'])</span>
                                    <span class="sidebar-link-label">Org Chart</span>
                                </a>
                            </li>
                        @endif
                        @if ($user->canSeeMenu('employees'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.employees.index'),
                                'label' => 'Employees',
                                'icon' => 'employees',
                                'active' => request()->routeIs('web.employees.*'),
                            ])
                        @endif
                    @endcomponent
                @endif

                @if ($user->canSeeMenuSection($attendanceKeys))
                    @component('layouts.partials.sidebar-group', [
                        'id' => 'sidebarAttendanceMenu',
                        'label' => 'Attendance',
                        'icon' => 'attendance',
                        'open' => $isAttendanceOpen,
                    ])
                        @if ($user->canSeeMenu('attendance'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.attendance.index'),
                                'label' => 'Attendance',
                                'icon' => 'calendar',
                                'active' => request()->routeIs('web.attendance.index'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('attendance.holidays'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.attendance.holidays'),
                                'label' => 'Holidays',
                                'icon' => 'holiday',
                                'active' => request()->routeIs('web.attendance.holidays', 'web.masters.attendance.holidays.*'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('attendance.team'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.attendance.overview'),
                                'label' => 'Team Attendance',
                                'icon' => 'team',
                                'active' => request()->routeIs('web.attendance.overview'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('attendance.today'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.attendance.today'),
                                'label' => "Today's Attendance",
                                'icon' => 'clock',
                                'active' => request()->routeIs('web.attendance.today'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('attendance.regularize'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.attendance.regularize.index'),
                                'label' => 'Regularization',
                                'icon' => 'edit',
                                'active' => request()->routeIs('web.attendance.regularize.*'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('masters.weekly_off'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.masters.attendance.weekly-off.index'),
                                'label' => 'Weekly Off',
                                'icon' => 'calendar',
                                'active' => request()->routeIs('web.masters.attendance.weekly-off.*'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('masters.portal_start'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.masters.attendance.portal-start.index'),
                                'label' => 'Start Portal Day',
                                'icon' => 'clock',
                                'active' => request()->routeIs('web.masters.attendance.portal-start.*'),
                            ])
                        @endif
                    @endcomponent
                @endif

                @if ($user->canSeeMenuSection($leaveKeys))
                    @component('layouts.partials.sidebar-group', [
                        'id' => 'sidebarLeaveMenu',
                        'label' => 'Leave',
                        'icon' => 'leave',
                        'open' => $isLeaveOpen,
                    ])
                        @if ($user->canSeeMenu('leave.apply'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.leave.apply'),
                                'label' => 'Apply',
                                'icon' => 'apply',
                                'active' => request()->routeIs('web.leave.apply'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('leave.management'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.leave.index'),
                                'label' => 'Requests',
                                'icon' => 'requests',
                                'active' => request()->routeIs('web.leave.index', 'web.leave.show'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('leave.calendar'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.leave.calendar'),
                                'label' => 'Calendar',
                                'icon' => 'calendar',
                                'active' => request()->routeIs('web.leave.calendar'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('leave.balances'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.leave.balances'),
                                'label' => 'Balances',
                                'icon' => 'balance',
                                'active' => request()->routeIs('web.leave.balances'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('masters.leave_types'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.masters.leave-types.index'),
                                'label' => 'Leave Types',
                                'icon' => 'documents',
                                'active' => request()->routeIs('web.masters.leave-types.*'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('masters.leave_balances'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.leave.manage-balances'),
                                'label' => 'Manage Balances',
                                'icon' => 'balance',
                                'active' => request()->routeIs('web.leave.manage-balances'),
                            ])
                        @endif
                    @endcomponent
                @endif

                @if ($user->canSeeMenu('requests'))
                    @include('layouts.partials.sidebar-link', [
                        'href' => route('web.requests.index'),
                        'label' => 'Requests',
                        'icon' => 'requests',
                        'active' => request()->routeIs('web.requests.*'),
                    ])
                @endif

                @if ($user->canSeeMenuSection($payrollKeys))
                    @component('layouts.partials.sidebar-group', [
                        'id' => 'sidebarPayrollMenu',
                        'label' => 'Payroll',
                        'icon' => 'payroll',
                        'open' => $isPayrollOpen,
                    ])
                        @if ($user->canSeeMenu('payroll.manage'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.payroll.index'),
                                'label' => 'Payroll',
                                'icon' => 'payroll',
                                'active' => request()->routeIs('web.payroll.index'),
                            ])
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.payroll.settings'),
                                'label' => 'Payroll Settings',
                                'icon' => 'payroll',
                                'active' => request()->routeIs('web.payroll.settings'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('payroll.payslips'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.payroll.my-payslips'),
                                'label' => 'My Payslips',
                                'icon' => 'payslip',
                                'active' => request()->routeIs('web.payroll.my-payslips'),
                            ])
                        @endif
                    @endcomponent
                @endif

                @if ($user->canSeeMenuSection($performanceKeys))
                    @component('layouts.partials.sidebar-group', [
                        'id' => 'sidebarPerformanceMenu',
                        'label' => 'Performance & OKRs',
                        'icon' => 'performance',
                        'open' => $isPerformanceOpen,
                    ])
                        @if ($user->canSeeMenu('performance'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.performance.overview'),
                                'label' => 'Overview',
                                'icon' => 'documents',
                                'active' => request()->routeIs('web.performance.overview'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('performance.review_cycles'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.performance.review-cycles'),
                                'label' => 'Review Cycles',
                                'icon' => 'documents',
                                'active' => request()->routeIs('web.performance.review-cycles'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('performance.feedback_forms'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.performance.feedback-forms'),
                                'label' => 'Feedback Forms',
                                'icon' => 'edit',
                                'active' => request()->routeIs('web.performance.feedback-forms'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('performance.question_bank'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.performance.question-bank'),
                                'label' => 'Question Bank',
                                'icon' => 'documents',
                                'active' => request()->routeIs('web.performance.question-bank'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('performance.goals'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.performance.goals'),
                                'label' => 'Goals and OKRs',
                                'icon' => 'target',
                                'active' => request()->routeIs('web.performance.goals'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('performance.kpi'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.performance.kpi'),
                                'label' => 'KPI',
                                'icon' => 'performance',
                                'active' => request()->routeIs('web.performance.kpi'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('performance.pip'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.performance.pip'),
                                'label' => 'PIP',
                                'icon' => 'performance',
                                'active' => request()->routeIs('web.performance.pip'),
                            ])
                        @endif
                    @endcomponent
                @endif

                @if ($user->canSeeMenuSection($hiringKeys))
                    @component('layouts.partials.sidebar-group', [
                        'id' => 'sidebarHiringMenu',
                        'label' => 'Hiring',
                        'icon' => 'hiring',
                        'open' => $isHiringOpen,
                    ])
                        @if ($user->canSeeMenu('hiring.careers'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.hiring.careers'),
                                'label' => 'Careers Page',
                                'icon' => 'globe',
                                'active' => request()->routeIs('web.hiring.careers'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('hiring.jobs'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.hiring.jobs'),
                                'label' => 'Jobs',
                                'icon' => 'briefcase',
                                'active' => request()->routeIs('web.hiring.jobs'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('hiring.candidates'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.hiring.candidates'),
                                'label' => 'Candidates',
                                'icon' => 'employees',
                                'active' => request()->routeIs('web.hiring.candidates'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('hiring.requisitions'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.hiring.requisitions'),
                                'label' => 'Requisitions',
                                'icon' => 'requests',
                                'active' => request()->routeIs('web.hiring.requisitions'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('hiring'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.hiring.overview'),
                                'label' => 'Overview',
                                'icon' => 'documents',
                                'active' => request()->routeIs('web.hiring.overview'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('hiring.offers'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.hiring.offers'),
                                'label' => 'Offers',
                                'icon' => 'employees',
                                'active' => request()->routeIs('web.hiring.offers'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('hiring.templates'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.hiring.templates'),
                                'label' => 'Templates',
                                'icon' => 'documents',
                                'active' => request()->routeIs('web.hiring.templates'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('hiring.interviews'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.hiring.interviews'),
                                'label' => 'Interviews',
                                'icon' => 'calendar',
                                'active' => request()->routeIs('web.hiring.interviews'),
                            ])
                        @endif
                    @endcomponent
                @endif

                @if ($user->canSeeMenuSection($documentsKeys))
                    @component('layouts.partials.sidebar-group', [
                        'id' => 'sidebarDocumentsMenu',
                        'label' => 'Documents',
                        'icon' => 'documents',
                        'open' => $isDocumentsOpen,
                    ])
                        @if ($user->canSeeMenu('masters.documents'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.masters.documents.index'),
                                'label' => 'Document Types',
                                'icon' => 'documents',
                                'active' => request()->routeIs('web.masters.documents.*'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('masters.assets'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.masters.assets.index'),
                                'label' => 'Asset Types',
                                'icon' => 'assets',
                                'active' => request()->routeIs('web.masters.assets.*'),
                            ])
                        @endif
                    @endcomponent
                @endif

                @if ($user->canSeeMenuSection($projectsKeys))
                    @component('layouts.partials.sidebar-group', [
                        'id' => 'sidebarProjectsMenu',
                        'label' => 'Projects & Time',
                        'icon' => 'projects',
                        'open' => $isProjectsOpen,
                    ])
                        @if ($user->canSeeMenu('projects'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.projects.index'),
                                'label' => 'Projects',
                                'icon' => 'projects',
                                'active' => request()->routeIs('web.projects.*'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('timesheets'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.timesheets.index'),
                                'label' => 'Timesheets',
                                'icon' => 'timesheet',
                                'active' => request()->routeIs('web.timesheets.*'),
                            ])
                        @endif
                    @endcomponent
                @endif

                @if ($user->canSeeMenu('expenses'))
                    @include('layouts.partials.sidebar-link', [
                        'href' => route('web.expenses.index'),
                        'label' => 'Expenses',
                        'icon' => 'expense',
                        'active' => request()->routeIs('web.expenses.*'),
                    ])
                @endif

                @if ($user->canSeeMenuSection($analyticsKeys))
                    @component('layouts.partials.sidebar-group', [
                        'id' => 'sidebarAnalyticsMenu',
                        'label' => 'Analytics',
                        'icon' => 'analytics',
                        'open' => $isAnalyticsOpen,
                    ])
                        @if ($user->canSeeMenu('analytics.leave') || $user->canSeeMenu('analytics.leave_balances'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.analytics.section', ['section' => 'leave']),
                                'label' => 'Leave',
                                'icon' => 'leave',
                                'active' => $analyticsActiveSection === 'leave',
                            ])
                        @endif
                        @if ($user->canSeeMenu('analytics.attendance'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.analytics.section', ['section' => 'attendance']),
                                'label' => 'Attendance',
                                'icon' => 'attendance',
                                'active' => $analyticsActiveSection === 'attendance',
                            ])
                        @endif
                        @if ($user->canSeeMenu('analytics.people'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.analytics.section', ['section' => 'people']),
                                'label' => 'People',
                                'icon' => 'people',
                                'active' => $analyticsActiveSection === 'people',
                            ])
                        @endif
                        @if ($user->canSeeMenu('analytics.expense'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.analytics.section', ['section' => 'expense']),
                                'label' => 'Expense',
                                'icon' => 'expense',
                                'active' => $analyticsActiveSection === 'expense',
                            ])
                        @endif
                        @if ($user->canSeeMenu('analytics.hiring'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.analytics.section', ['section' => 'hiring']),
                                'label' => 'Hiring',
                                'icon' => 'hiring',
                                'active' => $analyticsActiveSection === 'hiring',
                            ])
                        @endif
                        @if ($user->canSeeMenu('analytics.performance'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.analytics.section', ['section' => 'performance']),
                                'label' => 'Performance',
                                'icon' => 'performance',
                                'active' => $analyticsActiveSection === 'performance',
                            ])
                        @endif
                    @endcomponent
                @endif

                @if ($user->canSeeMenu('reports'))
                    @include('layouts.partials.sidebar-link', [
                        'href' => route('web.reports.index'),
                        'label' => 'Reports & Export',
                        'icon' => 'reports',
                        'active' => request()->routeIs('web.reports.*'),
                    ])
                @endif

                @if ($user->canSeeMenuSection($companyKeys))
                    @component('layouts.partials.sidebar-group', [
                        'id' => 'sidebarCompanyMenu',
                        'label' => 'Company',
                        'icon' => 'company',
                        'open' => $isCompanyOpen,
                    ])
                        @if ($user->canSeeMenu('masters.departments'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.masters.departments.index'),
                                'label' => 'Departments',
                                'icon' => 'building',
                                'active' => request()->routeIs('web.masters.departments.*'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('masters.shifts'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.masters.shifts.index'),
                                'label' => 'Shifts',
                                'icon' => 'shift',
                                'active' => request()->routeIs('web.masters.shifts.*'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('masters.roles'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.masters.roles.index'),
                                'label' => 'Manage Roles',
                                'icon' => 'roles',
                                'active' => request()->routeIs('web.masters.roles.*'),
                            ])
                        @endif
                        @if ($user->canSeeMenu('activity_logs'))
                            @include('layouts.partials.sidebar-link', [
                                'href' => route('web.activity-logs.index'),
                                'label' => 'Activity Logs',
                                'icon' => 'logs',
                                'active' => request()->routeIs('web.activity-logs.*'),
                            ])
                        @endif
                    @endcomponent
                @endif
            @endif

            @if ($user->isSuperAdmin())
                @include('layouts.partials.sidebar-link', [
                    'href' => route('web.dashboard'),
                    'label' => 'Dashboard',
                    'icon' => 'dashboard',
                    'active' => request()->routeIs('web.dashboard'),
                ])
                @include('layouts.partials.sidebar-link', [
                    'href' => route('web.companies.index'),
                    'label' => 'Companies',
                    'icon' => 'companies',
                    'active' => request()->routeIs('web.companies.*'),
                ])
            @endif
        </ul>
    </nav>
</aside>
