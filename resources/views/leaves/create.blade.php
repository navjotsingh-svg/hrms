@extends('layouts.app')

@section('title', 'Apply Leave - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Apply Leave</h1>
            <p class="page-subtitle mb-0">Submit a leave request with reason and supporting documents.</p>
        </div>
        <a href="{{ route('web.leave.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
@endsection

@section('content')
    <div id="leaveFormAlert" class="alert alert-danger d-none"></div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="content-card">
                <div class="content-card-body">
                    <form id="leaveForm" class="row g-4">
                        <div class="col-md-6">
                            <label for="leave_type_id" class="form-label">Leave Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="leave_type_id" name="leave_type_id" required></select>
                            <div id="leaveTypePolicyHint" class="form-text text-primary d-none"></div>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6" id="sessionWrap">
                            <label for="session" class="form-label">Session</label>
                            <select class="form-select" id="session" name="session">
                                <option value="full_day">Full Day</option>
                                <option value="first_half">First Half</option>
                                <option value="second_half">Second Half</option>
                            </select>
                            <div class="form-text" id="sessionHelpText">Half-day applies only when from and to date are the same.</div>
                        </div>
                        <div class="col-md-6 d-none" id="hourlyDurationWrap">
                            <label for="duration_minutes" class="form-label">Duration</label>
                            <select class="form-select" id="duration_minutes" name="duration_minutes"></select>
                            <div class="form-text" id="hourlyDeductionHint"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="from_date" class="form-label">From Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="from_date" name="from_date" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="to_date" class="form-label">To Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="to_date" name="to_date" required>
                            <div id="leaveDaysPreview" class="form-text d-none"></div>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12">
                            <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="4" maxlength="2000" required></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12">
                            <label for="proofs" class="form-label">Supporting Documents <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="file" class="form-control" id="proofs" name="proofs[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                            <div class="form-text">Optional at apply time. If proof is required for this leave type, upload it before approval — you can add documents later from the leave request page.</div>
                            <div id="proofPreview" class="small text-muted mt-2"></div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary" id="leaveSubmitBtn">Submit Leave Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="content-card">
                <div class="content-card-header border-bottom">
                    <h2 class="content-card-title mb-0">My Balances</h2>
                </div>
                <div class="content-card-body" id="leaveBalanceCards">
                    <div class="text-muted">Loading balances...</div>
                </div>
            </div>
        </div>
    </div>
    @vite(['resources/js/leaves-apply.js'])
@endsection
