<?php

namespace App\Services;

use App\Models\AttendancePunch;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceSelfieStorageService
{
    public function __construct(private ImageCompressor $imageCompressor) {}

    public function disk(): string
    {
        $disk = strtolower(trim((string) config('hrms.attendance.selfie_disk', 's3')));

        return in_array($disk, ['s3', 'local'], true) ? $disk : 's3';
    }

    public function usesRemoteDisk(): bool
    {
        return $this->disk() === 's3';
    }

    public function store(Employee $employee, UploadedFile $selfie): string
    {
        if ($this->usesRemoteDisk()) {
            return $this->storeOnS3($employee, $selfie);
        }

        $relativeDirectory = AttendancePunch::PUBLIC_UPLOAD_DIR."/{$employee->company_id}/{$employee->id}";

        return $this->imageCompressor->compressAndSave(
            $selfie,
            public_path($relativeDirectory),
            $relativeDirectory,
            480,
            70,
            true,
        );
    }

    public function url(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if ($this->isRemotePath($path)) {
            return $this->remoteUrl($path);
        }

        return '/'.ltrim($path, '/');
    }

    public function isRemotePath(string $path): bool
    {
        return str_starts_with($path, AttendancePunch::S3_UPLOAD_PREFIX)
            || str_starts_with($path, 's3:');
    }

    private function storeOnS3(Employee $employee, UploadedFile $selfie): string
    {
        $bucket = trim((string) config('filesystems.disks.s3.bucket'));

        if ($bucket === '') {
            throw ValidationException::withMessages([
                'selfie' => ['Attendance photo storage is not configured. Set AWS_BUCKET for S3 uploads.'],
            ]);
        }

        $stagingDirectory = AttendancePunch::PUBLIC_UPLOAD_DIR
            ."/_staging/{$employee->company_id}/{$employee->id}";
        $relativePath = $this->imageCompressor->compressAndSave(
            $selfie,
            public_path($stagingDirectory),
            $stagingDirectory,
            480,
            70,
            true,
        );
        $absolutePath = public_path($relativePath);

        $key = AttendancePunch::S3_UPLOAD_PREFIX
            ."{$employee->company_id}/{$employee->id}/"
            .now()->format('Ymd_His').'_'.Str::lower(Str::random(10)).'.jpg';

        try {
            if (! is_file($absolutePath)) {
                throw ValidationException::withMessages([
                    'selfie' => ['Unable to prepare attendance photo for upload.'],
                ]);
            }

            $uploaded = Storage::disk('s3')->put(
                $key,
                file_get_contents($absolutePath),
                [
                    'visibility' => 'private',
                    'ContentType' => 'image/jpeg',
                ],
            );

            if (! $uploaded) {
                throw ValidationException::withMessages([
                    'selfie' => ['Unable to upload attendance photo to S3. Check AWS credentials and bucket permissions.'],
                ]);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::warning('Attendance selfie S3 upload failed.', [
                'message' => $exception->getMessage(),
                'key' => $key,
            ]);

            throw ValidationException::withMessages([
                'selfie' => ['Unable to upload attendance photo to S3. Check AWS credentials and bucket permissions.'],
            ]);
        } finally {
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        return $key;
    }

    private function remoteUrl(string $path): string
    {
        $key = str_starts_with($path, 's3:') ? substr($path, 3) : $path;

        try {
            return Storage::disk('s3')->temporaryUrl($key, now()->addMinutes(60));
        } catch (\Throwable $exception) {
            Log::warning('Attendance selfie temporary URL failed; falling back to disk URL.', [
                'message' => $exception->getMessage(),
                'key' => $key,
            ]);

            return Storage::disk('s3')->url($key);
        }
    }
}
