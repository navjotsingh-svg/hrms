<?php

namespace App\Mail;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public string $plainPassword,
    ) {}

    public function envelope(): Envelope
    {
        $brandName = config('mail.from.name', config('app.name', 'HRMS'));

        return new Envelope(
            subject: "Welcome to {$brandName} – Your Employee Login Credentials",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.employee-welcome',
        );
    }
}
