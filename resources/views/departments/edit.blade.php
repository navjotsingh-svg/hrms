@extends('layouts.app')

@section('title', 'Edit Department - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Edit Department</h1>
            <p class="page-subtitle mb-0">Update department details.</p>
        </div>
        <a href="{{ route('web.masters.departments.index') }}" class="btn btn-outline-secondary">Back to list</a>
    </div>
@endsection

@section('content')
    <div id="departmentFormAlert" class="alert alert-danger alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card">
        <div class="content-card-body">
            <form id="departmentForm" class="row g-4" data-department-id="{{ $departmentId }}">
                @include('departments.partials.form')
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="departmentSubmitBtn">Update Department</button>
                    <a href="{{ route('web.masters.departments.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    @vite(['resources/js/departments.js'])
@endsection
