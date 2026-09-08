@extends('emails.layouts.professional', [

    'emailTitle' => 'Performance review reminder',

    'headline' => 'Performance review reminder',

    'subheadline' => $cycleName,

    'purpose' => 'This email is a reminder that you have a pending performance review assignment in the "' . $cycleName . '" cycle. The review must be completed in the HRMS portal so feedback can be recorded and the cycle can progress on schedule.',

    'supportNote' => 'Need an extension or have questions? Contact your HR administrator.',

])



@section('email-body')

    <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.6;">

        Hello <strong>{{ $recipientName }}</strong>,

    </p>



    <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.75; color: #475569;">

        This is a reminder to complete your performance review for

        <strong style="color: #0f172a;">{{ $revieweeName }}</strong> as part of the

        <strong>{{ $cycleName }}</strong> review cycle. Your feedback is important for employee development,

        compensation decisions, and cycle completion.

    </p>



    @if (! empty($reviewDetails))

        @include('emails.partials.details-table', [

            'detailsTitle' => 'Review assignment',

            'details' => $reviewDetails,

        ])

    @endif



    @php

        $resolvedNextSteps = $nextSteps ?: [

            'Sign in to the HRMS performance module using the link below.',

            'Open the assigned review for ' . $revieweeName . '.',

            'Answer all required questions thoughtfully and submit the review.',

            'Contact HR if you need clarification on rating criteria or deadlines.',

        ];

    @endphp



    @include('emails.partials.next-steps', ['nextSteps' => $resolvedNextSteps])



    @include('emails.partials.cta-section', [

        'actionUrl' => $actionUrl,

        'actionLabel' => 'Complete performance review',

    ])

@endsection

