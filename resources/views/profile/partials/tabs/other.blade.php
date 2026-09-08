<div class="profile-tab-section">
    <div class="profile-tab-section-head">
        <h3 class="profile-tab-section-title">Other Information</h3>
        <p class="profile-tab-section-desc" id="profileOtherTabDesc">View assets assigned to you by HR.</p>
    </div>

    <div id="profileOtherEmpty" class="profile-tab-placeholder d-none">
        <div class="profile-tab-placeholder-icon" aria-hidden="true">&#128187;</div>
        <p class="profile-tab-placeholder-title">No assets configured</p>
        <p class="profile-tab-placeholder-text">Asset types will appear here once HR configures the company asset master.</p>
    </div>

    <div id="profileOtherContent" class="d-none">
        <div class="profile-info-card mb-4">
            <ul class="profile-asset-list list-unstyled mb-0" id="profileAssetList"></ul>
        </div>

        <div id="profileOtherManageSection" class="d-none">
            <div class="profile-info-card">
                <h4 class="profile-info-card-title mb-1">Update Assigned Assets</h4>
                <p class="text-muted small mb-3">Toggle assigned assets and add details of what has been given to the employee.</p>
                <form id="profileAssetsForm" class="profile-form">
                    <ul class="profile-asset-edit-list list-unstyled mb-0" id="profileAssetEditList"></ul>
                    <div class="d-flex align-items-center gap-3 mt-3">
                        <button type="submit" class="btn btn-primary" id="profileAssetsSubmit">Save Assigned Assets</button>
                        <span class="text-success small d-none" id="profileAssetsStatusMsg"></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
