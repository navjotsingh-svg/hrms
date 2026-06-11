<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Holiday Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="name" name="name" maxlength="150" required>
        <div class="invalid-feedback"></div>
    </div>

    <div class="col-md-6">
        <label for="date" class="form-label">Date <span class="text-danger">*</span></label>
        <input type="date" class="form-control" id="date" name="date" required>
        <div class="invalid-feedback"></div>
    </div>

    <div class="col-md-6">
        <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
        <select class="form-select" id="type" name="type" required>
            <option value="public">Public Holiday</option>
            <option value="company">Company Holiday</option>
            <option value="optional">Optional Holiday</option>
        </select>
        <div class="invalid-feedback"></div>
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
        <select class="form-select" id="status" name="status" required>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
        <div class="invalid-feedback"></div>
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <textarea class="form-control" id="description" name="description" rows="3" maxlength="1000"></textarea>
        <div class="invalid-feedback"></div>
    </div>
</div>
