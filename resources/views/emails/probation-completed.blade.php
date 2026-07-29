@extends('emails.layouts.professional', [
    'emailTitle' => 'Probation completed',
    'headline' => 'Congratulations — your probation is complete',
    'subheadline' => $employee->company?->name ?? config('mail.from.name', config('app.name', 'HRMS')),
    'purpose' => 'This email confirms that your probation period has ended and your employment status in HRMS has been updated to Confirmed. You are now a fully confirmed employee of the organization.',
    'supportNote' => 'If you have questions about your role, benefits, or next steps, contact your manager or HR team.',
])

@section('email-body')
    @php
        $companyName = $employee->company?->name ?? 'your company';
        $endDate = $employee->probation_end_date?->format('d M Y') ?? '—';
    @endphp

    <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.6;">
        Hello <strong>{{ $employee->full_name }}</strong>,
    </p>

    <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.75; color: #475569;">
        Your probation period at <strong style="color: #0f172a;">{{ $companyName }}</strong> has been completed successfully.
        Your probation status in HRMS is now <strong>Confirmed</strong>, reflecting your transition to regular employment.
    </p>

    @include('emails.partials.details-table', [
        'detailsTitle' => 'Probation summary',
        'details' => array_filter([
            'Employee name' => $employee->full_name,
            'Employee code' => $employee->employee_code,
            'Probation end date' => $endDate,
            'Probation status' => 'Confirmed',
            'Designation' => $employee->designation,
        ]),
    ])

    @include('emails.partials.next-steps', [
        'nextSteps' => [
            'Review your employee profile in HRMS for updated employment details.',
            'Speak with your manager about goals and expectations post-probation.',
            'Contact HR if you need clarification on benefits or policies now available to confirmed employees.',
        ],
    ])

    @include('emails.partials.cta-section', [
        'actionUrl' => route('web.profile'),
        'actionLabel' => 'View my profile',
    ])
@endsection
