<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'HRMS'))</title>
    @include('layouts.partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="auth-wrapper row g-0">
        <div class="col-lg-6 auth-brand-panel d-flex align-items-center p-4 p-lg-5">
            <div class="auth-brand-content mx-auto mx-lg-0">
                <div class="auth-logo-badge mb-4">HR</div>

                <h1 class="display-6 fw-bold mb-3">{{ config('app.name', 'HRMS') }}</h1>
                <p class="lead mb-2 opacity-90">SaaS Human Resource Management System</p>
                <p class="text-white-50 mb-4 pe-lg-3">
                    A complete cloud-based platform to manage employees, attendance, payroll,
                    leave, and HR operations — built for growing businesses and enterprises.
                </p>

                <div class="mt-4">
                    <div class="auth-feature-item">
                        <div class="auth-feature-icon">&#128101;</div>
                        <div>
                            <div class="fw-semibold">Employee Management</div>
                            <div class="small text-white-50">Centralized employee records, departments & roles</div>
                        </div>
                    </div>
                    <div class="auth-feature-item">
                        <div class="auth-feature-icon">&#128197;</div>
                        <div>
                            <div class="fw-semibold">Attendance & Leave</div>
                            <div class="small text-white-50">Track attendance, shifts, and leave approvals</div>
                        </div>
                    </div>
                    <div class="auth-feature-item">
                        <div class="auth-feature-icon">&#128176;</div>
                        <div>
                            <div class="fw-semibold">Payroll & Reports</div>
                            <div class="small text-white-50">Automated payroll with Excel export & PDF reports</div>
                        </div>
                    </div>
                    <div class="auth-feature-item">
                        <div class="auth-feature-icon">&#9729;</div>
                        <div>
                            <div class="fw-semibold">Multi-Tenant SaaS</div>
                            <div class="small text-white-50">Secure, scalable platform for multiple organizations</div>
                        </div>
                    </div>
                </div>

                <p class="small text-white-50 mt-4 mb-0">
                    &copy; {{ date('Y') }} {{ config('app.name', 'HRMS') }}. All rights reserved.
                </p>
            </div>
        </div>

        <div class="col-lg-6 auth-form-panel d-flex align-items-center justify-content-center p-4 p-lg-5">
            <div class="card auth-form-card">
                <div class="card-body p-4 p-md-5">
                    @yield('content')
                </div>
            </div>
        </div>
    </div>
    @yield('scripts')
    @stack('scripts')
</body>
</html>
