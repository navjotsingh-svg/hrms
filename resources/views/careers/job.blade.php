<!DOCTYPE html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $selectedJob->title }} - Careers at {{ $company->name }}</title>

    @include('layouts.partials.fonts')

    @vite(['resources/css/app.css'])

    <style>

        .careers-page { min-height: 100vh; display: flex; flex-direction: column; background: #f8fafc; color: #0f172a; }

        .careers-header { background: #fff; border-bottom: 1px solid #e2e8f0; }

        .careers-footer { background: #fff; border-top: 1px solid #e2e8f0; margin-top: auto; }

        .careers-job-detail { background: #fff; border: 1px solid #e2e8f0; border-radius: 0.75rem; }

        .careers-apply-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 2rem; }

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



    <main class="container py-4 py-lg-5 flex-grow-1">

        <nav aria-label="breadcrumb" class="mb-4">

            <ol class="breadcrumb mb-0">

                <li class="breadcrumb-item"><a href="{{ route('careers.show', $company->slug) }}">Careers</a></li>

                <li class="breadcrumb-item active" aria-current="page">{{ $selectedJob->title }}</li>

            </ol>

        </nav>



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



        <div class="row g-4">

            <div class="col-lg-7">

                <article class="careers-job-detail p-4 p-lg-5">

                    <h1 class="h2 mb-3">{{ $selectedJob->title }}</h1>

                    <div class="text-muted mb-4">

                        @if ($selectedJob->location)

                            <span>{{ $selectedJob->location }}</span>

                        @endif

                        @if ($selectedJob->location && $selectedJob->employment_type)

                            <span> · </span>

                        @endif

                        @if ($selectedJob->employment_type)

                            <span>{{ str_replace('_', ' ', ucfirst($selectedJob->employment_type)) }}</span>

                        @endif

                        @if ($selectedJob->department)

                            <div class="mt-1">{{ $selectedJob->department->name }}</div>

                        @endif

                    </div>

                    @if ($selectedJob->description_html)

                        <div class="careers-job-description">

                            {!! $selectedJob->description_html !!}

                        </div>

                    @else

                        <p class="text-muted mb-0">No job description provided.</p>

                    @endif

                </article>

            </div>

            <div class="col-lg-5" id="apply">

                <div class="careers-apply-card">

                    <h2 class="h4 mb-2">Apply for this role</h2>

                    <p class="text-muted mb-4">Upload your resume to apply for <strong>{{ $selectedJob->title }}</strong>.</p>

                    @include('careers.partials.apply-form', [

                        'formAction' => route('careers.apply', [$company->slug, $selectedJob]),

                        'showJobSelect' => false,

                    ])

                </div>

            </div>

        </div>

    </main>



    @if ($settings->footer_html)

        <footer class="careers-footer">

            <div class="container py-4">

                {!! $settings->footer_html !!}

            </div>

        </footer>

    @endif

</body>

</html>

