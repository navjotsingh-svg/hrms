@php
    use App\Support\TimezoneOptions;

    $fieldId = $fieldId ?? 'timezone';
    $fieldName = $fieldName ?? 'timezone';
    $selected = old($fieldName, $selected ?? 'UTC');
    $required = $required ?? true;
    $groupedTimezones = TimezoneOptions::grouped();
@endphp

<div class="{{ $wrapperClass ?? 'col-md-6' }}">
    <label for="{{ $fieldId }}" class="form-label">
        Timezone @if ($required)<span class="text-danger">*</span>@endif
    </label>
    <select
        name="{{ $fieldName }}"
        id="{{ $fieldId }}"
        class="form-select @error($fieldName) is-invalid @enderror"
        @if ($required) required @endif
    >
        @foreach ($groupedTimezones as $region => $timezones)
            <optgroup label="{{ $region }}">
                @foreach ($timezones as $timezone)
                    <option value="{{ $timezone }}" @selected($selected === $timezone)>
                        {{ TimezoneOptions::label($timezone) }}
                    </option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    @if (! empty($helpText))
        <div class="form-text">{{ $helpText }}</div>
    @endif
    @error($fieldName)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @else
        <div class="invalid-feedback d-block" data-error="{{ $fieldName }}"></div>
    @enderror
</div>
