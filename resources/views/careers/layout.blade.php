<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('careers-title', ($settings->meta_title ?: ('Careers at ' . $company->name)))</title>
    @if ($settings->meta_description)
        <meta name="description" content="{{ $settings->meta_description }}">
    @endif
    @include('layouts.partials.fonts')
    @vite(['resources/css/careers-public.css', 'resources/js/careers-public.js'])
    @php
        $sections = $sections ?? $settings->resolvedSections();
        $logoUrl = $settings->logo_path ? asset($settings->logo_path) : $company->logo_url;
        $primary = $settings->theme_primary ?: '#0f172a';
        $accent = $settings->theme_accent ?: '#2563eb';
    @endphp
    <style>
        :root {
            --careers-primary: {{ $primary }};
            --careers-accent: {{ $accent }};
        }
    </style>
</head>
<body class="careers-public">
    @if (! empty($isPreview))
        <div class="careers-preview-banner">
            <div class="container">
                Draft preview — only visible to you. Enable <strong>Published (live on public URL)</strong> in Hiring → Careers and save to make this page public.
            </div>
        </div>
    @endif
    <header class="careers-nav">
        <div class="container careers-nav__inner">
            <a href="{{ route('careers.show', $company->slug) }}" class="careers-nav__brand">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $company->name }}" class="careers-nav__logo">
                @else
                    <span class="careers-nav__name">{{ $company->name }}</span>
                @endif
            </a>
            <nav class="careers-nav__links">
                <a href="#open-roles">Open Roles</a>
                <a href="#culture">Culture</a>
                <a href="#apply">Apply</a>
            </nav>
            <a href="#apply" class="careers-btn careers-btn--primary careers-nav__cta">Let's Connect</a>
        </div>
    </header>

    @if (session('success'))
        <div class="container mt-3">
            <div class="alert alert-success careers-alert">{{ session('success') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="container mt-3">
            <div class="alert alert-danger careers-alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @yield('careers-content')

    <footer class="careers-footer">
        <div class="container">
            <div class="careers-footer__grid">
                <div>
                    <div class="careers-footer__brand">{{ $company->name }}</div>
                    @if ($sections['footer_note'] ?? null)
                        <p class="careers-footer__note">{{ $sections['footer_note'] }}</p>
                    @endif
                </div>
                @if (! empty($sections['social_links']))
                    <div class="careers-footer__social">
                        @foreach ($sections['social_links'] as $link)
                            @if (! empty($link['url']))
                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener">{{ $link['platform'] ?? 'Link' }}</a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
            @if ($settings->footer_html)
                <div class="careers-footer__custom mt-4">{!! $settings->footer_html !!}</div>
            @endif
            <div class="careers-footer__copy">&copy; {{ date('Y') }} {{ $company->name }}. All rights reserved.</div>
        </div>
    </footer>
</body>
</html>
