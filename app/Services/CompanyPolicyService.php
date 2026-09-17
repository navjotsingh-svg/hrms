<?php

namespace App\Services;

use App\Models\CompanyPolicy;
use App\Models\CompanyPolicyConsent;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CompanyPolicyService
{
    public function __construct(
        private PublicUploadDirectoryService $uploadDirectories,
        private EmployeeAccessService $employeeAccessService,
    ) {}

    public function listForCompany(User $user, array $filters = []): LengthAwarePaginator
    {
        $canManage = $user->canManageDocuments();
        $employee = $this->employeeAccessService->linkedEmployee($user);

        $query = CompanyPolicy::query()
            ->with('uploadedBy')
            ->where('company_id', $user->company_id)
            ->when(! $canManage, fn ($q) => $q->where('status', CompanyPolicy::STATUS_PUBLISHED))
            ->when(
                $canManage && ! empty($filters['status']),
                fn ($q) => $q->where('status', $filters['status']),
            )
            ->when(
                ! empty($filters['category']),
                fn ($q) => $q->where('category', $filters['category']),
            )
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $search = trim((string) $filters['search']);
                $q->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('updated_at');

        $perPage = (int) ($filters['per_page'] ?? 10);
        $policies = $query->paginate($perPage);

        if ($employee) {
            $consentMap = CompanyPolicyConsent::query()
                ->where('employee_id', $employee->id)
                ->whereIn('company_policy_id', $policies->getCollection()->pluck('id'))
                ->get()
                ->keyBy('company_policy_id');

            $policies->getCollection()->transform(function (CompanyPolicy $policy) use ($consentMap) {
                $policy->setRelation('myConsent', $consentMap->get($policy->id));

                return $policy;
            });
        }

        return $policies;
    }

    public function create(User $user, array $data): CompanyPolicy
    {
        $this->assertCanManage($user);
        $this->assertPublishable($data);

        return CompanyPolicy::query()->create([
            'company_id' => $user->company_id,
            'title' => $data['title'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'body_html' => $data['body_html'] ?? null,
            'status' => $data['status'] ?? CompanyPolicy::STATUS_PUBLISHED,
            'requires_consent' => (bool) ($data['requires_consent'] ?? true),
            'version' => 1,
            'uploaded_by_user_id' => $user->id,
            'original_name' => null,
            'file_path' => null,
            'mime_type' => null,
            'file_size' => 0,
        ])->load('uploadedBy');
    }

    public function update(User $user, CompanyPolicy $policy, array $data): CompanyPolicy
    {
        $this->assertCanManage($user);
        $this->assertSameCompany($user, $policy);

        $merged = [
            'title' => $data['title'] ?? $policy->title,
            'category' => $data['category'] ?? $policy->category,
            'description' => array_key_exists('description', $data) ? $data['description'] : $policy->description,
            'body_html' => array_key_exists('body_html', $data) ? $data['body_html'] : $policy->body_html,
            'status' => $data['status'] ?? $policy->status,
            'requires_consent' => array_key_exists('requires_consent', $data)
                ? (bool) $data['requires_consent']
                : (bool) $policy->requires_consent,
        ];

        $this->assertPublishable($merged);

        $bodyChanged = array_key_exists('body_html', $data)
            && trim(strip_tags((string) $data['body_html'])) !== trim(strip_tags((string) $policy->body_html));

        $payload = $merged;
        $payload['uploaded_by_user_id'] = $user->id;

        if ($bodyChanged) {
            $payload['version'] = $policy->version + 1;
        }

        $policy->update($payload);

        return $policy->fresh()->load('uploadedBy');
    }

    public function delete(User $user, CompanyPolicy $policy): void
    {
        $this->assertCanManage($user);
        $this->assertSameCompany($user, $policy);

        $policy->deleteFile();
        $policy->consents()->delete();
        $policy->delete();
    }

    public function resolveForUser(User $user, CompanyPolicy $policy): CompanyPolicy
    {
        $this->assertSameCompany($user, $policy);

        if (! $user->canManageDocuments() && ! $policy->isPublished()) {
            throw new NotFoundHttpException('Policy not found.');
        }

        $policy->loadMissing('uploadedBy');
        $employee = $this->employeeAccessService->linkedEmployee($user);

        if ($employee) {
            $consent = CompanyPolicyConsent::query()
                ->where('company_policy_id', $policy->id)
                ->where('employee_id', $employee->id)
                ->first();

            $policy->setRelation('myConsent', $consent);
        }

        return $policy;
    }

    public function signConsent(User $user, CompanyPolicy $policy, array $data, ?UploadedFile $signatureImage = null): CompanyPolicyConsent
    {
        $this->assertSameCompany($user, $policy);

        if (! $policy->isPublished()) {
            throw ValidationException::withMessages([
                'policy' => ['Only published policies can be consented to.'],
            ]);
        }

        if (! $policy->requires_consent) {
            throw ValidationException::withMessages([
                'policy' => ['This policy does not require employee consent.'],
            ]);
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee) {
            throw new AccessDeniedHttpException('No employee profile is linked to your account.');
        }

        $personalEmail = trim((string) $employee->personal_email);

        if ($personalEmail === '') {
            throw ValidationException::withMessages([
                'consent_email' => ['Add your personal email in your profile before giving consent.'],
            ]);
        }

        $consentEmail = strtolower(trim((string) ($data['consent_email'] ?? '')));

        if ($consentEmail === '' || strtolower($personalEmail) !== $consentEmail) {
            throw ValidationException::withMessages([
                'consent_email' => ['Consent email must match your personal email ('.$personalEmail.').'],
            ]);
        }

        $existing = CompanyPolicyConsent::query()
            ->where('company_policy_id', $policy->id)
            ->where('employee_id', $employee->id)
            ->first();

        if ($existing && (int) $existing->policy_version === (int) $policy->version) {
            throw ValidationException::withMessages([
                'policy' => ['You have already consented to this version of the policy.'],
            ]);
        }

        $signaturePath = null;
        if ($signatureImage) {
            $signaturePath = $this->storeSignatureImage($policy, $signatureImage);
        } elseif (! empty($data['signature_data_url'])) {
            $signaturePath = $this->storeSignatureFromDataUrl($policy, (string) $data['signature_data_url']);
        }

        if (! $signaturePath) {
            throw ValidationException::withMessages([
                'signature' => ['Please draw your signature to give consent.'],
            ]);
        }

        return DB::transaction(function () use ($user, $policy, $employee, $consentEmail, $data, $signaturePath, $existing) {
            if ($existing) {
                if ($existing->signature_image_path) {
                    $old = public_path(ltrim($existing->signature_image_path, '/'));
                    if (is_file($old)) {
                        @unlink($old);
                    }
                }

                $existing->update([
                    'user_id' => $user->id,
                    'policy_version' => $policy->version,
                    'consent_email' => $consentEmail,
                    'signature_name' => trim((string) $data['signature_name']),
                    'signature_image_path' => $signaturePath,
                    'signed_at' => now(),
                    'signature_ip' => request()?->ip(),
                    'signature_meta' => [
                        'user_agent' => request()?->userAgent(),
                    ],
                ]);

                return $existing->fresh();
            }

            return CompanyPolicyConsent::query()->create([
                'company_id' => $policy->company_id,
                'company_policy_id' => $policy->id,
                'employee_id' => $employee->id,
                'user_id' => $user->id,
                'policy_version' => $policy->version,
                'consent_email' => $consentEmail,
                'signature_name' => trim((string) $data['signature_name']),
                'signature_image_path' => $signaturePath,
                'signed_at' => now(),
                'signature_ip' => request()?->ip(),
                'signature_meta' => [
                    'user_agent' => request()?->userAgent(),
                ],
            ]);
        });
    }

    public function consentSummaryForPolicy(User $user, CompanyPolicy $policy): array
    {
        $this->assertCanManage($user);
        $this->assertSameCompany($user, $policy);

        $employee = $this->employeeAccessService->linkedEmployee($user);
        $consent = null;

        if ($employee) {
            $consent = CompanyPolicyConsent::query()
                ->where('company_policy_id', $policy->id)
                ->where('employee_id', $employee->id)
                ->first();
        }

        $totalConsents = CompanyPolicyConsent::query()
            ->where('company_policy_id', $policy->id)
            ->where('policy_version', $policy->version)
            ->count();

        return [
            'consent_count' => $totalConsents,
            'my_consent' => $consent,
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function assertPublishable(array $data): void
    {
        $status = $data['status'] ?? CompanyPolicy::STATUS_DRAFT;
        $body = trim(strip_tags((string) ($data['body_html'] ?? '')));

        if ($status === CompanyPolicy::STATUS_PUBLISHED && $body === '') {
            throw ValidationException::withMessages([
                'body_html' => ['Add policy page content before publishing.'],
            ]);
        }
    }

    private function storeSignatureImage(CompanyPolicy $policy, UploadedFile $file): string
    {
        $relativeDirectory = CompanyPolicyConsent::PUBLIC_UPLOAD_DIR.'/'.$policy->company_id;
        $dir = $this->uploadDirectories->ensure($relativeDirectory);
        $filename = 'policy-'.$policy->id.'-'.time().'.png';
        $file->move($dir, $filename);

        return $relativeDirectory.'/'.$filename;
    }

    private function storeSignatureFromDataUrl(CompanyPolicy $policy, string $dataUrl): ?string
    {
        if (! preg_match('#^data:image/(png|jpeg|jpg);base64,#i', $dataUrl)) {
            return null;
        }

        $binary = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl), true);
        if ($binary === false) {
            return null;
        }

        $relativeDirectory = CompanyPolicyConsent::PUBLIC_UPLOAD_DIR.'/'.$policy->company_id;
        $dir = $this->uploadDirectories->ensure($relativeDirectory);
        $filename = 'policy-'.$policy->id.'-'.time().'_'.Str::lower(Str::random(8)).'.png';
        file_put_contents($dir.'/'.$filename, $binary);

        return $relativeDirectory.'/'.$filename;
    }

    private function assertCanManage(User $user): void
    {
        if (! $user->canManageDocuments()) {
            throw new AccessDeniedHttpException('You are not allowed to manage company policies.');
        }
    }

    private function assertSameCompany(User $user, CompanyPolicy $policy): void
    {
        if ((int) $policy->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Policy not found.');
        }
    }
}
