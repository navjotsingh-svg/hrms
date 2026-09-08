<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class EmployeeExperienceController extends Controller
{
    public function socialWall(): View
    {
        return view('employee-experience.social-wall', [
            'experiencePage' => 'social-wall',
        ]);
    }

    public function pollsAnnouncements(): View
    {
        return view('employee-experience.polls-announcements', [
            'experiencePage' => 'polls-announcements',
        ]);
    }

    public function publicPraise(): View
    {
        return view('employee-experience.public-praise', [
            'experiencePage' => 'public-praise',
        ]);
    }
}
