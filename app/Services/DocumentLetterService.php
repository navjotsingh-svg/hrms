<?php

namespace App\Services;

use App\Models\DocumentLetter;
use App\Models\DocumentLetterTemplate;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DocumentLetterService
{
    public function __construct(
        private EmployeeAccessService $employeeAccessService,
        private ActivityLogService $activityLogService,
    ) {}

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = DocumentLetter::query()
            ->with(['employee', 'template', 'issuedBy', 'signedBy'])
            ->where('company_id', $user->company_id)
            ->latest('updated_at');

        if ($user->canManageDocuments()) {
            if (! empty($filters['employee_id'])) {
                $query->where('employee_id', (int) $filters['employee_id']);
            }
        } else {
            $employee = $this->employeeAccessService->linkedEmployee($user);

            if (! $employee) {
                throw new AccessDeniedHttpException('No employee profile is linked to your account.');
            }

            $query->where('employee_id', $employee->id);
        }

        foreach (['status', 'category'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function pendingSignatureCount(User $user): int
    {
        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee) {
            return 0;
        }

        return DocumentLetter::query()
            ->where('company_id', $user->company_id)
            ->where('employee_id', $employee->id)
            ->where('status', DocumentLetter::STATUS_PENDING_SIGNATURE)
            ->count();
    }

    public function createDraft(User $user, array $data): DocumentLetter
    {
        $this->assertCanManage($user);

        $employee = Employee::query()
            ->where('company_id', $user->company_id)
            ->findOrFail((int) $data['employee_id']);

        $template = null;
        $bodyHtml = $data['body_html'] ?? '';
        $category = $data['category'] ?? 'other';
        $requiresSignature = $data['requires_signature'] ?? true;

        if (! empty($data['template_id'])) {
            $template = DocumentLetterTemplate::query()
                ->where('company_id', $user->company_id)
                ->findOrFail((int) $data['template_id']);
            $bodyHtml = $template->body_html;
            $category = $template->category;
            $requiresSignature = $template->requires_signature;
        }

        $custom = [
            'salary' => $data['salary'] ?? '',
            'joining_date' => $data['joining_date'] ?? '',
        ];

        $rendered = $this->renderHtml($bodyHtml, $employee, $user, $custom);

        return DocumentLetter::query()->create([
            'company_id' => $user->company_id,
            'employee_id' => $employee->id,
            'template_id' => $template?->id,
            'document_number' => $this->generateDocumentNumber($user->company_id),
            'title' => trim($data['title']),
            'category' => $category,
            'rendered_html' => $rendered['html'],
            'status' => DocumentLetter::STATUS_DRAFT,
            'requires_signature' => $requiresSignature,
            'issued_by_user_id' => $user->id,
        ]);
    }

    public function issue(User $user, DocumentLetter $letter): DocumentLetter
    {
        $this->assertCanManage($user);
        $this->assertSameCompany($user, $letter);

        if ($letter->status !== DocumentLetter::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft documents can be issued.'],
            ]);
        }

        $letter->update([
            'status' => $letter->requires_signature
                ? DocumentLetter::STATUS_PENDING_SIGNATURE
                : DocumentLetter::STATUS_SIGNED,
            'issued_at' => now(),
            'signed_at' => $letter->requires_signature ? null : now(),
        ]);

        $this->notifyEmployeeSignatureRequired($letter);

        return $letter->fresh(['employee', 'template', 'issuedBy']);
    }

    public function showForUser(User $user, DocumentLetter $letter): DocumentLetter
    {
        $this->assertSameCompany($user, $letter);

        if (! $user->canViewDocumentLetter($letter)) {
            throw new AccessDeniedHttpException('You are not allowed to view this document.');
        }

        return $letter->load(['employee', 'template', 'issuedBy', 'signedBy']);
    }

    public function sign(User $user, DocumentLetter $letter, array $data, ?UploadedFile $signatureImage = null): DocumentLetter
    {
        $this->assertSameCompany($user, $letter);

        if (! $user->canSignDocumentLetter($letter)) {
            throw new AccessDeniedHttpException('You are not allowed to sign this document.');
        }

        if ($letter->status !== DocumentLetter::STATUS_PENDING_SIGNATURE) {
            throw ValidationException::withMessages([
                'status' => ['This document is not awaiting signature.'],
            ]);
        }

        $signaturePath = null;
        if ($signatureImage) {
            $signaturePath = $this->storeSignatureImage($letter, $signatureImage);
        } elseif (! empty($data['signature_data_url'])) {
            $signaturePath = $this->storeSignatureFromDataUrl($letter, $data['signature_data_url']);
        }

        $letter->update([
            'status' => DocumentLetter::STATUS_SIGNED,
            'signature_name' => trim($data['signature_name']),
            'signature_image_path' => $signaturePath,
            'signed_at' => now(),
            'signed_by_user_id' => $user->id,
            'signature_ip' => request()?->ip(),
            'signature_meta' => [
                'user_agent' => request()?->userAgent(),
            ],
        ]);

        $this->activityLogService->logChange(
            $user,
            'document_letters',
            'document.signed',
            $letter,
            (int) $letter->id,
            "Document {$letter->document_number} signed by {$user->name}.",
            ['status' => DocumentLetter::STATUS_PENDING_SIGNATURE],
            ['status' => DocumentLetter::STATUS_SIGNED],
            request(),
        );

        return $letter->fresh(['employee', 'template', 'issuedBy', 'signedBy']);
    }

    public function decline(User $user, DocumentLetter $letter, string $reason): DocumentLetter
    {
        $this->assertSameCompany($user, $letter);

        if (! $user->canSignDocumentLetter($letter)) {
            throw new AccessDeniedHttpException('You are not allowed to decline this document.');
        }

        if ($letter->status !== DocumentLetter::STATUS_PENDING_SIGNATURE) {
            throw ValidationException::withMessages([
                'status' => ['This document is not awaiting signature.'],
            ]);
        }

        $letter->update([
            'status' => DocumentLetter::STATUS_DECLINED,
            'decline_reason' => trim($reason),
        ]);

        return $letter->fresh(['employee', 'template', 'issuedBy']);
    }

    public function cancel(User $user, DocumentLetter $letter): DocumentLetter
    {
        $this->assertCanManage($user);
        $this->assertSameCompany($user, $letter);

        if (in_array($letter->status, [DocumentLetter::STATUS_SIGNED, DocumentLetter::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => ['This document cannot be cancelled.'],
            ]);
        }

        $letter->update(['status' => DocumentLetter::STATUS_CANCELLED]);

        return $letter->fresh(['employee', 'template', 'issuedBy']);
    }

    /** @return array{html: string, placeholders: array<string, string>} */
    public function renderHtml(string $bodyHtml, ?Employee $employee, User $actor, array $custom = []): array
    {
        $employee?->loadMissing(['department', 'manager', 'company']);
        $company = $employee?->company ?? $actor->company;

        $placeholders = [
            'employee_name' => $employee?->full_name ?? '',
            'employee_first_name' => $employee?->first_name ?? '',
            'employee_code' => $employee?->employee_code ?? '',
            'employee_email' => $employee?->email ?? '',
            'employee_phone' => $employee?->phone ?? '',
            'designation' => $employee?->designation ?? '',
            'department' => $employee?->department?->name ?? '',
            'date_of_joining' => $employee?->date_of_joining?->format('d M Y') ?? '',
            'manager_name' => $employee?->manager?->full_name ?? '',
            'company_name' => $company?->name ?? '',
            'company_legal_name' => $company?->legal_name ?? $company?->name ?? '',
            'company_address' => collect([
                $company?->address_line_1,
                $company?->address_line_2,
                $company?->city,
                $company?->state,
                $company?->postal_code,
                $company?->country,
            ])->filter()->implode(', '),
            'today_date' => Carbon::now($company?->timezone ?: 'UTC')->format('d M Y'),
            'salary' => (string) ($custom['salary'] ?? ''),
            'joining_date' => (string) ($custom['joining_date'] ?? ''),
        ];

        $html = preg_replace_callback('/\{([a-z0-9_]+)\}/i', function ($matches) use ($placeholders) {
            $key = strtolower($matches[1]);

            return e($placeholders[$key] ?? $matches[0]);
        }, $bodyHtml);

        return [
            'html' => $html,
            'placeholders' => $placeholders,
        ];
    }

    private function generateDocumentNumber(int $companyId): string
    {
        $latest = DocumentLetter::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->value('document_number');

        $next = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return 'DOC-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function storeSignatureImage(DocumentLetter $letter, UploadedFile $file): string
    {
        $dir = public_path('images/document-signatures/'.$letter->company_id);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'letter-'.$letter->id.'-'.time().'.png';
        $file->move($dir, $filename);

        return 'images/document-signatures/'.$letter->company_id.'/'.$filename;
    }

    private function storeSignatureFromDataUrl(DocumentLetter $letter, string $dataUrl): ?string
    {
        if (! preg_match('#^data:image/(png|jpeg|jpg);base64,#i', $dataUrl, $matches)) {
            return null;
        }

        $binary = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl));
        if ($binary === false) {
            return null;
        }

        $dir = public_path('images/document-signatures/'.$letter->company_id);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'letter-'.$letter->id.'-'.time().'.png';
        file_put_contents($dir.'/'.$filename, $binary);

        return 'images/document-signatures/'.$letter->company_id.'/'.$filename;
    }

    private function notifyEmployeeSignatureRequired(DocumentLetter $letter): void
    {
        if ($letter->status !== DocumentLetter::STATUS_PENDING_SIGNATURE) {
            return;
        }

        $owner = $letter->employee?->user;
        if (! $owner) {
            return;
        }

        UserNotification::query()->create([
            'company_id' => $letter->company_id,
            'user_id' => $owner->id,
            'type' => UserNotification::TYPE_DOCUMENT_SIGNATURE_REQUIRED,
            'title' => 'Document awaiting your signature',
            'body' => mb_strimwidth("Please review and sign: {$letter->title}", 0, 240, '…'),
            'action_url' => '/documents-letters/'.$letter->id,
            'related_type' => 'document_letter',
            'related_id' => $letter->id,
        ]);
    }

    private function assertCanManage(User $user): void
    {
        if (! $user->canManageDocuments()) {
            throw new AccessDeniedHttpException('You are not allowed to manage documents.');
        }
    }

    private function assertSameCompany(User $user, DocumentLetter $letter): void
    {
        if ((int) $user->company_id !== (int) $letter->company_id) {
            abort(404);
        }
    }
}
