<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeocodingService
{
    public function reverseGeocode(float $latitude, float $longitude): ?string
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => config('app.name', 'HRMS').' Attendance',
                    'Accept-Language' => 'en',
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'json',
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'zoom' => 18,
                    'addressdetails' => 1,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $name = trim((string) $response->json('display_name', ''));

            return $name !== '' ? $name : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
