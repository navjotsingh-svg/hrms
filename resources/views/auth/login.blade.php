@extends('layouts.guest')

@section('title', 'Sign In - ' . config('app.name', 'HRMS'))

@section('content')
    <div class="text-center mb-4">
        <h4 class="fw-bold text-dark mb-1">Welcome Back</h4>
        <p class="text-muted small mb-0">Sign in to access your HRMS dashboard</p>
    </div>

    <div id="loginAlert" class="alert alert-danger py-2 small mb-4 d-none" role="alert"></div>

    <form id="loginForm" autocomplete="on" onsubmit="event.preventDefault(); return false;">
        <div class="mb-3">
            <label for="email" class="form-label fw-medium text-secondary">{{ __('Email Address') }}</label>
            <input
                id="email"
                type="email"
                name="email"
                class="form-control"
                placeholder="admin@hrms.com"
                required
                autofocus
                autocomplete="username"
            >
        </div>

        <div class="mb-3">
            <label for="password" class="form-label fw-medium text-secondary">{{ __('Password') }}</label>
            <input
                id="password"
                type="password"
                name="password"
                class="form-control"
                placeholder="Enter your password"
                required
                autocomplete="current-password"
            >
        </div>

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="form-check">
                <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
                <label for="remember_me" class="form-check-label small">{{ __('Remember me') }}</label>
            </div>

            @if (Route::has('password.request'))
                <a class="small text-decoration-none" href="{{ route('password.request') }}">
                    {{ __('Forgot password?') }}
                </a>
            @endif
        </div>

        <button type="button" id="loginSubmitBtn" class="btn btn-primary w-100 py-2">
            {{ __('Sign In') }}
        </button>
    </form>

    <p class="text-center auth-divider-text mt-4 mb-0">
        Authorized personnel only. Contact your administrator for access.
    </p>

    @vite(['resources/js/auth.js'])
@endsection
