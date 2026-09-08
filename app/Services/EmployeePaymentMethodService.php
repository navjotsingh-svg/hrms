<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeePaymentMethod;
use App\Models\EmployeePaymentMethodProof;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmployeePaymentMethodService
{
    public function __construct(
        private EmployeeProfileApprovalService $approvalService,
        private PublicUploadDirectoryService $uploadDirectories,
        private ImageCompressor $imageCompressor,
    ) {}

    public function assertCanSubmit(Employee $employee, string $paymentMode, ?User $user = null): void
    {
        $existing = $this->findForMode($employee, $paymentMode);

        if (! $existing) {
            return;
        }

        if (in_array($existing->status, ['rejected', 'approved'], true)) {
            return;
        }

        if ($user && $this->approvalService->shouldAutoApprove($user, $employee)) {
            return;
        }

        throw ValidationException::withMessages([
            'payment_mode' => ['This payment option is pending approval and cannot be changed.'],
        ]);
    }

    public function submit(User $user, Employee $employee, array $data, array $proofFiles = []): array
    {
        $autoApproved = $this->approvalService->shouldAutoApprove($user, $employee);
        $paymentMode = $data['payment_mode'];
        $this->assertCanSubmit($employee, $paymentMode, $user);

        if ($paymentMode !== 'bank_transfer') {
            $data['bank_name'] = null;
            $data['bank_branch'] = null;
            $data['bank_address'] = null;
            $data['account_holder_name'] = null;
            $data['account_number'] = null;
            $data['ifsc_code'] = null;
            $proofFiles = [];
        } else {
            $proofFiles = array_values(array_filter($proofFiles, fn ($file) => $file instanceof UploadedFile));

            if ($proofFiles === []) {
                throw ValidationException::withMessages([
                    'proofs' => ['Please attach at least one bank proof document.'],
                ]);
            }
        }

        return DB::transaction(function () use ($user, $employee, $data, $paymentMode, $autoApproved, $proofFiles) {
            $existing = $this->findForMode($employee, $paymentMode);
            $isResubmit = $existing && in_array($existing->status, ['rejected', 'approved'], true);
            $isChange = $existing && $existing->status === 'approved';

            $payload = [
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'payment_mode' => $paymentMode,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_branch' => $data['bank_branch'] ?? null,
                'bank_address' => $data['bank_address'] ?? null,
                'account_holder_name' => $data['account_holder_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'ifsc_code' => isset($data['ifsc_code']) ? strtoupper($data['ifsc_code']) : null,
                'submitted_by_user_id' => $user->id,
                'submitted_at' => now(),
                ...$this->approvalService->submissionMeta($user, $employee),
            ];

            if ($existing) {
                $existing->update($payload);
                $method = $existing->fresh()->load(['submittedBy.role', 'reviewedBy', 'employee', 'proofs']);
            } else {
                $method = EmployeePaymentMethod::create($payload)
                    ->load(['submittedBy.role', 'reviewedBy', 'employee', 'proofs']);
            }

            $this->syncProofs($user, $employee, $method, $proofFiles);

            return [
                'payment_method' => $method->fresh()->load(['submittedBy.role', 'reviewedBy', 'employee', 'proofs']),
                'is_resubmit' => $isResubmit,
                'is_change' => $isChange,
                'auto_approved' => $autoApproved,
            ];
        });
    }

    public function pendingForReviewer(User $user): Collection
    {
        if (! $user->canReviewEmployeeDocuments()) {
            return collect();
        }

        return EmployeePaymentMethod::query()
            ->with(['employee', 'submittedBy.role', 'reviewedBy', 'proofs'])
            ->where('company_id', $user->company_id)
            ->where('status', 'pending')
            ->latest('submitted_at')
            ->get()
            ->filter(fn (EmployeePaymentMethod $method) => $user->canReviewPaymentMethod($method))
            ->values();
    }

    public function approve(User $user, EmployeePaymentMethod $method, ?string $notes = null): EmployeePaymentMethod
    {
        $this->assertCanReview($user, $method);

        if ($method->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending payment options can be approved.'],
            ]);
        }

        $method->update([
            'status' => 'approved',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'notes' => $notes ? trim($notes) : null,
        ]);

        return $method->fresh()->load(['employee', 'submittedBy.role', 'reviewedBy', 'proofs']);
    }

    public function reject(User $user, EmployeePaymentMethod $method, string $notes): EmployeePaymentMethod
    {
        $this->assertCanReview($user, $method);

        if ($method->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending payment options can be rejected.'],
            ]);
        }

        $method->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'notes' => $notes,
        ]);

        return $method->fresh()->load(['employee', 'submittedBy.role', 'reviewedBy', 'proofs']);
    }

    public function downloadProofForUser(User $user, EmployeePaymentMethodProof $proof): array
    {
        if ((int) $proof->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Bank proof not found.');
        }

        $isOwner = $user->employee && (int) $user->employee->id === (int) $proof->employee_id;
        $canReview = $user->canReviewEmployeeDocuments();

        if (! $isOwner && ! $canReview) {
            throw new AccessDeniedHttpException('You are not allowed to view this bank proof.');
        }

        $path = $proof->absoluteFilePath();

        if (! $path) {
            throw new NotFoundHttpException('Bank proof file not found.');
        }

        return [
            'path' => $path,
            'name' => $proof->original_name,
            'mime' => $proof->mime_type ?: 'application/octet-stream',
        ];
    }

    public function assertProofBelongsToCompany(User $user, EmployeePaymentMethodProof $proof): void
    {
        if ((int) $proof->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Bank proof not found.');
        }
    }

    public function assertBelongsToCompany(User $user, EmployeePaymentMethod $method): void
    {
        if ((int) $method->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Payment option not found.');
        }
    }

    private function assertCanReview(User $user, EmployeePaymentMethod $method): void
    {
        $this->assertBelongsToCompany($user, $method);

        if (! $user->canReviewPaymentMethod($method)) {
            throw new AccessDeniedHttpException('You are not allowed to review this payment option.');
        }
    }

    private function findForMode(Employee $employee, string $paymentMode): ?EmployeePaymentMethod
    {
        return EmployeePaymentMethod::query()
            ->where('employee_id', $employee->id)
            ->where('payment_mode', $paymentMode)
            ->first();
    }

    private function syncProofs(
        User $user,
        Employee $employee,
        EmployeePaymentMethod $method,
        array $files,
    ): void {
        if ($method->payment_mode !== 'bank_transfer') {
            $this->deleteAllProofs($method);

            return;
        }

        $this->deleteAllProofs($method);

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $stored = $this->buildStoredFileMeta($file, $employee);

            EmployeePaymentMethodProof::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'employee_payment_method_id' => $method->id,
                'uploaded_by_user_id' => $user->id,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $stored['path'],
                'mime_type' => $stored['mime_type'],
                'file_size' => $stored['file_size'],
            ]);
        }
    }

    private function deleteAllProofs(EmployeePaymentMethod $method): void
    {
        $method->loadMissing('proofs');

        foreach ($method->proofs as $proof) {
            $proof->deleteFile();
            $proof->delete();
        }
    }

    private function buildStoredFileMeta(UploadedFile $file, Employee $employee): array
    {
        $relativeDirectory = EmployeePaymentMethodProof::PUBLIC_UPLOAD_DIR
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
