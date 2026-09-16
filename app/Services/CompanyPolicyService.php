<?php

namespace App\Services;

use App\Models\CompanyPolicy;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CompanyPolicyService
{
    public function __construct(
        private PublicUploadDirectoryService $uploadDirectories,
    ) {}

    public function listForCompany(User $user, array $filters = []): LengthAwarePaginator
    {
        $canManage = $user->canManageDocuments();

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
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('original_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('updated_at');

        $perPage = (int) ($filters['per_page'] ?? 10);

        return $query->paginate($perPage);
    }

    public function create(User $user, array $data, UploadedFile $file): CompanyPolicy
    {
        $this->assertCanManage($user);

        $stored = $this->storeFile($user, $file);

        return CompanyPolicy::query()->create([
            'company_id' => $user->company_id,
            'title' => $data['title'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $stored['path'],
            'mime_type' => $stored['mime_type'],
            'file_size' => $stored['file_size'],
            'status' => $data['status'] ?? CompanyPolicy::STATUS_PUBLISHED,
            'version' => 1,
            'uploaded_by_user_id' => $user->id,
        ])->load('uploadedBy');
    }

    public function update(User $user, CompanyPolicy $policy, array $data, ?UploadedFile $file = null): CompanyPolicy
    {
        $this->assertCanManage($user);
        $this->assertSameCompany($user, $policy);

        $payload = [
            'title' => $data['title'] ?? $policy->title,
            'category' => $data['category'] ?? $policy->category,
            'description' => array_key_exists('description', $data) ? $data['description'] : $policy->description,
            'status' => $data['status'] ?? $policy->status,
        ];

        if ($file) {
            $policy->deleteFile();
            $stored = $this->storeFile($user, $file);
            $payload['original_name'] = $file->getClientOriginalName();
            $payload['file_path'] = $stored['path'];
            $payload['mime_type'] = $stored['mime_type'];
            $payload['file_size'] = $stored['file_size'];
            $payload['version'] = $policy->version + 1;
            $payload['uploaded_by_user_id'] = $user->id;
        }

        $policy->update($payload);

        return $policy->fresh()->load('uploadedBy');
    }

    public function delete(User $user, CompanyPolicy $policy): void
    {
        $this->assertCanManage($user);
        $this->assertSameCompany($user, $policy);

        $policy->deleteFile();
        $policy->delete();
    }

    public function resolveForUser(User $user, CompanyPolicy $policy): CompanyPolicy
    {
        $this->assertSameCompany($user, $policy);

        if (! $user->canManageDocuments() && ! $policy->isPublished()) {
            throw new NotFoundHttpException('Policy not found.');
        }

        return $policy->loadMissing('uploadedBy');
    }

    /** @return array{path: string, mime_type: ?string, file_size: int} */
    private function storeFile(User $user, UploadedFile $file): array
    {
        $allowed = config('company_policies.allowed_mimes', []);
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($allowed !== [] && ! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => ['Unsupported file type. Allowed: '.implode(', ', $allowed).'.'],
            ]);
        }

        $relativeDirectory = CompanyPolicy::PUBLIC_UPLOAD_DIR.'/'.$user->company_id;
        $absoluteDirectory = $this->uploadDirectories->ensure($relativeDirectory);
        $filename = time().'_'.Str::lower(Str::random(13)).($extension ? '.'.$extension : '');
        $mimeType = $file->getClientMimeType() ?: $file->getMimeType();
        $fileSize = (int) $file->getSize();

        $file->move($absoluteDirectory, $filename);

        return [
            'path' => $relativeDirectory.'/'.$filename,
            'mime_type' => $mimeType,
            'file_size' => $fileSize > 0 ? $fileSize : (int) (filesize($absoluteDirectory.'/'.$filename) ?: 0),
        ];
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
