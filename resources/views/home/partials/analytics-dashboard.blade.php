<div id="homeDashboardAlert" class="alert d-none"></div>
<div id="homeDashboardRoot" class="row g-4"></div>
<div id="homeDashboardEmpty" class="text-center text-muted py-5 d-none">No widgets on your dashboard yet. Click <strong>Add Charts</strong> to get started.</div>

<div class="modal fade" id="homeDashboardWidgetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content home-dashboard-gallery-modal">
            <div class="modal-header border-0 pb-0">
                <div>
                    <p class="home-dashboard-saved-charts mb-1" id="homeDashboardSavedCount">Saved Charts (0)</p>
                    <h5 class="modal-title mb-0">Add Charts</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3" id="homeDashboardWidgetOptions"></div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
