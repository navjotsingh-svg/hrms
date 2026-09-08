@extends('layouts.app')

@section('title', 'Add Employee - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Add Employee</h1>
            <p class="page-subtitle mb-0">Complete all steps to onboard a new team member.</p>
        </div>
        <a href="{{ route('web.employees.index') }}" class="btn btn-outline-secondary">Back to list</a>
    </div>
@endsection

@section('content')
    <div id="employeeFormAlert" class="alert alert-danger alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card employee-wizard-card">
        <div class="content-card-body p-0">
            <form id="employeeForm" novalidate>
                @include('employees.partials.wizard-form')
            </form>
        </div>
    </div>
    @vite(['resources/js/employees.js'])
@endsection
