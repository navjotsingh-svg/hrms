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
<body class="dashboard-body">
    <div class="dashboard-wrapper">
        <div class="sidebar-desktop d-none d-lg-flex">
            @include('layouts.sidebar')
        </div>

        <div class="main-panel">
            @include('layouts.topbar')

            <main class="content-area">
                @hasSection('header')
                    <div class="page-header">
                        @yield('header')
                    </div>
                @endif

                @yield('content')
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
