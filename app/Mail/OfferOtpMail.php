<?php



namespace App\Mail;



use Illuminate\Bus\Queueable;

use Illuminate\Mail\Mailable;

use Illuminate\Mail\Mailables\Content;

use Illuminate\Mail\Mailables\Envelope;

use Illuminate\Queue\SerializesModels;



class OfferOtpMail extends Mailable

{

    use Queueable, SerializesModels;



    /** @param  array<int, string>  $nextSteps */

    public function __construct(

        public string $recipientName,

        public string $companyName,

        public string $offerTitle,

        public string $otpCode,

        public int $expiresMinutes,

        public array $nextSteps = [],

    ) {}



    public function envelope(): Envelope

    {

        return new Envelope(

            subject: "Verify your offer acceptance — {$this->companyName}",

        );

    }



    public function content(): Content

    {

        return new Content(

            view: 'emails.offer-otp',

        );

    }

}

