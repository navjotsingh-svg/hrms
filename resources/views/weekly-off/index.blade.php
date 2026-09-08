@extends('layouts.app')

@section('title', 'Weekly Off - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Weekly Off</h1>
            <p class="page-subtitle mb-0">Choose which weekdays are non-working days for your company.</p>
        </div>
        <a href="{{ route('web.masters.attendance.holidays.index') }}" class="btn btn-outline-secondary">Manage Holidays</a>
    </div>
@endsection

@section('content')
    <div id="weeklyOffAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card">
        <div class="content-card-body">
            <form id="weeklyOffForm">
                <p class="text-muted mb-4">Selected days appear as weekly off on the attendance calendar. Employees cannot punch in or out on these days.</p>

                <div class="row g-3" id="weeklyOffOptions">
                    @foreach ([0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'] as $weekday => $label)
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="form-check weekly-off-option">
                                <input class="form-check-input" type="checkbox" value="{{ $weekday }}" id="weekday{{ $weekday }}" name="weekdays[]">
                                <label class="form-check-label" for="weekday{{ $weekday }}">{{ $label }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="weeklyOffSubmitBtn">Save Weekly Off</button>
                </div>
            </form>
        </div>
    </div>
    @vite(['resources/js/weekly-off.js'])
@endsection
