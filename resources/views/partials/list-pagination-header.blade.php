@php
    $perPageId = $perPageId ?? 'listPerPage';
    $perPageOptions = $perPageOptions ?? [10, 25, 50];
    $defaultPerPage = $defaultPerPage ?? 10;
    $wrapClass = $wrapClass ?? 'content-card-body border-bottom companies-pagination-header';
@endphp

<div class="{{ $wrapClass }}">
    <div class="d-flex flex-wrap align-items-center justify-content-end">
        <div class="companies-per-page">
            <label for="{{ $perPageId }}" class="companies-per-page-label">Per page</label>
            <select id="{{ $perPageId }}" class="form-select form-select-sm companies-per-page-select">
                @foreach ($perPageOptions as $option)
                    <option value="{{ $option }}" @selected((int) $option === (int) $defaultPerPage)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
