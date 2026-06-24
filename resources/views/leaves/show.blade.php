@extends('layouts.app')

@section('title', 'Leave Request - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Leave Request</h1>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div id="leaveShowHeaderActions" class="table-action-group d-none"></div>
            <a href="{{ route('web.leave.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>
@endsection

@section('content')
    <div id="leaveShowAlert" class="alert d-none"></div>
    <div class="content-card" id="leaveShowCard" data-leave-id="{{ $leaveId }}">
        <div class="content-card-body">
            <div class="text-muted py-4 text-center">Loading leave request...</div>
        </div>
    </div>
    @vite(['resources/js/leaves-show.js'])
@endsection
