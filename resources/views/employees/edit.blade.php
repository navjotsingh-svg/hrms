@extends('layouts.app')

@section('title', 'Edit Employee - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Edit Employee</h1>
            <p class="page-subtitle mb-0">Update job, organization, and salary details. For personal, bank, and document details, use <strong>Manage Profile</strong>.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if (Auth::user()->canReviewEmployeeDocuments())
            <a href="{{ route('web.employees.profile.edit', ['employee' => $employeeId]) }}" class="btn btn-primary">Manage Profile</a>
            @endif
            <a href="{{ route('web.employees.index') }}" class="btn btn-outline-secondary">Back to list</a>
        </div>
    </div>
@endsection

@section('content')
    <div id="employeeFormAlert" class="alert alert-danger alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card employee-wizard-card">
        <div class="content-card-body p-0">
            <form id="employeeForm" novalidate data-employee-id="{{ $employeeId }}">
                @include('employees.partials.wizard-form')
            </form>
        </div>
    </div>
    @vite(['resources/js/employees.js'])
@endsection
