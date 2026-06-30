<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company Branding
    |--------------------------------------------------------------------------
    |
    | Set COMPANY_LOGO in .env to a public path, e.g. images/company-logo.png
    | Place the file in public/images/. If empty, the default HRMS logo is shown.
    |
    */

    'company_name' => env('COMPANY_NAME', 'HRMS'),

    'company_logo' => env('COMPANY_LOGO'),

    /*
    |--------------------------------------------------------------------------
    | People Module
    |--------------------------------------------------------------------------
    |
    | Set HRMS_PEOPLE_MENU_ENABLED=true in .env when ready to show People in sidebar.
    |
    */

    'people_menu_enabled' => env('HRMS_PEOPLE_MENU_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Attendance
    |--------------------------------------------------------------------------
    */

    'attendance' => [
        'face_match_threshold' => (int) env('ATTENDANCE_FACE_MATCH_THRESHOLD', 80),
        'require_face_match' => filter_var(
            env('ATTENDANCE_REQUIRE_FACE_MATCH', env('APP_ENV', 'production') === 'local' ? false : true),
            FILTER_VALIDATE_BOOL
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Role permission catalog (Company Admin matrix UI)
    |--------------------------------------------------------------------------
    |
    | Maps sidebar modules to permission slugs. "manage" = add/edit/delete;
    | "view" = read-only access. Additional operation keys are domain-specific.
    |
    */

    'permission_catalog' => [
        [
            'group' => 'Home',
            'modules' => [
                [
                    'key' => 'home',
                    'label' => 'Home',
                    'description' => 'Home landing, dashboard widgets, and company moments',
                    'operations' => [
                        ['type' => 'view', 'label' => 'View Home', 'slug' => 'home.view'],
                        ['type' => 'dashboard_view', 'label' => 'View Dashboard', 'slug' => 'home.dashboard.view', 'requires' => ['home.view']],
                        ['type' => 'dashboard_manage', 'label' => 'Manage Dashboard', 'slug' => 'home.dashboard.manage', 'requires' => ['home.dashboard.view']],
                        ['type' => 'moments_view', 'label' => 'View Moments', 'slug' => 'home.moments.view', 'requires' => ['home.view']],
                        ['type' => 'moments_post', 'label' => 'Post Moments', 'slug' => 'home.moments.post', 'requires' => ['home.moments.view']],
                        ['type' => 'moments_comment', 'label' => 'Comment on Moments', 'slug' => 'home.moments.comment', 'requires' => ['home.moments.view']],
                    ],
                ],
            ],
        ],
        [
            'group' => 'People & HR',
            'modules' => [
                [
                    'key' => 'employees',
                    'label' => 'Employees',
                    'description' => 'Employee directory, profiles, and onboarding',
                    'operations' => [
                        ['type' => 'view', 'label' => 'View', 'slug' => 'employees.view'],
                        ['type' => 'manage', 'label' => 'Add / Edit', 'slug' => 'employees.manage', 'requires' => ['employees.view']],
                        ['type' => 'assign_admin', 'label' => 'Assign Company Admin', 'slug' => 'employees.assign_admin', 'requires' => ['employees.manage']],
                    ],
                ],
                [
                    'key' => 'departments',
                    'label' => 'Departments',
                    'description' => 'Department master data',
                    'operations' => [
                        ['type' => 'view', 'label' => 'View', 'slug' => 'departments.view'],
                        ['type' => 'manage', 'label' => 'Add / Edit', 'slug' => 'departments.manage', 'requires' => ['departments.view']],
                    ],
                ],
                [
                    'key' => 'documents',
                    'label' => 'Document Types',
                    'description' => 'Employee document type master',
                    'operations' => [
                        ['type' => 'view', 'label' => 'View', 'slug' => 'documents.view'],
                        ['type' => 'manage', 'label' => 'Add / Edit', 'slug' => 'documents.manage', 'requires' => ['documents.view']],
                    ],
                ],
                [
                    'key' => 'assets',
                    'label' => 'Asset Types',
                    'description' => 'Company asset type master',
                    'operations' => [
                        ['type' => 'view', 'label' => 'View', 'slug' => 'assets.view'],
                        ['type' => 'manage', 'label' => 'Add / Edit', 'slug' => 'assets.manage', 'requires' => ['assets.view']],
                    ],
                ],
                [
                    'key' => 'shifts',
                    'label' => 'Shifts',
                    'description' => 'Work shift schedules',
                    'operations' => [
                        ['type' => 'view', 'label' => 'View', 'slug' => 'shifts.view'],
                        ['type' => 'manage', 'label' => 'Add / Edit', 'slug' => 'shifts.manage', 'requires' => ['shifts.view']],
                    ],
                ],
            ],
        ],
        [
            'group' => 'Attendance',
            'modules' => [
                [
                    'key' => 'attendance',
                    'label' => 'Attendance',
                    'description' => 'Attendance calendar, team view, and daily records',
                    'operations' => [
                        ['type' => 'view', 'label' => 'View Own', 'slug' => 'attendance.view'],
                        ['type' => 'view_team', 'label' => 'View Team', 'slug' => 'attendance.view_team', 'requires' => ['attendance.view']],
                        ['type' => 'manage', 'label' => 'Masters / Policies', 'slug' => 'attendance.manage', 'requires' => ['attendance.view']],
                        ['type' => 'regularize', 'label' => 'Regularize', 'slug' => 'attendance.regularize', 'requires' => ['attendance.view']],
                        ['type' => 'approve', 'label' => 'Approve', 'slug' => 'attendance.approve', 'requires' => ['attendance.view']],
                    ],
                ],
            ],
        ],
        [
            'group' => 'Leave',
            'modules' => [
                [
                    'key' => 'leave',
                    'label' => 'Leave Management',
                    'description' => 'Leave requests, types, balances, and calendar',
                    'operations' => [
                        ['type' => 'apply', 'label' => 'Apply', 'slug' => 'leave.apply'],
                        ['type' => 'approve', 'label' => 'Approve', 'slug' => 'leave.approve', 'requires' => ['leave.apply']],
                        ['type' => 'manage', 'label' => 'Configure', 'slug' => 'leave.manage'],
                    ],
                ],
            ],
        ],
        [
            'group' => 'Payroll',
            'modules' => [
                [
                    'key' => 'payroll',
                    'label' => 'Payroll',
                    'description' => 'Payroll runs, salary structures, and payslips',
                    'operations' => [
                        ['type' => 'view', 'label' => 'View', 'slug' => 'payroll.view'],
                        ['type' => 'manage', 'label' => 'Manage', 'slug' => 'payroll.manage', 'requires' => ['payroll.view']],
                    ],
                ],
            ],
        ],
        [
            'group' => 'Projects & Time',
            'modules' => [
                [
                    'key' => 'projects',
                    'label' => 'Projects',
                    'description' => 'Project assignments and team management',
                    'operations' => [
                        ['type' => 'view', 'label' => 'View', 'slug' => 'projects.view'],
                        ['type' => 'manage', 'label' => 'Add / Edit', 'slug' => 'projects.manage', 'requires' => ['projects.view']],
                    ],
                ],
                [
                    'key' => 'timesheets',
                    'label' => 'Timesheets',
                    'description' => 'Daily work hour logging',
                    'operations' => [
                        ['type' => 'submit', 'label' => 'Submit', 'slug' => 'timesheets.submit'],
                    ],
                ],
            ],
        ],
        [
            'group' => 'Expenses',
            'modules' => [
                [
                    'key' => 'expenses',
                    'label' => 'Expenses',
                    'description' => 'Expense claims, approvals, and configuration',
                    'operations' => [
                        ['type' => 'apply', 'label' => 'Apply', 'slug' => 'expenses.apply'],
                        ['type' => 'approve', 'label' => 'Approve', 'slug' => 'expenses.approve', 'requires' => ['expenses.apply']],
                        ['type' => 'manage', 'label' => 'Manage All', 'slug' => 'expenses.manage'],
                    ],
                ],
            ],
        ],
        [
            'group' => 'Performance',
            'modules' => [
                [
                    'key' => 'performance',
                    'label' => 'Performance Reviews',
                    'description' => 'Review cycles, goals, and self/manager reviews',
                    'operations' => [
                        ['type' => 'manage', 'label' => 'Configure', 'slug' => 'performance.manage'],
                        ['type' => 'participate', 'label' => 'Participate', 'slug' => 'performance.participate'],
                        ['type' => 'review', 'label' => 'Review Others', 'slug' => 'performance.review', 'requires' => ['performance.participate']],
                        ['type' => 'pip', 'label' => 'Manage PIPs', 'slug' => 'pip.manage'],
                    ],
                ],
            ],
        ],
        [
            'group' => 'Hiring',
            'modules' => [
                [
                    'key' => 'hiring',
                    'label' => 'Hiring',
                    'description' => 'Job requisitions, interviews, and careers page',
                    'operations' => [
                        ['type' => 'manage', 'label' => 'Configure', 'slug' => 'hiring.manage'],
                        ['type' => 'requisition_create', 'label' => 'Create Requisitions', 'slug' => 'hiring.requisition.create'],
                        ['type' => 'requisition_approve', 'label' => 'Approve Requisitions', 'slug' => 'hiring.requisition.approve'],
                        ['type' => 'interview', 'label' => 'Interview', 'slug' => 'hiring.interview'],
                        ['type' => 'careers', 'label' => 'Publish Careers', 'slug' => 'hiring.careers.publish'],
                    ],
                ],
            ],
        ],
        [
            'group' => 'Reports & Admin',
            'modules' => [
                [
                    'key' => 'reports',
                    'label' => 'Reports',
                    'description' => 'Export HR reports',
                    'operations' => [
                        ['type' => 'export', 'label' => 'Export', 'slug' => 'reports.export'],
                    ],
                ],
                [
                    'key' => 'logs',
                    'label' => 'Activity Logs',
                    'description' => 'Company audit and activity history',
                    'operations' => [
                        ['type' => 'view', 'label' => 'View', 'slug' => 'logs.view'],
                    ],
                ],
                [
                    'key' => 'roles',
                    'label' => 'Role Management',
                    'description' => 'Configure roles and permissions',
                    'operations' => [
                        ['type' => 'view', 'label' => 'View', 'slug' => 'roles.view'],
                        ['type' => 'manage', 'label' => 'Manage', 'slug' => 'roles.manage', 'requires' => ['roles.view']],
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sidebar menu visibility (permission-driven)
    |--------------------------------------------------------------------------
    |
    | Each item is shown when the user has ANY listed permission, unless a
    | special rule applies (see MenuAccessService).
    |
    */

    'sidebar_menu' => [
        'home' => ['permissions' => ['home.view']],
        'home.dashboard' => ['permissions' => ['home.dashboard.view', 'home.dashboard.manage']],
        'home.moments' => ['permissions' => ['home.moments.view', 'home.moments.post', 'home.moments.comment']],

        'masters.departments' => ['permissions' => ['departments.view', 'departments.manage']],
        'masters.documents' => ['permissions' => ['documents.view', 'documents.manage']],
        'masters.assets' => ['permissions' => ['assets.view', 'assets.manage']],
        'masters.shifts' => ['permissions' => ['shifts.view', 'shifts.manage']],
        'masters.holidays' => ['permissions' => ['attendance.manage']],
        'masters.weekly_off' => ['permissions' => ['attendance.manage']],
        'masters.portal_start' => ['permissions' => ['attendance.manage']],
        'masters.leave_types' => ['permissions' => ['leave.manage']],
        'masters.leave_balances' => ['permissions' => ['leave.manage']],
        'masters.roles' => ['permissions' => ['roles.view', 'roles.manage', 'settings.manage']],

        'people' => ['permissions' => ['employees.view', 'employees.manage'], 'feature' => 'people_menu_enabled'],

        'requests' => ['permissions' => [
            'leave.apply', 'leave.approve', 'attendance.regularize', 'attendance.approve',
            'expenses.apply', 'expenses.approve', 'hiring.requisition.create', 'hiring.requisition.approve',
        ]],
        'employees' => ['permissions' => ['employees.view', 'employees.manage']],
        'attendance' => ['rule' => 'attendance_calendar'],
        'attendance.holidays' => ['permissions' => ['attendance.view'], 'rule' => 'attendance_calendar'],
        'attendance.team' => ['permissions' => ['attendance.view_team', 'attendance.manage'], 'rule' => 'attendance_team'],
        'attendance.today' => ['permissions' => ['attendance.manage']],
        'attendance.regularize' => ['permissions' => ['attendance.regularize', 'attendance.approve', 'attendance.manage']],
        'leave.calendar' => ['permissions' => ['leave.manage', 'leave.approve']],
        'leave.apply' => ['permissions' => ['leave.apply']],
        'leave.balances' => ['permissions' => ['leave.apply']],
        'leave.management' => ['permissions' => ['leave.apply', 'leave.approve', 'leave.manage']],
        'timesheets' => ['rule' => 'timesheets_access'],
        'expenses' => ['permissions' => ['expenses.apply', 'expenses.approve', 'expenses.manage']],
        'projects' => ['permissions' => ['projects.view', 'projects.manage']],
        'payroll.manage' => ['permissions' => ['payroll.manage']],
        'payroll.settings' => ['permissions' => ['payroll.manage']],
        'payroll.payslips' => ['permissions' => ['payroll.view']],
        'performance' => ['permissions' => ['performance.manage', 'performance.participate', 'performance.review', 'pip.manage']],
        'performance.review_cycles' => ['permissions' => ['performance.manage']],
        'performance.feedback_forms' => ['permissions' => ['performance.manage']],
        'performance.question_bank' => ['permissions' => ['performance.manage']],
        'performance.goals' => ['permissions' => ['performance.manage', 'performance.participate', 'performance.review']],
        'performance.kpi' => ['permissions' => ['performance.manage']],
        'performance.pip' => ['permissions' => ['pip.manage', 'performance.participate']],
        'hiring' => ['permissions' => [
            'hiring.manage', 'hiring.requisition.create', 'hiring.requisition.approve',
            'hiring.interview', 'hiring.careers.publish',
        ]],
        'hiring.requisitions' => ['permissions' => ['hiring.requisition.create', 'hiring.manage']],
        'hiring.jobs' => ['permissions' => ['hiring.manage']],
        'hiring.candidates' => ['permissions' => ['hiring.manage']],
        'hiring.offers' => ['permissions' => ['hiring.manage']],
        'hiring.templates' => ['permissions' => ['hiring.manage']],
        'hiring.interviews' => ['permissions' => ['hiring.interview', 'hiring.manage']],
        'hiring.careers' => ['permissions' => ['hiring.careers.publish', 'hiring.manage']],
        'analytics.leave_balances' => ['permissions' => ['leave.manage', 'reports.export']],
        'analytics.leave' => ['permissions' => ['leave.manage', 'reports.export']],
        'analytics.attendance' => ['permissions' => ['attendance.manage']],
        'analytics.people' => ['permissions' => ['employees.view', 'employees.manage']],
        'analytics.expense' => ['permissions' => ['expenses.manage']],
        'analytics.hiring' => ['permissions' => ['hiring.manage']],
        'analytics.performance' => ['permissions' => ['performance.manage']],
        'reports' => ['permissions' => ['reports.export']],
        'activity_logs' => ['permissions' => ['logs.view']],
    ],

];
