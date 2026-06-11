@extends('layouts.app')

@section('title', 'Role Details - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1" id="roleTitle">Role Details</h1>
            <p class="page-subtitle mb-0" id="roleSubtitle">Loading role information...</p>
        </div>
        <a href="{{ route('web.masters.roles.index') }}" class="btn btn-outline-secondary">Back to roles</a>
    </div>
@endsection

@section('content')
    <div id="roleShowRoot" data-role-id="{{ $roleId }}">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="content-card h-100">
                    <div class="content-card-body">
                        <div class="mb-3">
                            <span class="text-muted small text-uppercase fw-semibold">Role</span>
                            <h4 class="mb-1 mt-1" id="roleName">—</h4>
                            <p class="text-muted mb-0" id="roleDescription">—</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge rounded-pill text-bg-light border" id="roleLevelBadge">Level —</span>
                            <span class="badge rounded-pill" id="roleStatusBadge">—</span>
                        </div>
                        <p class="small text-muted mb-0" id="roleMeta">System role</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="content-card h-100">
                    <div class="content-card-header">
                        <h5 class="content-card-title mb-0">Permissions</h5>
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
