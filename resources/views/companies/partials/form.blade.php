@php
    $company = $company ?? null;
@endphp

<div class="row g-4">
    <div class="col-12">
        <h6 class="form-section-title">Basic Information</h6>
    </div>

    <div class="col-md-6">
        <label for="name" class="form-label">Company Name <span class="text-danger">*</span></label>
        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $company?->name) }}" required>
        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="legal_name" class="form-label">Legal Name</label>
        <input type="text" name="legal_name" id="legal_name" class="form-control @error('legal_name') is-invalid @enderror" value="{{ old('legal_name', $company?->legal_name) }}">
        @error('legal_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Company Email <span class="text-danger">*</span></label>
        <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $company?->email) }}" required>
        <div class="form-text">Used as the company admin login email. A welcome email with password is sent on create.</div>
        @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="ajax-feedback" data-for="email"></div>
    </div>

    <div class="col-md-6">
        <label for="phone" class="form-label">Mobile Number</label>
        <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $company?->phone) }}" maxlength="10" inputmode="numeric" placeholder="10-digit mobile number">
        @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="ajax-feedback" data-for="phone"></div>
    </div>

    <div class="col-md-6">
        <label for="website" class="form-label">Website</label>
        <input type="url" name="website" id="website" class="form-control @error('website') is-invalid @enderror" value="{{ old('website', $company?->website) }}" placeholder="https://example.com">
        @error('website')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="logo" class="form-label">Company Logo</label>
        <input type="file" name="logo" id="logo" class="form-control @error('logo') is-invalid @enderror" accept="image/jpeg,image/png,image/jpg,image/webp,image/svg+xml">
        @error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="logo-preview-wrap mt-2" id="logoPreviewWrap" style="{{ $company?->logo ? 'display:flex;' : 'display:none;' }}">
            <img src="{{ $company?->logo_url ?? '' }}" alt="Company logo" class="d-none" id="logoPreviewImg" aria-hidden="true">
            <button type="button" class="btn btn-sm btn-outline-primary logo-view-btn" id="logoViewBtn" title="View logo">
                <span>&#128065;</span> View Logo
            </button>
        </div>
    </div>

    <div class="col-md-4">
        <label for="industry" class="form-label">Industry</label>
        <input type="text" name="industry" id="industry" class="form-control @error('industry') is-invalid @enderror" value="{{ old('industry', $company?->industry) }}" placeholder="e.g. IT, Manufacturing">
        @error('industry')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="founded_year" class="form-label">Founded Year</label>
        <input type="text" name="founded_year" id="founded_year" class="form-control @error('founded_year') is-invalid @enderror" value="{{ old('founded_year', $company?->founded_year) }}" maxlength="4" inputmode="numeric" placeholder="YYYY">
        @error('founded_year')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="employee_strength" class="form-label">Employee Strength</label>
        <select name="employee_strength" id="employee_strength" class="form-select @error('employee_strength') is-invalid @enderror">
            <option value="">Select range</option>
            @foreach (['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+'] as $range)
                <option value="{{ $range }}" {{ old('employee_strength', $company?->employee_strength) == $range ? 'selected' : '' }}>{{ $range }}</option>
            @endforeach
        </select>
        @error('employee_strength')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 mt-2">
        <h6 class="form-section-title">Legal & Tax Details</h6>
    </div>

    <div class="col-md-4">
        <label for="registration_number" class="form-label">Registration Number</label>
        <input type="text" name="registration_number" id="registration_number" class="form-control @error('registration_number') is-invalid @enderror" value="{{ old('registration_number', $company?->registration_number) }}">
        @error('registration_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="ajax-feedback" data-for="registration_number"></div>
    </div>

    <div class="col-md-4">
        <label for="gstin" class="form-label">GSTIN</label>
        <input type="text" name="gstin" id="gstin" class="form-control text-uppercase @error('gstin') is-invalid @enderror" value="{{ old('gstin', $company?->gstin) }}" maxlength="15" placeholder="15 character GSTIN">
        @error('gstin')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="ajax-feedback" data-for="gstin"></div>
    </div>

    <div class="col-md-4">
        <label for="pan_number" class="form-label">PAN Number</label>
        <input type="text" name="pan_number" id="pan_number" class="form-control text-uppercase @error('pan_number') is-invalid @enderror" value="{{ old('pan_number', $company?->pan_number) }}" maxlength="10" placeholder="10 character PAN">
        @error('pan_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="ajax-feedback" data-for="pan_number"></div>
    </div>

    <div class="col-12 mt-2">
        <h6 class="form-section-title">Contact Person</h6>
    </div>

    <div class="col-md-4">
        <label for="contact_person_name" class="form-label">Contact Person Name</label>
        <input type="text" name="contact_person_name" id="contact_person_name" class="form-control @error('contact_person_name') is-invalid @enderror" value="{{ old('contact_person_name', $company?->contact_person_name) }}">
        @error('contact_person_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="contact_person_email" class="form-label">Contact Person Email</label>
        <input type="email" name="contact_person_email" id="contact_person_email" class="form-control @error('contact_person_email') is-invalid @enderror" value="{{ old('contact_person_email', $company?->contact_person_email) }}">
        @error('contact_person_email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="contact_person_phone" class="form-label">Contact Person Mobile</label>
        <input type="text" name="contact_person_phone" id="contact_person_phone" class="form-control @error('contact_person_phone') is-invalid @enderror" value="{{ old('contact_person_phone', $company?->contact_person_phone) }}" maxlength="10" inputmode="numeric" placeholder="10-digit mobile number">
        @error('contact_person_phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="ajax-feedback" data-for="contact_person_phone"></div>
    </div>

    <div class="col-12 mt-2">
        <h6 class="form-section-title">Address</h6>
    </div>

    <div class="col-md-6">
        <label for="address_line_1" class="form-label">Address Line 1</label>
        <input type="text" name="address_line_1" id="address_line_1" class="form-control @error('address_line_1') is-invalid @enderror" value="{{ old('address_line_1', $company?->address_line_1) }}">
        @error('address_line_1')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="address_line_2" class="form-label">Address Line 2</label>
        <input type="text" name="address_line_2" id="address_line_2" class="form-control @error('address_line_2') is-invalid @enderror" value="{{ old('address_line_2', $company?->address_line_2) }}">
        @error('address_line_2')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="city" class="form-label">City</label>
        <input type="text" name="city" id="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $company?->city) }}">
        @error('city')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="state" class="form-label">State</label>
        <input type="text" name="state" id="state" class="form-control @error('state') is-invalid @enderror" value="{{ old('state', $company?->state) }}">
        @error('state')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="postal_code" class="form-label">Postal Code</label>
        <input type="text" name="postal_code" id="postal_code" class="form-control @error('postal_code') is-invalid @enderror" value="{{ old('postal_code', $company?->postal_code) }}" maxlength="6" inputmode="numeric" placeholder="6-digit PIN">
        @error('postal_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="country" class="form-label">Country</label>
        <input type="text" name="country" id="country" class="form-control @error('country') is-invalid @enderror" value="{{ old('country', $company?->country ?? 'India') }}">
        @error('country')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 mt-2">
        <h6 class="form-section-title">Settings</h6>
    </div>

    <div class="col-md-6">
        @include('partials.timezone-select', [
            'selected' => old('timezone', $company?->timezone ?? 'Asia/Kolkata'),
            'helpText' => 'Company default timezone. All IANA timezones are available.',
        ])
    </div>

    @include('partials.status-toggle', [
        'value' => old('status', $company?->status ?? 'active'),
        'showError' => true,
    ])

    <div class="col-12 company-description-field">
        <label for="description" class="form-label">Company Description</label>
        <div id="descriptionEditor" class="company-description-editor @error('description') is-invalid @enderror"></div>
        <textarea name="description" id="description" class="d-none">{{ old('description', $company?->description) }}</textarea>
        @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="ajax-feedback" data-for="description"></div>
    </div>
</div>
