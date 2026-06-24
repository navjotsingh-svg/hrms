<!DOCTYPE html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $settings->meta_title ?: ('Careers at ' . $company->name) }}</title>

    @if ($settings->meta_description)

        <meta name="description" content="{{ $settings->meta_description }}">

    @endif

    @include('layouts.partials.fonts')

    @vite(['resources/css/app.css'])

    <style>

        .careers-page { min-height: 100vh; display: flex; flex-direction: column; background: #f8fafc; color: #0f172a; }

        .careers-header { background: #fff; border-bottom: 1px solid #e2e8f0; }

        .careers-hero {

            position: relative;

            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);

            color: #fff;

            padding: 4rem 0;

            overflow: hidden;

        }

        .careers-hero.has-banner { background: #0f172a center/cover no-repeat; min-height: 320px; display: flex; align-items: center; }

        .careers-hero-overlay { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.65); }

        .careers-hero-content { position: relative; z-index: 1; }

        .careers-section { padding: 3rem 0; }

        .careers-job-card {

            background: #fff;

            border: 1px solid #e2e8f0;

            border-radius: 0.75rem;

            padding: 1.5rem;

            height: 100%;

            transition: box-shadow 0.2s ease, transform 0.2s ease;

        }

        .careers-job-card:hover { box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08); transform: translateY(-2px); }

        .careers-apply-card {

            background: #fff;

            border: 1px solid #e2e8f0;

            border-radius: 0.75rem;

            padding: 2rem;

        }

        .careers-footer { background: #fff; border-top: 1px solid #e2e8f0; margin-top: auto; }

        .careers-about { background: #fff; }

    </style>

</head>

<body class="careers-page">

    @if ($settings->header_html)

        <div class="careers-header">

            <div class="container py-3">

                {!! $settings->header_html !!}

            </div>

        </div>

    @endif



    <section

        class="careers-hero {{ $settings->banner_path ? 'has-banner' : '' }}"

        @if ($settings->banner_path) style="background-image: url('{{ asset($settings->banner_path) }}');" @endif

    >

        @if ($settings->banner_path)

            <div class="careers-hero-overlay"></div>

        @endif

        <div class="container careers-hero-content">

            <h1 class="display-5 fw-bold mb-3">{{ $settings->hero_title ?: ('Join ' . $company->name) }}</h1>

            @if ($settings->hero_subtitle)

                <p class="lead mb-0 opacity-90">{{ $settings->hero_subtitle }}</p>

            @endif

        </div>

    </section>



    <section class="careers-section">

        <div class="container">

            @if (session('success'))

                <div class="alert alert-success">{{ session('success') }}</div>

            @endif



            @if ($errors->any())

                <div class="alert alert-danger">

                    <ul class="mb-0">

                        @foreach ($errors->all() as $error)

                            <li>{{ $error }}</li>

                        @endforeach

                    </ul>

                </div>

            @endif



            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">

                <h2 class="h3 mb-0">Open Positions</h2>

                <span class="text-muted">{{ $jobs->count() }} {{ Str::plural('role', $jobs->count()) }}</span>

            </div>



            @if ($jobs->isEmpty())

                <div class="text-center text-muted py-4 mb-4">

                    <p class="mb-0">No open positions at the moment. You can still send us your resume below.</p>

                </div>

            @else

                <div class="row g-4 mb-5">

                    @foreach ($jobs as $job)

                        <div class="col-md-6 col-lg-4">

                            <article class="careers-job-card d-flex flex-column">

                                <h3 class="h5 mb-2">{{ $job->title }}</h3>

                                <div class="text-muted small mb-3">

                                    @if ($job->location)

                                        <span>{{ $job->location }}</span>

                                    @endif

                                    @if ($job->location && $job->employment_type)

                                        <span> · </span>

                                    @endif

                                    @if ($job->employment_type)

                                        <span>{{ str_replace('_', ' ', ucfirst($job->employment_type)) }}</span>

                                    @endif

                                    @if ($job->department)

                                        <div class="mt-1">{{ $job->department->name }}</div>

                                    @endif

                                </div>

                                <div class="mt-auto d-flex gap-2 flex-wrap">

                                    <a href="{{ route('careers.job', [$company->slug, $job]) }}" class="btn btn-outline-primary btn-sm">View Details</a>

                                    <a href="{{ route('careers.job', [$company->slug, $job]) }}#apply" class="btn btn-primary btn-sm">Apply Now</a>

                                </div>

                            </article>

                        </div>

                    @endforeach

                </div>

            @endif



            <div class="row justify-content-center" id="apply">

                <div class="col-lg-8">

                    <div class="careers-apply-card">

                        <h2 class="h4 mb-2">Submit Your Application</h2>

                        <p class="text-muted mb-4">Fill in your details and upload your resume. We'll get back to you soon.</p>

                        @include('careers.partials.apply-form', [

                            'showJobSelect' => $jobs->isNotEmpty(),

                        ])

                    </div>

                </div>

            </div>

        </div>

    </section>



    @if ($settings->about_html)

        <section class="careers-section careers-about">

            <div class="container">

                <div class="mx-auto" style="max-width: 800px;">

                    {!! $settings->about_html !!}

                </div>

            </div>

        </section>

    @endif



    @if ($settings->footer_html)

        <footer class="careers-footer">

            <div class="container py-4">

                {!! $settings->footer_html !!}

            </div>

        </footer>

    @endif

</body>

</html>

