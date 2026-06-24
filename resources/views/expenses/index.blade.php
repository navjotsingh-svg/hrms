@extends('layouts.app')

@section('title', 'Expenses - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Expenses</h1>
            <p class="page-subtitle mb-0">Submit expense claims and groups. Only HR or Company Admin can approve; approved reimbursements are included in payroll.</p>
        </div>
        <div class="d-flex gap-2">
            @if (Auth::user()->canApplyExpenses())
                <button type="button" class="btn btn-outline-secondary" id="exportExpensesBtn">Export CSV</button>
                <button type="button" class="btn btn-primary" id="openExpenseModalBtn">+ Add Expense</button>
            @endif
        </div>
    </div>
@endsection

@section('content')
    <div id="expensesAlert" class="alert alert-success alert-dismissible fade show d-none"></div>

    <div class="content-card companies-list-card">
        <div class="content-card-body border-bottom">
            <p class="small text-muted mb-3 mb-md-0">HR or Company Admin approve expenses from <a href="{{ route('web.requests.index') }}">Requests</a>. Only approved claims are paid through payroll.</p>
            <ul class="nav nav-tabs mb-0" id="expensesTabs">
                <li class="nav-item">
                    <button class="nav-link active" type="button" data-expenses-tab="all">All Expenses</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" type="button" data-expenses-tab="groups">Expense Groups</button>
                </li>
            </ul>
        </div>

        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="filterApprovalStatus" class="form-label">Approval Status</label>
                    <select class="form-select" id="filterApprovalStatus">
                        <option value="">All</option>
                        <option value="draft">Draft</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filterBelongsTo" class="form-label">Belongs To</label>
                    <select class="form-select" id="filterBelongsTo">
                        @if (Auth::user()->canViewAllExpenses())
                            <option value="all">All</option>
                            <option value="myself" selected>Myself</option>
                            <option value="reportees">My Reportees</option>
                        @else
                            <option value="myself" selected>Myself</option>
                        @endif
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="filterSearch" class="form-label">Search</label>
                    <input type="search" class="form-control" id="filterSearch" placeholder="Search by expense type or employee name">
                </div>
                <div class="col-md-2 d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" id="filterReset">Reset</button>
                </div>
            </div>
        </div>

        <div id="allExpensesPanel">
            <div class="table-responsive">
                <table class="companies-table table mb-0">
                    <thead>
                        <tr>
                            <th>Expense Date</th>
                            <th>Created On</th>
                            <th>Expense Type</th>
                            <th>Amount</th>
                            <th>Payout Status</th>
                            <th>Approval Status</th>
                            <th>Actioned By</th>
                            <th>Belongs To</th>
                            <th>Receipt</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="expensesTableBody">
                        <tr><td colspan="10" class="text-center text-muted py-5">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="groupsPanel" class="d-none">
            <div class="content-card-body border-bottom d-flex justify-content-end">
                @if (Auth::user()->canApplyExpenses())
                    <button type="button" class="btn btn-primary" id="openGroupModalBtn">+ Add Expense Group</button>
                @endif
            </div>
            <div class="table-responsive">
                <table class="companies-table table mb-0">
                    <thead>
                        <tr>
                            <th>Expense Group Name</th>
                            <th>Belongs To</th>
                            <th>Created On</th>
                            <th>Total Amount</th>
                            <th>Approved Reimbursable</th>
                            <th>Travel Advance</th>
                            <th>Net Adjustment</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="groupsTableBody">
                        <tr><td colspan="9" class="text-center text-muted py-5">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="content-card-body border-top">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2">
                    <label for="itemsPerPage" class="small text-muted mb-0">Items per page</label>
                    <select class="form-select form-select-sm" id="itemsPerPage" style="width: auto;">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                    </select>
                </div>
                <div class="small text-muted" id="expensesPaginationInfo"></div>
                <ul class="pagination pagination-sm mb-0" id="expensesPaginationList"></ul>
            </div>
        </div>
    </div>

    @include('expenses.partials.request-detail-modal')

    @if (Auth::user()->canApplyExpenses())
        <div class="modal fade" id="expenseModal" tabindex="-1" aria-labelledby="expenseModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form id="expenseForm" class="modal-scroll-form">
                        <input type="hidden" id="expenseEditingId" value="">
                        <div class="modal-header">
                            <h5 class="modal-title" id="expenseModalLabel">Create Expense</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="expenseFormAlert" class="alert alert-danger d-none"></div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="expenseIndependent" checked>
                                <label class="form-check-label" for="expenseIndependent">Independent expense</label>
                            </div>
                            <div class="mb-3 d-none" id="expenseGroupSelectWrap">
                                <label for="expenseGroupId" class="form-label">Expense Group</label>
                                <select class="form-select" id="expenseGroupId"></select>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="expenseDate" class="form-label">Expense Date *</label>
                                    <input type="date" class="form-control" id="expenseDate" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="expenseMerchant" class="form-label">Merchant</label>
                                    <input type="text" class="form-control" id="expenseMerchant">
                                </div>
                                <div class="col-md-6">
                                    <label for="expenseTypeId" class="form-label">Expense Type *</label>
                                    <select class="form-select" id="expenseTypeId" required></select>
                                </div>
                                <div class="col-md-6">
                                    <label for="expenseAmount" class="form-label">Amount *</label>
                                    <input type="number" class="form-control" id="expenseAmount" min="0.01" step="0.01" required>
                                </div>
                                <div class="col-12">
                                    <label for="expenseDescription" class="form-label">Description</label>
                                    <textarea class="form-control" id="expenseDescription" rows="2"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="expenseReference" class="form-label">Reference #</label>
                                    <input type="text" class="form-control" id="expenseReference">
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="expenseClaimReimbursement" checked>
                                        <label class="form-check-label" for="expenseClaimReimbursement">Claim Reimbursement</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label for="expenseReceipt" class="form-label">Receipt</label>
                                    <input type="file" class="form-control" id="expenseReceipt" accept=".pdf,.zip,.xlsx,.xls,image/*">
                                    <div class="form-text">PDF, ZIP, XLSX, or images (&lt; 10 MB)</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-outline-primary d-none" id="expenseFormDraftBtn" name="action" value="draft">Save Draft</button>
                            <button type="submit" class="btn btn-primary" id="expenseFormPrimaryBtn" name="action" value="submit">Create & Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="groupModal" tabindex="-1" aria-labelledby="groupModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="groupModalLabel">Create Expense Group</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="groupForm">
                        <input type="hidden" id="groupEditingId" value="">
                        <div class="modal-body">
                            <div id="groupFormAlert" class="alert alert-danger d-none"></div>
                            <div class="mb-3">
                                <label for="groupName" class="form-label">Expense group name *</label>
                                <input type="text" class="form-control" id="groupName" required placeholder="Expense group name">
                            </div>
                            <div class="mb-3">
                                <label for="groupDescription" class="form-label">Purpose / description</label>
                                <input type="text" class="form-control" id="groupDescription" placeholder="Description">
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="groupFromDate" class="form-label">From *</label>
                                    <input type="date" class="form-control" id="groupFromDate" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="groupToDate" class="form-label">To *</label>
                                    <input type="date" class="form-control" id="groupToDate" required>
                                </div>
                                <div class="col-12">
                                    <label for="groupTravelAdvance" class="form-label">Travel advance amount</label>
                                    <input type="number" class="form-control" id="groupTravelAdvance" min="0" step="0.01" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="groupFormSubmitBtn">Create</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="groupDetailModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="groupDetailTitle">Expense Group</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="groupDetailSummary" class="mb-3"></div>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Receipt</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="groupDetailExpenses"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer" id="groupDetailActions"></div>
                </div>
            </div>
        </div>
    @endif

    @vite(['resources/js/expenses-index.js'])
@endsection
