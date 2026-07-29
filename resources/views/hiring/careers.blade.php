@extends('hiring.layout')

@section('hiring-content')
    <div class="content-card companies-list-card">
        <div class="content-card-body border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h5 mb-1">Public Careers Page</h2>
                <p class="text-muted small mb-0">Design your careers site like a modern landing page — hero, stats, culture, jobs, testimonials, and apply form. All content is dynamic.</p>
            </div>
            <a class="btn btn-outline-primary btn-sm" id="careersPreviewLink" href="#" target="_blank" rel="noopener">Preview Page</a>
        </div>

        <div class="content-card-body">
            <ul class="nav nav-tabs mb-4" id="careersEditorTabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#careersTabGeneral" type="button">General</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#careersTabSections" type="button">Sections</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#careersTabContent" type="button">Culture & Stats</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#careersTabSocial" type="button">Testimonials & Footer</button></li>
            </ul>

            <form id="careersForm">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="careersTabGeneral">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="careersHeroTitle">Hero Title</label>
                                <input type="text" class="form-control" id="careersHeroTitle" placeholder="We're not just a company. We're your growth partner.">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="careersHeroSubtitle">Hero Subtitle</label>
                                <input type="text" class="form-control" id="careersHeroSubtitle" placeholder="Join a team that builds the future.">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="careersHeroCtaText">Hero CTA Text</label>
                                <input type="text" class="form-control" id="careersHeroCtaText" placeholder="View Open Roles">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="careersHeroCtaUrl">Hero CTA Link</label>
                                <input type="text" class="form-control" id="careersHeroCtaUrl" placeholder="#open-roles">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="careersThemePrimary">Primary Color</label>
                                <input type="color" class="form-control form-control-color w-100" id="careersThemePrimary" value="#0f172a">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="careersThemeAccent">Accent Color</label>
                                <input type="color" class="form-control form-control-color w-100" id="careersThemeAccent" value="#2563eb">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="careersMetaTitle">SEO Title</label>
                                <input type="text" class="form-control" id="careersMetaTitle">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="careersMetaDescription">SEO Description</label>
                                <input type="text" class="form-control" id="careersMetaDescription">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="careersBanner">Hero Banner Image</label>
                                <input type="file" class="form-control" id="careersBanner" accept="image/jpeg,image/png,image/webp">
                                <div class="mt-2" id="careersBannerPreview"></div>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="careersIsPublished">
                                    <label class="form-check-label" for="careersIsPublished">Published (live on public URL)</label>
                                </div>
                                <p class="text-muted small mb-0 mt-2" id="careersPublishHint">The public URL returns a “coming soon” page until you publish.</p>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Public URL</label>
                                <input type="text" class="form-control" id="careersPublicUrl" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="careersAboutHtml">About / Culture HTML (optional rich block)</label>
                                <textarea class="form-control font-monospace" id="careersAboutHtml" rows="4"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="careersTabSections">
                        <div class="row g-3 mb-4">
                            <div class="col-12"><h3 class="h6">Marquee Tags</h3></div>
                            <div class="col-12"><div id="careersMarqueeList" class="d-flex flex-column gap-2"></div></div>
                            <div class="col-12"><button type="button" class="btn btn-sm btn-outline-secondary" id="careersAddMarquee">+ Add Tag</button></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Culture Eyebrow</label><input type="text" class="form-control" data-section-title="culture.eyebrow"></div>
                            <div class="col-md-6"><label class="form-label">Jobs Eyebrow</label><input type="text" class="form-control" data-section-title="jobs.eyebrow"></div>
                            <div class="col-md-6"><label class="form-label">Culture Title</label><input type="text" class="form-control" data-section-title="culture.title"></div>
                            <div class="col-md-6"><label class="form-label">Jobs Title</label><input type="text" class="form-control" data-section-title="jobs.title"></div>
                            <div class="col-md-6"><label class="form-label">Culture Subtitle</label><textarea class="form-control" rows="2" data-section-title="culture.subtitle"></textarea></div>
                            <div class="col-md-6"><label class="form-label">Jobs Subtitle</label><textarea class="form-control" rows="2" data-section-title="jobs.subtitle"></textarea></div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="careersTabContent">
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2"><h3 class="h6 mb-0">Why Join Cards</h3><button type="button" class="btn btn-sm btn-outline-secondary" id="careersAddWhyJoin">+ Add Card</button></div>
                            <div id="careersWhyJoinList" class="d-flex flex-column gap-2"></div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2"><h3 class="h6 mb-0">Stats</h3><button type="button" class="btn btn-sm btn-outline-secondary" id="careersAddStat">+ Add Stat</button></div>
                            <div id="careersStatsList" class="d-flex flex-column gap-2"></div>
                            <p class="form-text mt-2">Open roles count is shown automatically from live job postings.</p>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="careersTabSocial">
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2"><h3 class="h6 mb-0">Testimonials</h3><button type="button" class="btn btn-sm btn-outline-secondary" id="careersAddTestimonial">+ Add Testimonial</button></div>
                            <div id="careersTestimonialsList" class="d-flex flex-column gap-2"></div>
                        </div>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2"><h3 class="h6 mb-0">Trust Badges</h3><button type="button" class="btn btn-sm btn-outline-secondary" id="careersAddBadge">+ Add Badge</button></div>
                            <div id="careersBadgesList" class="d-flex flex-column gap-2"></div>
                        </div>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2"><h3 class="h6 mb-0">Social Links</h3><button type="button" class="btn btn-sm btn-outline-secondary" id="careersAddSocial">+ Add Link</button></div>
                            <div id="careersSocialList" class="d-flex flex-column gap-2"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="careersFooterNote">Footer Note</label>
                            <textarea class="form-control" id="careersFooterNote" rows="2"></textarea>
                        </div>
                        <div>
                            <label class="form-label" for="careersFooterHtml">Custom Footer HTML</label>
                            <textarea class="form-control font-monospace" id="careersFooterHtml" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary">Save Careers Page</button>
                </div>
            </form>
        </div>
    </div>
@endsection
