{{-- Deprecated: use partials.list-pagination-top instead. --}}
@php
    $perPageId = $perPageId ?? 'listPerPage';
    $perPageOptions = $perPageOptions ?? [10, 25, 50];
    $defaultPerPage = $defaultPerPage ?? 10;
@endphp

@include('partials.list-pagination-top', [
    'infoId' => $infoId ?? 'listPaginationInfo',
    'listId' => $listId ?? 'listPaginationList',
    'perPageId' => $perPageId,
    'wrapId' => $wrapId ?? null,
    'wrapClassTop' => $wrapClass ?? 'content-card-body border-bottom companies-pagination-footer',
    'ariaLabel' => $ariaLabel ?? 'List pagination',
    'perPageOptions' => $perPageOptions,
    'defaultPerPage' => $defaultPerPage,
])
