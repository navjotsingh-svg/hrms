@extends('layouts.app')

@section('title', 'Request Asset - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Request Asset</h1>
            <p class="page-subtitle mb-0">Browse company assets and submit a request for approval.</p>
        </div>
        <a href="{{ route('web.asset-requests.index') }}" class="btn btn-outline-secondary">My Requests</a>
    </div>
@endsection

@section('content')
    <div id="assetApplyAlert" class="alert alert-danger d-none"></div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="content-card mb-4">
                <div class="content-card-header border-bottom">
                    <h2 class="content-card-title mb-0">Available Assets</h2>
                </div>
                <div class="content-card-body">
                    <div class="table-responsive">
                        <table class="companies-table table mb-0">
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody id="assetCatalogBody">
                                <tr><td colspan="3" class="text-center text-muted py-4">Loading assets...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="content-card">
                <div class="content-card-header border-bottom">
                    <h2 class="content-card-title mb-0">Submit Request</h2>
                </div>
                <div class="content-card-body asset-request-form">
                    <form id="assetRequestForm" class="row g-3">
                        <div class="col-12">
                            <label for="asset_type_ids" class="form-label">Assets <span class="text-danger">*</span></label>
                            <select class="form-select asset-select2" id="asset_type_ids" name="asset_type_ids[]" multiple data-placeholder="Select one or more assets">
                            </select>
                            <div class="form-text">Search and select multiple assets in one request.</div>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12">
                            <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="4" maxlength="2000" required placeholder="Explain why you need this asset"></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100" id="assetRequestSubmitBtn">Submit Request</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="content-card mt-4">
                <div class="content-card-body">
                    <ul class="small text-muted mb-0 ps-3">
                        <li class="mb-2">Requests are reviewed by HR or your manager.</li>
                        <li class="mb-2">Approved assets appear on your profile under Assigned Assets.</li>
                        <li class="mb-2">You can select multiple assets in one submission.</li>
                        <li>You cannot request an asset you already have or one with a pending request.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    @vite(['resources/js/assets-apply.js'])
@endsection
