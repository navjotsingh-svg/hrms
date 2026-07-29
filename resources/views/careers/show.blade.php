@extends('careers.layout')

@section('careers-content')
    @php
        $culture = $sections['section_titles']['culture'] ?? [];
        $statsTitle = $sections['section_titles']['stats'] ?? [];
        $jobsTitle = $sections['section_titles']['jobs'] ?? [];
        $testimonialsTitle = $sections['section_titles']['testimonials'] ?? [];
        $contactTitle = $sections['section_titles']['contact'] ?? [];
        $ctaText = $settings->hero_cta_text ?: 'View Open Roles';
        $ctaUrl = $settings->hero_cta_url ?: '#open-roles';
    @endphp

    {{-- Hero --}}
    <section class="careers-hero {{ $settings->banner_path ? 'careers-hero--banner' : '' }}"
        @if ($settings->banner_path) style="--careers-hero-image: url('{{ asset($settings->banner_path) }}');" @endif>
        <div class="careers-hero__overlay"></div>
        <div class="container careers-hero__content">
            <p class="careers-eyebrow">Careers at {{ $company->name }}</p>
            <h1 class="careers-hero__title">{{ $settings->hero_title ?: ('Join ' . $company->name) }}</h1>
            @if ($settings->hero_subtitle)
                <p class="careers-hero__subtitle">{{ $settings->hero_subtitle }}</p>
            @endif
            <div class="careers-hero__actions">
                <a href="{{ $ctaUrl }}" class="careers-btn careers-btn--primary careers-btn--lg">{{ $ctaText }}</a>
                <a href="#apply" class="careers-btn careers-btn--ghost careers-btn--lg">Submit Application</a>
            </div>
        </div>
    </section>

    {{-- Marquee tags --}}
    @if (($sections['show_marquee'] ?? true) && ! empty($sections['marquee_tags']))
        <section class="careers-marquee" aria-hidden="true">
            <div class="careers-marquee__track">
                @foreach (array_merge($sections['marquee_tags'], $sections['marquee_tags']) as $tag)
                    <span class="careers-marquee__item">{{ $tag }}</span>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Culture / Why join --}}
    @if (($sections['show_why_join'] ?? true) && ! empty($sections['why_join']))
        <section class="careers-section" id="culture">
            <div class="container">
                <div class="careers-section-head">
                    <span class="careers-eyebrow">{{ $culture['eyebrow'] ?? 'Life at our company' }}</span>
                    <h2 class="careers-section-head__title">{{ $culture['title'] ?? 'Where passion aligns with progress' }}</h2>
                    <p class="careers-section-head__subtitle">{{ $culture['subtitle'] ?? '' }}</p>
                </div>
                <div class="careers-culture-grid">
                    @foreach ($sections['why_join'] as $item)
                        <article class="careers-culture-card">
                            <div class="careers-culture-card__icon" data-icon="{{ $item['icon'] ?? 'star' }}"></div>
                            <h3 class="careers-culture-card__title">{{ $item['title'] ?? '' }}</h3>
                            <p class="careers-culture-card__text">{{ $item['description'] ?? '' }}</p>
                        </article>
                    @endforeach
                </div>
                @if ($settings->about_html)
                    <div class="careers-about-rich mt-5">{!! $settings->about_html !!}</div>
                @endif
            </div>
        </section>
    @endif

    {{-- Stats --}}
    @if (($sections['show_stats'] ?? true) && ! empty($sections['stats']))
        <section class="careers-stats">
            <div class="container">
                <div class="careers-section-head careers-section-head--light">
                    <span class="careers-eyebrow">{{ $statsTitle['eyebrow'] ?? 'Our numbers' }}</span>
                    <h2 class="careers-section-head__title">{{ $statsTitle['title'] ?? 'Growth you can measure' }}</h2>
                    <p class="careers-section-head__subtitle">{{ $statsTitle['subtitle'] ?? '' }}</p>
                </div>
                <div class="careers-stats-grid">
                    @foreach ($sections['stats'] as $index => $stat)
                        <div class="careers-stat" data-careers-stat>
                            <div class="careers-stat__value">
                                <span data-count="{{ preg_replace('/[^0-9.]/', '', $stat['value'] ?? '0') }}">{{ $stat['value'] ?? '0' }}</span><span>{{ $stat['suffix'] ?? '' }}</span>
                            </div>
                            <div class="careers-stat__label">{{ $stat['label'] ?? '' }}</div>
                        </div>
                    @endforeach
                    <div class="careers-stat careers-stat--live" data-careers-stat>
                        <div class="careers-stat__value"><span data-count="{{ $jobs->count() }}">{{ $jobs->count() }}</span><span>+</span></div>
                        <div class="careers-stat__label">Open Roles (Live)</div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Open jobs --}}
    <section class="careers-section careers-section--jobs" id="open-roles">
        <div class="container">
            <div class="careers-section-head">
                <span class="careers-eyebrow">{{ $jobsTitle['eyebrow'] ?? 'Open positions' }}</span>
                <h2 class="careers-section-head__title">{{ $jobsTitle['title'] ?? 'The future looks promising' }}</h2>
                <p class="careers-section-head__subtitle">{{ $jobsTitle['subtitle'] ?? '' }}</p>
            </div>

            @if ($jobs->isEmpty())
                <div class="careers-empty">
                    <p>No open positions right now. Send us your resume — we're always looking for great talent.</p>
                    <a href="#apply" class="careers-btn careers-btn--primary">General Application</a>
                </div>
            @else
                <div class="careers-jobs-grid">
                    @foreach ($jobs as $job)
                        <article class="careers-job-card">
                            <div class="careers-job-card__dept">{{ $job->department?->name ?? 'General' }}</div>
                            <h3 class="careers-job-card__title">{{ $job->title }}</h3>
                            <div class="careers-job-card__meta">
                                @if ($job->location)<span>{{ $job->location }}</span>@endif
                                @if ($job->employment_type)<span>{{ str_replace('_', ' ', ucfirst($job->employment_type)) }}</span>@endif
                            </div>
                            <div class="careers-job-card__actions">
                                <a href="{{ route('careers.job', [$company->slug, $job]) }}" class="careers-btn careers-btn--outline">View Role</a>
                                <a href="{{ route('careers.job', [$company->slug, $job]) }}#apply" class="careers-btn careers-btn--primary">Apply</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- Testimonials --}}
    @if (($sections['show_testimonials'] ?? true) && ! empty($sections['testimonials']))
        <section class="careers-section careers-section--muted">
            <div class="container">
                <div class="careers-section-head">
                    <span class="careers-eyebrow">{{ $testimonialsTitle['eyebrow'] ?? 'Testimonials' }}</span>
                    <h2 class="careers-section-head__title">{{ $testimonialsTitle['title'] ?? 'Hear from our team' }}</h2>
                    <p class="careers-section-head__subtitle">{{ $testimonialsTitle['subtitle'] ?? '' }}</p>
                </div>
                <div class="careers-testimonials" data-careers-testimonials>
                    <div class="careers-testimonials__track">
                        @foreach ($sections['testimonials'] as $testimonial)
                            <blockquote class="careers-testimonial">
                                @if (! empty($testimonial['photo_url']))
                                    <img src="{{ $testimonial['photo_url'] }}" alt="" class="careers-testimonial__photo">
                                @endif
                                <p class="careers-testimonial__quote">"{{ $testimonial['quote'] ?? '' }}"</p>
                                <footer>
                                    <strong>{{ $testimonial['name'] ?? '' }}</strong>
                                    <span>{{ $testimonial['role'] ?? '' }}</span>
                                </footer>
                            </blockquote>
                        @endforeach
                    </div>
                    @if (count($sections['testimonials']) > 1)
                        <div class="careers-testimonials__nav">
                            <button type="button" class="careers-testimonials__btn" data-testimonial-prev aria-label="Previous">‹</button>
                            <button type="button" class="careers-testimonials__btn" data-testimonial-next aria-label="Next">›</button>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    {{-- Badges --}}
    @if (($sections['show_badges'] ?? true) && ! empty($sections['badges']))
        <section class="careers-badges">
            <div class="container">
                <div class="careers-badges__grid">
                    @foreach ($sections['badges'] as $badge)
                        <div class="careers-badge">
                            @if (! empty($badge['image_url']))
                                <img src="{{ $badge['image_url'] }}" alt="{{ $badge['title'] ?? '' }}">
                            @else
                                <span>{{ $badge['title'] ?? '' }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Apply --}}
    <section class="careers-section careers-apply-section" id="apply">
        <div class="container">
            <div class="careers-apply-wrap">
                <div class="careers-apply-copy">
                    <span class="careers-eyebrow">{{ $contactTitle['eyebrow'] ?? 'Join us' }}</span>
                    <h2 class="careers-section-head__title">{{ $contactTitle['title'] ?? 'Ready to make an impact?' }}</h2>
                    <p class="careers-section-head__subtitle mb-0">{{ $contactTitle['subtitle'] ?? '' }}</p>
                </div>
                <div class="careers-apply-form-wrap">
                    @include('careers.partials.apply-form', [
                        'showJobSelect' => $jobs->isNotEmpty(),
                    ])
                </div>
            </div>
        </div>
    </section>
@endsection
