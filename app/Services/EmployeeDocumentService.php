<?php

namespace App\Services;

use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmployeeDocumentService
{
    public function __construct(
        private PublicUploadDirectoryService $uploadDirectories,
        private EmployeeProfileApprovalService $approvalService,
        private ImageCompressor $imageCompressor,
        private WorkflowNotificationService $workflowNotificationService,
    ) {}

    public function assertCanUpload(User $user, Employee $employee, DocumentType $documentType): void
    {
        if ($this->approvalService->shouldAutoApprove($user, $employee)) {
            return;
        }

        if ($documentType->allow_multiple) {
            return;
        }

        $existing = $this->findForType($employee, $documentType->id);

        if (! $existing) {
            return;
        }

        if ($existing->status === 'rejected') {
            return;
        }

        throw ValidationException::withMessages([
            'document_type_id' => [$existing->status === 'pending'
                ? 'This document is pending approval and cannot be changed.'
                : 'This document is approved and cannot be changed.'],
        ]);
    }

    public function store(User $user, Employee $employee, int $documentTypeId, array $files): array
    {
        $documentType = $this->resolveDocumentType($employee, $documentTypeId);
        $files = array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile));

        if ($files === []) {
            throw ValidationException::withMessages([
                'file' => ['Please select at least one file to upload.'],
            ]);
        }

        if (! $documentType->allow_multiple && count($files) > 1) {
            throw ValidationException::withMessages([
                'file' => ['Only one file can be uploaded for this document type.'],
            ]);
        }

        $autoApproved = $this->approvalService->shouldAutoApprove($user, $employee);
        $this->assertCanUpload($user, $employee, $documentType);

        $result = DB::transaction(function () use ($user, $employee, $documentType, $files, $autoApproved) {
            $documents = [];
            $isReupload = false;

            if ($documentType->allow_multiple) {
                foreach ($files as $file) {
                    $documents[] = $this->createDocument($user, $employee, $documentType, $file);
                }
            } else {
                $singleResult = $this->storeSingleDocument($user, $employee, $documentType, $files[0]);
                $documents[] = $singleResult['document'];
                $isReupload = $singleResult['is_reupload'];
            }

            return [
                'documents' => $documents,
                'document' => $documents[0],
                'is_reupload' => $isReupload,
                'auto_approved' => $autoApproved,
                'count' => count($documents),
            ];
        });

        if (! $result['auto_approved']) {
            foreach ($result['documents'] as $document) {
                if ($document->status === 'pending') {
                    $this->workflowNotificationService->notifyDocumentVerification($document, $user);
                }
            }
        }

        return $result;
    }

    public function pendingForReviewer(User $user)
    {
        if (! $user->canReviewEmployeeDocuments()) {
            return collect();
        }

        return EmployeeDocument::query()
            ->with(['documentType', 'employee', 'uploadedBy.role', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->filter(fn (EmployeeDocument $document) => $user->canReviewDocument($document))
            ->values();
    }

    public function approve(User $user, EmployeeDocument $document, ?string $notes = null): EmployeeDocument
    {
        $this->assertCanReview($user, $document);

        if ($document->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending documents can be approved.'],
            ]);
        }

        $document->update([
            'status' => 'approved',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'notes' => $notes ? trim($notes) : null,
        ]);

        $fresh = $document->fresh()->load(['documentType', 'employee', 'uploadedBy.role', 'reviewedBy']);

        $this->workflowNotificationService->notifyDocumentDecision($fresh, $user, 'approved');

        return $fresh;
    }

    public function reject(User $user, EmployeeDocument $document, string $notes): EmployeeDocument
    {
        $this->assertCanReview($user, $document);

        if ($document->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending documents can be rejected.'],
            ]);
        }

        $document->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'notes' => $notes,
        ]);

        $fresh = $document->fresh()->load(['documentType', 'employee', 'uploadedBy.role', 'reviewedBy']);

        $this->workflowNotificationService->notifyDocumentDecision($fresh, $user, 'rejected');

        return $fresh;
    }

    public function delete(User $user, EmployeeDocument $document): void
    {
        if (! $user->canDeleteEmployeeDocument($document)) {
            throw new AccessDeniedHttpException('You are not allowed to delete this document.');
        }

        $document->delete();
        $document->deleteFile();
    }

    public function downloadForUser(User $user, EmployeeDocument $document): array
    {
        if ((int) $document->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Document not found.');
        }

        $isOwner = $user->employee && (int) $user->employee->id === (int) $document->employee_id;
        $canReview = $user->canReviewEmployeeDocuments();

        if (! $isOwner && ! $canReview) {
            throw new AccessDeniedHttpException('You are not allowed to download this document.');
        }

        $path = $document->absoluteFilePath();

        if (! $path) {
            throw new NotFoundHttpException('Document file not found.');
        }

        return [
            'path' => $path,
            'name' => $document->original_name,
            'mime' => $document->mime_type ?: 'application/octet-stream',
        ];
    }

    public function assertBelongsToCompany(User $user, EmployeeDocument $document): void
    {
        if ((int) $document->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Document not found.');
        }
    }

    private function storeSingleDocument(
        User $user,
        Employee $employee,
        DocumentType $documentType,
        UploadedFile $file,
    ): array {
        $existing = $this->findForType($employee, $documentType->id);
        $isReupload = (bool) $existing;

        if ($existing) {
            $existing->deleteFile();
            $stored = $this->buildStoredFileMeta($file, $employee);
            $existing->update([
                'uploaded_by_user_id' => $user->id,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $stored['path'],
                'mime_type' => $stored['mime_type'],
                'file_size' => $stored['file_size'],
                ...$this->approvalService->submissionMeta($user, $employee),
            ]);

            return [
                'document' => $existing->fresh()->load(['documentType', 'uploadedBy.role', 'reviewedBy']),
                'is_reupload' => $isReupload,
            ];
        }

        return [
            'document' => $this->createDocument($user, $employee, $documentType, $file),
            'is_reupload' => false,
        ];
    }

    private function createDocument(
        User $user,
        Employee $employee,
        DocumentType $documentType,
        UploadedFile $file,
    ): EmployeeDocument {
        $stored = $this->buildStoredFileMeta($file, $employee);

        return EmployeeDocument::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'document_type_id' => $documentType->id,
            'uploaded_by_user_id' => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $stored['path'],
            'mime_type' => $stored['mime_type'],
            'file_size' => $stored['file_size'],
            ...$this->approvalService->submissionMeta($user, $employee),
        ])->load(['documentType', 'uploadedBy.role', 'reviewedBy']);
    }

    private function resolveDocumentType(Employee $employee, int $documentTypeId): DocumentType
    {
        $documentType = DocumentType::query()
            ->where('company_id', $employee->company_id)
            ->where('status', 'active')
            ->find($documentTypeId);

        if (! $documentType) {
            throw ValidationException::withMessages([
                'document_type_id' => ['The selected document type is invalid.'],
            ]);
        }

        return $documentType;
    }

    private function assertCanReview(User $user, EmployeeDocument $document): void
    {
        $this->assertBelongsToCompany($user, $document);

        if (! $user->canReviewDocument($document)) {
            throw new AccessDeniedHttpException('You are not allowed to review this document.');
        }
    }

    private function findForType(Employee $employee, int $documentTypeId): ?EmployeeDocument
    {
        return EmployeeDocument::query()
            ->where('employee_id', $employee->id)
            ->where('document_type_id', $documentTypeId)
            ->latest()
            ->first();
    }

    private function buildStoredFileMeta(UploadedFile $file, Employee $employee): array
    {
        $relativeDirectory = EmployeeDocument::PUBLIC_UPLOAD_DIR
            ."/{$employee->company_id}/{$employee->id}";
        $this->uploadDirectories->ensure($relativeDirectory);
        $absoluteDirectory = public_path($relativeDirectory);

        $mime = (string) $file->getMimeType();
        $isImage = str_starts_with($mime, 'image/');

        if ($isImage) {
            $path = $this->imageCompressor->compressAndSave(
                $file,
                $absoluteDirectory,
                $relativeDirectory,
                1200,
                75,
                true,
            );
            $fileSize = filesize(public_path($path)) ?: 0;
        } else {
            $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
            $filename = now()->format('YmdHis').'_'.uniqid().'.'.$extension;
            $fileSize = $file->getSize() ?: 0;
            $file->move($absoluteDirectory, $filename);
            $path = $relativeDirectory.'/'.$filename;
        }

        return [
            'path' => $path,
            'mime_type' => $mime,
            'file_size' => $fileSize,
        ];
    }
}
