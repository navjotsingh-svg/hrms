<?php

namespace App\Http\Controllers;

class DocumentTypeController extends Controller
{
    public function index()
    {
        return view('documents.index');
    }

    public function create()
    {
        return view('documents.create');
    }

    public function edit(int $documentType)
    {
        return view('documents.edit', ['documentTypeId' => $documentType]);
    }
}
