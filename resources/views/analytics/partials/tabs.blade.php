@php
    $sections = $sections ?? [];
    $activeSection = $activeSection ?? null;
@endphp

@if (count($sections) > 1)
    <div class="analytics-section-tabs-wrap">
        <ul class="nav nav-tabs analytics-section-tabs" role="tablist">
            @foreach ($sections as $section)
                <li class="nav-item" role="presentation">
                    <a
                        class="nav-link {{ $activeSection === $section['key'] ? 'active' : '' }}"
                        href="{{ route('web.analytics.section', ['section' => $section['key']]) }}"
                        role="tab"
                        @if ($activeSection === $section['key']) aria-current="page" @endif
                    >{{ $section['label'] }}</a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
