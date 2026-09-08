<?php



namespace App\Mail;



use Illuminate\Bus\Queueable;

use Illuminate\Mail\Mailable;

use Illuminate\Mail\Mailables\Content;

use Illuminate\Mail\Mailables\Envelope;

use Illuminate\Queue\SerializesModels;



class WorkflowActionMail extends Mailable

{

    use Queueable, SerializesModels;



    /** @param  array<string, string>  $details */

    /** @param  array<int, string>  $nextSteps */

    public function __construct(

        public string $recipientName,

        public string $subjectLine,

        public string $intro,

        public array $details,

        public string $actionUrl,

        public string $actionLabel = 'Review request',

        public ?string $headline = null,

        public ?string $purpose = null,

        public ?string $summary = null,

        public array $nextSteps = [],

    ) {}



    public function envelope(): Envelope

    {

        return new Envelope(

            subject: $this->subjectLine,

        );

    }



    public function content(): Content

    {

        return new Content(

            view: 'emails.workflow-action',

        );

    }

}

