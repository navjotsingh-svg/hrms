@extends('careers.layout')

@section('careers-title', $selectedJob->title . ' - Careers at ' . $company->name)

@section('careers-content')
    <section class="careers-hero careers-hero--compact">
        <div class="careers-hero__overlay"></div>
        <div class="container careers-hero__content">
            <nav aria-label="breadcrumb" class="careers-breadcrumb mb-3">
                <a href="{{ route('careers.show', $company->slug) }}">Careers</a>
                <span>/</span>
                <span>{{ $selectedJob->title }}</span>
            </nav>
            <h1 class="careers-hero__title careers-hero__title--sm">{{ $selectedJob->title }}</h1>
            <div class="careers-hero__meta">
                @if ($selectedJob->location)<span>{{ $selectedJob->location }}</span>@endif
                @if ($selectedJob->employment_type)<span>{{ str_replace('_', ' ', ucfirst($selectedJob->employment_type)) }}</span>@endif
                @if ($selectedJob->department)<span>{{ $selectedJob->department->name }}</span>@endif
            </div>
        </div>
    </section>

    <section class="careers-section">
        <div class="container">
            <div class="careers-job-layout">
                <article class="careers-job-detail">
                    @if ($selectedJob->description_html)
                        <div class="careers-job-description">{!! $selectedJob->description_html !!}</div>
                    @else
                        <p class="text-muted">No job description provided.</p>
                    @endif
                </article>
                <aside class="careers-apply-form-wrap careers-apply-form-wrap--sticky" id="apply">
                    <h2 class="h4 mb-2">Apply for this role</h2>
                    <p class="careers-muted mb-4">Upload your resume to apply for <strong>{{ $selectedJob->title }}</strong>.</p>
                    @include('careers.partials.apply-form', [
                        'formAction' => route('careers.apply', [$company->slug, $selectedJob]),
                        'showJobSelect' => false,
                    ])
                </aside>
            </div>
        </div>
    </section>
@endsection
