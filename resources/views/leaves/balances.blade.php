@extends('layouts.app')

@section('title', 'My Leave Balances - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">My Leave Balances</h1>
            <p class="page-subtitle mb-0">Allocated, used, pending, and available leave counts.</p>
        </div>
        <a href="{{ route('web.leave.apply') }}" class="btn btn-primary">Apply Leave</a>
    </div>
@endsection

@section('content')
    <div class="content-card">
        <div class="content-card-body border-bottom">
            <label for="balanceYear" class="form-label">Year</label>
            <select class="form-select w-auto" id="balanceYear"></select>
        </div>
        <div class="content-card-body" id="leaveBalancePage">
            <div class="text-muted">Loading...</div>
        </div>
    </div>
    @vite(['resources/js/leaves-balances.js'])
@endsection
