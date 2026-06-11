<aside class="sidebar" id="sidebar">
    <div class="sidebar-header d-lg-none">
        @include('layouts.partials.logo')
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close" id="sidebarClose"></button>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section">
            <span class="sidebar-label">Main</span>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('web.dashboard') ? 'active' : '' }}" href="{{ route('web.dashboard') }}">
                        <span class="sidebar-icon">&#9632;</span>
                        <span>Dashboard</span>
                    </a>
                </li>
            </ul>
        </div>

        @if (Auth::user()->isSuperAdmin())
            <div class="sidebar-section">
                <span class="sidebar-label">SaaS Management</span>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('web.companies.*') ? 'active' : '' }}" href="{{ route('web.companies.index') }}">
                            <span class="sidebar-icon">&#127970;</span>
                            <span>Companies</span>
                        </a>
                    </li>
                </ul>
            </div>
        @endif

        @if (Auth::user()->company_id && ! Auth::user()->isSuperAdmin())
            @if (Auth::user()->canManageCompanyMasters())
                <div class="sidebar-section">
                    <span class="sidebar-label">Masters</span>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('web.masters.departments.*') ? 'active' : '' }}" href="{{ route('web.masters.departments.index') }}">
                                <span class="sidebar-icon">&#127970;</span>
                                <span>Departments</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('web.masters.documents.*') ? 'active' : '' }}" href="{{ route('web.masters.documents.index') }}">
                                <span class="sidebar-icon">&#128196;</span>
                                <span>Documents</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('web.masters.assets.*') ? 'active' : '' }}" href="{{ route('web.masters.assets.index') }}">
                                <span class="sidebar-icon">&#128187;</span>
                                <span>Assets</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('web.masters.shifts.*') ? 'active' : '' }}" href="{{ route('web.masters.shifts.index') }}">
                                <span class="sidebar-icon">&#128337;</span>
                                <span>Shifts</span>
                            </a>
                        </li>
                        @if (Auth::user()->canManageAttendanceMasters())
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('web.masters.attendance.holidays.*') ? 'active' : '' }}" href="{{ route('web.masters.attendance.holidays.index') }}">
                                <span class="sidebar-icon">&#127881;</span>
                                <span>Holidays</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('web.masters.attendance.weekly-off.*') ? 'active' : '' }}" href="{{ route('web.masters.attendance.weekly-off.index') }}">
                                <span class="sidebar-icon">&#128197;</span>
                                <span>Weekly Off</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('web.masters.attendance.portal-start.*') ? 'active' : '' }}" href="{{ route('web.masters.attendance.portal-start.index') }}">
                                <span class="sidebar-icon">&#128640;</span>
                                <span>Start Portal Day</span>
                            </a>
                        </li>
                        @endif
                        @if (Auth::user()->canManageLeaveTypes())
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('web.masters.leave-types.*') ? 'active' : '' }}" href="{{ route('web.masters.leave-types.index') }}">
                                <span class="sidebar-icon">&#128221;</span>
                                <span>Leave Types</span>
                            </a>
                        </li>
                        @endif
                        @if (Auth::user()->canManageLeaveBalances())
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('web.leave.manage-balances') ? 'active' : '' }}" href="{{ route('web.leave.manage-balances') }}">
                                <span class="sidebar-icon">&#128202;</span>
                                <span>Leave Balances</span>
                            </a>
                        </li>
                        @endif
                        @if (Auth::user()->isCompanyAdmin())
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('web.masters.roles.*') ? 'active' : '' }}" href="{{ route('web.masters.roles.index') }}">
                                    <span class="sidebar-icon">&#128274;</span>
                                    <span>Manage Roles</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif

            @if (config('hrms.people_menu_enabled'))
            <div class="sidebar-section">
                <span class="sidebar-label">People</span>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <button
                            class="nav-link sidebar-submenu-toggle {{ request()->routeIs('web.people.*') ? 'active' : '' }}"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#sidebarPeopleMenu"
                            aria-expanded="{{ request()->routeIs('web.people.*') ? 'true' : 'false' }}"
                        >
                            <span class="sidebar-icon">&#128100;</span>
                            <span>People</span>
                            <span class="sidebar-chevron" aria-hidden="true">&#8963;</span>
                        </button>
                        <div class="collapse {{ request()->routeIs('web.people.*') ? 'show' : '' }}" id="sidebarPeopleMenu">
                            <ul class="nav flex-column sidebar-submenu">
                                <li class="nav-item">
                                    <a class="nav-link sidebar-people-link {{ request()->routeIs('web.people.*') ? 'active' : '' }}" href="{{ route('web.people.index') }}" id="sidebarPeopleSummaryLink">
                                        <span class="sidebar-icon">&#128101;</span>
                                        <span>Summary</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link sidebar-people-link" href="{{ route('web.people.index') }}#org-chart" id="sidebarPeopleOrgChartLink">
                                        <span class="sidebar-icon">&#128202;</span>
                                        <span>Org Chart</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                </ul>
            </div>
            @endif

            <div class="sidebar-section">
                <span class="sidebar-label">HR Management</span>
                <ul class="nav flex-column">
                    @if (Auth::user()->canViewEmployees())
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('web.employees.*') ? 'active' : '' }}" href="{{ route('web.employees.index') }}">
                            <span class="sidebar-icon">&#128101;</span>
                            <span>Employees</span>
                        </a>
                    </li>
                    @endif
                    @if (Auth::user()->canViewAttendance())
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('web.attendance.index') ? 'active' : '' }}" href="{{ route('web.attendance.index') }}">
                            <span class="sidebar-icon">&#128337;</span>
                            <span>Attendance</span>
                        </a>
                    </li>
                    @endif
                    @if (Auth::user()->canViewAllAttendance())
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('web.attendance.today') ? 'active' : '' }}" href="{{ route('web.attendance.today') }}">
                            <span class="sidebar-icon">&#128202;</span>
                            <span>Today's Attendance</span>
                        </a>
                    </li>
                    @endif
                    @if (Auth::user()->canViewRegularizationRequests())
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('web.attendance.regularize.*') ? 'active' : '' }}" href="{{ route('web.attendance.regularize.index') }}">
                            <span class="sidebar-icon">&#9998;</span>
                            <span>Regularization</span>
                        </a>
                    </li>
                    @endif
                    @if (Auth::user()->canViewLeaveRequests())
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('web.leave.*') ? 'active' : '' }}" href="{{ route('web.leave.index') }}">
                            <span class="sidebar-icon">&#128197;</span>
                            <span>Leave Management</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </div>

            @if (Auth::user()->canViewPayroll())
            <div class="sidebar-section">
                <span class="sidebar-label">Finance</span>
                <ul class="nav flex-column">
                    @if (Auth::user()->canManagePayroll())
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('web.payroll.index') ? 'active' : '' }}" href="{{ route('web.payroll.index') }}">
                            <span class="sidebar-icon">&#128176;</span>
                            <span>Payroll</span>
                        </a>
                    </li>
                    @endif
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('web.payroll.my-payslips') ? 'active' : '' }}" href="{{ route('web.payroll.my-payslips') }}">
                            <span class="sidebar-icon">&#128196;</span>
                            <span>My Payslips</span>
                        </a>
                    </li>
                </ul>
            </div>
            @endif

            <div class="sidebar-section">
                <span class="sidebar-label">Reports</span>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <span class="sidebar-icon">&#128202;</span>
                            <span>Reports & Export</span>
                        </a>
                    </li>
                </ul>
            </div>
        @endif
    </nav>
</aside>
