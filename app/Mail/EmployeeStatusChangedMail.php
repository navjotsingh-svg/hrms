<?php

namespace App\Mail;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public string $previousStatus,
        public string $newStatus,
        public ?User $changedBy = null,
    ) {}

    public function envelope(): Envelope
    {
        $brandName = config('mail.from.name', config('app.name', 'HRMS'));
        $label = $this->newStatus === 'active' ? 'reactivated' : 'deactivated';

        return new Envelope(
            subject: "Your {$brandName} employee account has been {$label}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.employee-status-changed',
        );
    }
}
