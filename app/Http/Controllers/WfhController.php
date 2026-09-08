<?php

namespace App\Http\Controllers;

class WfhController extends Controller
{
    public function index()
    {
        return view('wfh.index');
    }

    public function create()
    {
        return view('wfh.create');
    }

    public function show(int $wfh)
    {
        return view('wfh.show', ['wfhId' => $wfh]);
    }
}
