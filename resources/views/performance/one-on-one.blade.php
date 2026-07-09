@extends('performance.layout')

@section('performance-content')
    <div id="oneOnOnePageRoot" data-can-schedule="{{ ($canScheduleMeetings ?? false) ? '1' : '0' }}">
        <div class="content-card companies-list-card">
            <div class="content-card-body companies-filter-bar border-bottom">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label for="meetingStatusFilter" class="form-label">Status</label>
                        <select class="form-select" id="meetingStatusFilter">
                            <option value="">All</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="meetingSearchFilter" class="form-label">Search</label>
                        <input type="search" class="form-control" id="meetingSearchFilter" placeholder="Title or employee">
                    </div>
                    <div class="col-md-6 d-flex justify-content-end gap-2">
                        @if ($canScheduleMeetings ?? false)
                            <button type="button" class="btn btn-primary" id="scheduleMeetingBtn">Schedule Meeting</button>
                        @endif
                    </div>
                </div>
            </div>
        @include('partials.list-pagination-header', ['perPageId' => 'meetingsPerPage'])
        <div class="table-responsive">
                <table class="companies-table table mb-0">
                    <thead>
                        <tr>
                            <th>Meeting</th>
                            <th>Employee</th>
                            <th>When</th>
                            <th>Duration</th>
                            <th>Meeting link</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="meetingsTableBody">
                        <tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
                @include('partials.list-pagination-footer', [
                    'infoId' => 'meetingsPaginationInfo',
                    'listId' => 'meetingsPaginationList',
                    'perPageId' => 'meetingsPerPage',
                    'wrapClass' => 'content-card-body border-top',
                    'ariaLabel' => 'Meetings pagination',
                ])
        </div>
    </div>

    <div class="modal fade" id="meetingScheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="meetingScheduleForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="meetingScheduleModalLabel">Schedule One-on-one</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="meetingTitle" class="form-label">Title</label>
                                <input type="text" class="form-control" id="meetingTitle" required maxlength="255" value="One-on-one Meeting">
                            </div>
                            <div class="col-md-4">
                                <label for="meetingDuration" class="form-label">Duration (minutes)</label>
                                <select class="form-select" id="meetingDuration">
                                    <option value="15">15 min</option>
                                    <option value="30" selected>30 min</option>
                                    <option value="45">45 min</option>
                                    <option value="60">60 min</option>
                                    <option value="90">90 min</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                @include('partials.employee-search-select', [
                                    'inputId' => 'meetingEmployeeSearch',
                                    'hiddenId' => 'meetingEmployeeId',
                                    'label' => 'Team member',
                                    'placeholder' => 'Search employee',
                                ])
                            </div>
                            <div class="col-md-6">
                                <label for="meetingScheduledAt" class="form-label">Date & time</label>
                                <input type="datetime-local" class="form-control" id="meetingScheduledAt" required>
                            </div>
                            <div class="col-12">
                                <label for="meetingAgenda" class="form-label">Agenda</label>
                                <textarea class="form-control" id="meetingAgenda" rows="3" maxlength="5000" placeholder="Topics to discuss, updates, blockers…"></textarea>
                            </div>
                            <div class="col-12">
                                <label for="meetingLink" class="form-label">Meeting link (optional)</label>
                                <input type="url" class="form-control" id="meetingLink" placeholder="https://zoom.us/j/..., https://teams.microsoft.com/..., https://meet.google.com/...">
                                <div class="form-text">Paste a video meeting link from Zoom, Microsoft Teams, Google Meet, or any other platform.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="meetingDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="meetingDetailModalLabel">Meeting Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="meetingDetailBody">
                    <div class="text-center text-muted py-4">Loading…</div>
                </div>
                <div class="modal-footer" id="meetingDetailFooter"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/one-on-one-meetings.js'])
@endpush
