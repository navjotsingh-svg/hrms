<?php

namespace App\Services;

use App\Models\DocumentLetterTemplate;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DocumentLetterTemplateService
{
    public function listForCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = DocumentLetterTemplate::query()
            ->where('company_id', $companyId)
            ->latest();

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function create(User $user, array $data): DocumentLetterTemplate
    {
        $this->assertCanManage($user);

        return DB::transaction(function () use ($user, $data) {
            if (! empty($data['is_default'])) {
                $this->clearDefault($user->company_id, $data['category'] ?? 'other');
            }

            return DocumentLetterTemplate::query()->create([
                'company_id' => $user->company_id,
                'name' => trim($data['name']),
                'category' => $data['category'],
                'subject' => $data['subject'] ?? null,
                'description' => $data['description'] ?? null,
                'body_html' => $data['body_html'],
                'requires_signature' => $data['requires_signature'] ?? true,
                'is_default' => $data['is_default'] ?? false,
                'status' => $data['status'] ?? 'active',
                'created_by_user_id' => $user->id,
            ]);
        });
    }

    public function update(User $user, DocumentLetterTemplate $template, array $data): DocumentLetterTemplate
    {
        $this->assertCanManage($user);
        $this->assertSameCompany($user, $template);

        return DB::transaction(function () use ($user, $template, $data) {
            if (! empty($data['is_default'])) {
                $this->clearDefault($user->company_id, $data['category'] ?? $template->category, $template->id);
            }

            $template->update([
                'name' => trim($data['name'] ?? $template->name),
                'category' => $data['category'] ?? $template->category,
                'subject' => array_key_exists('subject', $data) ? $data['subject'] : $template->subject,
                'description' => array_key_exists('description', $data) ? $data['description'] : $template->description,
                'body_html' => $data['body_html'] ?? $template->body_html,
                'requires_signature' => array_key_exists('requires_signature', $data)
                    ? (bool) $data['requires_signature']
                    : $template->requires_signature,
                'is_default' => $data['is_default'] ?? $template->is_default,
                'status' => $data['status'] ?? $template->status,
            ]);

            return $template->fresh();
        });
    }

    public function preview(User $user, DocumentLetterTemplate $template, ?int $employeeId = null, array $custom = []): array
    {
        $this->assertSameCompany($user, $template);

        $employee = $employeeId
            ? Employee::query()->where('company_id', $user->company_id)->findOrFail($employeeId)
            : null;

        return app(DocumentLetterService::class)->renderHtml($template->body_html, $employee, $user, $custom);
    }

    private function clearDefault(int $companyId, string $category, ?int $exceptId = null): void
    {
        DocumentLetterTemplate::query()
            ->where('company_id', $companyId)
            ->where('category', $category)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->update(['is_default' => false]);
    }

    private function assertCanManage(User $user): void
    {
        if (! $user->canManageDocuments()) {
            throw new AccessDeniedHttpException('You are not allowed to manage document templates.');
        }
    }

    private function assertSameCompany(User $user, DocumentLetterTemplate $template): void
    {
        if ((int) $user->company_id !== (int) $template->company_id) {
            abort(404);
        }
    }
}
