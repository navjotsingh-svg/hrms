<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Analytics sections and reports (Asanify-style catalog)
    |--------------------------------------------------------------------------
    |
    | access: User model method that must return true to view the report.
    | dedicated_route: when set, the report card links to a custom page instead
    |                  of the generic analytics report runner.
    |
    */

    'sections' => [
        [
            'key' => 'leave',
            'label' => 'Leave',
            'menu_key' => 'analytics.leave',
            'reports' => [
                [
                    'key' => 'leave-balances',
                    'name' => 'Leave Balances',
                    'description' => 'Leave balances by employee and policy for a period.',
                    'access' => 'canViewLeaveAnalytics',
                    'dedicated_route' => 'web.analytics.leave-balances',
                    'filters' => ['from_date', 'to_date', 'employee_status', 'employment_type', 'policy_status', 'assignment_status', 'department_id', 'leave_type_id', 'employee_id'],
                    'export' => 'csv',
                ],
            ],
        ],
        [
            'key' => 'attendance',
            'label' => 'Attendance',
            'menu_key' => 'analytics.attendance',
            'reports' => [
                [
                    'key' => 'attendance-daily-status',
                    'name' => 'Daily Attendance Status',
                    'description' => 'Attendance status by day for a period.',
                    'access' => 'canViewAllAttendance',
                    'filters' => ['from_date', 'to_date', 'status', 'employment_type', 'department_id', 'employee_id'],
                    'export' => 'csv',
                ],
                [
                    'key' => 'attendance-today-status',
                    'name' => "Today's Status",
                    'description' => "Today's attendance status summary.",
                    'access' => 'canViewAllAttendance',
                    'filters' => ['status', 'employment_type', 'department_id', 'employee_id'],
                    'export' => 'csv',
                ],
                [
                    'key' => 'attendance-summary',
                    'name' => 'Attendance Summary',
                    'description' => 'Attendance summary for a period.',
                    'access' => 'canViewAllAttendance',
                    'filters' => ['from_date', 'to_date', 'status', 'employment_type', 'department_id', 'employee_id'],
                    'export' => 'csv',
                ],
                [
                    'key' => 'attendance-clocks-hours',
                    'name' => 'Daily Clocks and Hours',
                    'description' => 'Attendance clock data for a period.',
                    'access' => 'canViewAllAttendance',
                    'filters' => ['from_date', 'to_date', 'status', 'employment_type', 'department_id', 'employee_id'],
                    'export' => 'csv',
                ],
                [
                    'key' => 'regularization-summary',
                    'name' => 'Regularization Requests Summary',
                    'description' => 'Regularization requests summary for a period.',
                    'access' => 'canViewAllAttendance',
                    'filters' => ['from_date', 'to_date', 'status', 'department_id', 'employee_id', 'employment_type'],
                    'export' => 'csv',
                ],
                [
                    'key' => 'regularization-details',
                    'name' => 'Regularization Request Details',
                    'description' => 'Regularization details for a period.',
                    'access' => 'canViewAllAttendance',
                    'filters' => ['from_date', 'to_date', 'status', 'employee_id', 'employment_type'],
                    'export' => 'excel',
                ],
            ],
        ],
        [
            'key' => 'people',
            'label' => 'People',
            'menu_key' => 'analytics.people',
            'reports' => [
                [
                    'key' => 'employee-master',
                    'name' => 'Employee Master',
                    'description' => 'Comprehensive employee core information.',
                    'access' => 'canViewEmployees',
                    'filters' => ['status', 'employment_type', 'department_id', 'employee_id'],
                    'export' => 'csv',
                ],
            ],
        ],
        [
            'key' => 'expense',
            'label' => 'Expense',
            'menu_key' => 'analytics.expense',
            'reports' => [
                [
                    'key' => 'expense-summary',
                    'name' => 'Expense Summary',
                    'description' => 'Declared and approved amounts per employee for a given period.',
                    'access' => 'canViewAllExpenses',
                    'filters' => ['from_date', 'to_date', 'date_type', 'status', 'employment_type', 'department_id', 'employee_id'],
                    'export' => 'csv',
                ],
            ],
        ],
        [
            'key' => 'hiring',
            'label' => 'Hiring',
            'menu_key' => 'analytics.hiring',
            'reports' => [
                [
                    'key' => 'candidate-summary',
                    'name' => 'Candidate Summary',
                    'description' => 'Detailed candidate applications information.',
                    'access' => 'canManageHiring',
                    'filters' => ['candidate_status', 'department_id', 'job_id'],
                    'export' => 'csv',
                ],
            ],
        ],
        [
            'key' => 'performance',
            'label' => 'Performance',
            'menu_key' => 'analytics.performance',
            'reports' => [
                [
                    'key' => 'review-cycle-summary',
                    'name' => 'Review Cycle Summary',
                    'description' => 'Detailed review cycle information by reviewee and reviewer.',
                    'access' => 'canManagePerformance',
                    'filters' => ['cycle_id', 'status'],
                    'export' => 'excel',
                ],
            ],
        ],
    ],
];
