@extends('emails.layouts.professional', [

    'emailTitle' => $subjectLine,

    'headline' => $headline ?? $subjectLine,

    'subheadline' => 'HR workflow notification',

    'purpose' => $purpose ?? 'This email is an official notification from your organization\'s HRMS platform. It contains the full details of a request or decision so you can review and take action without logging in first.',

    'supportNote' => 'Questions about this request? Contact your HR team or company administrator.',

])



@section('email-body')

    <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.6;">

        Hello <strong>{{ $recipientName }}</strong>,

    </p>



    <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.75; color: #475569;">

        {{ $intro }}

    </p>



    @if (! empty($summary))

        <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.75; color: #334155;">

            {{ $summary }}

        </p>

    @endif



    @include('emails.partials.details-table', [

        'details' => $details,

        'detailsTitle' => 'Request summary',

    ])



    @php

        $resolvedNextSteps = $nextSteps ?: [

            'Review every field in the summary above to understand the full context.',

            'Sign in to the HRMS portal using the link below if you need to approve, reject, or respond.',

            'Add remarks where required so the employee and audit trail stay complete.',

        ];

    @endphp



    @include('emails.partials.next-steps', ['nextSteps' => $resolvedNextSteps])



    @include('emails.partials.cta-section', [

        'actionUrl' => $actionUrl,

        'actionLabel' => $actionLabel,

    ])

@endsection

