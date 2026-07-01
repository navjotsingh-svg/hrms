@extends('layouts.app')

@section('title', 'Document Letter - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1" id="docLetterShowTitle">Document Letter</h1>
            <p class="page-subtitle mb-0" id="docLetterShowSubtitle">Review document details and sign when ready.</p>
        </div>
        <a href="{{ route('web.documents-letters.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
@endsection

@section('content')
    <div id="docLetterShowAlert" class="alert d-none"></div>

    <div id="docLetterShowRoot"
         data-letter-id="{{ $letterId }}"
         data-can-manage="{{ $canManage ? '1' : '0' }}">
        <div class="content-card mb-4">
            <div class="content-card-body" id="docLetterMeta">
                <div class="text-muted py-4 text-center">Loading document...</div>
            </div>
        </div>

        <div class="content-card mb-4">
            <div class="content-card-body border-bottom">
                <h2 class="h6 mb-0">Document Content</h2>
            </div>
            <div class="content-card-body document-letter-preview" id="docLetterContent"></div>
        </div>

        <div class="content-card mb-4 d-none" id="docLetterSignatureCard">
            <div class="content-card-body border-bottom">
                <h2 class="h6 mb-0">Your Signature</h2>
                <p class="text-muted small mb-0">Draw your signature below or type your full name to accept this document.</p>
            </div>
            <div class="content-card-body">
                <form id="docLetterSignForm">
                    <div class="mb-3">
                        <label for="signatureName" class="form-label">Full name</label>
                        <input type="text" class="form-control" id="signatureName" required maxlength="255" placeholder="Type your full legal name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Draw signature</label>
                        <div class="border rounded bg-white position-relative">
                            <canvas id="signatureCanvas" width="600" height="180" class="w-100" style="touch-action: none; cursor: crosshair;"></canvas>
                        </div>
                        <button type="button" class="btn btn-link btn-sm px-0 mt-1" id="signatureClearBtn">Clear drawing</button>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Sign & Accept</button>
                        <button type="button" class="btn btn-outline-danger" id="docLetterDeclineBtn">Decline</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="content-card mb-4 d-none" id="docLetterSignedCard">
            <div class="content-card-body border-bottom">
                <h2 class="h6 mb-0">Signature Record</h2>
            </div>
            <div class="content-card-body" id="docLetterSignedDetails"></div>
        </div>

        <div class="content-card d-none" id="docLetterManageCard">
            <div class="content-card-body border-bottom">
                <h2 class="h6 mb-0">HR Actions</h2>
            </div>
            <div class="content-card-body d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary d-none" id="docLetterIssueBtn">Issue to Employee</button>
                <button type="button" class="btn btn-outline-danger d-none" id="docLetterCancelBtn">Cancel Document</button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="docLetterDeclineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="docLetterDeclineForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Decline Document</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label for="declineReason" class="form-label">Reason</label>
                        <textarea class="form-control" id="declineReason" rows="4" required maxlength="2000"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Decline</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @vite(['resources/js/documents-letters-show.js'])
@endsection
