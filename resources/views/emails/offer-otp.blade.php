@extends('emails.layouts.professional', [

    'emailTitle' => 'Verify offer acceptance',

    'brandName' => $companyName,

    'headline' => 'Verify your offer acceptance',

    'subheadline' => 'One-time security code from ' . $companyName,

    'purpose' => 'This email provides a one-time verification code (OTP) to confirm your acceptance of the job offer for "' . $offerTitle . '" at ' . $companyName . '. Enter this code on the offer review page to complete the verification step before your acceptance is recorded.',

    'supportNote' => 'Did not request this code? Do not share it with anyone and contact ' . $companyName . ' immediately.',

])



@section('email-body')

    <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.6;">

        Hello <strong>{{ $recipientName }}</strong>,

    </p>



    <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.75; color: #475569;">

        You are accepting the offer for <strong style="color: #0f172a;">{{ $offerTitle }}</strong>.

        To protect your identity and ensure only you can confirm acceptance, please enter the verification code below

        on the offer review page. This code is valid for a limited time and can be used only once.

    </p>



    @include('emails.partials.details-table', [

        'detailsTitle' => 'Verification details',

        'details' => [

            'Offer' => $offerTitle,

            'Company' => $companyName,

            'Verification code' => $otpCode,

            'Expires in' => $expiresMinutes . ' minutes',

            'Code type' => 'One-time password (OTP)',

        ],

    ])



    <p style="margin: 0 0 24px; font-size: 32px; font-weight: 700; letter-spacing: 0.35em; text-align: center; color: #1e3a5f;">{{ $otpCode }}</p>



    @php

        $resolvedNextSteps = $nextSteps ?: [

            'Return to the offer review page in your browser.',

            'Enter the verification code exactly as shown above before it expires.',

            'Complete the digital signature step to finalize your acceptance.',

            'Do not share this code with anyone — ' . $companyName . ' will never ask for it by phone or chat.',

        ];

    @endphp



    @include('emails.partials.next-steps', ['nextSteps' => $resolvedNextSteps])



    @include('emails.partials.security-note', [

        'message' => 'This code expires in ' . $expiresMinutes . ' minutes. Never share your OTP. If you did not initiate offer acceptance, ignore this email and notify the company.',

    ])

@endsection

