@props([
    'id',
    'label',
    'icon',
    'open' => false,
])

<li class="nav-item sidebar-group">
    <button
        class="nav-link sidebar-submenu-toggle {{ $open ? 'active' : '' }}"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#{{ $id }}"
        aria-expanded="{{ $open ? 'true' : 'false' }}"
        aria-controls="{{ $id }}"
    >
        <span class="sidebar-icon">@include('layouts.partials.sidebar-icon', ['name' => $icon])</span>
        <span class="sidebar-link-label">{{ $label }}</span>
        <span class="sidebar-chevron" aria-hidden="true">&#8963;</span>
    </button>
    <div class="collapse {{ $open ? 'show' : '' }}" id="{{ $id }}">
        <ul class="nav flex-column sidebar-submenu">
            {{ $slot }}
        </ul>
    </div>
</li>
