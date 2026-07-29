<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Promotion recommendation criteria
    |--------------------------------------------------------------------------
    |
    | Used by the promotion recommendation engine to suggest eligible employees.
    | Adjust thresholds here without code changes.
    |
    */

    'min_tenure_months' => (int) env('PROMOTION_MIN_TENURE_MONTHS', 12),

    'min_performance_rating' => (float) env('PROMOTION_MIN_PERFORMANCE_RATING', 3.5),

    'require_submitted_review' => filter_var(env('PROMOTION_REQUIRE_SUBMITTED_REVIEW', true), FILTER_VALIDATE_BOOL),

    'exclude_active_pip' => filter_var(env('PROMOTION_EXCLUDE_ACTIVE_PIP', true), FILTER_VALIDATE_BOOL),

    'exclude_open_nomination' => filter_var(env('PROMOTION_EXCLUDE_OPEN_NOMINATION', true), FILTER_VALIDATE_BOOL),

    'require_active_employee' => filter_var(env('PROMOTION_REQUIRE_ACTIVE_EMPLOYEE', true), FILTER_VALIDATE_BOOL),

    'require_probation_cleared' => filter_var(env('PROMOTION_REQUIRE_PROBATION_CLEARED', true), FILTER_VALIDATE_BOOL),

    'criteria_labels' => [
        'active_status' => 'Employee is active',
        'minimum_tenure' => 'Minimum tenure completed',
        'probation_cleared' => 'Probation cleared',
        'no_active_pip' => 'No active performance improvement plan',
        'performance_rating' => 'Meets minimum performance rating',
        'review_completed' => 'Latest performance review submitted',
        'no_open_nomination' => 'No pending promotion recommendation',
    ],

];
