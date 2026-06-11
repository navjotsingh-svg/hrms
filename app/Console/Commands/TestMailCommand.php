<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMailCommand extends Command
{
    protected $signature = 'mail:test {email : Recipient email address}';

    protected $description = 'Send a test email using the configured mailer';

    public function handle(): int
    {
        $email = $this->argument('email');
        $mailer = config('mail.default');

        $this->info("Sending test email via [{$mailer}] to {$email}...");

        try {
            Mail::raw(
                'This is a test email from '.config('app.name').'. If you received this, mail is configured correctly.',
                function ($message) use ($email) {
                    $message->to($email)->subject('Test email from '.config('mail.from.name', config('app.name')));
                }
            );

            $this->info('Test email sent successfully.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Failed to send test email: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
