<div class="modal fade profile-photo-crop-modal" id="profilePhotoCropModal" tabindex="-1" aria-labelledby="profilePhotoCropModalLabel" aria-hidden="true" data-bs-focus="false" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profilePhotoCropModalLabel">Crop profile photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Drag the photo to position your face inside the blue square. Use the buttons or scroll wheel to zoom.</p>
                <div class="profile-photo-crop-wrap" id="profilePhotoCropStage">
                    <canvas id="profilePhotoCropCanvas" class="profile-photo-crop-canvas" aria-label="Crop preview"></canvas>
                </div>
                <div class="profile-photo-crop-controls mt-3 d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="profilePhotoCropZoomOutBtn" aria-label="Zoom out">Zoom out</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="profilePhotoCropZoomResetBtn" aria-label="Reset zoom">Reset</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="profilePhotoCropZoomInBtn" aria-label="Zoom in">Zoom in</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="profilePhotoCropCancelBtn">Cancel</button>
                <button type="button" class="btn btn-primary" id="profilePhotoCropConfirmBtn">Use photo</button>
            </div>
        </div>
    </div>
</div>
