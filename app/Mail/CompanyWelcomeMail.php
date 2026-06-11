<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company,
        public string $plainPassword,
    ) {}

    public function envelope(): Envelope
    {
        $brandName = config('mail.from.name', config('app.name', 'HRMS'));

        return new Envelope(
            subject: "Welcome to {$brandName} – Your Login Credentials",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.company-welcome',
        );
    }
}
