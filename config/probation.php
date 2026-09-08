<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Probation completion automation
    |--------------------------------------------------------------------------
    |
    | Daily job confirms employees whose probation_end_date has passed and
    | sends notifications to the employee, their manager, and HR.
    |
    */
    'auto_confirm_on_end_date' => filter_var(env('PROBATION_AUTO_CONFIRM', true), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Upcoming probation end reminders (days before end date)
    |--------------------------------------------------------------------------
    */
    'reminder_days_before' => (int) env('PROBATION_REMINDER_DAYS', 7),
];
