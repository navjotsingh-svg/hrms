@extends('emails.layouts.professional', [
    'emailTitle' => 'Employee account status update',
    'headline' => $newStatus === 'active' ? 'Your account has been reactivated' : 'Your account has been deactivated',
    'subheadline' => $employee->company?->name ?? config('mail.from.name', config('app.name', 'HRMS')),
    'purpose' => $newStatus === 'active'
        ? 'This email confirms that your employee account status in HRMS has been changed to Active. You may sign in to the portal if portal access is enabled for your account.'
        : 'This email confirms that your employee account status in HRMS has been changed to Inactive. Your portal login has been disabled and active sessions have been signed out.',
    'supportNote' => 'If you believe this change was made in error, contact your company HR administrator immediately.',
])

@section('email-body')
    @php
        $companyName = $employee->company?->name ?? 'your company';
        $statusLabel = $newStatus === 'active' ? 'Active' : 'Inactive';
        $changedByName = $changedBy?->name ?? 'HR administrator';
    @endphp

    <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.6;">
        Hello <strong>{{ $employee->full_name }}</strong>,
    </p>

    <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.75; color: #475569;">
        Your employment account at <strong style="color: #0f172a;">{{ $companyName }}</strong> has been updated.
        @if ($newStatus === 'inactive')
            You will no longer be able to sign in to the HRMS portal until your account is reactivated by an administrator.
        @else
            Your account is active again. If portal access is enabled, you may sign in using your work email and password.
        @endif
    </p>

    @include('emails.partials.details-table', [
        'detailsTitle' => 'Status change summary',
        'details' => array_filter([
            'Employee name' => $employee->full_name,
            'Employee code' => $employee->employee_code,
            'Previous status' => ucfirst($previousStatus),
            'New status' => $statusLabel,
            'Updated by' => $changedByName,
            'Portal access' => $newStatus === 'active'
                ? ($employee->user_id ? 'Enabled (if previously granted)' : 'Contact HR if you need portal access')
                : 'Disabled — all sessions signed out',
        ]),
    ])

    @include('emails.partials.next-steps', [
        'nextSteps' => $newStatus === 'active' ? [
            'Sign in to HRMS using your work email if portal access is active.',
            'Contact HR if you cannot access the portal or need a password reset.',
        ] : [
            'Return any company assets still in your possession if applicable.',
            'Contact HR if you have questions about your exit or account status.',
        ],
    ])

    @if ($newStatus === 'active')
        @include('emails.partials.cta-section', [
            'actionUrl' => url('/'),
            'actionLabel' => 'Open HRMS portal',
        ])
    @endif
@endsection
