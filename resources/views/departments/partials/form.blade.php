<div class="col-md-6">
    <label for="name" class="form-label">Department Name <span class="text-danger">*</span></label>
    <input type="text" class="form-control" id="name" name="name" required>
    <div class="invalid-feedback d-block" data-error="name"></div>
</div>

<div class="col-md-6">
    <label for="code" class="form-label">Department Code</label>
    <input type="text" class="form-control" id="code" name="code" placeholder="e.g. HR, IT, FIN">
    <div class="form-text">Optional short code used internally.</div>
    <div class="invalid-feedback d-block" data-error="code"></div>
</div>

<div class="col-md-6">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select class="form-select" id="status" name="status" required>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </select>
    <div class="invalid-feedback d-block" data-error="status"></div>
</div>

<div class="col-12">
    <label for="description" class="form-label">Description</label>
    <textarea class="form-control" id="description" name="description" rows="3" placeholder="Brief description of this department"></textarea>
    <div class="invalid-feedback d-block" data-error="description"></div>
</div>
