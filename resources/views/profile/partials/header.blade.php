<div class="profile-sidebar-card">
    <div class="profile-card-photo-block">
        <div class="profile-card-photo" id="profilePhotoWrap" aria-hidden="true">
            <img id="profilePhotoImage" class="profile-card-photo-img d-none" alt="">
            <span id="profileAvatarInitials" class="profile-card-photo-initials">—</span>
        </div>
        <button type="button" class="profile-card-photo-edit btn btn-light btn-sm" id="profilePhotoUploadBtn" title="Upload profile photo">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168z"/></svg>
        </button>
        <input type="file" id="profilePhotoInput" accept="image/jpeg,image/jpg,image/png,.jpg,.jpeg,.png" class="d-none">
    </div>

    <h2 class="profile-card-name" id="profileDisplayName">Loading profile...</h2>
    <p class="profile-card-designation mb-1" id="profileDisplayDesignation">—</p>
    <a class="profile-card-email" id="profileDisplayEmail" href="#">—</a>

    <div id="profilePhotoStatus" class="profile-photo-status d-none"></div>

    <div class="profile-card-actions">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="profileEditTabBtn">Edit</button>
        <button type="button" class="btn btn-outline-primary btn-sm" id="profileViewWorkBtn">View more &rarr;</button>
    </div>

    <div class="profile-card-org">
        <div class="profile-card-org-section">
            <div class="profile-card-org-label">Manager</div>
            <div class="profile-card-org-list" id="profileManagerList">
                <span class="text-muted small">—</span>
            </div>
        </div>
        <div class="profile-card-org-section">
            <div class="profile-card-org-label">Direct Reports</div>
            <div class="profile-card-org-list profile-card-org-list--avatars" id="profileDirectReportsList">
                <span class="text-muted small">—</span>
            </div>
        </div>
    </div>
</div>
