@extends('layouts.app')

@section('title', 'Add Shift - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Add Shift</h1>
            <p class="page-subtitle mb-0">Create a new work shift with start and end timings.</p>
        </div>
        <a href="{{ route('web.masters.shifts.index') }}" class="btn btn-outline-secondary">Back to list</a>
    </div>
@endsection

@section('content')
    <div id="shiftFormAlert" class="alert alert-danger alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card">
        <div class="content-card-body">
            <form id="shiftForm" class="row g-4">
                @include('shifts.partials.form')
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="shiftSubmitBtn">Save Shift</button>
                    <a href="{{ route('web.masters.shifts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    @vite(['resources/js/shifts.js'])
@endsection
