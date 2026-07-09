@extends('layouts.app')

@section('title', 'Dashboard - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h1 class="page-title mb-1">Dashboard</h1>
            <p class="page-subtitle mb-0">Analytics widgets for employees, leave, attendance, expenses, hiring, and performance.</p>
        </div>
        <div class="d-flex flex-wrap align-items-end gap-2">
            <div class="home-dashboard-range-filter">
                <label for="homeDashboardRangePreset" class="form-label small mb-1">Period</label>
                <select class="form-select form-select-sm" id="homeDashboardRangePreset">
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="this_week">This Week</option>
                    <option value="this_month" selected>This Month</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
            <div id="homeDashboardCustomRange" class="d-none d-flex flex-wrap align-items-end gap-2">
                <div>
                    <label for="homeDashboardFromDate" class="form-label small mb-1">From</label>
                    <input type="date" class="form-control form-control-sm" id="homeDashboardFromDate">
                </div>
                <div>
                    <label for="homeDashboardToDate" class="form-label small mb-1">To</label>
                    <input type="date" class="form-control form-control-sm" id="homeDashboardToDate">
                </div>
                <button type="button" class="btn btn-primary btn-sm" id="homeDashboardApplyRangeBtn">Apply</button>
            </div>
            @if (Auth::user()->hasPermission('home.dashboard.manage'))
                <button type="button" class="btn btn-outline-primary btn-sm" id="homeDashboardManageBtn">Manage Widgets</button>
            @endif
        </div>
    </div>
    <p class="text-muted small mb-0 mt-2" id="homeDashboardRangeSummary"></p>
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
