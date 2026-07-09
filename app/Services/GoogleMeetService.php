<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class GoogleMeetService
{
    public function isValidMeetLink(?string $url): bool
    {
        return $this->isValidMeetingLink($url);
    }

    public function isValidMeetingLink(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        try {
            $this->normalizeMeetingLink($url);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function normalizeMeetLink(string $url): string
    {
        return $this->normalizeMeetingLink($url);
    }

    public function normalizeMeetingLink(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            throw ValidationException::withMessages([
                'meeting_link' => ['Enter a meeting link.'],
            ]);
        }

        if (! preg_match('#^https://#i', $url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'meeting_link' => ['Enter a valid https meeting link (Zoom, Microsoft Teams, Google Meet, etc.).'],
            ]);
        }

        return $url;
    }

    public function calendarUrl(
        string $title,
        Carbon $start,
        int $durationMinutes,
        ?string $details = null,
        ?string $meetLink = null,
        array $attendeeEmails = [],
    ): string {
        $end = $start->copy()->addMinutes(max(1, $durationMinutes));
        $body = trim(collect([
            $details,
            $meetLink ? "Join meeting: {$meetLink}" : null,
        ])->filter()->implode("\n\n"));

        $params = [
            'action' => 'TEMPLATE',
            'text' => $title,
            'dates' => $start->copy()->utc()->format('Ymd\THis\Z').'/'.$end->utc()->format('Ymd\THis\Z'),
        ];

        if ($body !== '') {
            $params['details'] = $body;
        }

        $emails = collect($attendeeEmails)->filter()->unique()->values()->all();

        if ($emails !== []) {
            $params['add'] = implode(',', $emails);
        }

        return 'https://calendar.google.com/calendar/render?'.http_build_query($params);
    }
}
