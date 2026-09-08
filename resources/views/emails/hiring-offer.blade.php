@extends('emails.layouts.professional', [

    'emailTitle' => $subjectLine,

    'brandName' => $companyName,

    'headline' => 'Job offer letter',

    'subheadline' => 'Formal employment offer from ' . $companyName,

    'purpose' => 'This email delivers your official job offer from ' . $companyName . '. It includes the offer summary below, a secure link to review and digitally sign the letter, and a PDF copy attached for your records. Please read all sections carefully before accepting.',

    'supportNote' => 'If you have questions about this offer, reply to this email or contact the hiring team at ' . $companyName . '.',

])



@section('email-body')

    <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.6;">

        Hello <strong>{{ $recipientName }}</strong>,

    </p>



    <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.75; color: #475569;">

        Congratulations! We are pleased to extend an offer of employment for

        <strong style="color: #0f172a;">{{ $offerTitle }}</strong> at {{ $companyName }}.

        This message contains the key terms of your offer. The complete offer letter is attached as a PDF

        and can also be reviewed and signed online using the secure link below.

    </p>



    @if (! empty($offerDetails))

        @include('emails.partials.details-table', [

            'detailsTitle' => 'Offer summary',

            'details' => $offerDetails,

        ])

    @endif



    @php

        $resolvedNextSteps = $nextSteps ?: [

            'Read the attached PDF offer letter in full.',

            'Open the secure review link below to view the offer online.',

            'Digitally sign the offer if you wish to accept, or decline with a reason if you choose not to proceed.',

            'Complete any verification steps (such as OTP confirmation) requested on the offer page.',

        ];

    @endphp



    @include('emails.partials.next-steps', ['nextSteps' => $resolvedNextSteps])



    @include('emails.partials.cta-section', [

        'actionUrl' => $reviewUrl,

        'actionLabel' => 'Review & sign offer online',

    ])



    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">

        <tr>

            <td style="padding: 16px 18px; font-size: 13px; line-height: 1.7; color: #475569;">

                <strong style="color: #0f172a;">Attachment:</strong> A PDF copy of your offer letter

                (<strong>{{ $pdfFilename }}</strong>) is attached to this email for your records.

            </td>

        </tr>

    </table>

@endsection

