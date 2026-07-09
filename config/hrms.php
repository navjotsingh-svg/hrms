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
    | HR Assistant
    |--------------------------------------------------------------------------
    */

    'assistant' => [
        'enabled' => filter_var(env('HRMS_ASSISTANT_ENABLED', true), FILTER_VALIDATE_BOOL),
        'use_ai' => filter_var(env('HRMS_ASSISTANT_USE_AI', true), FILTER_VALIDATE_BOOL),
        'openai_api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'max_tokens' => (int) env('HRMS_ASSISTANT_MAX_TOKENS', 700),
        'rate_limit_per_hour' => (int) env('HRMS_ASSISTANT_RATE_LIMIT', 30),
    ],

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
            'group' => 'Employee Experience',
            'modules' => [
                [
                    'key' => 'experience',
                    'label' => 'Employee Experience',
                    'description' => 'Social wall, polls, announcements, and peer praise',
                    'operations' => [
                        ['type' => 'social_wall_view', 'label' => 'View Social Wall', 'slug' => 'home.moments.view', 'requires' => ['home.view']],
                        ['type' => 'social_wall_post', 'label' => 'Post on Social Wall', 'slug' => 'home.moments.post', 'requires' => ['home.moments.view']],
                        ['type' => 'social_wall_comment', 'label' => 'Comment on Social Wall', 'slug' => 'home.moments.comment', 'requires' => ['home.moments.view']],
                    ],
                ],
                [
                    'key' => 'helpdesk',
                    'label' => 'Helpdesk',
                    'description' => 'Employee support tickets and HR resolutions',
                    'operations' => [
                        ['type' => 'apply', 'label' => 'Raise Tickets', 'slug' => 'helpdesk.apply'],
                        ['type' => 'manage', 'label' => 'Manage Tickets', 'slug' => 'helpdesk.manage', 'requires' => ['helpdesk.apply']],
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
                    'description' => 'Employee document type master and issued letters',
                    'operations' => [
                        ['type' => 'view', 'label' => 'View', 'slug' => 'documents.view'],
                        ['type' => 'manage', 'label' => 'Add / Edit', 'slug' => 'documents.manage', 'requires' => ['documents.view']],
                        ['type' => 'sign', 'label' => 'Sign Letters', 'slug' => 'documents.sign'],
                    ],
                ],
                [
                    'key' => 'assets',
                    'label' => 'Asset Types',
                    'description' => 'Company asset type master',
                    'operations' => [
                        ['type' => 'view', 'label' => 'View', 'slug' => 'assets.view'],
                        ['type' => 'manage', 'label' => 'Add / Edit', 'slug' => 'assets.manage', 'requires' => ['assets.view']],
                        ['type' => 'apply', 'label' => 'Request', 'slug' => 'assets.apply'],
                        ['type' => 'approve', 'label' => 'Approve Requests', 'slug' => 'assets.approve', 'requires' => ['assets.apply']],
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
                [
                    'key' => 'wfh',
                    'label' => 'Work From Home',
                    'description' => 'WFH requests and approvals (separate from leave)',
                    'operations' => [
                        ['type' => 'apply', 'label' => 'Apply', 'slug' => 'wfh.apply'],
                        ['type' => 'approve', 'label' => 'Approve', 'slug' => 'wfh.approve', 'requires' => ['wfh.apply']],
                    ],
                ],
                [
                    'key' => 'offboarding',
                    'label' => 'Offboarding',
                    'description' => 'Resignation, clearance, asset return, and F&F settlement',
                    'operations' => [
                        ['type' => 'apply', 'label' => 'Submit Resignation', 'slug' => 'offboarding.apply'],
                        ['type' => 'approve', 'label' => 'Approve Resignation', 'slug' => 'offboarding.approve', 'requires' => ['offboarding.apply']],
                        ['type' => 'manage', 'label' => 'Manage Exit Cases', 'slug' => 'offboarding.manage'],
                        ['type' => 'clearance', 'label' => 'Review Clearance', 'slug' => 'clearance.review'],
                        ['type' => 'fnf', 'label' => 'F&F Settlement', 'slug' => 'offboarding.fnf.manage'],
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

        'experience.social_wall' => ['permissions' => ['home.moments.view', 'home.moments.post', 'home.moments.comment']],
        'experience.polls' => ['permissions' => ['home.moments.view', 'home.moments.post', 'home.dashboard.view', 'home.dashboard.manage']],
        'experience.public_praise' => ['permissions' => ['home.moments.view', 'home.moments.post', 'performance.participate']],
        'experience.helpdesk' => ['permissions' => ['helpdesk.apply', 'helpdesk.manage']],
        'experience.assistant' => ['rule' => 'company_member', 'feature' => 'assistant.enabled'],

        'core_hr.documents_letters' => ['permissions' => ['documents.view', 'documents.manage', 'documents.sign']],
        'org_chart' => ['permissions' => ['employees.view', 'employees.manage']],

        'masters.departments' => ['permissions' => ['departments.view', 'departments.manage']],
        'masters.documents' => ['permissions' => ['documents.view', 'documents.manage']],
        'masters.assets' => ['permissions' => ['assets.view', 'assets.manage']],
        'masters.shifts' => ['permissions' => ['shifts.view', 'shifts.manage']],
        'masters.holidays' => ['permissions' => ['attendance.manage']],
        'masters.weekly_off' => ['permissions' => ['attendance.manage']],
        'masters.portal_start' => ['permissions' => ['attendance.manage']],
        'masters.leave_types' => ['permissions' => ['leave.manage']],
        'masters.leave_balances' => ['permissions' => ['leave.manage']],
        'masters.roles' => ['rule' => 'company_admin'],

        'people' => ['permissions' => ['employees.view', 'employees.manage'], 'feature' => 'people_menu_enabled'],

        'requests' => ['permissions' => [
            'leave.apply', 'leave.approve', 'wfh.apply', 'wfh.approve',
            'assets.apply', 'assets.approve',
            'offboarding.apply', 'offboarding.approve',
            'helpdesk.apply',
            'documents.sign',
            'attendance.regularize', 'attendance.approve',
            'expenses.apply', 'expenses.approve', 'hiring.requisition.create', 'hiring.requisition.approve',
        ]],
        'employees' => ['permissions' => ['employees.view', 'employees.manage']],
        'attendance' => ['rule' => 'attendance_calendar'],
        'attendance.holidays' => ['permissions' => ['attendance.view'], 'rule' => 'attendance_calendar'],
        'attendance.team' => ['permissions' => ['attendance.view_team', 'attendance.manage'], 'rule' => 'attendance_team'],
        'attendance.today' => ['permissions' => ['attendance.manage']],
        'attendance.regularize' => ['permissions' => ['attendance.regularize', 'attendance.approve', 'attendance.manage']],
        'wfh.apply' => ['permissions' => ['wfh.apply']],
        'wfh.management' => ['permissions' => ['wfh.apply', 'wfh.approve']],
        'assets.apply' => ['permissions' => ['assets.apply']],
        'assets.management' => ['permissions' => ['assets.apply', 'assets.approve']],
        'offboarding.apply' => ['permissions' => ['offboarding.apply']],
        'offboarding.management' => ['permissions' => ['offboarding.apply', 'offboarding.approve', 'offboarding.manage', 'clearance.review', 'offboarding.fnf.manage']],
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
        'performance.praise' => ['permissions' => ['performance.participate', 'performance.manage', 'performance.review']],
        'performance.continuous_feedback' => ['permissions' => ['performance.participate', 'performance.manage', 'performance.review']],
        'performance.one_on_one' => ['permissions' => ['performance.participate', 'performance.review', 'performance.manage']],
        'performance.reviews' => ['permissions' => ['performance.participate', 'performance.review', 'performance.manage']],
        'performance.calibration' => ['permissions' => ['performance.manage']],
        'performance.promotions' => ['permissions' => ['performance.manage', 'employees.manage']],
        'performance.pip' => ['permissions' => ['pip.manage', 'performance.participate']],
        'performance.insights' => ['permissions' => ['performance.manage', 'performance.participate', 'performance.review']],
        'performance.compensation' => ['permissions' => ['performance.manage']],
        'performance.skills' => ['permissions' => ['performance.participate', 'performance.manage', 'performance.review']],
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
