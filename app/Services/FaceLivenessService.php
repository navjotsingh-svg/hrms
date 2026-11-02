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
     * @return array{confidence: float, reference_bytes: string, status: string, feedback: list<string>}
     */
    public function assertPassed(string $sessionId): array
    {
        $sessionId = trim($sessionId);

        if ($sessionId === '' || ! preg_match('/^[0-9a-f-]{36}$/i', $sessionId)) {
            throw ValidationException::withMessages([
                'liveness_session_id' => ['A valid face liveness session is required.'],
            ]);
        }

        $result = $this->waitForSessionResults($sessionId);
        $status = strtoupper((string) ($result['Status'] ?? ''));
        $confidence = round((float) ($result['Confidence'] ?? 0), 2);
        $required = $this->thresholdPercent();
        $feedback = $this->feedbackMessages($result);

        if (in_array($status, ['CREATED', 'IN_PROGRESS'], true)) {
            throw ValidationException::withMessages([
                'liveness_session_id' => ['Face liveness is still processing. Wait a moment and try again.'],
            ]);
        }

        if ($status === 'EXPIRED') {
            throw ValidationException::withMessages([
                'liveness_session_id' => ['Face liveness session expired. Tap Try again to start a new check.'],
            ]);
        }

        if ($status !== 'SUCCEEDED') {
            $detail = $confidence > 0
                ? " Face liveness score was {$confidence}%."
                : '';
            $tips = $feedback !== []
                ? ' '.implode(' ', $feedback)
                : ' Keep your face centered in the oval with even lighting, and do not use a photo or video of a face.';

            Log::info('AWS Face Liveness session did not succeed.', [
                'session_id' => $sessionId,
                'status' => $status,
                'confidence' => $confidence,
                'feedback' => $feedback,
            ]);

            throw ValidationException::withMessages([
                'liveness_session_id' => [
                    "Face liveness did not succeed ({$status}).{$detail}{$tips}",
                ],
            ]);
        }

        if ($confidence < $required) {
            $tips = $feedback !== []
                ? ' '.implode(' ', $feedback)
                : ' Do not use a photo or video of a face.';

            throw ValidationException::withMessages([
                'liveness_session_id' => [
                    "Face liveness failed ({$confidence}%). It must be at least {$required}%.{$tips}",
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
            'status' => $status,
            'feedback' => $feedback,
        ];
    }

    /**
     * Amplify onAnalysisComplete means streaming finished; AWS may still report IN_PROGRESS briefly.
     *
     * @return array<string, mixed>
     */
    private function waitForSessionResults(string $sessionId): array
    {
        $attempts = 8;
        $delayMs = 400;
        $lastResult = [];

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $lastResult = $this->rekognitionClient()->getFaceLivenessSessionResults([
                    'SessionId' => $sessionId,
                ])->toArray();
            } catch (AwsException $exception) {
                Log::warning('AWS Face Liveness results failed.', [
                    'code' => $exception->getAwsErrorCode(),
                    'message' => $exception->getAwsErrorMessage() ?: $exception->getMessage(),
                    'session_id' => $sessionId,
                    'attempt' => $attempt,
                ]);

                throw ValidationException::withMessages([
                    'liveness_session_id' => [$this->awsErrorMessage($exception, 'Face liveness could not be verified. Complete the check again.')],
                ]);
            } catch (\Throwable $exception) {
                Log::warning('AWS Face Liveness results failed.', [
                    'message' => $exception->getMessage(),
                    'session_id' => $sessionId,
                    'attempt' => $attempt,
                ]);

                throw ValidationException::withMessages([
                    'liveness_session_id' => ['Face liveness could not be verified. Complete the check again.'],
                ]);
            }

            $status = strtoupper((string) ($lastResult['Status'] ?? ''));

            if (! in_array($status, ['CREATED', 'IN_PROGRESS', ''], true)) {
                return $lastResult;
            }

            if ($attempt < $attempts) {
                usleep($delayMs * 1000);
                $delayMs = min(1200, (int) round($delayMs * 1.35));
            }
        }

        return $lastResult;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return list<string>
     */
    private function feedbackMessages(array $result): array
    {
        $items = $result['Feedback'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        $messages = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $message = trim((string) ($item['Message'] ?? ''));

            if ($message !== '') {
                $messages[] = $message;
            }
        }

        return array_values(array_unique($messages));
    }

    /**
     * @return array{accessKeyId: string, secretAccessKey: string, sessionToken?: string, expiration: string}
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

            $credentials = $result['Credentials'] ?? null;

            if (is_array($credentials)
                && ! empty($credentials['AccessKeyId'])
                && ! empty($credentials['SecretAccessKey'])
                && ! empty($credentials['SessionToken'])
            ) {
                return [
                    // Amplify FaceLivenessDetectorCore expects camelCase + ISO-8601 expiration.
                    'accessKeyId' => (string) $credentials['AccessKeyId'],
                    'secretAccessKey' => (string) $credentials['SecretAccessKey'],
                    'sessionToken' => (string) $credentials['SessionToken'],
                    'expiration' => $this->formatCredentialExpiration($credentials['Expiration'] ?? null),
                ];
            }

            Log::warning('AWS STS GetSessionToken returned incomplete credentials; falling back to IAM keys.');
        } catch (AwsException $exception) {
            Log::warning('AWS STS GetSessionToken failed for Face Liveness; falling back to IAM keys.', [
                'code' => $exception->getAwsErrorCode(),
                'message' => $exception->getAwsErrorMessage() ?: $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('AWS STS GetSessionToken failed for Face Liveness; falling back to IAM keys.', [
                'message' => $exception->getMessage(),
            ]);
        }

        // Fallback for hosts where sts:GetSessionToken is blocked. Prefer a Cognito
        // identity pool / AssumeRole in production when possible.
        return [
            'accessKeyId' => $key,
            'secretAccessKey' => $secret,
            'expiration' => now()->addMinutes(14)->toIso8601String(),
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
