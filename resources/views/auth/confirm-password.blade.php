@extends('layouts.guest')

@section('title', 'Confirm Password - ' . config('app.name', 'HRMS'))

@section('content')
    <h5 class="card-title text-center mb-3">{{ __('Confirm Password') }}</h5>

    <p class="text-muted small text-center mb-4">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="mb-3">
            <label for="password" class="form-label">{{ __('Password') }}</label>
            <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">{{ __('Confirm') }}</button>
        </div>
    </form>
@endsection
