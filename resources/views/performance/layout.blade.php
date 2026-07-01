@extends('layouts.app')

@php
    $titles = [
        'overview' => 'Overview',
        'praise-recognition' => 'Praise & Recognition',
        'continuous-feedback' => 'Continuous Feedback',
        'one-on-one' => 'One-on-one Meetings',
        'reviews' => 'Performance Reviews',
        'calibration' => 'Performance Calibration',
        'promotions' => 'Promotions',
        'insights' => 'Performance Insights',
        'compensation' => 'Basic Compensation Plans',
        'skills' => 'Skills and Competencies',
        'review-cycles' => 'Review Cycles',
        'feedback-forms' => 'Feedback Forms',
        'question-bank' => 'Question Bank',
        'goals' => 'Goals and OKRs',
        'kpi' => 'KPI',
        'pip' => 'Performance Improvement Plan',
    ];
    $subtitles = [
        'overview' => 'Performance dashboard with active cycles, goals, reviews, and PIPs.',
        'praise-recognition' => 'Celebrate achievements and recognize colleagues across the organization.',
        'continuous-feedback' => 'Create reusable feedback form templates from your question bank.',
        'one-on-one' => 'Schedule and track manager–employee one-on-one meetings.',
        'reviews' => 'View pending reviews, submit self-assessments, and complete manager reviews.',
        'calibration' => 'Align ratings across teams before finalizing performance scores.',
        'promotions' => 'Manage promotion nominations and approvals.',
        'insights' => 'Organization-wide performance metrics, trends, and completion rates.',
        'compensation' => 'Salary bands and merit increase planning linked to reviews.',
        'skills' => 'Role competencies and employee skill profiles.',
        'review-cycles' => 'Configure review periods, questions, reviewer pairs, and track completion.',
        'feedback-forms' => 'Create reusable feedback form templates from your question bank.',
        'question-bank' => 'Maintain a library of rating and text questions for reviews and forms.',
        'goals' => 'Set goals and key results for yourself or your team.',
        'kpi' => 'Track employee KPIs with targets, current values, and progress.',
        'pip' => 'Manage performance improvement plans with milestones and outcomes.',
    ];
    $pageTitle = $titles[$performancePage] ?? 'Performance';
    $pageSubtitle = $subtitles[$performancePage] ?? '';
@endphp

@section('title', $pageTitle . ' - Performance - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">{{ $pageTitle }}</h1>
            <p class="page-subtitle mb-0">{{ $pageSubtitle }}</p>
        </div>
        <div class="d-flex gap-2" id="performanceHeaderActions"></div>
    </div>
@endsection

@section('content')
    <div id="performanceAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>
    @yield('performance-content')
@endsection

@push('scripts')
    @vite('resources/js/performance.js')
    <script>
        window.HRMS_PERFORMANCE = {
            page: @json($performancePage),
            canManage: @json($canManage),
            canReview: @json($canReview),
            canManagePips: @json($canManagePips),
        };
    </script>
@endpush
