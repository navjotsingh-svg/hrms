<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Validation\ValidationException;

class FaceVerificationService
{
    public function defaultThresholdPercent(): int
    {
        return max(1, min(100, (int) config('hrms.attendance.face_match_threshold', 80)));
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
        return round($this->humanSimilarity($descriptorA, $descriptorB) * 100, 2);
    }

    /** @param  array<int, float|int|string>  $descriptorA
     * @param  array<int, float|int|string>  $descriptorB
     */
    public function meetsThreshold(array $descriptorA, array $descriptorB, ?int $companyId = null): bool
    {
        return $this->similarityPercent($descriptorA, $descriptorB) >= $this->thresholdPercent($companyId);
    }

    /** @param  array<int, float|int|string>|null  $selfieDescriptor */
    public function assertPunchAllowed(Employee $employee, ?float $clientMatchScore, ?array $selfieDescriptor): ?float
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

        $threshold = $this->thresholdPercent($companyId);

        if ($clientMatchScore === null) {
            throw ValidationException::withMessages([
                'face_match_score' => ['Face verification is required to mark attendance.'],
            ]);
        }

        $verifiedScore = round($clientMatchScore, 2);

        if ($clientMatchScore < $threshold) {
            throw ValidationException::withMessages([
                'selfie' => ["Face did not match your profile photo ({$clientMatchScore}% match). At least {$threshold}% is required."],
            ]);
        }

        $storedDescriptor = $employee->profile_face_descriptor;

        if (
            is_array($storedDescriptor)
            && $storedDescriptor !== []
            && is_array($selfieDescriptor)
            && $selfieDescriptor !== []
            && count($storedDescriptor) === count($selfieDescriptor)
        ) {
            $verifiedScore = $this->similarityPercent($storedDescriptor, $selfieDescriptor);

            if ($verifiedScore < $threshold) {
                throw ValidationException::withMessages([
                    'selfie' => ["Face verification failed ({$verifiedScore}% match). Please align your face clearly and try again."],
                ]);
            }
        }

        return $verifiedScore;
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
    private function humanSimilarity(
        array $descriptorA,
        array $descriptorB,
        int $order = 2,
        float $multiplier = 25,
        float $min = 0.2,
        float $max = 0.8,
    ): float {
        $length = min(count($descriptorA), count($descriptorB));

        if ($length === 0) {
            return 0.0;
        }

        $sum = 0.0;

        for ($index = 0; $index < $length; $index += 1) {
            if ($order === 2) {
                $delta = (float) $descriptorA[$index] - (float) $descriptorB[$index];
                $sum += $delta * $delta;
            } else {
                $sum += abs((float) $descriptorA[$index] - (float) $descriptorB[$index]) ** $order;
            }
        }

        $distance = round(100 * $multiplier * $sum) / 100;

        if ($distance === 0.0) {
            return 1.0;
        }

        $root = $order === 2 ? sqrt($distance) : $distance ** (1 / $order);
        $normalized = (1 - ($root / 100) - $min) / ($max - $min);

        return round(max(0, min(1, $normalized)) * 100) / 100;
    }
}
