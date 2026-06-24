<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HiringController extends Controller
{
    public function overview(): View
    {
        return view('hiring.overview', $this->pageData('overview'));
    }

    public function requisitions(): View
    {
        return view('hiring.requisitions', $this->pageData('requisitions'));
    }

    public function jobs(): View
    {
        return view('hiring.jobs', $this->pageData('jobs'));
    }

    public function candidates(): View
    {
        return view('hiring.candidates', $this->pageData('candidates'));
    }

    public function interviews(): View
    {
        return view('hiring.interviews', $this->pageData('interviews'));
    }

    public function offers(): View
    {
        return view('hiring.offers', $this->pageData('offers'));
    }

    public function templates(): View
    {
        return view('hiring.templates', $this->pageData('templates'));
    }

    public function careers(): View
    {
        return view('hiring.careers', $this->pageData('careers'));
    }

    private function pageData(string $page): array
    {
        $user = auth()->user();

        return [
            'hiringPage' => $page,
            'canManage' => $user->canManageHiring(),
            'canCreateRequisition' => $user->canCreateRequisition(),
            'canApproveRequisitions' => $user->canApproveRequisitions(),
            'canInterview' => $user->canInterviewCandidates(),
            'canPublishCareers' => $user->canPublishCareers(),
            'companySlug' => $user->company?->slug,
        ];
    }
}
