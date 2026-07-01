<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DocumentLettersController extends Controller
{
    public function index(): View
    {
        return view('documents-letters.index', [
            'canManage' => auth()->user()->canManageDocuments(),
        ]);
    }

    public function show(int $letter): View
    {
        return view('documents-letters.show', [
            'letterId' => $letter,
            'canManage' => auth()->user()->canManageDocuments(),
        ]);
    }
}
