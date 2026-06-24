<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-link topbar-toggle d-lg-none p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
            <span class="topbar-toggle-icon">&#9776;</span>
        </button>

        <button class="btn btn-link topbar-toggle topbar-sidebar-toggle d-none d-lg-inline-flex p-0" type="button" id="sidebarDesktopToggle" aria-label="Toggle sidebar" aria-expanded="true" title="Hide sidebar">
            <span class="topbar-toggle-icon" id="sidebarDesktopToggleIcon" aria-hidden="true">&#10094;</span>
        </button>

        @include('layouts.partials.logo')
    </div>

    <div class="topbar-actions d-flex align-items-center gap-3">
        @if (Auth::user()->canViewAllLeaveRequests())
            <button
                type="button"
                class="btn btn-link topbar-calendar-btn p-0"
                data-bs-toggle="modal"
                data-bs-target="#leaveCalendarModal"
                aria-label="Open leave calendar"
                title="Leave calendar"
            >
                <svg class="topbar-calendar-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M7 2a1 1 0 0 1 1 1v1h8V3a1 1 0 1 1 2 0v1h1.5A2.5 2.5 0 0 1 22 6.5v13A2.5 2.5 0 0 1 19.5 22h-15A2.5 2.5 0 0 1 2 19.5v-13A2.5 2.5 0 0 1 4.5 4H6V3a1 1 0 0 1 1-1Zm12.5 7H4.5v10.5c0 .276.224.5.5.5h15a.5.5 0 0 0 .5-.5V9ZM6 6h-.5a.5.5 0 0 0-.5.5V7h14v-.5a.5.5 0 0 0-.5-.5H18v1a1 1 0 1 1-2 0V6H8v1a1 1 0 0 1-2 0V6Z"/>
                    <path d="M15 14a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/>
                </svg>
            </button>
        @endif

        <div class="dropdown">
            <a class="topbar-user dropdown-toggle text-decoration-none" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="topbar-avatar">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
                <div class="topbar-user-info d-none d-md-block">
                    <span class="topbar-user-name">{{ Auth::user()->name }}</span>
                    <span class="topbar-user-role">{{ Auth::user()->role?->name ?? 'User' }}</span>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                <li>
                    <a class="dropdown-item" href="{{ route('web.profile') }}">
                        <span class="me-2">&#128100;</span> My Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('web.profile.change-password') }}">
                        <span class="me-2">&#128274;</span> Change Password
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button type="button" class="dropdown-item text-danger" id="logoutButton">
                        <span class="me-2">&#10140;</span> Log Out
                    </button>
                </li>
            </ul>
        </div>
    </div>
</header>

<div class="offcanvas offcanvas-start sidebar-offcanvas d-lg-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-body p-0">
        @include('layouts.sidebar')
    </div>
</div>
