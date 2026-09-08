@extends('emails.layouts.professional', [
    'emailTitle' => 'Welcome to ' . config('mail.from.name', config('app.name', 'HRMS')),
    'headline' => 'Welcome aboard',
    'subheadline' => 'Your company administrator account is ready',
    'purpose' => 'This email confirms that your company has been registered on the HRMS platform and provides your initial login credentials. Use these details to sign in, configure your organization, and begin managing employees, attendance, leave, payroll, and hiring.',
    'supportNote' => 'Need help getting started? Reply to this email and our support team will assist you.',
])

@section('email-body')
    @php
        $recipientName = $company->contact_person_name ?? $company->name;
        $loginUrl = url('/');
    @endphp

    <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.6;">
        Hello <strong>{{ $recipientName }}</strong>,
    </p>

    <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.75; color: #475569;">
        <strong style="color: #0f172a;">{{ $company->name }}</strong> has been successfully registered on
        {{ config('mail.from.name', config('app.name', 'HRMS')) }}. As the company administrator, you can now set up
        departments, invite employees, configure attendance policies, and manage day-to-day HR operations from a single portal.
    </p>

    @include('emails.partials.details-table', [
        'detailsTitle' => 'Company profile',
        'details' => array_filter([
            'Company name' => $company->name,
            'Legal name' => $company->legal_name,
            'Registered email' => $company->email,
            'Phone' => $company->phone,
            'Industry' => $company->industry,
            'Location' => collect([$company->city, $company->state, $company->country])->filter()->implode(', '),
            'Account role' => 'Company Administrator',
        ]),
    ])

    @include('emails.partials.details-table', [
        'detailsTitle' => 'Your login credentials',
        'details' => [
            'Login URL' => $loginUrl,
            'Email / username' => $company->email,
            'Temporary password' => $plainPassword,
            'Sign-in method' => 'Use the email and password above on the login page.',
        ],
    ])

    @include('emails.partials.next-steps', [
        'nextSteps' => [
            'Open the login URL and sign in with the credentials provided above.',
            'Change your password immediately after the first successful login.',
            'Complete your company profile, add departments, and invite your first employees.',
            'Configure attendance, leave, and payroll settings for your organization.',
        ],
    ])

    @include('emails.partials.cta-section', [
        'actionUrl' => $loginUrl,
        'actionLabel' => 'Sign in to your account',
    ])

    @include('emails.partials.security-note')
@endsection
