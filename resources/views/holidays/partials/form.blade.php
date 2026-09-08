<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Holiday Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="name" name="name" maxlength="150" required>
        <div class="invalid-feedback"></div>
    </div>

    <div class="col-md-6">
        <label for="frequency" class="form-label">Fixed / Variable <span class="text-danger">*</span></label>
        <select class="form-select" id="frequency" name="frequency" required>
            <option value="fixed">Fixed</option>
            <option value="variable">Variable</option>
        </select>
        <div class="form-text">Fixed holidays repeat every year (e.g. 15 Aug). Variable holidays use a specific year.</div>
        <div class="invalid-feedback"></div>
    </div>

    <div class="col-md-6">
        <label for="duration" class="form-label">Duration <span class="text-danger">*</span></label>
        <select class="form-select" id="duration" name="duration" required>
            <option value="single">Single Day</option>
            <option value="range">Multiple Days</option>
        </select>
        <div class="invalid-feedback"></div>
    </div>

    <div class="col-md-6">
        <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
        <select class="form-select" id="type" name="type" required>
            <option value="public">Public Holiday</option>
            <option value="company">Company Holiday</option>
            <option value="optional">Optional Holiday</option>
            <option value="other">Other</option>
        </select>
        <div class="invalid-feedback"></div>
    </div>

    {{-- Fixed: day & month (single day, or start of range) --}}
    <div class="col-md-6 holiday-field holiday-field--fixed">
        <label for="start_day" class="form-label"><span class="holiday-start-label">Day</span> <span class="text-danger">*</span></label>
        <input type="number" class="form-control" id="start_day" name="start_day" min="1" max="31" placeholder="e.g. 15">
        <div class="invalid-feedback"></div>
    </div>
    <div class="col-md-6 holiday-field holiday-field--fixed">
        <label for="start_month" class="form-label"><span class="holiday-start-label">Month</span> <span class="text-danger">*</span></label>
        <select class="form-select" id="start_month" name="start_month">
            <option value="">Select month</option>
            @foreach (['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] as $index => $monthName)
                <option value="{{ $index + 1 }}">{{ $monthName }}</option>
            @endforeach
        </select>
        <div class="form-text holiday-fixed-hint">Repeats every year — no year needed.</div>
        <div class="invalid-feedback"></div>
    </div>

    {{-- Fixed: end of range --}}
    <div class="col-md-6 holiday-field holiday-field--fixed-range">
        <label for="end_day" class="form-label">End Day <span class="text-danger">*</span></label>
        <input type="number" class="form-control" id="end_day" name="end_day" min="1" max="31" placeholder="e.g. 2">
        <div class="invalid-feedback"></div>
    </div>
    <div class="col-md-6 holiday-field holiday-field--fixed-range">
        <label for="end_month" class="form-label">End Month <span class="text-danger">*</span></label>
        <select class="form-select" id="end_month" name="end_month">
            <option value="">Select month</option>
            @foreach (['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] as $index => $monthName)
                <option value="{{ $index + 1 }}">{{ $monthName }}</option>
            @endforeach
        </select>
        <div class="form-text">Can span year-end (e.g. 26 Dec – 2 Jan).</div>
        <div class="invalid-feedback"></div>
    </div>

    {{-- Variable: single day --}}
    <div class="col-md-6 holiday-field holiday-field--variable-single">
        <label for="holiday_date" class="form-label">Date <span class="text-danger">*</span></label>
        <input type="date" class="form-control" id="holiday_date" name="holiday_date" min="1000-01-01" max="9999-12-31">
        <div class="invalid-feedback"></div>
    </div>

    {{-- Variable: multiple days --}}
    <div class="col-md-6 holiday-field holiday-field--variable-range">
        <label for="from_date" class="form-label">From Date <span class="text-danger">*</span></label>
        <input type="date" class="form-control" id="from_date" name="from_date" min="1000-01-01" max="9999-12-31">
        <div class="invalid-feedback"></div>
    </div>
    <div class="col-md-6 holiday-field holiday-field--variable-range">
        <label for="to_date" class="form-label">To Date <span class="text-danger">*</span></label>
        <input type="date" class="form-control" id="to_date" name="to_date" min="1000-01-01" max="9999-12-31">
        <div class="invalid-feedback"></div>
    </div>

    @include('partials.status-toggle')

    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <textarea class="form-control" id="description" name="description" rows="3" maxlength="1000"></textarea>
        <div class="invalid-feedback"></div>
    </div>
</div>
