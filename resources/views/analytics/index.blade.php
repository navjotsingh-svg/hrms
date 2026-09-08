@extends('layouts.app')

@section('title', 'Analytics - ' . config('app.name', 'HRMS'))

@section('header')
    <div>
        <nav aria-label="breadcrumb" class="mb-1">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('web.analytics.index') }}">Analytics</a></li>
            </ol>
        </nav>
        <h1 class="page-title mb-1">Analytics</h1>
        <p class="page-subtitle mb-0">Reports and charts across leave, attendance, people, and more.</p>
    </div>
@endsection

@section('content')
    @include('analytics.partials.tabs', [
        'sections' => $sections,
        'activeSection' => $activeSection,
    ])

    <div class="analytics-report-cards">
        @forelse ($reports as $report)
            @php
                $href = ! empty($report['dedicated_route'])
                    ? route($report['dedicated_route'])
                    : route('web.analytics.report', ['reportKey' => $report['key']]);
            @endphp
            <a href="{{ $href }}" class="analytics-report-card">
                <h2 class="analytics-report-card-title">{{ $report['name'] }}</h2>
                <p class="analytics-report-card-description">{{ $report['description'] }}</p>
            </a>
        @empty
            <div class="content-card">
                <div class="content-card-body text-muted py-5 text-center">
                    No analytics reports are available for this section.
                </div>
            </div>
        @endforelse
    </div>
@endsection
