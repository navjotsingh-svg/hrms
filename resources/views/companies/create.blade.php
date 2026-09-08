@extends('layouts.app')

@section('title', 'Add Company - ' . config('app.name', 'HRMS'))

@section('header')
    <div>
        <h1 class="page-title mb-1">Add Company</h1>
        <p class="page-subtitle mb-0">Register a new company on the HRMS platform.</p>
    </div>
@endsection

@section('content')
    <div class="content-card">
        <div id="companyFormAlert" class="alert alert-danger d-none"></div>
        <form id="companyForm" enctype="multipart/form-data" onsubmit="event.preventDefault(); return false;">
            <div class="content-card-body">
                @include('companies.partials.form')
            </div>
            <div class="content-card-footer d-flex justify-content-between">
                <a href="{{ route('web.companies.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="button" id="companySubmitBtn" class="btn btn-primary">Save Company</button>
            </div>
        </form>
    </div>
    @include('companies.partials.logo-lightbox')
    @vite(['resources/js/companies.js'])
@endsection
