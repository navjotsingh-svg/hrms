<?php

namespace App\Services;

use App\Models\Employee;
use Aws\Exception\AwsException;
use Aws\Rekognition\RekognitionClient;
use Aws\Sts\StsClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FaceLivenessService
{
    public function enabled(): bool
    {
        return (bool) config('hrms.attendance.require_liveness', true);
    }

    public function thresholdPercent(): float
    {
        return max(50.0, min(100.0, (float) config('hrms.attendance.liveness_threshold', 80)));
    }

    public function region(): string
    {
        $region = trim((string) config('hrms.attendance.rekognition_region', 'us-east-1'));

        return $region !== '' ? $region : 'us-east-1';
    }

    /**
     * @return array{session_id: string, region: string, credentials: array{accessKeyId: string, secretAccessKey: string, sessionToken: string, expiration: string}}
     */
    public function startSession(Employee $employee): array
    {
        try {
            $result = $this->rekognitionClient()->createFaceLivenessSession([
                'Settings' => [
                    // Default challenge is FaceMovementAndLightChallenge (harder to spoof with a phone photo).
                    'AuditImagesLimit' => 1,
                ],
            ]);
        } catch (AwsException $exception) {
            Log::warning('AWS Face Liveness session create failed.', [
                'code' => $exception->getAwsErrorCode(),
                'message' => $exception->getAwsErrorMessage() ?: $exception->getMessage(),
                'employee_id' => $employee->id,
            ]);

            throw ValidationException::withMessages([
                'liveness' => [$this->awsErrorMessage($exception, 'Unable to start face liveness. Check AWS Rekognition Face Liveness permissions.')],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('AWS Face Liveness session create failed.', [
                'message' => $exception->getMessage(),
                'employee_id' => $employee->id,
            ]);

            throw ValidationException::withMessages([
                'liveness' => ['Unable to start face liveness. Try again.'],
            ]);
        }

        $sessionId = (string) ($result['SessionId'] ?? '');

        if ($sessionId === '') {
            throw ValidationException::withMessages([
                'liveness' => ['Unable to start face liveness. Try again.'],
            ]);
        }

        return [
            'session_id' => $sessionId,
            'region' => $this->region(),
            'credentials' => $this->temporaryCredentials(),
            'threshold' => $this->thresholdPercent(),
        ];
    }

    /**
     * @return array{confidence: float, reference_bytes: string}
     */
    public function assertPassed(string $sessionId): array
    {
        $sessionId = trim($sessionId);

        if ($sessionId === '' || ! preg_match('/^[0-9a-f-]{36}$/i', $sessionId)) {
            throw ValidationException::withMessages([
                'liveness_session_id' => ['A valid face liveness session is required.'],
            ]);
        }

        try {
            $result = $this->rekognitionClient()->getFaceLivenessSessionResults([
                'SessionId' => $sessionId,
            ]);
        } catch (AwsException $exception) {
            Log::warning('AWS Face Liveness results failed.', [
                'code' => $exception->getAwsErrorCode(),
                'message' => $exception->getAwsErrorMessage() ?: $exception->getMessage(),
                'session_id' => $sessionId,
            ]);

            throw ValidationException::withMessages([
                'liveness_session_id' => [$this->awsErrorMessage($exception, 'Face liveness could not be verified. Complete the check again.')],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('AWS Face Liveness results failed.', [
                'message' => $exception->getMessage(),
                'session_id' => $sessionId,
            ]);

            throw ValidationException::withMessages([
                'liveness_session_id' => ['Face liveness could not be verified. Complete the check again.'],
            ]);
        }

        $status = strtoupper((string) ($result['Status'] ?? ''));
        $confidence = round((float) ($result['Confidence'] ?? 0), 2);
        $required = $this->thresholdPercent();

        if ($status !== 'SUCCEEDED') {
            throw ValidationException::withMessages([
                'liveness_session_id' => ['Face liveness did not succeed. Follow the on-screen oval instructions and try again.'],
            ]);
        }

        if ($confidence < $required) {
            throw ValidationException::withMessages([
                'liveness_session_id' => [
                    "Face liveness failed ({$confidence}%). It must be at least {$required}%. Do not use a photo or video of a face.",
                ],
            ]);
        }

        $referenceBytes = $result['ReferenceImage']['Bytes'] ?? null;

        if (! is_string($referenceBytes) || $referenceBytes === '') {
            throw ValidationException::withMessages([
                'liveness_session_id' => ['Face liveness completed but no live reference photo was returned. Try again.'],
            ]);
        }

        return [
            'confidence' => $confidence,
            'reference_bytes' => $referenceBytes,
        ];
    }

    /**
     * @return array{accessKeyId: string, secretAccessKey: string, sessionToken: string, expiration: string}
     */
    private function temporaryCredentials(): array
    {
        $key = trim((string) config('services.rekognition.key'));
        $secret = trim((string) config('services.rekognition.secret'));

        if ($key === '' || $secret === '') {
            throw ValidationException::withMessages([
                'liveness' => ['Face liveness is not configured. Add AWS credentials.'],
            ]);
        }

        try {
            $result = $this->stsClient()->getSessionToken([
                'DurationSeconds' => 900,
            ]);
        } catch (AwsException $exception) {
            Log::warning('AWS STS GetSessionToken failed for Face Liveness.', [
                'code' => $exception->getAwsErrorCode(),
                'message' => $exception->getAwsErrorMessage() ?: $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'liveness' => ['Unable to issue temporary AWS credentials for face liveness. Allow sts:GetSessionToken on the IAM user.'],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('AWS STS GetSessionToken failed for Face Liveness.', [
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'liveness' => ['Unable to issue temporary AWS credentials for face liveness.'],
            ]);
        }

        $credentials = $result['Credentials'] ?? null;

        if (! is_array($credentials)
            || empty($credentials['AccessKeyId'])
            || empty($credentials['SecretAccessKey'])
            || empty($credentials['SessionToken'])
        ) {
            throw ValidationException::withMessages([
                'liveness' => ['Unable to issue temporary AWS credentials for face liveness.'],
            ]);
        }

        return [
            // Amplify FaceLivenessDetectorCore expects camelCase + ISO-8601 expiration.
            'accessKeyId' => (string) $credentials['AccessKeyId'],
            'secretAccessKey' => (string) $credentials['SecretAccessKey'],
            'sessionToken' => (string) $credentials['SessionToken'],
            'expiration' => $this->formatCredentialExpiration($credentials['Expiration'] ?? null),
        ];
    }

    private function formatCredentialExpiration(mixed $expiration): string
    {
        if ($expiration instanceof \DateTimeInterface) {
            return $expiration->format(DATE_ATOM);
        }

        if (is_string($expiration) && trim($expiration) !== '') {
            try {
                return (new \DateTimeImmutable($expiration))->format(DATE_ATOM);
            } catch (\Throwable) {
                // fall through
            }
        }

        return now()->addMinutes(14)->toIso8601String();
    }

    private function rekognitionClient(): RekognitionClient
    {
        $key = trim((string) config('services.rekognition.key'));
        $secret = trim((string) config('services.rekognition.secret'));
        $region = $this->region();

        if ($key === '' || $secret === '') {
            throw ValidationException::withMessages([
                'liveness' => ['Face liveness is not configured. Add AWS credentials for Rekognition.'],
            ]);
        }

        return new RekognitionClient([
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);
    }

    private function stsClient(): StsClient
    {
        $key = trim((string) config('services.rekognition.key'));
        $secret = trim((string) config('services.rekognition.secret'));
        $region = $this->region();

        return new StsClient([
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);
    }

    private function awsErrorMessage(AwsException $exception, string $fallback): string
    {
        $code = (string) $exception->getAwsErrorCode();

        return match ($code) {
            'AccessDeniedException', 'UnrecognizedClientException' => 'Face liveness is unavailable. Check AWS Rekognition credentials and Face Liveness permissions.',
            'InvalidParameterException' => 'Face liveness could not start with the current settings. Try again.',
            'ProvisionedThroughputExceededException', 'ThrottlingException' => 'Face liveness is busy. Wait a moment and try again.',
            default => $fallback,
        };
    }
}
