@php
    $infoId = $infoId ?? 'listPaginationInfo';
    $listId = $listId ?? 'listPaginationList';
    $wrapId = $wrapId ?? null;
    $wrapClass = $wrapClass ?? 'content-card-body border-top companies-pagination-footer';
    $ariaLabel = $ariaLabel ?? 'List pagination';
    $infoText = $infoText ?? '';
@endphp

<div @if($wrapId) id="{{ $wrapId }}" @endif class="{{ $wrapClass }}">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="small text-muted" id="{{ $infoId }}">{{ $infoText }}</div>
        <nav aria-label="{{ $ariaLabel }}">
            <ul class="pagination pagination-sm mb-0" id="{{ $listId }}"></ul>
        </nav>
    </div>
</div>
