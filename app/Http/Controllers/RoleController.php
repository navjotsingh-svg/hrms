<?php

namespace App\Http\Controllers;

class RoleController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->canManageRoles(), 403);

        return view('roles.index');
    }

    public function show(int $role)
    {
        abort_unless(auth()->user()->canManageRoles(), 403);

        return view('roles.show', ['roleId' => $role]);
    }
}
