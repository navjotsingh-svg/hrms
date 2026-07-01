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
            <div class="input-group auth-password-group">
                <input
                    id="password"
                    type="password"
                    name="password"
                    class="form-control"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                >
                <button
                    type="button"
                    class="btn btn-outline-secondary auth-password-toggle"
                    id="togglePasswordBtn"
                    aria-label="Show password"
                    aria-pressed="false"
                >
                    <span class="auth-password-toggle-icon auth-password-toggle-icon--show" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                            <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                        </svg>
                    </span>
                    <span class="auth-password-toggle-icon auth-password-toggle-icon--hide d-none" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7 7 0 0 0-2.79.588l.77.771A6 6 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486z"/>
                            <path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829"/>
                            <path d="M3.35 5.47q-.27.24-.518.487A13 13 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7 7 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12z"/>
                        </svg>
                    </span>
                </button>
            </div>
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
