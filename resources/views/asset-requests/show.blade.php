@extends('layouts.app')

@section('title', 'Asset Request - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Asset Request</h1>
            <p class="page-subtitle mb-0">Asset request details and review actions.</p>
        </div>
        <button type="button" class="btn btn-outline-secondary" id="assetRequestShowBackBtn">Back</button>
    </div>
@endsection

@section('content')
    <div id="assetRequestShowAlert" class="alert d-none"></div>
    <div class="content-card" id="assetRequestShowCard" data-asset-request-id="{{ $assetRequestId }}">
        <div class="content-card-body request-show-card-body">
            <div id="assetRequestShowCardToolbar" class="request-show-toolbar d-none"></div>
            <div id="assetRequestShowCardDetails">
                <div class="text-muted py-4 text-center">Loading asset request...</div>
            </div>
        </div>
    </div>
    @vite(['resources/js/assets-request-show.js'])
@endsection
