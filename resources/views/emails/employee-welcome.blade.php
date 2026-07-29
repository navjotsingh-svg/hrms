@extends('emails.layouts.professional', [
    'emailTitle' => 'Welcome to ' . config('mail.from.name', config('app.name', 'HRMS')),
    'headline' => 'Welcome to the team',
    'subheadline' => 'Your employee HR portal account is ready',
    'purpose' => 'This email confirms that you have been onboarded as an employee and provides your login credentials for the company HRMS portal. Use this portal to view payslips, apply for leave, mark attendance, submit timesheets, and access company policies.',
    'supportNote' => 'Need help? Contact your company HR administrator or reply to this email.',
])

@section('email-body')
    @php
        $loginUrl = url('/');
        $companyName = $employee->company?->name ?? 'your company';
    @endphp

    <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.6;">
        Hello <strong>{{ $employee->full_name }}</strong>,
    </p>

    <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.75; color: #475569;">
        You have been added as an employee at <strong style="color: #0f172a;">{{ $companyName }}</strong>.
        Your HR portal account is now active. The credentials below let you access self-service HR features,
        including leave requests, attendance records, documents, and performance updates.
    </p>

    @include('emails.partials.details-table', [
        'detailsTitle' => 'Employee profile',
        'details' => array_filter([
            'Full name' => $employee->full_name,
            'Employee code' => $employee->employee_code,
            'Work email' => $employee->email,
            'Designation' => $employee->designation,
            'Department' => $employee->department?->name,
            'Employment type' => $employee->employment_type,
            'Joining date' => $employee->joining_date?->format('d M Y'),
            'Assigned role' => $employee->role?->name ?? 'Employee',
            'Company' => $companyName,
        ]),
    ])

    @include('emails.partials.details-table', [
        'detailsTitle' => 'Your login credentials',
        'details' => [
            'Login URL' => $loginUrl,
            'Email / username' => $employee->email,
            'Temporary password' => $plainPassword,
            'Sign-in method' => 'Use the email and password above on the login page.',
        ],
    ])

    @include('emails.partials.next-steps', [
        'nextSteps' => [
            'Sign in using the login URL and credentials above.',
            'Change your password immediately after your first login.',
            'Complete your employee profile and upload any required documents.',
            'Review company policies, your leave balance, and attendance settings.',
        ],
    ])

    @include('emails.partials.cta-section', [
        'actionUrl' => $loginUrl,
        'actionLabel' => 'Sign in to your HR portal',
    ])

    @include('emails.partials.security-note')
@endsection
