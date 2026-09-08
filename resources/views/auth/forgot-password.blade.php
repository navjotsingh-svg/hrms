@extends('layouts.guest')

@section('title', 'Forgot Password - ' . config('app.name', 'HRMS'))

@section('content')
    <h5 class="card-title text-center mb-3">{{ __('Forgot Password') }}</h5>

    <p class="text-muted small text-center mb-4">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </p>

    @if (session('status'))
        <div class="alert alert-success mb-3" role="alert">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">{{ __('Email Password Reset Link') }}</button>
        </div>
    </form>
@endsection
