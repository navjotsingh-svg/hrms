@extends('layouts.app')

@section('title', 'Manage Leave Balances - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Manage Leave Balances</h1>
            <p class="page-subtitle mb-0">Update utilised leave and grant comp off credits to employees.</p>
        </div>
        <a href="{{ route('web.leave.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
@endsection

@section('content')
    <div id="manageBalancesAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card mb-4">
        <div class="content-card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    @include('partials.employee-search-select', [
                        'inputId' => 'balanceEmployeeInput',
                        'hiddenId' => 'balanceEmployeeId',
                    ])
                </div>
                <div class="col-md-3">
                    <label for="balanceYear" class="form-label">Year</label>
                    <select class="form-select" id="balanceYear"></select>
                </div>
                <div class="col-md-4">
                    <button type="button" class="btn btn-primary" id="loadBalancesBtn">Load Balances</button>
                </div>
            </div>
        </div>
    </div>

    <div class="content-card mb-4 d-none" id="grantCompOffCard">
        <div class="content-card-header border-bottom">
            <h2 class="content-card-title mb-0">Grant Comp Off</h2>
        </div>
        <div class="content-card-body">
            <p class="small text-muted mb-3">Credit comp off days when an employee works on a holiday or weekly off. Employees can apply comp off only against their credited balance.</p>
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="grantCompOffDays" class="form-label">Days to grant</label>
                    <input type="number" class="form-control" id="grantCompOffDays" min="0.5" step="0.5" value="1">
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-success" id="grantCompOffBtn">Grant Comp Off</button>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted" id="compOffSummary">Comp off balance: —</div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-card d-none" id="balancesCard">
        <div class="content-card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="content-card-title mb-0" id="balancesEmployeeTitle">Leave Balances</h2>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Annual Quota</th>
                        <th>Allocated</th>
                        <th>Credited</th>
                        <th>Used</th>
                        <th>Pending</th>
                        <th>Available</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="manageBalancesTableBody"></tbody>
            </table>
        </div>
    </div>
    @vite(['resources/js/leaves-manage-balances.js'])
@endsection
