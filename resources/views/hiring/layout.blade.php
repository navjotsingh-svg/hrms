@extends('layouts.app')

@php
    $titles = [
        'overview' => 'Overview',
        'jobs' => 'Jobs',
        'candidates' => 'Candidates',
        'interviews' => 'Interviews',
        'offers' => 'Offers',
        'templates' => 'Templates',
        'careers' => 'Careers Page',
    ];
    $subtitles = [
        'overview' => 'Hiring dashboard with open jobs, candidates, and upcoming interviews.',
        'jobs' => 'Manage job postings, publish to careers page, and close openings.',
        'candidates' => 'Track applicants through the hiring pipeline.',
        'interviews' => 'Schedule and manage candidate interviews.',
        'offers' => 'Create and send offer letters to candidates.',
        'templates' => 'Maintain reusable offer and communication templates with dynamic fields.',
        'careers' => 'Customize your public careers page and publish open roles.',
    ];
    $pageTitle = $titles[$hiringPage] ?? 'Hiring';
    $pageSubtitle = $subtitles[$hiringPage] ?? '';
@endphp

@section('title', $pageTitle . ' - Hiring - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">{{ $pageTitle }}</h1>
            <p class="page-subtitle mb-0">{{ $pageSubtitle }}</p>
        </div>
        <div class="d-flex gap-2" id="hiringHeaderActions"></div>
    </div>
@endsection

@section('content')
    <div id="hiringAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>
    @yield('hiring-content')
@endsection

@push('scripts')
    @vite('resources/js/hiring.js')
    <script>
        window.HRMS_HIRING = {
            page: @json($hiringPage),
            canManage: @json($canManage),
            canInterview: @json($canInterview),
            canPublishCareers: @json($canPublishCareers),
            companySlug: @json($companySlug),
        };
    </script>
@endpush
