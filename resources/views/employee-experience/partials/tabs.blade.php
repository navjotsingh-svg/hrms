@php
    $user = Auth::user();
    $active = $active ?? 'social-wall';
    $tabs = [];

    if ($user->canSeeMenu('experience.social_wall')) {
        $tabs[] = [
            'key' => 'social-wall',
            'label' => 'Social Wall',
            'href' => route('web.employee-experience.social-wall'),
            'badgeId' => 'experienceTabSocialWallBadge',
        ];
    }
    if ($user->canSeeMenu('experience.polls')) {
        $tabs[] = [
            'key' => 'polls-announcements',
            'label' => 'Polls & Announcements',
            'href' => route('web.employee-experience.polls-announcements'),
        ];
    }
    if ($user->canSeeMenu('experience.public_praise')) {
        $tabs[] = [
            'key' => 'public-praise',
            'label' => 'Public Praise',
            'href' => route('web.employee-experience.public-praise'),
        ];
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
                        @if (! empty($tab['badgeId']))
                            <span class="moments-tab-badge d-none" id="{{ $tab['badgeId'] }}" aria-hidden="true"></span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
