@php
    $infoId = $infoId ?? 'listPaginationInfo';
    $listId = $listId ?? 'listPaginationList';
    $wrapId = $wrapId ?? null;
    $wrapClass = $wrapClass ?? 'content-card-body border-top companies-pagination-footer';
    $ariaLabel = $ariaLabel ?? 'List pagination';
    $infoText = $infoText ?? '';
    $showPerPage = $showPerPage ?? false;
    $perPageId = $perPageId ?? 'listPerPage';
    $perPageOptions = $perPageOptions ?? [10, 25, 50];
    $defaultPerPage = $defaultPerPage ?? 10;
@endphp

<div @if($wrapId) id="{{ $wrapId }}" @endif class="{{ $wrapClass }}">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="small text-muted" id="{{ $infoId }}">{{ $infoText }}</div>
        <div class="d-flex flex-wrap align-items-center gap-3 ms-auto">
            @if ($showPerPage)
                <div class="companies-per-page">
                    <label for="{{ $perPageId }}" class="companies-per-page-label">Per page</label>
                    <select id="{{ $perPageId }}" class="form-select form-select-sm companies-per-page-select">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected((int) $option === (int) $defaultPerPage)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <nav aria-label="{{ $ariaLabel }}">
                <ul class="pagination pagination-sm mb-0" id="{{ $listId }}"></ul>
            </nav>
        </div>
    </div>
</div>
