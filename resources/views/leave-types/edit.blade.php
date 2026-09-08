@extends('layouts.app')

@section('title', 'Edit Leave Type - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Edit Leave Type</h1>
        </div>
        <a href="{{ route('web.masters.leave-types.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
@endsection

@section('content')
    <div id="leaveTypeFormAlert" class="alert alert-danger d-none"></div>
    <div class="content-card">
        <div class="content-card-body">
            <form id="leaveTypeForm" class="row g-4" data-leave-type-id="{{ $leaveTypeId }}">
                @include('leave-types.partials.form')
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="leaveTypeSubmitBtn">Update</button>
                </div>
            </form>
        </div>
    </div>
    @vite(['resources/js/leave-types.js'])
@endsection
