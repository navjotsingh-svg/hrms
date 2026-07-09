@extends('layouts.app')

@section('title', 'Org Chart - ' . config('app.name', 'HRMS'))

@section('header')
    <div>
        <h1 class="page-title mb-1">Org Chart</h1>
        <p class="page-subtitle mb-0">Reporting hierarchy starting from company admins who manage teams.</p>
    </div>
@endsection

@section('content')
    <div class="content-card">
        <div class="content-card-body p-0">
            <div class="org-chart-wrap" id="orgChartRoot">
                <div class="text-center text-muted py-5">Loading organization chart...</div>
            </div>
        </div>
    </div>

    @vite(['resources/js/org-chart-page.js'])
@endsection
