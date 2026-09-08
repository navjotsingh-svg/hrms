@php
    $isActive = ($value ?? 'active') !== 'inactive';
@endphp

<div class="{{ $colClass ?? 'col-md-6' }}">
    <label class="form-label d-block" for="status_toggle">Status @if($required ?? true)<span class="text-danger">*</span>@endif</label>
    <div class="form-check form-switch company-status-switch company-status-switch--solo mb-0">
        <input class="form-check-input" type="checkbox" role="switch" id="status_toggle" @checked($isActive) aria-label="Status">
    </div>
    <input type="hidden" id="status" name="status" value="{{ $isActive ? 'active' : 'inactive' }}">
    @if(!empty($showError))
        @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @endif
    <div class="invalid-feedback d-block" data-error="status"></div>
</div>
