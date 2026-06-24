@extends('layouts.app')

@section('title', 'Moments - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Company Moments</h1>
            <p class="page-subtitle mb-0">Celebrate milestones, share updates, and stay connected with your team.</p>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="momentsRefreshBtn">Refresh</button>
    </div>
@endsection

@section('content')
    @include('home.partials.tabs', ['active' => 'moments'])

    <div id="momentsPageRoot" data-can-comment="{{ (Auth::user()->hasPermission('home.moments.comment') || Auth::user()->hasPermission('home.moments.view')) ? '1' : '0' }}">
    <div id="momentsAlert" class="alert d-none"></div>

    @if (Auth::user()->hasPermission('home.moments.post'))
        <div class="content-card mb-4">
            <div class="content-card-body">
                <form id="momentsPostForm">
                    <label class="form-label fw-semibold" for="momentsPostContent">Share with your organisation</label>
                    <textarea class="form-control mb-3" id="momentsPostContent" rows="3" maxlength="5000" placeholder="Write something to celebrate or update the team..."></textarea>
                    <div class="mb-3">
                        <label class="form-label small text-muted mb-1" for="momentsPostAttachments">Attachments (PDF or images, up to 5 files, 5 MB each)</label>
                        <input type="file" class="form-control form-control-sm" id="momentsPostAttachments" accept=".pdf,image/jpeg,image/png,image/gif,image/webp" multiple>
                        <div id="momentsPostAttachmentPreview" class="moments-attachment-preview mt-2"></div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary" id="momentsPostBtn" disabled>Share</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="moments-filter-bar mb-3">
        <div class="btn-group flex-wrap" role="group" aria-label="Filter moments">
            <button type="button" class="btn btn-sm btn-outline-secondary active" data-moments-filter="">All</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-moments-filter="post">Posts</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-moments-filter="birthday">Birthdays</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-moments-filter="work_anniversary">Work Anniversaries</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-moments-filter="new_joinee">New Joiners</button>
        </div>
    </div>

    <div id="momentsFeed" class="moments-feed"></div>
    <div id="momentsEmpty" class="text-center text-muted py-5 d-none">No moments yet. Check back later or share the first post.</div>
    <div class="d-flex justify-content-center mt-3">
        <ul class="pagination pagination-sm mb-0" id="momentsPagination"></ul>
    </div>
    </div>

    @vite(['resources/js/moments.js'])
@endsection
