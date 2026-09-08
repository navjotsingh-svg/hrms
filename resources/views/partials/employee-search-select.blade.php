@props([
    'inputId',
    'hiddenId',
    'label' => 'Search Employee',
    'placeholder' => 'Search by name or employee code...',
    'required' => false,
    'hiddenName' => null,
    'inputClass' => '',
    'wrapClass' => '',
])

@php
    $wrapId = $inputId . 'Wrap';
    $toggleId = $inputId . 'Toggle';
    $menuId = $inputId . 'Menu';
@endphp

<label for="{{ $inputId }}" class="form-label">{{ $label }}</label>
<div class="employee-search-select filter-autocomplete {{ $wrapClass }}" id="{{ $wrapId }}">
    <input
        type="text"
        class="form-control employee-search-select-input {{ $inputClass }}"
        id="{{ $inputId }}"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        role="combobox"
        aria-expanded="false"
        aria-controls="{{ $menuId }}"
        aria-autocomplete="list"
        @if($required) required @endif
    >
    <button
        type="button"
        class="employee-search-select-toggle"
        id="{{ $toggleId }}"
        aria-label="Show employee list"
        aria-expanded="false"
    >
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
        </svg>
    </button>
    <input type="hidden" id="{{ $hiddenId }}" @if($hiddenName) name="{{ $hiddenName }}" @endif>
    <div id="{{ $menuId }}" class="filter-autocomplete-menu employee-search-select-menu d-none" role="listbox"></div>
</div>
