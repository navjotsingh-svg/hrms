<?php

namespace App\Http\Controllers;

class HelpdeskController extends Controller
{
    public function index()
    {
        return view('helpdesk.index');
    }

    public function create()
    {
        return view('helpdesk.create');
    }

    public function show(int $ticket)
    {
        return view('helpdesk.show', ['ticketId' => $ticket]);
    }
}
