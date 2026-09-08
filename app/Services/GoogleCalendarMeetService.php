<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleCalendarMeetService
{
    public function isConfigured(): bool
    {
        if (! config('services.google.calendar_enabled')) {
            return false;
        }

        $path = config('services.google.service_account_json');

        return is_string($path) && $path !== '' && is_file($path);
    }

    /**
     * @return array{meet_link: ?string, calendar_link: ?string, event_id: ?string}
     */
    public function createEventWithMeet(
        UserContext $organizer,
        string $title,
        Carbon $start,
        int $durationMinutes,
        ?string $agenda,
        array $attendeeEmails,
    ): array {
        if (! $this->isConfigured()) {
            return ['meet_link' => null, 'calendar_link' => null, 'event_id' => null];
        }

        try {
            $credentials = json_decode((string) file_get_contents(config('services.google.service_account_json')), true, 512, JSON_THROW_ON_ERROR);
            $impersonate = config('services.google.impersonate_email') ?: $organizer->email;
            $accessToken = $this->fetchAccessToken($credentials, $impersonate);
            $timezone = $organizer->timezone ?: config('app.timezone', 'UTC');
            $end = $start->copy()->addMinutes(max(1, $durationMinutes));
            $calendarId = config('services.google.calendar_id', 'primary');
            $requestId = Str::uuid()->toString();

            $eventPayload = [
                'summary' => $title,
                'description' => $agenda,
                'start' => [
                    'dateTime' => $start->copy()->timezone($timezone)->format('Y-m-d\TH:i:s'),
                    'timeZone' => $timezone,
                ],
                'end' => [
                    'dateTime' => $end->copy()->timezone($timezone)->format('Y-m-d\TH:i:s'),
                    'timeZone' => $timezone,
                ],
                'attendees' => collect($attendeeEmails)
                    ->filter()
                    ->unique()
                    ->map(fn (string $email) => ['email' => $email])
                    ->values()
                    ->all(),
                'conferenceData' => [
                    'createRequest' => [
                        'requestId' => $requestId,
                        'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post(
                    'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events?conferenceDataVersion=1',
                    $eventPayload,
                );

            if (! $response->successful()) {
                Log::warning('Google Calendar Meet creation failed.', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return ['meet_link' => null, 'calendar_link' => null, 'event_id' => null];
            }

            $event = $response->json();
            $meetLink = $event['hangoutLink'] ?? null;

            if (! $meetLink && ! empty($event['conferenceData']['entryPoints'])) {
                foreach ($event['conferenceData']['entryPoints'] as $entryPoint) {
                    if (($entryPoint['entryPointType'] ?? null) === 'video' && ! empty($entryPoint['uri'])) {
                        $meetLink = $entryPoint['uri'];
                        break;
                    }
                }
            }

            return [
                'meet_link' => $meetLink,
                'calendar_link' => $event['htmlLink'] ?? null,
                'event_id' => $event['id'] ?? null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Google Calendar Meet creation error.', [
                'message' => $exception->getMessage(),
            ]);

            return ['meet_link' => null, 'calendar_link' => null, 'event_id' => null];
        }
    }

    /** @param array<string, mixed> $credentials */
    private function fetchAccessToken(array $credentials, ?string $impersonateEmail = null): string
    {
        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));

        $payload = [
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        if ($impersonateEmail) {
            $payload['sub'] = $impersonateEmail;
        }

        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $unsignedToken = $header.'.'.$encodedPayload;

        $privateKey = openssl_pkey_get_private($credentials['private_key']);

        if (! $privateKey) {
            throw new \RuntimeException('Invalid Google service account private key.');
        }

        openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $jwt = $unsignedToken.'.'.$this->base64UrlEncode($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Unable to authenticate with Google Calendar API.');
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('Google Calendar API returned an empty access token.');
        }

        return $token;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
