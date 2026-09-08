@extends('layouts.app')

@php
    $titles = [
        'social-wall' => 'Social Wall',
        'polls-announcements' => 'Polls and Announcements',
        'public-praise' => 'Public Praise',
    ];
    $subtitles = [
        'social-wall' => 'Build an intra-company live feed where employees participate through posts, praises, announcements, and more.',
        'polls-announcements' => 'Create polls and track response analytics in real-time. Publish announcements on the social wall to reach all employees.',
        'public-praise' => 'Let employees praise their peers to foster collaboration and boost morale.',
    ];
    $pageTitle = $titles[$experiencePage] ?? 'Employee Experience';
    $pageSubtitle = $subtitles[$experiencePage] ?? '';
@endphp

@section('title', $pageTitle . ' - Employee Experience - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">{{ $pageTitle }}</h1>
            <p class="page-subtitle mb-0">{{ $pageSubtitle }}</p>
        </div>
        @yield('experience-header-actions')
    </div>
@endsection

@section('content')
    @include('employee-experience.partials.tabs', ['active' => $experiencePage])
    @yield('experience-content')
@endsection
