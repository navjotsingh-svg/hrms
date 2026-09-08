@extends('layouts.app')

@section('title', 'Apply WFH - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Apply Work From Home</h1>
            <p class="page-subtitle mb-0">Request WFH for a single day or a date range. Approved WFH days allow punch in/out like a regular working day.</p>
        </div>
        <a href="{{ route('web.wfh.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
@endsection

@section('content')
    <div id="wfhFormAlert" class="alert alert-danger d-none"></div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="content-card">
                <div class="content-card-body">
                    <form id="wfhForm" class="row g-4">
                        <div class="col-md-6">
                            <label for="wfh_application_type" class="form-label">Application Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="wfh_application_type" name="wfh_application_type" required>
                                <option value="single" selected>Single Day</option>
                                <option value="multiple">Date Range</option>
                            </select>
                            <div class="form-text">Use date range when you need WFH for multiple consecutive days.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="from_date" class="form-label" id="fromDateLabel">WFH Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="from_date" name="from_date" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 d-none" id="toDateWrap">
                            <label for="to_date" class="form-label">To Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="to_date" name="to_date">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12">
                            <div id="wfhDaysPreview" class="form-text d-none"></div>
                        </div>
                        <div class="col-12">
                            <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="4" maxlength="2000" required placeholder="Briefly explain why you need to work from home"></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12">
                            <label for="proofs" class="form-label">Attachments <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="file" class="form-control" id="proofs" name="proofs[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                            <div class="form-text">Upload supporting documents if needed (images or PDF, max 5MB each).</div>
                            <div id="proofPreview" class="small text-muted mt-2"></div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary" id="wfhSubmitBtn">Submit WFH Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="content-card">
                <div class="content-card-header border-bottom">
                    <h2 class="content-card-title mb-0">About WFH</h2>
                </div>
                <div class="content-card-body">
                    <ul class="small text-muted mb-0 ps-3">
                        <li class="mb-2">WFH requests require manager/HR approval.</li>
                        <li class="mb-2">Once approved, you can punch in and out on those days.</li>
                        <li class="mb-2">Weekends and holidays in a date range are excluded automatically.</li>
                        <li>WFH does not consume leave balance.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    @vite(['resources/js/wfh-apply.js'])
@endsection
