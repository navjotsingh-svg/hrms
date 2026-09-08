<?php

return [
    'template_types' => [
        'offer' => 'Offer Letter',
        'email' => 'Email',
        'other' => 'Other',
    ],

    'sample_templates' => [
        'offer' => <<<'HTML'
<h2>Offer of Employment</h2>
<p>Date: {today_date}</p>
<p>Dear {candidate_name},</p>
<p>We are pleased to offer you the position of <strong>{job_title}</strong> at <strong>{company_name}</strong>, reporting to {manager_name}.</p>
<p>Your date of joining will be <strong>{joining_date}</strong> and your compensation will be <strong>{salary}</strong>.</p>
<p>Employment type: {employment_type}<br>Department: {department}</p>
<p>Please review this offer and confirm your acceptance by {offer_expiry_date}.</p>
<p>Sincerely,<br>{company_name}<br>{company_address}</p>
HTML,
    ],

    'placeholders' => [
        ['key' => 'candidate_name', 'label' => 'Candidate full name', 'group' => 'Candidate'],
        ['key' => 'candidate_first_name', 'label' => 'Candidate first name', 'group' => 'Candidate'],
        ['key' => 'candidate_email', 'label' => 'Candidate email', 'group' => 'Candidate'],
        ['key' => 'candidate_phone', 'label' => 'Candidate phone', 'group' => 'Candidate'],
        ['key' => 'job_title', 'label' => 'Job title', 'group' => 'Job'],
        ['key' => 'department', 'label' => 'Department', 'group' => 'Job'],
        ['key' => 'employment_type', 'label' => 'Employment type', 'group' => 'Job'],
        ['key' => 'work_location', 'label' => 'Work location', 'group' => 'Job'],
        ['key' => 'salary', 'label' => 'Salary / CTC', 'group' => 'Offer'],
        ['key' => 'joining_date', 'label' => 'Joining date', 'group' => 'Offer'],
        ['key' => 'offer_expiry_date', 'label' => 'Offer expiry date', 'group' => 'Offer'],
        ['key' => 'manager_name', 'label' => 'Reporting manager', 'group' => 'Offer'],
        ['key' => 'company_name', 'label' => 'Company name', 'group' => 'Company'],
        ['key' => 'company_legal_name', 'label' => 'Company legal name', 'group' => 'Company'],
        ['key' => 'company_address', 'label' => 'Company address', 'group' => 'Company'],
        ['key' => 'today_date', 'label' => "Today's date", 'group' => 'Company'],
    ],

    'offer_link' => [
        'expires_days' => (int) env('HRMS_OFFER_LINK_EXPIRES_DAYS', 30),
    ],

    'offer_otp' => [
        'expires_minutes' => (int) env('HRMS_OFFER_OTP_EXPIRES_MINUTES', 10),
        'max_attempts' => (int) env('HRMS_OFFER_OTP_MAX_ATTEMPTS', 5),
        'accept_window_minutes' => (int) env('HRMS_OFFER_OTP_ACCEPT_WINDOW_MINUTES', 15),
    ],
];
