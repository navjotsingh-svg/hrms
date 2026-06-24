<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RequestHubController extends Controller
{
    public function index(): View
    {
        return view('requests.index');
    }

    public function show(string $category, string $id): View|RedirectResponse
    {
        if ($category === 'leave') {
            return redirect()->route('web.leave.show', (int) $id);
        }

        return view('requests.show', [
            'category' => $category,
            'entityId' => $id,
        ]);
    }
}
