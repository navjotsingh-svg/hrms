@extends('layouts.app')

@section('title', 'Request Details - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1" id="requestShowTitle">Request Details</h1>
            <p class="page-subtitle mb-0" id="requestShowSubtitle"></p>
        </div>
        <button type="button" class="btn btn-outline-secondary" id="requestShowBackBtn">Back</button>
    </div>
@endsection

@section('content')
    <div id="requestShowAlert" class="alert d-none"></div>
    <div class="content-card" id="requestShowCard" data-category="{{ $category }}" data-entity-id="{{ $entityId }}">
        <div class="content-card-body request-show-card-body">
            <div id="requestShowCardToolbar" class="request-show-toolbar d-none"></div>
            <div id="requestShowCardDetails">
                <div class="text-muted py-4 text-center">Loading request...</div>
            </div>
        </div>
    </div>
    @include('partials.document-lightbox')
    @vite(['resources/js/requests-show.js'])
@endsection
