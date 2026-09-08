<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ExpenseController extends Controller
{
    public function index(): View
    {
        if (! auth()->user()?->canViewExpenses()) {
            throw new AccessDeniedHttpException('You are not allowed to view expenses.');
        }

        return view('expenses.index');
    }
}
