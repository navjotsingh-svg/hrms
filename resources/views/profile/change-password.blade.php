@extends('layouts.app')

@section('title', 'Change Password - ' . config('app.name', 'HRMS'))

@section('header')
    <div>
        <h1 class="page-title mb-1">Change Password</h1>
        <p class="page-subtitle mb-0">Update your login password.</p>
    </div>
@endsection

@section('content')
    <div id="changePasswordAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card">
        <div class="content-card-body">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/change-password.js'])
@endsection
