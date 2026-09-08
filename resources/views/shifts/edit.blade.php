@extends('layouts.app')

@section('title', 'Edit Shift - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Edit Shift</h1>
            <p class="page-subtitle mb-0">Update shift details and timings.</p>
        </div>
        <a href="{{ route('web.masters.shifts.index') }}" class="btn btn-outline-secondary">Back to list</a>
    </div>
@endsection

@section('content')
    <div id="shiftFormAlert" class="alert alert-danger alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card">
        <div class="content-card-body">
            <form id="shiftForm" class="row g-4" data-shift-id="{{ $shiftId }}" data-default-timezone="{{ $defaultTimezone ?? 'UTC' }}">
                @include('shifts.partials.form')
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="shiftSubmitBtn">Update Shift</button>
                    <a href="{{ route('web.masters.shifts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    @vite(['resources/js/shifts.js'])
@endsection
