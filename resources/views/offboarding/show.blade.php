@extends('layouts.app')

@section('title', 'Exit Case - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Exit Case</h1>
            <p class="page-subtitle mb-0">Clearance, asset return, exit survey, and full &amp; final settlement.</p>
        </div>
        <a href="{{ route('web.offboarding.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
@endsection

@section('content')
    <div id="offboardingShowAlert" class="alert d-none"></div>
    <div id="offboardingShowCard" data-exit-case-id="{{ $exitCaseId }}">
        <div id="offboardingShowContent">
            <div class="content-card">
                <div class="content-card-body text-center text-muted py-5">Loading exit case...</div>
            </div>
        </div>
    </div>
    @vite(['resources/js/offboarding-show.js'])
@endsection
