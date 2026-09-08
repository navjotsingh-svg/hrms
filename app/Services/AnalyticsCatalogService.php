<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AnalyticsCatalogService
{
    /** @return array<int, array{key: string, label: string, reports: array<int, array<string, mixed>>}> */
    public function sectionsForUser(User $user): array
    {
        return collect(config('analytics.sections', []))
            ->map(function (array $section) use ($user) {
                $reports = collect($section['reports'] ?? [])
                    ->filter(fn (array $report) => $this->canAccessReport($user, $report['key']))
                    ->map(fn (array $report) => $this->formatReport($report))
                    ->values()
                    ->all();

                if ($reports === []) {
                    return null;
                }

                return [
                    'key' => $section['key'],
                    'label' => $section['label'],
                    'menu_key' => $section['menu_key'] ?? null,
                    'reports' => $reports,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function reportsForSection(User $user, string $sectionKey): array
    {
        $section = collect($this->sectionsForUser($user))->firstWhere('key', $sectionKey);

        return $section['reports'] ?? [];
    }

    public function firstAccessibleSectionKey(User $user): ?string
    {
        $sections = $this->sectionsForUser($user);

        return $sections[0]['key'] ?? null;
    }

    public function canAccessReport(User $user, string $reportKey): bool
    {
        $report = $this->findReportDefinition($reportKey);

        if (! $report) {
            return false;
        }

        $method = $report['access'] ?? null;

        if (! $method || ! method_exists($user, $method)) {
            return false;
        }

        return (bool) $user->{$method}();
    }

    public function canAccessSection(User $user, string $sectionKey): bool
    {
        return $this->reportsForSection($user, $sectionKey) !== [];
    }

    /** @return array<string, mixed> */
    public function reportDefinition(string $reportKey): array
    {
        $report = $this->findReportDefinition($reportKey);

        if (! $report) {
            throw ValidationException::withMessages(['report' => ['Unknown analytics report.']]);
        }

        return $report;
    }

    /** @return array<string, mixed>|null */
    public function findReportDefinition(string $reportKey): ?array
    {
        foreach (config('analytics.sections', []) as $section) {
            foreach ($section['reports'] ?? [] as $report) {
                if (($report['key'] ?? null) === $reportKey) {
                    return array_merge($report, [
                        'section_key' => $section['key'],
                        'section_label' => $section['label'],
                    ]);
                }
            }
        }

        return null;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function allReportDefinitions(): Collection
    {
        return collect(config('analytics.sections', []))
            ->flatMap(fn (array $section) => collect($section['reports'] ?? [])
                ->map(fn (array $report) => array_merge($report, [
                    'section_key' => $section['key'],
                    'section_label' => $section['label'],
                ])));
    }

    /** @param  array<string, mixed>  $report */
    private function formatReport(array $report): array
    {
        return [
            'key' => $report['key'],
            'name' => $report['name'],
            'description' => $report['description'],
            'filters' => $report['filters'] ?? [],
            'export' => $report['export'] ?? 'csv',
            'dedicated_route' => $report['dedicated_route'] ?? null,
            'section_key' => $report['section_key'] ?? null,
        ];
    }
}
