<?php

return [
    'categories' => [
        'offer_letter' => 'Offer Letter',
        'onboarding' => 'Onboarding',
        'compliance' => 'Compliance',
        'policy' => 'Policy',
        'appointment' => 'Appointment Letter',
        'experience' => 'Experience / Relieving',
        'other' => 'Other',
    ],

    'statuses' => [
        'draft' => 'Draft',
        'pending_signature' => 'Pending Signature',
        'signed' => 'Signed',
        'declined' => 'Declined',
        'cancelled' => 'Cancelled',
    ],

    'sample_templates' => [
        'offer_letter' => <<<'HTML'
<h2>Offer of Employment</h2>
<p>Date: {today_date}</p>
<p>Dear {employee_name},</p>
<p>We are pleased to offer you the position of <strong>{designation}</strong> at <strong>{company_name}</strong>, reporting to {manager_name}.</p>
<p>Your date of joining will be <strong>{joining_date}</strong> and your compensation will be <strong>{salary}</strong>.</p>
<p>Please review this letter and sign below to confirm your acceptance.</p>
<p>Sincerely,<br>{company_name}<br>{company_address}</p>
HTML,
    ],

    'placeholders' => [
        ['key' => 'employee_name', 'label' => 'Employee full name'],
        ['key' => 'employee_first_name', 'label' => 'Employee first name'],
        ['key' => 'employee_code', 'label' => 'Employee code'],
        ['key' => 'employee_email', 'label' => 'Employee email'],
        ['key' => 'employee_phone', 'label' => 'Employee phone'],
        ['key' => 'designation', 'label' => 'Designation'],
        ['key' => 'department', 'label' => 'Department'],
        ['key' => 'date_of_joining', 'label' => 'Date of joining'],
        ['key' => 'manager_name', 'label' => 'Reporting manager'],
        ['key' => 'company_name', 'label' => 'Company name'],
        ['key' => 'company_legal_name', 'label' => 'Company legal name'],
        ['key' => 'company_address', 'label' => 'Company address'],
        ['key' => 'today_date', 'label' => 'Today\'s date'],
        ['key' => 'salary', 'label' => 'Salary / CTC (custom field)'],
        ['key' => 'joining_date', 'label' => 'Joining date (custom field)'],
    ],
];
