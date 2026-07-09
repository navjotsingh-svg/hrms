<?php

namespace App\Services;

use App\Models\BulkImport;
use App\Models\BulkImportRow;
use App\Models\Employee;
use App\Models\HelpdeskCategory;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AiFeatureService
{
    public function __construct(
        private AiCompletionService $ai,
        private EmployeeAssistantContextService $contextService,
        private AnalyticsReportService $analyticsReportService,
    ) {}

    public function aiEnabled(): bool
    {
        return $this->ai->enabled() && (bool) config('hrms.assistant.enabled', true);
    }

    /** @return array<string, mixed> */
    public function suggestHelpdeskTicket(User $user, string $description): array
    {
        $this->assertEnabled();
        abort_unless($user->canApplyHelpdesk(), 403);

        $categories = HelpdeskCategory::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])
            ->all();

        $priorities = collect(config('helpdesk.priorities', []))
            ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();

        if (! $this->aiEnabled()) {
            return [
                'subject' => str($description)->limit(80)->value(),
                'description' => $description,
                'priority' => 'medium',
                'helpdesk_category_id' => $categories[0]['id'] ?? null,
                'source' => 'rules',
            ];
        }

        $result = $this->ai->chatJson([
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'You triage HR helpdesk tickets. Return JSON only with keys:',
                    'subject (string, max 120), description (string), priority (low|medium|high|urgent), helpdesk_category_id (integer|null).',
                    'Categories JSON: '.json_encode($categories),
                    'Priorities JSON: '.json_encode($priorities),
                ]),
            ],
            ['role' => 'user', 'content' => $description],
        ]);

        return [
            'subject' => (string) ($result['subject'] ?? str($description)->limit(80)),
            'description' => (string) ($result['description'] ?? $description),
            'priority' => (string) ($result['priority'] ?? 'medium'),
            'helpdesk_category_id' => $result['helpdesk_category_id'] ?? ($categories[0]['id'] ?? null),
            'source' => 'ai',
        ];
    }

    /** @return array<string, mixed> */
    public function draftDocument(User $user, string $category, string $prompt, ?int $employeeId = null): array
    {
        $this->assertEnabled();
        abort_unless($user->canManageDocuments(), 403);

        $employee = $employeeId
            ? Employee::query()->where('company_id', $user->company_id)->findOrFail($employeeId)
            : null;

        $sample = config("document_letters.sample_templates.{$category}")
            ?? config('document_letters.sample_templates.offer_letter');

        if (! $this->aiEnabled()) {
            return [
                'body_html' => $sample,
                'source' => 'rules',
            ];
        }

        $content = $this->ai->chat([
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'You draft HR document letter HTML for an HRMS.',
                    'Use placeholders like {employee_name}, {designation}, {company_name}, {joining_date}, {salary}, {today_date}.',
                    'Return HTML only, professional tone, no markdown fences.',
                    'Category: '.$category,
                    'Sample structure: '.$sample,
                    $employee ? 'Employee context: '.json_encode([
                        'name' => $employee->full_name,
                        'designation' => $employee->designation,
                        'joining_date' => $employee->joining_date?->toDateString(),
                    ]) : '',
                ]),
            ],
            ['role' => 'user', 'content' => $prompt],
        ], 1200)['content'];

        return [
            'body_html' => $content,
            'source' => 'ai',
        ];
    }

    /** @return array<string, mixed> */
    public function generateJobDescription(User $user, string $title, ?string $department = null, ?string $requirements = null): array
    {
        $this->assertEnabled();
        abort_unless($user->canManageHiring(), 403);

        if (! $this->aiEnabled()) {
            $body = "<h2>{$title}</h2><p>Department: ".($department ?: '—')."</p><p>".($requirements ?: 'Add responsibilities and requirements.').'</p>';

            return ['body_html' => $body, 'source' => 'rules'];
        }

        $content = $this->ai->chat([
            [
                'role' => 'system',
                'content' => 'Write a professional job description in HTML with sections: Overview, Responsibilities, Requirements, Benefits. Return HTML only.',
            ],
            [
                'role' => 'user',
                'content' => "Title: {$title}\nDepartment: ".($department ?: 'Not specified')."\nNotes: ".($requirements ?: 'Standard corporate role'),
            ],
        ], 1200)['content'];

        return ['body_html' => $content, 'source' => 'ai'];
    }

    /** @return array<string, mixed> */
    public function suggestReviewComments(User $user, string $contextNotes, ?string $employeeName = null): array
    {
        $this->assertEnabled();
        abort_unless($user->canReviewPerformance(), 403);

        if (! $this->aiEnabled()) {
            return [
                'comments' => 'Based on the provided notes: '.$contextNotes,
                'source' => 'rules',
            ];
        }

        $content = $this->ai->chat([
            [
                'role' => 'system',
                'content' => 'You help managers write fair, constructive performance review comments. Be specific, balanced, and professional. Plain text only.',
            ],
            [
                'role' => 'user',
                'content' => 'Employee: '.($employeeName ?: 'Team member')."\nNotes:\n".$contextNotes,
            ],
        ])['content'];

        return ['comments' => $content, 'source' => 'ai'];
    }

    /** @return array<string, mixed> */
    public function suggestOneOnOneAgenda(User $user, ?string $employeeName = null, ?string $notes = null): array
    {
        $this->assertEnabled();
        abort_unless($user->canParticipateInPerformance(), 403);

        if (! $this->aiEnabled()) {
            return [
                'agenda' => "- Check-in and priorities\n- Blockers\n- Career development\n- Action items",
                'source' => 'rules',
            ];
        }

        $content = $this->ai->chat([
            [
                'role' => 'system',
                'content' => 'Create a concise 1:1 meeting agenda with bullet points. Plain text markdown bullets allowed.',
            ],
            [
                'role' => 'user',
                'content' => 'Employee: '.($employeeName ?: 'Direct report')."\nContext:\n".($notes ?: 'Regular weekly 1:1'),
            ],
        ])['content'];

        return ['agenda' => $content, 'source' => 'ai'];
    }

    /** @return array<string, mixed> */
    public function explainBulkImportErrors(User $user, BulkImport $bulkImport): array
    {
        $this->assertEnabled();
        abort_unless($user->canManageEmployees(), 403);

        if ((int) $bulkImport->company_id !== (int) $user->company_id) {
            throw new AccessDeniedHttpException('Import not found.');
        }

        $failedRows = BulkImportRow::query()
            ->where('bulk_import_id', $bulkImport->id)
            ->where('status', 'failed')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'row_number' => $row->row_number,
                'error_message' => $row->error_message,
                'raw_data' => $row->raw_data,
            ])
            ->all();

        if ($failedRows === []) {
            return [
                'explanation' => 'No failed rows found for this import.',
                'source' => 'rules',
            ];
        }

        if (! $this->aiEnabled()) {
            return [
                'explanation' => collect($failedRows)->map(fn ($row) => 'Row '.$row['row_number'].': '.($row['error_message'] ?? 'Unknown error'))->implode("\n"),
                'source' => 'rules',
            ];
        }

        $content = $this->ai->chat([
            [
                'role' => 'system',
                'content' => 'Explain bulk import errors in plain language for an HR admin. Group similar issues and suggest fixes. Plain text.',
            ],
            ['role' => 'user', 'content' => json_encode($failedRows, JSON_UNESCAPED_UNICODE)],
        ], 900)['content'];

        return ['explanation' => $content, 'source' => 'ai'];
    }

    /** @return array<string, mixed> */
    public function summarizeAnalytics(User $user, string $reportKey, array $filters = []): array
    {
        $this->assertEnabled();

        $report = $this->analyticsReportService->run($user, $reportKey, $filters);
        $snapshot = json_encode($report, JSON_UNESCAPED_UNICODE);

        if (! $this->aiEnabled()) {
            return [
                'summary' => 'Analytics data loaded. Enable OpenAI for an AI-generated narrative summary.',
                'report' => $report,
                'source' => 'rules',
            ];
        }

        $content = $this->ai->chat([
            [
                'role' => 'system',
                'content' => 'Summarize HR analytics data for leadership. Highlight trends, outliers, and actionable insights. Use markdown bullets. Only use provided data.',
            ],
            ['role' => 'user', 'content' => $snapshot],
        ], 900)['content'];

        return [
            'summary' => $content,
            'report_key' => $reportKey,
            'source' => 'ai',
        ];
    }

    /** @return array<string, mixed> */
    public function adviseRolePermissions(User $user, string $roleName, ?string $description = null): array
    {
        $this->assertEnabled();
        abort_unless($user->hasPermission('roles.manage'), 403);

        $catalog = config('hrms.permission_catalog', []);

        if (! $this->aiEnabled()) {
            return [
                'suggestions' => 'Enable OpenAI for permission recommendations, or configure permissions manually in the role matrix.',
                'source' => 'rules',
            ];
        }

        $content = $this->ai->chat([
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'You advise which HRMS permission slugs fit a new role.',
                    'Return JSON: {"summary":"...", "recommended_permission_slugs":["slug1","slug2"]}',
                    'Only pick slugs that exist in this catalog JSON:',
                    json_encode($catalog, JSON_UNESCAPED_UNICODE),
                ]),
            ],
            [
                'role' => 'user',
                'content' => "Role name: {$roleName}\nDescription: ".($description ?: 'Custom company role'),
            ],
        ], 900);

        try {
            $parsed = $this->ai->chatJson([
                [
                    'role' => 'system',
                    'content' => 'Return JSON only: {"summary":"...","recommended_permission_slugs":[]}. Pick from catalog slugs only.',
                ],
                [
                    'role' => 'user',
                    'content' => "Role: {$roleName}\nDescription: ".($description ?: '')."\nCatalog: ".json_encode($catalog),
                ],
            ]);

            return [
                'summary' => $parsed['summary'] ?? $content['content'],
                'recommended_permission_slugs' => $parsed['recommended_permission_slugs'] ?? [],
                'source' => 'ai',
            ];
        } catch (\Throwable) {
            return [
                'summary' => $content['content'],
                'recommended_permission_slugs' => [],
                'source' => 'ai',
            ];
        }
    }

    /** @return array<string, mixed> */
    public function scanDataQuality(User $user): array
    {
        $this->assertEnabled();
        abort_unless($user->canManageEmployees(), 403);

        $report = $this->contextService->dataQualitySummary((int) $user->company_id);

        if (! $this->aiEnabled()) {
            return [
                'report' => $report,
                'summary' => "Found {$report['incomplete_profiles']} incomplete profile(s) out of {$report['active_employees']} active employees.",
                'source' => 'rules',
            ];
        }

        $content = $this->ai->chat([
            [
                'role' => 'system',
                'content' => 'Summarize employee data quality issues and recommend remediation steps for HR. Plain text with bullets.',
            ],
            ['role' => 'user', 'content' => json_encode($report, JSON_UNESCAPED_UNICODE)],
        ])['content'];

        return [
            'report' => $report,
            'summary' => $content,
            'source' => 'ai',
        ];
    }

    /** @return array<string, mixed> */
    public function askPolicy(User $user, string $question): array
    {
        $this->assertEnabled();

        $context = $this->contextService->buildForUser($user);
        $policies = $context['company_policies'] ?? [];

        if (! $this->aiEnabled()) {
            if ($policies === []) {
                return [
                    'answer' => 'No signed policy documents are available yet. Please contact HR.',
                    'source' => 'rules',
                ];
            }

            return [
                'answer' => 'Policy documents on file: '.collect($policies)->pluck('title')->implode(', ').'. Enable OpenAI for detailed policy Q&A.',
                'source' => 'rules',
            ];
        }

        $content = $this->ai->chat([
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'Answer policy questions using ONLY the policy documents JSON below.',
                    'If the answer is not in the documents, say you do not have that information and suggest contacting HR.',
                    'Policies JSON:',
                    json_encode($policies, JSON_UNESCAPED_UNICODE),
                ]),
            ],
            ['role' => 'user', 'content' => $question],
        ])['content'];

        return ['answer' => $content, 'source' => 'ai'];
    }

    private function assertEnabled(): void
    {
        if (! config('hrms.assistant.enabled', true)) {
            throw new AccessDeniedHttpException('AI features are not enabled.');
        }
    }
}
