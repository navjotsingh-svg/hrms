<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'HRMS'))</title>
    @include('layouts.partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('layouts.partials.web-routes')
    <script>
        (function () {
            try {
                if (window.localStorage.getItem('hrms_sidebar_collapsed') === '1') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (e) {}
        })();
    </script>
</head>
@php
    $pageTheme = $hrmsPageTheme ?? ['module' => 'default', 'theme' => 'brand', 'icon' => 'grid', 'label' => 'HRMS'];
@endphp
<body class="dashboard-body hrms-theme-{{ $pageTheme['theme'] }} hrms-module-{{ $pageTheme['module'] }}">
    <div class="dashboard-wrapper">
        <div class="sidebar-desktop d-none d-lg-flex">
            @include('layouts.sidebar')
        </div>

        <div class="main-panel">
            @include('layouts.topbar')

            <main class="content-area">
                @hasSection('header')
                    <div class="page-header hrms-page-banner hrms-page-banner--{{ $pageTheme['theme'] }}">
                        <div class="hrms-page-banner__pattern" aria-hidden="true"></div>
                        <div class="hrms-page-banner__row">
                            <div class="hrms-page-banner__icon-wrap">
                                @include('layouts.partials.page-icon', ['icon' => $pageTheme['icon']])
                            </div>
                            <div class="hrms-page-banner__main">
                                <div class="hrms-page-banner__eyebrow">{{ $pageTheme['label'] }}</div>
                                @yield('header')
                            </div>
                        </div>
                    </div>
                @endif

                <div class="hrms-page-body">
                    @yield('content')
                </div>
            </main>

            <footer class="dashboard-footer">
                <span>&copy; {{ date('Y') }} {{ config('hrms.company_name', config('app.name', 'HRMS')) }}. All rights reserved.</span>
            </footer>
        </div>
    </div>

    @auth
        @if (Auth::user()->company_id && ! Auth::user()->isSuperAdmin() && config('hrms.assistant.enabled', true))
            @include('layouts.partials.assistant-chat-widget')
        @endif
        @if (Auth::user()->canViewAllLeaveRequests())
            @include('layouts.partials.leave-calendar-modal')
        @endif
    @endauth

    @yield('scripts')
    @stack('scripts')
</body>
</html>
