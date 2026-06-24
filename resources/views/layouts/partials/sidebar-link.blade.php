@props([
    'href',
    'label',
    'icon',
    'active' => false,
    'badge' => null,
])

<li class="nav-item">
    <a class="nav-link {{ $active ? 'active' : '' }}" href="{{ $href }}">
        <span class="sidebar-icon">@include('layouts.partials.sidebar-icon', ['name' => $icon])</span>
        <span class="sidebar-link-label">{{ $label }}</span>
        @if ($badge)
            <span class="sidebar-link-badge">{{ $badge }}</span>
        @endif
    </a>
</li>
