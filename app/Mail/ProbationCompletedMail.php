<?php

namespace App\Mail;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProbationCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Employee $employee) {}

    public function envelope(): Envelope
    {
        $brandName = config('mail.from.name', config('app.name', 'HRMS'));

        return new Envelope(
            subject: "Probation completed – welcome as a confirmed employee at {$brandName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.probation-completed',
        );
    }
}
