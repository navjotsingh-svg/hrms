@php
    $suffix = 'Top';
    $resolvedInfoId = ($infoId ?? 'listPaginationInfo') . $suffix;
    $resolvedListId = ($listId ?? 'listPaginationList') . $suffix;
    $resolvedWrapId = isset($wrapId) ? $wrapId . $suffix : null;
    $resolvedWrapClass = $wrapClassTop ?? 'content-card-body border-bottom companies-pagination-footer';
@endphp

@include('partials.list-pagination-footer', [
    'infoId' => $resolvedInfoId,
    'listId' => $resolvedListId,
    'wrapId' => $resolvedWrapId,
    'wrapClass' => $resolvedWrapClass,
    'ariaLabel' => $ariaLabel ?? 'List pagination',
    'infoText' => $infoText ?? '',
    'showPerPage' => true,
    'perPageId' => $perPageId ?? 'listPerPage',
    'perPageOptions' => $perPageOptions ?? [10, 25, 50],
    'defaultPerPage' => $defaultPerPage ?? 10,
])
