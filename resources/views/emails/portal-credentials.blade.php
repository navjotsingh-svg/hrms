@extends('emails.layouts.professional', [
    'emailTitle' => 'Portal login credentials',
    'headline' => $isRegrant ? 'Portal access restored' : 'Your portal login credentials',
    'subheadline' => $employee->company?->name ?? config('mail.from.name', config('app.name', 'HRMS')),
    'purpose' => $isRegrant
        ? 'This email confirms that your HRMS portal access has been restored or re-issued. A new temporary password has been generated for your account. Your previous password will no longer work.'
        : 'This email contains updated login credentials for your HRMS employee portal. Use the details below to sign in. Any previously issued password may no longer work.',
    'supportNote' => 'Did not expect this email? Contact your company HR administrator immediately.',
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
        @if ($isRegrant)
            Portal access for your account at <strong style="color: #0f172a;">{{ $companyName }}</strong> has been
            re-enabled. For security, a new temporary password has been issued and all existing sessions have been signed out.
        @else
            New login credentials have been issued for your HRMS portal account at
            <strong style="color: #0f172a;">{{ $companyName }}</strong>.
        @endif
        Please use only the password in this email to sign in.
    </p>

    @include('emails.partials.details-table', [
        'detailsTitle' => 'Account details',
        'details' => array_filter([
            'Employee name' => $employee->full_name,
            'Employee code' => $employee->employee_code,
            'Company' => $companyName,
            'Work email' => $employee->email,
            'Portal status' => 'Active',
        ]),
    ])

    @include('emails.partials.details-table', [
        'detailsTitle' => 'New login credentials',
        'details' => [
            'Login URL' => $loginUrl,
            'Email / username' => $employee->email,
            'New temporary password' => $plainPassword,
            'Previous password' => 'No longer valid — do not use',
        ],
    ])

    @include('emails.partials.next-steps', [
        'nextSteps' => [
            'Sign in using the login URL and new password above.',
            'Change your password immediately after logging in.',
            'Do not share these credentials with anyone.',
        ],
    ])

    @include('emails.partials.cta-section', [
        'actionUrl' => $loginUrl,
        'actionLabel' => 'Sign in to HRMS',
    ])

    @include('emails.partials.security-note', [
        'message' => 'Your old password has been invalidated and active sessions were signed out. If you did not request portal access, contact HR immediately.',
    ])
@endsection
