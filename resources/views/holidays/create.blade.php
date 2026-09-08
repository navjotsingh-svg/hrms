@extends('layouts.app')

@section('title', 'Add Holiday - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Add Holiday</h1>
            <p class="page-subtitle mb-0">Create a holiday for your company calendar.</p>
        </div>
        <a href="{{ route('web.masters.attendance.holidays.index') }}" class="btn btn-outline-secondary">Back to list</a>
    </div>
@endsection

@section('content')
    <div id="holidayFormAlert" class="alert alert-danger alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card">
        <div class="content-card-body">
            <form id="holidayForm" class="row g-4">
                @include('holidays.partials.form')
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="holidaySubmitBtn">Save Holiday</button>
                    <a href="{{ route('web.masters.attendance.holidays.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    @vite(['resources/js/holidays.js'])
@endsection
