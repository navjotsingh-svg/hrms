<div class="col-md-6">
    <label for="name" class="form-label">Shift Name <span class="text-danger">*</span></label>
    <input type="text" class="form-control" id="name" name="name" required placeholder="e.g. General Shift, Night Shift">
    <div class="invalid-feedback d-block" data-error="name"></div>
</div>

<div class="col-md-6">
    <label for="code" class="form-label">Shift Code</label>
    <input type="text" class="form-control" id="code" name="code" placeholder="e.g. GS, NS">
    <div class="form-text">Optional short code for internal reference.</div>
    <div class="invalid-feedback d-block" data-error="code"></div>
</div>

<div class="col-md-4">
    <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
    <input type="time" class="form-control" id="start_time" name="start_time" required>
    <div class="invalid-feedback d-block" data-error="start_time"></div>
</div>

<div class="col-md-4">
    <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
    <input type="time" class="form-control" id="end_time" name="end_time" required>
    <div class="invalid-feedback d-block" data-error="end_time"></div>
</div>

<div class="col-md-4">
    <label for="break_duration_minutes" class="form-label">Break Duration (minutes)</label>
    <input type="number" class="form-control" id="break_duration_minutes" name="break_duration_minutes" min="0" max="480" step="1" value="60">
    <div class="form-text">For reference only. Full-day attendance is based on total shift hours (start to end), not net of break.</div>
    <div class="invalid-feedback d-block" data-error="break_duration_minutes"></div>
</div>

<div class="col-md-6">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select class="form-select" id="status" name="status" required>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </select>
    <div class="invalid-feedback d-block" data-error="status"></div>
</div>

<div class="col-md-6 d-flex align-items-end">
    <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" id="is_overnight" name="is_overnight" value="1">
        <label class="form-check-label" for="is_overnight">Overnight shift (end time is next day)</label>
    </div>
    <div class="invalid-feedback d-block" data-error="is_overnight"></div>
</div>

<div class="col-12">
    <label for="description" class="form-label">Description</label>
    <textarea class="form-control" id="description" name="description" rows="3" placeholder="Optional notes about this shift"></textarea>
    <div class="invalid-feedback d-block" data-error="description"></div>
</div>
