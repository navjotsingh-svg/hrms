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
    <div class="col-md-4 day-leave-fields">
        <label for="max_days_per_request" class="form-label">Max Days Per Request</label>
        <input type="number" class="form-control" id="max_days_per_request" name="max_days_per_request" min="0.5" max="365" step="0.5" placeholder="Empty = apply all at once">
        <div class="form-text">Set to 1 for one-day-at-a-time. Leave empty to allow full balance in one request.</div>
        <div class="invalid-feedback"></div>
    </div>
    <div class="col-md-4 day-leave-fields">
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
        <label for="requires_proof" class="form-label">Requires Proof</label>
        <select class="form-select" id="requires_proof" name="requires_proof">
            <option value="0">No</option>
            <option value="1">Yes (before approval)</option>
        </select>
        <div class="form-text">If yes, employee may apply without proof but must upload before HR approves.</div>
    </div>
    <div class="col-md-4">
        <label for="is_hourly_leave" class="form-label">Short Leave Type</label>
        <select class="form-select" id="is_hourly_leave" name="is_hourly_leave">
            <option value="0">No — full / half day only</option>
            <option value="1">Yes — short leave (hours)</option>
        </select>
        <div class="form-text">Use a dedicated type (e.g. Short Leave) for 1h / 2h requests. Annual quota is tracked in hours.</div>
    </div>
    <div class="col-md-4 hourly-leave-fields d-none">
        <label for="hourly_max_days_per_request" class="form-label">Max Hours Per Request</label>
        <input type="number" class="form-control" id="hourly_max_days_per_request" name="hourly_max_days_per_request" min="1" max="8" step="1" placeholder="Example: 2">
    </div>
    <div class="col-md-4 hourly-leave-fields d-none">
        <label for="max_hours_per_month" class="form-label">Max Hours Per Month</label>
        <input type="number" class="form-control" id="max_hours_per_month" name="max_hours_per_month" min="1" max="744" step="1" placeholder="Example: 4">
        <div class="form-text">Maximum total hours an employee can apply in one calendar month.</div>
    </div>
    <div class="col-md-8 hourly-leave-fields d-none">
        <label for="allowed_hourly_durations" class="form-label">Allowed Durations (minutes)</label>
        <input type="text" class="form-control" id="allowed_hourly_durations" name="allowed_hourly_durations" placeholder="60, 120">
        <div class="form-text">Comma-separated minutes shown to employees. Example: 60, 120 for 1 and 2 hour options.</div>
    </div>
    <div class="col-md-4">
        <label for="status" class="form-label">Status</label>
        <select class="form-select" id="status" name="status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
    </div>
</div>
