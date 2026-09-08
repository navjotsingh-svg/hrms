<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Careers — {{ $company->name }}</title>
    @include('layouts.partials.fonts')
    @vite(['resources/css/careers-public.css'])
</head>
<body class="careers-public">
    <main class="careers-unavailable">
        <div class="container text-center py-5">
            <p class="careers-eyebrow mb-2">{{ $company->name }}</p>
            <h1 class="h2 mb-3">Careers page coming soon</h1>
            <p class="text-muted mb-0">We are preparing our careers page. Please check back later.</p>
        </div>
    </main>
</body>
</html>
