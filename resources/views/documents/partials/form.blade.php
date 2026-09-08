<div class="col-md-6">
    <label for="name" class="form-label">Document Name <span class="text-danger">*</span></label>
    <input type="text" class="form-control" id="name" name="name" required placeholder="e.g. PAN Card, Aadhaar, Offer Letter">
    <div class="invalid-feedback d-block" data-error="name"></div>
</div>

<div class="col-md-6">
    <label for="code" class="form-label">Document Code</label>
    <input type="text" class="form-control" id="code" name="code" placeholder="e.g. PAN, AADHAAR">
    <div class="form-text">Optional short code used internally.</div>
    <div class="invalid-feedback d-block" data-error="code"></div>
</div>

<div class="col-md-6">
    <label for="allow_multiple" class="form-label">Upload Mode <span class="text-danger">*</span></label>
    <select class="form-select" id="allow_multiple" name="allow_multiple" required>
        <option value="0">Single file</option>
        <option value="1">Multiple files</option>
    </select>
    <div class="form-text">Single allows one file per employee. Multiple lets employees upload several files for this type.</div>
    <div class="invalid-feedback d-block" data-error="allow_multiple"></div>
</div>

@include('partials.status-toggle')

<div class="col-md-6 d-flex align-items-end">
    <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" id="is_required" name="is_required" value="1">
        <label class="form-check-label" for="is_required">Required for all employees</label>
    </div>
    <div class="invalid-feedback d-block" data-error="is_required"></div>
</div>

<div class="col-12">
    <label for="description" class="form-label">Description</label>
    <textarea class="form-control" id="description" name="description" rows="3" placeholder="Brief description or instructions for this document"></textarea>
    <div class="invalid-feedback d-block" data-error="description"></div>
</div>
