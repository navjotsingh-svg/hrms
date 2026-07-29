@php($prefix = $prefix ?? 'attendance')
<div class="attendance-punch-widget">
    <div class="attendance-punch-widget-top">
        <div class="attendance-status-pill" id="{{ $prefix }}NextActionPill">Loading status...</div>
        <div class="attendance-punch-summary" id="{{ $prefix }}TodaySummary">—</div>
    </div>

    <div class="small text-muted attendance-punch-location" id="{{ $prefix }}LocationStatus">
        Location captured at punch time.
    </div>

    <button type="button" class="btn btn-primary attendance-punch-btn" id="{{ $prefix }}PunchBtn" disabled>
        Mark Attendance
    </button>
</div>

<div class="modal fade attendance-punch-modal" id="{{ $prefix }}PunchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="{{ $prefix }}PunchModalTitle">Punch In</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="attendance-punch-modal-layout" id="{{ $prefix }}PunchModalLayout">
                    <div class="attendance-punch-modal-camera-col" id="{{ $prefix }}PunchModalCameraCol">
                        <div class="attendance-camera-wrap attendance-camera-wrap--modal">
                            <video id="{{ $prefix }}Camera" class="attendance-camera d-none" autoplay playsinline webkit-playsinline muted></video>
                            <canvas id="{{ $prefix }}CameraCanvas" class="d-none"></canvas>
                            <div class="attendance-camera-placeholder" id="{{ $prefix }}CameraPlaceholder">
                                <span>Opening camera…</span>
                            </div>
                            <div class="attendance-live-match d-none" id="{{ $prefix }}LiveMatchOverlay" aria-live="polite">
                                <div class="attendance-live-match-value" id="{{ $prefix }}LiveMatchValue">—%</div>
                                <div class="attendance-live-match-label">match</div>
                            </div>
                        </div>
                    </div>

                    <div class="attendance-punch-modal-info-col">
                        <div class="attendance-punch-location-card">
                            <div class="attendance-punch-location-card-header">
                                <span class="attendance-punch-location-kicker">Location</span>
                                <span class="attendance-punch-location-badge" id="{{ $prefix }}ModalLocationBadge">Detecting</span>
                            </div>

                            <div class="attendance-punch-location-map-wrap" id="{{ $prefix }}ModalLocationMapWrap">
                                <div class="attendance-punch-location-map-loading" id="{{ $prefix }}ModalLocationMapLoading">
                                    <span class="attendance-punch-location-map-spinner" aria-hidden="true"></span>
                                    <span>Getting your location…</span>
                                </div>
                                <iframe
                                    id="{{ $prefix }}ModalLocationMap"
                                    class="attendance-punch-location-map d-none"
                                    title="Map showing your current location"
                                    loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade"
                                ></iframe>
                            </div>

                            <div class="attendance-punch-location-details">
                                <div class="attendance-punch-location-field-label">Address</div>
                                <div class="attendance-punch-location-field-value" id="{{ $prefix }}ModalLocationStatus">Getting location…</div>
                                <div class="attendance-punch-location-coords" id="{{ $prefix }}ModalLocationCoords"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="{{ $prefix }}CaptureBtn" disabled>
                    Take Photo & Punch
                </button>
            </div>
        </div>
    </div>
</div>
