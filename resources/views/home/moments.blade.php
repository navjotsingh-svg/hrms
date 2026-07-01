@extends('layouts.app')

@section('title', 'Moments - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Company Moments</h1>
            <p class="page-subtitle mb-0">Celebrate milestones, share updates, and stay connected with your team.</p>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="momentsRefreshBtn">Refresh</button>
    </div>
@endsection

@section('content')
    @include('home.partials.tabs', ['active' => 'moments'])
    @include('home.partials.moments-feed')
    @vite(['resources/js/moments.js'])
@endsection
