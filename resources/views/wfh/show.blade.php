@extends('layouts.app')

@section('title', 'WFH Request - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">WFH Request</h1>
            <p class="page-subtitle mb-0">Work From Home request details and review actions.</p>
        </div>
        <button type="button" class="btn btn-outline-secondary" id="wfhShowBackBtn">Back</button>
    </div>
@endsection

@section('content')
    <div id="wfhShowAlert" class="alert d-none"></div>
    <div class="content-card" id="wfhShowCard" data-wfh-id="{{ $wfhId }}">
        <div class="content-card-body request-show-card-body">
            <div id="wfhShowCardToolbar" class="request-show-toolbar d-none"></div>
            <div id="wfhShowCardDetails">
                <div class="text-muted py-4 text-center">Loading WFH request...</div>
            </div>
        </div>
    </div>
    @include('partials.document-lightbox')
    @vite(['resources/js/wfh-show.js'])
@endsection
