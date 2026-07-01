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
        return $this->placeholder(
            'praise-recognition',
            'Praise & Recognition',
            'Celebrate achievements, send kudos to colleagues, and build a culture of recognition across your organization.',
        );
    }

    public function continuousFeedback(): View
    {
        return view('performance.feedback-forms', $this->pageData('continuous-feedback'));
    }

    public function oneOnOne(): View
    {
        return $this->placeholder(
            'one-on-one',
            'One-on-one Meetings',
            'Schedule, track, and document manager–employee one-on-one meetings with shared agendas and action items.',
        );
    }

    public function reviews(): View
    {
        return view('performance.overview', $this->pageData('reviews'));
    }

    public function calibration(): View
    {
        return $this->placeholder(
            'calibration',
            'Performance Calibration',
            'Align ratings across teams, compare review outcomes, and finalize performance scores before compensation cycles.',
        );
    }

    public function promotions(): View
    {
        return $this->placeholder(
            'promotions',
            'Promotions',
            'Manage promotion nominations, approvals, and role changes tied to performance outcomes.',
        );
    }

    public function insights(): View
    {
        return view('performance.insights', $this->pageData('insights'));
    }

    public function compensation(): View
    {
        return $this->placeholder(
            'compensation',
            'Basic Compensation Plans',
            'Define salary bands, merit increase guidelines, and link compensation planning to review results.',
        );
    }

    public function skills(): View
    {
        return $this->placeholder(
            'skills',
            'Skills and Competencies',
            'Map role competencies, track employee skill profiles, and identify development gaps.',
        );
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
