@extends('layouts.app')

@section('title', 'Role Details - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1" id="roleTitle">Role Details</h1>
            <p class="page-subtitle mb-0" id="roleSubtitle">Loading role information...</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-danger d-none" id="deleteRoleBtn">Delete role</button>
            <button type="button" class="btn btn-outline-secondary d-none" id="resetRolePermissionsBtn">Reset to defaults</button>
            <button type="button" class="btn btn-primary d-none" id="saveRolePermissionsBtn">Save permissions</button>
            <a href="{{ route('web.masters.roles.index') }}" class="btn btn-outline-secondary">Back to roles</a>
        </div>
    </div>
@endsection

@section('content')
    <div id="roleShowAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>

    <div id="roleShowRoot" data-role-id="{{ $roleId }}">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="content-card h-100">
                    <div class="content-card-body">
                        <form id="roleDetailsForm" class="d-none">
                            <div class="mb-3">
                                <label for="editRoleName" class="form-label">Role name</label>
                                <input type="text" class="form-control" id="editRoleName" maxlength="120" required>
                            </div>
                            <div class="mb-3">
                                <label for="editRoleDescription" class="form-label">Description</label>
                                <textarea class="form-control" id="editRoleDescription" rows="3" maxlength="2000"></textarea>
                            </div>
                        </form>

                        <div id="roleDetailsReadonly">
                            <div class="mb-3">
                                <span class="text-muted small text-uppercase fw-semibold">Role</span>
                                <h4 class="mb-1 mt-1" id="roleName">—</h4>
                                <p class="text-muted mb-0" id="roleDescription">—</p>
                            </div>
                            <p class="small text-muted mb-3" id="roleMeta">—</p>
                        </div>

                        <div class="mb-3" id="roleStatusWrap">
                            <label class="form-label d-block" for="roleStatusToggle">Status</label>
                            <div class="company-status-cell">
                                <div class="form-check form-switch company-status-switch company-status-switch--solo mb-0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        id="roleStatusToggle"
                                        aria-label="Role status"
                                    >
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 d-none" id="saveRoleDetailsBtn" form="roleDetailsForm">Save role details</button>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="content-card h-100">
                    <div class="content-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="content-card-title mb-0">Menu permissions</h5>
                        <span class="badge text-bg-light border" id="roleOverrideBadge">—</span>
                    </div>
                    <div class="content-card-body" id="rolePermissionsWrap">
                        <p class="text-muted mb-0">Loading permissions...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @vite(['resources/js/roles-show.js'])
@endsection
