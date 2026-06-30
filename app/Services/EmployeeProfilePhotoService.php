<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeProfilePhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmployeeProfilePhotoService
{
    public function __construct(
        private EmployeeProfileApprovalService $approvalService,
        private PublicUploadDirectoryService $uploadDirectories,
        private ImageCompressor $imageCompressor,
        private WorkflowNotificationService $workflowNotificationService,
    ) {}

    public function assertCanSubmit(Employee $employee, ?User $user = null): void
    {
        $existing = $this->findForEmployee($employee);

        if (! $existing || in_array($existing->status, ['rejected', 'approved'], true)) {
            return;
        }

        if ($user && $this->approvalService->shouldAutoApprove($user, $employee)) {
            return;
        }

        throw ValidationException::withMessages([
            'photo' => ['Your profile photo is pending HR approval and cannot be changed yet.'],
        ]);
    }

    public function submit(User $user, Employee $employee, UploadedFile $photo): array
    {
        $autoApproved = $this->approvalService->shouldAutoApprove($user, $employee);
        $this->assertCanSubmit($employee, $user);

        return DB::transaction(function () use ($user, $employee, $photo, $autoApproved) {
            $existing = $this->findForEmployee($employee);
            $isResubmit = $existing && in_array($existing->status, ['rejected', 'approved'], true);
            $stored = $this->buildStoredFileMeta($photo, $employee);

            if ($existing) {
                $existing->deleteFile();
            }

            $payload = [
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'file_path' => $stored['path'],
                'original_name' => $photo->getClientOriginalName(),
                'mime_type' => $stored['mime_type'],
                'file_size' => $stored['file_size'],
                'submitted_by_user_id' => $user->id,
                'submitted_at' => now(),
                ...$this->approvalService->submissionMeta($user, $employee),
            ];

            if ($existing) {
                $existing->update($payload);
                $submission = $existing->fresh()->load(['submittedBy.role', 'reviewedBy', 'employee']);
            } else {
                $submission = EmployeeProfilePhoto::create($payload)
                    ->load(['submittedBy.role', 'reviewedBy', 'employee']);
            }

            if ($autoApproved) {
                $this->syncApprovedPhoto($employee, $submission);
            }

            $fresh = $submission->fresh()->load(['submittedBy.role', 'reviewedBy', 'employee']);

            if (! $autoApproved && $fresh->status === 'pending') {
                $this->workflowNotificationService->notifyProfilePhotoSubmitted($fresh, $user);
            }

            return [
                'profile_photo' => $fresh,
                'is_resubmit' => $isResubmit,
                'auto_approved' => $autoApproved,
            ];
        });
    }

    public function pendingForReviewer(User $user): Collection
    {
        if (! $user->canReviewEmployeeDocuments()) {
            return collect();
        }

        return EmployeeProfilePhoto::query()
            ->with(['employee', 'submittedBy.role', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->where('status', 'pending')
            ->latest('submitted_at')
            ->get()
            ->filter(fn (EmployeeProfilePhoto $photo) => $user->canReviewProfilePhoto($photo))
            ->values();
    }

    public function approve(User $user, EmployeeProfilePhoto $photo, ?string $notes = null): EmployeeProfilePhoto
    {
        $this->assertCanReview($user, $photo);

        if ($photo->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending profile photos can be approved.'],
            ]);
        }

        $photo->update([
            'status' => 'approved',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'notes' => $notes ? trim($notes) : null,
        ]);

        $photo = $photo->fresh()->load(['employee', 'submittedBy.role', 'reviewedBy']);
        $this->syncApprovedPhoto($photo->employee, $photo);
        $this->workflowNotificationService->notifyProfilePhotoDecision($photo, $user, 'approved');

        return $photo;
    }

    public function reject(User $user, EmployeeProfilePhoto $photo, string $notes): EmployeeProfilePhoto
    {
        $this->assertCanReview($user, $photo);

        if ($photo->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending profile photos can be rejected.'],
            ]);
        }

        $photo->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'notes' => $notes,
        ]);

        $photo = $photo->fresh()->load(['employee', 'submittedBy.role', 'reviewedBy']);
        $this->workflowNotificationService->notifyProfilePhotoDecision($photo, $user, 'rejected');

        return $photo;
    }

    public function assertBelongsToCompany(User $user, EmployeeProfilePhoto $photo): void
    {
        if ((int) $photo->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Profile photo not found.');
        }
    }

    public function downloadForUser(User $user, EmployeeProfilePhoto $photo): array
    {
        $this->assertBelongsToCompany($user, $photo);

        if (! $user->canViewProfilePhotoRequest($photo)) {
            throw new AccessDeniedHttpException('You are not allowed to view this profile photo.');
        }

        $path = $photo->absoluteFilePath();

        if (! $path) {
            throw new NotFoundHttpException('Profile photo file not found.');
        }

        return [
            'path' => $path,
            'name' => $photo->original_name ?: 'profile-photo.jpg',
            'mime' => $photo->mime_type ?: 'image/jpeg',
        ];
    }

    private function assertCanReview(User $user, EmployeeProfilePhoto $photo): void
    {
        $this->assertBelongsToCompany($user, $photo);

        if (! $user->canReviewProfilePhoto($photo)) {
            throw new AccessDeniedHttpException('You are not allowed to review this profile photo.');
        }
    }

    private function findForEmployee(Employee $employee): ?EmployeeProfilePhoto
    {
        return EmployeeProfilePhoto::query()
            ->where('employee_id', $employee->id)
            ->first();
    }

    private function syncApprovedPhoto(Employee $employee, EmployeeProfilePhoto $photo): void
    {
        $employee->update([
            'profile_photo_path' => $photo->file_path,
            'profile_face_descriptor' => null,
        ]);
    }

    /** @return array{path: string, mime_type: string, file_size: int} */
    private function buildStoredFileMeta(UploadedFile $file, Employee $employee): array
    {
        $relativeDirectory = EmployeeProfilePhoto::PUBLIC_UPLOAD_DIR
            ."/{$employee->company_id}/{$employee->id}";
        $this->uploadDirectories->ensure($relativeDirectory);
        $absoluteDirectory = public_path($relativeDirectory);

        $path = $this->imageCompressor->compressAndSave(
            $file,
            $absoluteDirectory,
            $relativeDirectory,
            640,
            85,
            true,
        );

        return [
            'path' => $path,
            'mime_type' => 'image/jpeg',
            'file_size' => (int) (@filesize(public_path($path)) ?: 0),
        ];
    }
}
