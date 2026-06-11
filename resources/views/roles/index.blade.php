@extends('layouts.app')

@section('title', 'Manage Roles - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Manage Roles</h1>
            <p class="page-subtitle mb-0">View company roles and their permissions before assigning employees.</p>
        </div>
    </div>
@endsection

@section('content')
    <div class="content-card companies-list-card">
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th class="companies-th-serial">#</th>
                        <th>Role</th>
                        <th>Level</th>
                        <th>Permissions</th>
                        <th>Status</th>
                        <th class="companies-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="rolesTableBody">
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">Loading roles...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @vite(['resources/js/roles-index.js'])
@endsection
