@extends('layouts.app')

@section('title', 'Helpdesk Ticket - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Helpdesk Ticket</h1>
            <p class="page-subtitle mb-0">Track updates and communicate with HR on this ticket.</p>
        </div>
        <button type="button" class="btn btn-outline-secondary" id="helpdeskShowBackBtn">Back</button>
    </div>
@endsection

@section('content')
    <div id="helpdeskShowAlert" class="alert d-none"></div>
    <div class="content-card mb-4" id="helpdeskShowCard" data-ticket-id="{{ $ticketId }}">
        <div class="content-card-body">
            <div id="helpdeskShowDetails">
                <div class="text-muted py-4 text-center">Loading ticket...</div>
            </div>
        </div>
    </div>

    <div class="content-card mb-4 d-none" id="helpdeskManageCard">
        <div class="content-card-body border-bottom">
            <h2 class="h6 mb-0">Update status</h2>
        </div>
        <div class="content-card-body">
            <form id="helpdeskStatusForm" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="helpdeskStatusSelect" class="form-label">Status</label>
                    <select class="form-select" id="helpdeskStatusSelect"></select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <div class="content-card">
        <div class="content-card-body border-bottom">
            <h2 class="h6 mb-0">Conversation</h2>
        </div>
        <div class="content-card-body">
            <div id="helpdeskComments" class="d-flex flex-column gap-3 mb-4"></div>
            <form id="helpdeskCommentForm" class="d-none">
                <label for="helpdeskCommentBody" class="form-label">Add a reply</label>
                <textarea class="form-control mb-2" id="helpdeskCommentBody" rows="3" maxlength="5000" required></textarea>
                <div class="form-check mb-3 d-none" id="helpdeskInternalWrap">
                    <input class="form-check-input" type="checkbox" id="helpdeskInternalNote">
                    <label class="form-check-label" for="helpdeskInternalNote">Internal note (visible to HR only)</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Post Reply</button>
            </form>
        </div>
    </div>
    @vite(['resources/js/helpdesk-show.js'])
@endsection
