@extends('hiring.layout')

@section('hiring-content')
    <div class="content-card">
        <div class="content-card-body">
            <form id="careersForm">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label" for="careersHeroTitle">Hero Title</label>
                        <input type="text" class="form-control" id="careersHeroTitle">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="careersHeroSubtitle">Hero Subtitle</label>
                        <input type="text" class="form-control" id="careersHeroSubtitle">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="careersAboutHtml">About (HTML)</label>
                        <textarea class="form-control font-monospace" id="careersAboutHtml" rows="5"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="careersHeaderHtml">Header (HTML)</label>
                        <textarea class="form-control font-monospace" id="careersHeaderHtml" rows="4"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="careersFooterHtml">Footer (HTML)</label>
                        <textarea class="form-control font-monospace" id="careersFooterHtml" rows="4"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="careersBanner">Banner Image</label>
                        <input type="file" class="form-control" id="careersBanner" accept="image/jpeg,image/png,image/webp">
                        <div class="mt-2" id="careersBannerPreview"></div>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="careersIsPublished">
                            <label class="form-check-label" for="careersIsPublished">Published</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Public URL</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="careersPublicUrl" readonly>
                            <a class="btn btn-outline-secondary" id="careersPreviewLink" href="#" target="_blank" rel="noopener">Preview</a>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save Careers Page</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
