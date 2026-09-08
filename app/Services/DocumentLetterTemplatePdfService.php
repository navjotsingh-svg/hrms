<?php

namespace App\Services;

use App\Models\DocumentLetterTemplate;
use App\Models\Employee;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class DocumentLetterTemplatePdfService
{
    public function __construct(
        private DocumentLetterTemplateService $templateService,
    ) {}

    public function render(
        User $user,
        DocumentLetterTemplate $template,
        ?int $employeeId = null,
        array $custom = [],
    ): \Barryvdh\DomPDF\PDF {
        $preview = $this->templateService->preview($user, $template, $employeeId, $custom);
        $company = $user->company;
        $logoPath = $this->resolveLogoPath($company?->logo);

        return Pdf::loadView('documents-letters.template-pdf', [
            'title' => $template->name,
            'subject' => $template->subject,
            'bodyHtml' => $preview['html'],
            'company' => $company,
            'companyLegalName' => $company?->legal_name ?? $company?->name ?? config('app.name'),
            'logoPath' => $logoPath,
        ])->setPaper('a4', 'portrait');
    }

    public function renderDraft(
        User $user,
        string $bodyHtml,
        ?string $title = null,
        ?string $subject = null,
        ?int $employeeId = null,
        array $custom = [],
    ): \Barryvdh\DomPDF\PDF {
        $employee = $employeeId
            ? Employee::query()->where('company_id', $user->company_id)->findOrFail($employeeId)
            : null;

        $preview = app(DocumentLetterService::class)->renderHtml($bodyHtml, $employee, $user, $custom);
        $company = $user->company;
        $logoPath = $this->resolveLogoPath($company?->logo);

        return Pdf::loadView('documents-letters.template-pdf', [
            'title' => $title ?: 'Document Preview',
            'subject' => $subject,
            'bodyHtml' => $preview['html'],
            'company' => $company,
            'companyLegalName' => $company?->legal_name ?? $company?->name ?? config('app.name'),
            'logoPath' => $logoPath,
        ])->setPaper('a4', 'portrait');
    }

    public function inline(
        User $user,
        DocumentLetterTemplate $template,
        ?int $employeeId = null,
        array $custom = [],
    ): Response {
        return $this->render($user, $template, $employeeId, $custom)
            ->stream($this->filename($template));
    }

    public function inlineDraft(
        User $user,
        string $bodyHtml,
        ?string $title = null,
        ?string $subject = null,
        ?int $employeeId = null,
        array $custom = [],
    ): Response {
        $safeTitle = preg_replace('/[^\w\-]+/', '-', strtolower($title ?: 'preview')) ?: 'preview';

        return $this->renderDraft($user, $bodyHtml, $title, $subject, $employeeId, $custom)
            ->stream("template-{$safeTitle}.pdf");
    }

    private function filename(DocumentLetterTemplate $template): string
    {
        $slug = preg_replace('/[^\w\-]+/', '-', strtolower($template->name)) ?: 'template';

        return "template-{$slug}.pdf";
    }

    private function resolveLogoPath(?string $logo): ?string
    {
        if (! $logo) {
            return null;
        }

        $candidate = public_path(ltrim($logo, '/'));

        return is_file($candidate) ? $candidate : null;
    }
}
