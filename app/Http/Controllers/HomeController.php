<?php



namespace App\Http\Controllers;



use Illuminate\Http\RedirectResponse;

use Illuminate\View\View;



class HomeController extends Controller

{

    public function index(): View

    {

        return view('dashboard');

    }



    public function dashboard(): RedirectResponse

    {

        return redirect()->to(route('web.home.index').'#home-analytics');

    }



    public function moments(): RedirectResponse

    {

        return redirect()->to(route('web.home.index').'#home-moments');

    }

}

