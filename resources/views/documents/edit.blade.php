@extends('layouts.app')

@section('title', 'Edit Document Type - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Edit Document Type</h1>
            <p class="page-subtitle mb-0">Update document category details.</p>
        </div>
        <a href="{{ route('web.masters.documents.index') }}" class="btn btn-outline-secondary">Back to list</a>
    </div>
@endsection

@section('content')
    <div id="documentFormAlert" class="alert alert-danger alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card">
        <div class="content-card-body">
            <form id="documentForm" class="row g-4" data-document-type-id="{{ $documentTypeId }}">
                @include('documents.partials.form')
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="documentSubmitBtn">Update Document Type</button>
                    <a href="{{ route('web.masters.documents.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    @vite(['resources/js/documents.js'])
@endsection
