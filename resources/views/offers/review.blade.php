@extends('layouts.public-document')

@section('title', ($offer->title ?? 'Offer Letter') . ' — ' . ($companyName ?? config('app.name')))
@section('company_name', $companyName)
@section('page_heading', $offer->title)

@section('header_actions')
    <a href="{{ route('offer.pdf', ['token' => $token]) }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">Download PDF</a>
@endsection

@section('content')
    <div id="offerReviewRoot"
         data-token="{{ $token }}"
         data-can-respond="{{ $canRespond ? '1' : '0' }}"
         data-is-accepted="{{ $isAccepted ? '1' : '0' }}"
         data-is-declined="{{ $isDeclined ? '1' : '0' }}"
         data-candidate-email="{{ $offer->candidate?->email }}">

        <div id="offerReviewAlert" class="alert alert-danger d-none" role="alert"></div>

        @if ($isAccepted)
            <div class="content-card profile-page-card mb-4">
                <div class="content-card-body text-center py-5">
                    <div class="display-6 mb-3" aria-hidden="true">✓</div>
                    <h2 class="h4 mb-2">Offer Accepted</h2>
                    <p class="text-muted mb-0">Thank you, {{ $candidateName ?: 'Candidate' }}. Your signed offer has been recorded.</p>
                </div>
            </div>
        @elseif ($isDeclined)
            <div class="content-card profile-page-card mb-4">
                <div class="content-card-body text-center py-5">
                    <h2 class="h4 mb-2">Offer Declined</h2>
                    <p class="text-muted mb-0">You have declined this offer. Contact HR if you have questions.</p>
                </div>
            </div>
        @else
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="content-card profile-page-card mb-4">
                        <div class="content-card-body offer-letter-preview">
                            {!! $offer->letter_html !!}
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    @if ($canRespond)
                    <div class="content-card profile-page-card mb-4" id="offerSignCard">
                        <div class="content-card-body">
                            <h2 class="h5 mb-3">Sign &amp; Accept</h2>
                            <p class="text-muted small">Draw your signature below or upload a signature image. Email verification is required before acceptance.</p>

                            <ul class="nav nav-tabs mb-3" id="offerSignatureTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="offer-draw-tab" data-bs-toggle="tab" data-bs-target="#offerDrawPane" type="button" role="tab">Draw</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="offer-upload-tab" data-bs-toggle="tab" data-bs-target="#offerUploadPane" type="button" role="tab">Upload</button>
                                </li>
                            </ul>

                            <div class="tab-content mb-3">
                                <div class="tab-pane fade show active" id="offerDrawPane" role="tabpanel">
                                    <canvas id="offerSignatureCanvas" class="border rounded bg-white w-100" style="height: 160px; touch-action: none;"></canvas>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="offerSignatureClearBtn">Clear signature</button>
                                </div>
                                <div class="tab-pane fade" id="offerUploadPane" role="tabpanel">
                                    <input type="file" class="form-control" id="offerSignatureFile" accept="image/png,image/jpeg,image/jpg">
                                    <div class="form-text">PNG or JPG, max 2 MB.</div>
                                    <img id="offerSignaturePreview" class="img-fluid border rounded mt-2 d-none" alt="Uploaded signature preview">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="offerSignatureName" class="form-label">Full name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="offerSignatureName" value="{{ $candidateName }}" required>
                            </div>

                            <div id="offerOtpSection" class="border rounded p-3 bg-light d-none mb-3">
                                <p class="small text-muted mb-2">We sent a 6-digit code to <strong id="offerOtpEmail">{{ $offer->candidate?->email }}</strong>.</p>
                                <label for="offerOtpInput" class="form-label">Verification code</label>
                                <input type="text" class="form-control mb-2" id="offerOtpInput" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="Enter 6-digit code">
                                <button type="button" class="btn btn-sm btn-link p-0" id="offerResendOtpBtn">Resend code</button>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-primary" id="offerAcceptBtn">Accept Offer</button>
                                <button type="button" class="btn btn-outline-danger" id="offerDeclineBtn">Decline Offer</button>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="content-card profile-page-card">
                        <div class="content-card-body">
                            <p class="text-muted mb-0">This offer is no longer open for response.</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="modal fade" id="offerDeclineModal" tabindex="-1" aria-labelledby="offerDeclineModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="offerDeclineModalLabel">Decline Offer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="offerDeclineReason" class="form-label">Reason (optional)</label>
                    <textarea class="form-control" id="offerDeclineReason" rows="3" maxlength="1000"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="offerDeclineConfirmBtn">Confirm Decline</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/offer-review.js'])
@endpush
