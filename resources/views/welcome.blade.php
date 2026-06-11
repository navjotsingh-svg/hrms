<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'HRMS') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-light">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand fw-semibold" href="/">{{ config('app.name', 'HRMS') }}</a>
                <div class="d-flex gap-2">
                    @auth
                        <a href="{{ route('web.dashboard') }}" class="btn btn-light btn-sm">{{ __('Dashboard') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-outline-light btn-sm">{{ __('Log in') }}</a>
                    @endauth
                </div>
            </div>
        </nav>

        <section class="py-5">
            <div class="container py-5">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <h1 class="display-5 fw-bold mb-3">SaaS Based HRMS</h1>
                        <p class="lead text-muted mb-4">
                            Manage employees, attendance, payroll, and leave — all in one modern human resource management platform.
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            @auth
                                <a href="{{ route('web.dashboard') }}" class="btn btn-primary btn-lg">Go to Dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Get Started</a>
                            @endauth
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h5 class="card-title fw-semibold mb-3">Core Modules</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item px-0">Employee Management</li>
                                    <li class="list-group-item px-0">Attendance Tracking</li>
                                    <li class="list-group-item px-0">Leave Management</li>
                                    <li class="list-group-item px-0">Payroll & Reports</li>
                                    <li class="list-group-item px-0">Excel Export & PDF Generation</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <footer class="bg-white border-top py-4 mt-auto">
            <div class="container text-center text-muted small">
                &copy; {{ date('Y') }} {{ config('app.name', 'HRMS') }}. All rights reserved.
            </div>
        </footer>
    </body>
</html>
