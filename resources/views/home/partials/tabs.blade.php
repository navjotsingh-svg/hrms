@php
    $user = Auth::user();
    $active = $active ?? 'home';
    $tabs = [];

    if ($user->canSeeMenu('home')) {
        $tabs[] = ['key' => 'home', 'label' => 'Home', 'href' => route('web.home.index')];
    }
    if ($user->canSeeMenu('home.dashboard')) {
        $tabs[] = ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('web.home.dashboard')];
    }
@endphp

@if (count($tabs) > 1)
    <div class="home-section-tabs-wrap">
        <ul class="nav nav-tabs home-section-tabs" role="tablist">
            @foreach ($tabs as $tab)
                <li class="nav-item" role="presentation">
                    <a
                        class="nav-link {{ $active === $tab['key'] ? 'active' : '' }}"
                        href="{{ $tab['href'] }}"
                        role="tab"
                        @if ($active === $tab['key']) aria-current="page" @endif
                    >
                        {{ $tab['label'] }}
                        @if ($tab['key'] === 'moments')
                            <span class="moments-tab-badge d-none" id="homeTabMomentsBadge" aria-hidden="true"></span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
