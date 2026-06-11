<div class="col-md-6">
    <label for="name" class="form-label">Asset Name <span class="text-danger">*</span></label>
    <input type="text" class="form-control" id="name" name="name" required placeholder="e.g. Laptop, Monitor, Headset">
    <div class="invalid-feedback d-block" data-error="name"></div>
</div>

<div class="col-md-6">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select class="form-select" id="status" name="status" required>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </select>
    <div class="invalid-feedback d-block" data-error="status"></div>
</div>
