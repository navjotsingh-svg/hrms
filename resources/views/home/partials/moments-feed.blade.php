<div id="momentsPageRoot" data-can-comment="{{ (Auth::user()->hasPermission('home.moments.comment') || Auth::user()->hasPermission('home.moments.view')) ? '1' : '0' }}">
    <div id="momentsAlert" class="alert d-none"></div>

    @if (Auth::user()->isCompanyAdmin())
        <div class="content-card mb-4 d-none" id="momentsTemplatesCard">
            <div class="content-card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h6 mb-1">Celebration message templates</h2>
                        <p class="text-muted small mb-0">Set default messages for birthdays, anniversaries, and new joiners. Use placeholders: <code>{name}</code>, <code>{years}</code>, <code>{employee_code}</code></p>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="momentsTemplatesToggle" aria-expanded="false">Edit templates</button>
                </div>
                <form id="momentsTemplatesForm" class="d-none">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="momentsTemplateBirthday">Birthday</label>
                            <textarea class="form-control" id="momentsTemplateBirthday" rows="3" maxlength="2000"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="momentsTemplateAnniversary">Work anniversary</label>
                            <textarea class="form-control" id="momentsTemplateAnniversary" rows="3" maxlength="2000"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="momentsTemplateNewJoinee">New joiner</label>
                            <textarea class="form-control" id="momentsTemplateNewJoinee" rows="3" maxlength="2000"></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary btn-sm" id="momentsTemplatesSaveBtn">Save templates</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if (Auth::user()->hasPermission('home.moments.post'))
        <div class="content-card mb-4">
            <div class="content-card-body">
                <form id="momentsPostForm">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="momentsPostType">Post type</label>
                            <select class="form-select" id="momentsPostType">
                                <option value="post">General post</option>
                                <option value="birthday">Birthday post</option>
                                <option value="work_anniversary">Work anniversary post</option>
                                <option value="new_joinee">New joiner post</option>
                            </select>
                        </div>
                        <div class="col-md-8 d-none" id="momentsPostEmployeeWrap">
                            <label class="form-label fw-semibold" for="momentsPostEmployee">Team member</label>
                            <select class="form-select" id="momentsPostEmployee">
                                <option value="">Select employee</option>
                            </select>
                            <div class="form-text" id="momentsPostTemplateHint"></div>
                        </div>
                    </div>
                    <label class="form-label fw-semibold" for="momentsPostContent">Message</label>
                    <textarea class="form-control mb-3" id="momentsPostContent" rows="3" maxlength="5000" placeholder="Share an update, praise a colleague, or publish an announcement..."></textarea>
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
        <div class="btn-group flex-wrap moments-filter-group" role="group" aria-label="Filter feed">
            <button type="button" class="btn btn-sm btn-outline-secondary active" data-moments-filter="">
                All <span class="moments-filter-badge d-none" data-moments-filter-count="all"></span>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-moments-filter="post">
                Posts <span class="moments-filter-badge d-none" data-moments-filter-count="post"></span>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-moments-filter="birthday">
                Birthdays <span class="moments-filter-badge d-none" data-moments-filter-count="birthday"></span>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-moments-filter="work_anniversary">
                Work Anniversaries <span class="moments-filter-badge d-none" data-moments-filter-count="work_anniversary"></span>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-moments-filter="new_joinee">
                New Joiners <span class="moments-filter-badge d-none" data-moments-filter-count="new_joinee"></span>
            </button>
        </div>
    </div>

    <div class="content-card mb-3 d-none" id="momentsAuthorStatsCard">
        <div class="content-card-body py-3">
            <h2 class="h6 mb-2">Posts by team member</h2>
            <div id="momentsAuthorStats" class="moments-author-stats"></div>
        </div>
    </div>

    @include('partials.list-pagination-header', ['perPageId' => 'momentsPerPage'])
    <div id="momentsFeed" class="moments-feed"></div>
    <div id="momentsEmpty" class="text-center text-muted py-5 d-none">No posts yet. Share the first update on your social wall.</div>
    @include('partials.list-pagination-footer', [
        'infoId' => 'momentsPaginationInfo',
        'listId' => 'momentsPagination',
        'perPageId' => 'momentsPerPage',
        'wrapClass' => 'mt-3 companies-pagination-footer',
        'ariaLabel' => 'Moments pagination',
    ])
</div>
