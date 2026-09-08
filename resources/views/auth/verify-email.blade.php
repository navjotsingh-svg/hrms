@extends('layouts.guest')

@section('title', 'Verify Email - ' . config('app.name', 'HRMS'))

@section('content')
    <h5 class="card-title text-center mb-3">{{ __('Verify Email') }}</h5>

    <p class="text-muted small text-center mb-4">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success" role="alert">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="d-flex align-items-center justify-content-between gap-2">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary">{{ __('Resend Verification Email') }}</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-link btn-sm text-decoration-none">{{ __('Log Out') }}</button>
        </form>
    </div>
@endsection
