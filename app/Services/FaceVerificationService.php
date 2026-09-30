<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FaceVerificationService
{
    public function defaultThresholdPercent(): int
    {
        return max(1, min(100, (int) config('hrms.attendance.face_match_threshold', 90)));
    }

    public function defaultRequireFaceMatch(): bool
    {
        return (bool) config('hrms.attendance.require_face_match', true);
    }

    public function thresholdPercent(?int $companyId = null): int
    {
        if ($companyId === null) {
            return $this->defaultThresholdPercent();
        }

        $companyThreshold = Company::query()
            ->whereKey($companyId)
            ->value('attendance_face_match_threshold');

        if ($companyThreshold === null) {
            return $this->defaultThresholdPercent();
        }

        return max(1, min(100, (int) $companyThreshold));
    }

    public function requiresFaceMatch(?int $companyId = null): bool
    {
        if ($companyId === null) {
            return $this->defaultRequireFaceMatch();
        }

        $companySetting = Company::query()
            ->whereKey($companyId)
            ->value('attendance_require_face_match');

        if ($companySetting === null) {
            return $this->defaultRequireFaceMatch();
        }

        return (bool) $companySetting;
    }

    /** @param  array<int, float|int|string>  $descriptorA
     * @param  array<int, float|int|string>  $descriptorB
     */
    public function similarityPercent(array $descriptorA, array $descriptorB): float
    {
        return $this->attendanceMatchPercent($this->rawSimilarityRatio($descriptorA, $descriptorB));
    }

    /** @param  array<int, float|int|string>  $descriptorA
     * @param  array<int, float|int|string>  $descriptorB
     */
    public function meetsThreshold(array $descriptorA, array $descriptorB, ?int $companyId = null): bool
    {
        return $this->similarityPercent($descriptorA, $descriptorB) >= $this->thresholdPercent($companyId);
    }

    public function assertPunchAllowed(Employee $employee, ?UploadedFile $selfie): ?float
    {
        $companyId = (int) $employee->company_id;

        if (! $this->requiresFaceMatch($companyId)) {
            return null;
        }

        if (! $employee->profile_photo_path) {
            throw ValidationException::withMessages([
                'selfie' => ['An approved profile photo is required before marking attendance. Upload one from your profile.'],
            ]);
        }

        if (! $selfie) {
            throw ValidationException::withMessages([
                'selfie' => ['A punch photo is required for face verification.'],
            ]);
        }

        $profilePath = public_path(ltrim((string) $employee->profile_photo_path, '/'));

        if (! is_file($profilePath)) {
            throw ValidationException::withMessages([
                'selfie' => ['The approved profile photo could not be read. Upload it again from your profile.'],
            ]);
        }

        $minSimilarity = (float) config('hrms.attendance.insightface_min_similarity', 0.40);
        $requiredPercent = round($minSimilarity * 100, 2);

        try {
            $response = Http::timeout((int) config('hrms.attendance.insightface_timeout', 30))
                ->attach('profile', fopen($profilePath, 'r'), basename($profilePath))
                ->attach('selfie', fopen($selfie->getRealPath(), 'r'), 'selfie.jpg')
                ->post(rtrim((string) config('hrms.attendance.insightface_url'), '/').'/compare', [
                    'threshold' => $minSimilarity,
                ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'selfie' => ['Face verification is unavailable. Start the InsightFace service and try again.'],
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'selfie' => [$response->json('message') ?: 'Face verification could not compare the photos.'],
            ]);
        }

        $percent = round((float) $response->json('percent', 0), 2);

        if (! $response->json('matched')) {
            $message = $response->json('message')
                ?: "Face verification failed ({$percent}% similar). It must be at least {$requiredPercent}% similar to your approved profile photo.";

            throw ValidationException::withMessages([
                'selfie' => [$message],
            ]);
        }

        return $percent;
    }

    /** @param  array<int, float|int|string>  $descriptor */
    public function syncProfileDescriptor(Employee $employee, array $descriptor): void
    {
        if (! $employee->profile_photo_path) {
            throw ValidationException::withMessages([
                'descriptor' => ['Profile photo is not available yet.'],
            ]);
        }

        if (count($descriptor) < 64) {
            throw ValidationException::withMessages([
                'descriptor' => ['Invalid face descriptor payload.'],
            ]);
        }

        $employee->update([
            'profile_face_descriptor' => array_map('floatval', $descriptor),
        ]);
    }

    public function clearProfileDescriptor(Employee $employee): void
    {
        if ($employee->profile_face_descriptor !== null) {
            $employee->update(['profile_face_descriptor' => null]);
        }
    }

    /** @param  array<int, float|int|string>  $descriptorA
     * @param  array<int, float|int|string>  $descriptorB
     */
    private function rawSimilarityRatio(array $descriptorA, array $descriptorB): float
    {
        $length = min(count($descriptorA), count($descriptorB));

        if ($length < 64) {
            return 0.0;
        }

        $sum = 0.0;

        for ($index = 0; $index < $length; $index += 1) {
            $diff = (float) $descriptorA[$index] - (float) $descriptorB[$index];
            $sum += $diff * $diff;
        }

        $distance = round(100 * 25 * $sum) / 100;

        if ($distance <= 0.0) {
            return 1.0;
        }

        $root = sqrt($distance);
        $min = 0.2;
        $max = 0.8;
        $normalized = (1 - ($root / 100) - $min) / ($max - $min);

        return max(0.0, min(1.0, round($normalized, 4)));
    }

    private function attendanceMatchPercent(float $rawSimilarity): float
    {
        if ($rawSimilarity <= 0.0) {
            return 0.0;
        }

        $floor = 0.34;
        $ceiling = 0.48;
        $scaled = ($rawSimilarity - $floor) / ($ceiling - $floor);

        return round(max(0.0, min(100.0, $scaled * 100)), 2);
    }
}
