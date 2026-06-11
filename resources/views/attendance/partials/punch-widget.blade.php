@php($prefix = $prefix ?? 'attendance')
<div class="attendance-punch-widget">
    <div class="attendance-punch-widget-top">
        <div class="attendance-status-pill" id="{{ $prefix }}NextActionPill">Loading status...</div>
        <div class="attendance-punch-summary" id="{{ $prefix }}TodaySummary">—</div>
    </div>

    <div class="small text-muted attendance-punch-location" id="{{ $prefix }}LocationStatus">
        Location will be captured when you punch.
    </div>

    <button type="button" class="btn btn-primary attendance-punch-btn" id="{{ $prefix }}PunchBtn" disabled>
        Mark Attendance
    </button>
</div>

<div class="modal fade attendance-punch-modal" id="{{ $prefix }}PunchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="{{ $prefix }}PunchModalTitle">Punch In</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="small text-muted mb-2" id="{{ $prefix }}ModalHint">Align your face in the frame and tap capture.</p>
                <div class="attendance-camera-wrap attendance-camera-wrap--modal mb-2">
                    <video id="{{ $prefix }}Camera" class="attendance-camera d-none" autoplay playsinline muted></video>
                    <canvas id="{{ $prefix }}CameraCanvas" class="d-none"></canvas>
                    <div class="attendance-camera-placeholder" id="{{ $prefix }}CameraPlaceholder">
                        <span>Opening camera...</span>
                    </div>
                </div>
                <div class="small text-muted" id="{{ $prefix }}ModalLocationStatus">Getting location...</div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="{{ $prefix }}CaptureBtn" disabled>
                    Take Photo & Punch
                </button>
            </div>
        </div>
    </div>
</div>
