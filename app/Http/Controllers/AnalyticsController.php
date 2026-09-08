<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsCatalogService;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private AnalyticsCatalogService $catalogService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $sectionKey = $this->catalogService->firstAccessibleSectionKey($user);

        if (! $sectionKey) {
            abort(403);
        }

        return redirect()->route('web.analytics.section', ['section' => $sectionKey]);
    }

    public function section(Request $request, string $section)
    {
        $user = $request->user();

        if (! $this->catalogService->canAccessSection($user, $section)) {
            abort(403);
        }

        $reports = $this->catalogService->reportsForSection($user, $section);
        $sections = $this->catalogService->sectionsForUser($user);
        $sectionLabel = collect($sections)->firstWhere('key', $section)['label'] ?? ucfirst($section);

        return view('analytics.index', [
            'activeSection' => $section,
            'sectionLabel' => $sectionLabel,
            'reports' => $reports,
            'sections' => $sections,
        ]);
    }

    public function report(Request $request, string $reportKey)
    {
        $user = $request->user();
        $definition = $this->catalogService->findReportDefinition($reportKey);

        if (! $definition || ! $this->catalogService->canAccessReport($user, $reportKey)) {
            abort(403);
        }

        if (! empty($definition['dedicated_route'])) {
            return redirect()->route($definition['dedicated_route']);
        }

        $sections = $this->catalogService->sectionsForUser($user);

        return view('analytics.report', [
            'reportKey' => $reportKey,
            'reportName' => $definition['name'],
            'reportDescription' => $definition['description'],
            'sectionKey' => $definition['section_key'] ?? null,
            'filters' => $definition['filters'] ?? [],
            'exportType' => $definition['export'] ?? 'csv',
            'sections' => $sections,
        ]);
    }

    public function leaveBalances(Request $request)
    {
        if (! $request->user()->canViewLeaveAnalytics()) {
            abort(403);
        }

        return view('analytics.leave-balances', [
            'sections' => $this->catalogService->sectionsForUser($request->user()),
            'activeSection' => 'leave',
        ]);
    }
}
