<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PerformanceController extends Controller
{
    public function overview(): View
    {
        return view('performance.overview', $this->pageData('overview'));
    }

    public function praiseRecognition(): View
    {
        $user = auth()->user();

        return view('performance.praise-recognition', array_merge($this->pageData('praise-recognition'), [
            'canPostPraise' => $user->hasPermission('home.moments.post') || $user->hasPermission('performance.participate'),
            'canManagePraise' => $user->canManagePerformance(),
        ]));
    }

    public function continuousFeedback(): View
    {
        return view('performance.feedback-forms', $this->pageData('continuous-feedback'));
    }

    public function oneOnOne(): View
    {
        $user = auth()->user();

        return view('performance.one-on-one', array_merge($this->pageData('one-on-one'), [
            'canScheduleMeetings' => app(\App\Services\OneOnOneMeetingService::class)->canSchedule($user),
        ]));
    }

    public function reviews(): View
    {
        return view('performance.overview', $this->pageData('reviews'));
    }

    public function calibration(): View
    {
        return view('performance.calibration', $this->pageData('calibration'));
    }

    public function promotions(): View
    {
        return view('performance.promotions', array_merge($this->pageData('promotions'), [
            'canNominate' => auth()->user()->canManagePerformance() || auth()->user()->canReviewPerformance(),
        ]));
    }

    public function insights(): View
    {
        return view('performance.insights', $this->pageData('insights'));
    }

    public function compensation(): View
    {
        return view('performance.compensation', $this->pageData('compensation'));
    }

    public function skills(): View
    {
        return view('performance.skills', $this->pageData('skills'));
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

    private function placeholder(string $page, string $title, string $description): View
    {
        return view('performance.placeholder', array_merge($this->pageData($page), [
            'featureTitle' => $title,
            'featureDescription' => $description,
        ]));
    }

    /** @return array<string, mixed> */
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
