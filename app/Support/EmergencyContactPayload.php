<?php

namespace App\Support;

class EmergencyContactPayload
{
    public static function normalize(array $payload): array
    {
        if (! empty($payload['contacts']) && is_array($payload['contacts'])) {
            return [
                'contacts' => collect($payload['contacts'])
                    ->map(fn (array $contact) => self::normalizeContact($contact))
                    ->filter(fn (array $contact) => $contact['name'] !== '')
                    ->values()
                    ->all(),
            ];
        }

        $name = trim((string) ($payload['name'] ?? ''));

        if ($name === '') {
            return ['contacts' => []];
        }

        $phones = [];

        if (! empty($payload['phone'])) {
            $phones[] = trim((string) $payload['phone']);
        }

        return [
            'contacts' => [
                self::normalizeContact([
                    'name' => $name,
                    'relation' => $payload['relation'] ?? '',
                    'phones' => $phones,
                ]),
            ],
        ];
    }

    public static function normalizeContact(array $contact): array
    {
        $phones = collect($contact['phones'] ?? [])
            ->map(fn ($phone) => trim((string) $phone))
            ->filter(fn (string $phone) => $phone !== '')
            ->values()
            ->all();

        if ($phones === [] && ! empty($contact['phone'])) {
            $phones = [trim((string) $contact['phone'])];
        }

        return [
            'name' => trim((string) ($contact['name'] ?? '')),
            'relation' => trim((string) ($contact['relation'] ?? '')),
            'phones' => $phones,
        ];
    }

    public static function summary(array $payload): string
    {
        $contacts = self::normalize($payload)['contacts'];

        if ($contacts === []) {
            return '—';
        }

        return collect($contacts)
            ->map(function (array $contact) {
                $name = $contact['name'];
                $relation = $contact['relation'];

                return $relation !== '' ? "{$name} ({$relation})" : $name;
            })
            ->implode(', ');
    }
}
