<?php

namespace App\Http\Controllers;

class RoleController extends Controller
{
    public function index()
    {
        return view('roles.index');
    }

    public function show(int $role)
    {
        return view('roles.show', ['roleId' => $role]);
    }
}
