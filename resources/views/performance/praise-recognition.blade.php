@extends('performance.layout')

@section('performance-content')
    <div id="praisePageRoot" data-can-post="{{ ($canPostPraise ?? false) ? '1' : '0' }}" data-can-manage="{{ ($canManagePraise ?? false) ? '1' : '0' }}">
        @if ($canPostPraise ?? false)
            <div class="content-card mb-4">
                <div class="content-card-body">
                    <h2 class="h6 mb-3">Send praise</h2>
                    <form id="praiseForm" class="row g-3">
                        <div class="col-md-5">
                            @include('partials.employee-search-select', [
                                'inputId' => 'praiseEmployeeSearch',
                                'hiddenId' => 'praiseEmployeeId',
                                'label' => 'Recognize colleague',
                                'placeholder' => 'Search employee',
                            ])
                        </div>
                        <div class="col-md-7">
                            <label for="praiseContent" class="form-label">Your message</label>
                            <textarea class="form-control" id="praiseContent" rows="3" maxlength="2000" placeholder="Share what they did well and why it matters..."></textarea>
                            <div class="mt-3">
                                <label class="form-label small text-muted mb-1" for="praiseAttachments">Attachments (PDF or images, up to 5 files, 5 MB each)</label>
                                <input type="file" class="form-control form-control-sm" id="praiseAttachments" accept=".pdf,image/jpeg,image/png,image/gif,image/webp" multiple>
                                <div id="praiseAttachmentPreview" class="moments-attachment-preview mt-2"></div>
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary" id="praiseSubmitBtn">Share Praise</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="content-card companies-list-card">
            <div class="content-card-body border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h2 class="h6 mb-0">Recognition wall</h2>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="praiseRefreshBtn">Refresh</button>
            </div>
            @include('partials.list-pagination-header', ['perPageId' => 'praisePerPage'])
            <div class="content-card-body" id="praiseFeed">
                <div class="text-center text-muted py-5">Loading praise...</div>
            </div>
                @include('partials.list-pagination-footer', [
                    'infoId' => 'praisePaginationInfo',
                    'listId' => 'praisePaginationList',
                    'perPageId' => 'praisePerPage',
                    'wrapClass' => 'content-card-body border-top',
                    'ariaLabel' => 'Praise pagination',
                ])
        </div>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/praise-recognition.js'])
@endpush
