<?php

namespace App\Mail;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public string $plainPassword,
        public bool $isRegrant = true,
    ) {}

    public function envelope(): Envelope
    {
        $brandName = config('mail.from.name', config('app.name', 'HRMS'));

        return new Envelope(
            subject: $this->isRegrant
                ? "Your {$brandName} portal password has been reset"
                : "Your {$brandName} portal login credentials",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal-credentials',
        );
    }
}
