@extends('layouts.app')

@section('title', 'Dashboard - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Dashboard</h1>
            <p class="page-subtitle mb-0">Analytics widgets for employees, leave, attendance, and requests.</p>
        </div>
        @if (Auth::user()->hasPermission('home.dashboard.manage'))
            <button type="button" class="btn btn-outline-primary" id="homeDashboardManageBtn">Manage Widgets</button>
        @endif
    </div>
@endsection

@section('content')
    @include('home.partials.tabs', ['active' => 'dashboard'])

    <div id="homeDashboardAlert" class="alert d-none"></div>
    <div id="homeDashboardRoot" class="row g-4"></div>
    <div id="homeDashboardEmpty" class="text-center text-muted py-5 d-none">No widgets available for your role.</div>

    <div class="modal fade" id="homeDashboardWidgetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Dashboard Widgets</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="homeDashboardWidgetOptions"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="homeDashboardSaveWidgetsBtn">Save Layout</button>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/home-dashboard.js'])
@endsection
