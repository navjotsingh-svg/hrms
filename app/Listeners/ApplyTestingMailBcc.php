<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;

class ApplyTestingMailBcc
{
    public function handle(MessageSending $event): void
    {
        if (! config('mail.bcc.enabled')) {
            return;
        }

        $addresses = config('mail.bcc.addresses', []);

        if ($addresses === []) {
            return;
        }

        $message = $event->message;
        $existing = [];

        foreach ($message->getBcc() as $address) {
            $existing[] = strtolower($address->getAddress());
        }

        foreach ($addresses as $address) {
            $normalized = strtolower(trim($address));

            if ($normalized === '' || in_array($normalized, $existing, true)) {
                continue;
            }

            $message->addBcc($normalized);
            $existing[] = $normalized;
        }
    }
}
