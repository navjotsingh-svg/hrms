@extends('layouts.app')

@section('title', 'Manage Roles - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Manage Roles</h1>
            <p class="page-subtitle mb-0">Only Company Admin has full access. Assign modules and actions to HR and other roles below.</p>
        </div>
        <button type="button" class="btn btn-primary" id="createRoleBtn">Create custom role</button>
    </div>
@endsection

@section('content')
    <div id="rolesAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card companies-list-card">
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th class="companies-th-serial">#</th>
                        <th>Role</th>
                        <th>Permissions</th>
                        <th>Users</th>
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

    <div class="modal fade" id="createRoleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create custom role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="createRoleForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="createRoleName" class="form-label">Role name</label>
                            <input type="text" class="form-control" id="createRoleName" required maxlength="120" placeholder="e.g. Payroll Coordinator">
                        </div>
                        <div class="mb-0">
                            <label for="createRoleDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="createRoleDescription" rows="3" maxlength="2000" placeholder="What this role is responsible for"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create & configure</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @vite(['resources/js/roles-index.js'])
@endsection
