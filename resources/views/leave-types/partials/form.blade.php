<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Leave Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="name" name="name" maxlength="150" required>
        <div class="invalid-feedback"></div>
    </div>
    <div class="col-md-6">
        <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
        <input type="text" class="form-control text-uppercase" id="code" name="code" maxlength="20" required>
        <div class="invalid-feedback"></div>
    </div>
    <div class="col-md-4">
        <label for="annual_quota" class="form-label" id="annualQuotaLabel">Annual Quota (days)</label>
        <input type="number" class="form-control" id="annual_quota" name="annual_quota" min="0" max="365" step="0.5" placeholder="Leave empty for unlimited">
        <div class="invalid-feedback"></div>
    </div>
    <div class="col-md-4">
        <label for="max_days_per_request" class="form-label">Max Days Per Request</label>
        <input type="number" class="form-control" id="max_days_per_request" name="max_days_per_request" min="0.5" max="365" step="0.5" placeholder="Empty = apply all at once">
        <div class="form-text">Set to 1 for one-day-at-a-time. Leave empty to allow full balance in one request.</div>
        <div class="invalid-feedback"></div>
    </div>
    <div class="col-md-4">
        <label for="max_days_per_month" class="form-label">Max Days Per Month</label>
        <input type="number" class="form-control" id="max_days_per_month" name="max_days_per_month" min="0.5" max="365" step="0.5" placeholder="Empty = no monthly cap">
        <div class="form-text">Example: Casual Leave = 2 means max 2 days per calendar month.</div>
        <div class="invalid-feedback"></div>
    </div>
    <div class="col-md-4">
        <label for="sort_order" class="form-label">Sort Order</label>
        <input type="number" class="form-control" id="sort_order" name="sort_order" min="0" max="999" value="0">
    </div>
    <div class="col-md-4">
        <label for="color" class="form-label">Color</label>
        <input type="color" class="form-control form-control-color w-100" id="color" name="color" value="#3b82f6">
    </div>
    <div class="col-md-4">
        <label for="is_paid" class="form-label">Paid Leave</label>
        <select class="form-select" id="is_paid" name="is_paid">
            <option value="1">Yes</option>
            <option value="0">No</option>
        </select>
    </div>
    <div class="col-md-4">
        <label for="allows_attendance_punch" class="form-label">Allow Punch In/Out</label>
        <select class="form-select" id="allows_attendance_punch" name="allows_attendance_punch">
            <option value="0">No — blocks attendance punch</option>
            <option value="1">Yes — e.g. Work From Home</option>
        </select>
        <div class="form-text">Enable for WFH-style leave. Employee can still punch in/out on approved days.</div>
    </div>
    <div class="col-md-4">
        <label for="requires_proof" class="form-label">Requires Proof</label>
        <select class="form-select" id="requires_proof" name="requires_proof">
            <option value="0">No</option>
            <option value="1">Yes (before approval)</option>
        </select>
        <div class="form-text">If yes, employee may apply without proof but must upload before HR approves.</div>
    </div>
    @include('partials.status-toggle', ['colClass' => 'col-md-4'])
</div>
