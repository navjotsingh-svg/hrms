<?php



namespace App\Mail;



use Illuminate\Bus\Queueable;

use Illuminate\Mail\Mailable;

use Illuminate\Mail\Mailables\Attachment;

use Illuminate\Mail\Mailables\Content;

use Illuminate\Mail\Mailables\Envelope;

use Illuminate\Queue\SerializesModels;



class HiringOfferMail extends Mailable

{

    use Queueable, SerializesModels;



    /** @param  array<string, string|null>  $offerDetails */

    /** @param  array<int, string>  $nextSteps */

    public function __construct(

        public string $recipientName,

        public string $subjectLine,

        public string $companyName,

        public string $offerTitle,

        public string $reviewUrl,

        public string $pdfBinary,

        public string $pdfFilename,

        public array $offerDetails = [],

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

            view: 'emails.hiring-offer',

        );

    }



    /** @return array<int, Attachment> */

    public function attachments(): array

    {

        return [

            Attachment::fromData(fn () => $this->pdfBinary, $this->pdfFilename)

                ->withMime('application/pdf'),

        ];

    }

}

