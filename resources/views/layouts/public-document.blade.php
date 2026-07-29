<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Offer Letter')</title>
    @include('layouts.partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <header class="border-bottom bg-white">
        <div class="container py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <div class="small text-muted">@yield('company_name', config('app.name'))</div>
                <h1 class="h5 mb-0">@yield('page_heading', 'Offer Letter')</h1>
            </div>
            @yield('header_actions')
        </div>
    </header>

    <main class="container py-4 py-lg-5">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
