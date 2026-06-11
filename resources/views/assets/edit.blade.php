@extends('layouts.app')

@section('title', 'Edit Asset - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Edit Asset</h1>
            <p class="page-subtitle mb-0">Update asset type details.</p>
        </div>
        <a href="{{ route('web.masters.assets.index') }}" class="btn btn-outline-secondary">Back to list</a>
    </div>
@endsection

@section('content')
    <div id="assetFormAlert" class="alert alert-danger alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card">
        <div class="content-card-body">
            <form id="assetForm" class="row g-4" data-asset-type-id="{{ $assetTypeId }}">
                @include('assets.partials.form')
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="assetSubmitBtn">Update Asset</button>
                    <a href="{{ route('web.masters.assets.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    @vite(['resources/js/assets.js'])
@endsection
