<?php



namespace App\Mail;



use Illuminate\Bus\Queueable;

use Illuminate\Mail\Mailable;

use Illuminate\Mail\Mailables\Content;

use Illuminate\Mail\Mailables\Envelope;

use Illuminate\Queue\SerializesModels;



class PerformanceReviewReminderMail extends Mailable

{

    use Queueable, SerializesModels;



    /** @param  array<string, string|null>  $reviewDetails */

    /** @param  array<int, string>  $nextSteps */

    public function __construct(

        public string $recipientName,

        public string $cycleName,

        public string $revieweeName,

        public string $actionUrl,

        public array $reviewDetails = [],

        public array $nextSteps = [],

    ) {}



    public function envelope(): Envelope

    {

        return new Envelope(

            subject: "Performance review reminder — {$this->cycleName}",

        );

    }



    public function content(): Content

    {

        return new Content(

            view: 'emails.performance-review-reminder',

        );

    }

}

