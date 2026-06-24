<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PerformanceController extends Controller
{
    public function overview(): View
    {
        return view('performance.overview', $this->pageData('overview'));
    }

    public function reviewCycles(): View
    {
        return view('performance.review-cycles', $this->pageData('review-cycles'));
    }

    public function feedbackForms(): View
    {
        return view('performance.feedback-forms', $this->pageData('feedback-forms'));
    }

    public function questionBank(): View
    {
        return view('performance.question-bank', $this->pageData('question-bank'));
    }

    public function goals(): View
    {
        return view('performance.goals', $this->pageData('goals'));
    }

    public function kpi(): View
    {
        return view('performance.kpi', $this->pageData('kpi'));
    }

    public function pip(): View
    {
        return view('performance.pip', $this->pageData('pip'));
    }

    private function pageData(string $page): array
    {
        return [
            'performancePage' => $page,
            'canManage' => auth()->user()->canManagePerformance(),
            'canReview' => auth()->user()->canReviewPerformance(),
            'canManagePips' => auth()->user()->canManagePips(),
        ];
    }
}
