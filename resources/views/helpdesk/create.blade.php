@extends('layouts.app')



@section('title', 'Raise Ticket - Helpdesk - ' . config('app.name', 'HRMS'))



@section('header')

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">

        <div>

            <h1 class="page-title mb-1">Raise Ticket</h1>

            <p class="page-subtitle mb-0">Describe your issue and our HR team will follow up with updates.</p>

        </div>

        <a href="{{ route('web.helpdesk.index') }}" class="btn btn-outline-secondary">Back to Helpdesk</a>

    </div>

@endsection



@section('content')

    <div id="helpdeskCreateRoot" data-can-manage="{{ Auth::user()->canManageHelpdesk() ? '1' : '0' }}">

        <div id="helpdeskCreateAlert" class="alert d-none"></div>



        <div class="content-card">

            <div class="content-card-body">

                <form id="helpdeskCreateForm" class="row g-3">

                    <div class="col-md-8">

                        <label for="subject" class="form-label">Subject</label>

                        <input type="text" class="form-control" id="subject" name="subject" maxlength="255" required placeholder="Brief summary of your issue">

                    </div>

                    <div class="col-md-2">

                        <label for="category" class="form-label">Category</label>

                        <div class="d-flex gap-2">

                            <select class="form-select" id="category" name="helpdesk_category_id" required></select>

                            @if (Auth::user()->canManageHelpdesk())

                                <button type="button" class="btn btn-outline-secondary flex-shrink-0" id="helpdeskAddCategoryBtn" title="Add category">+</button>

                            @endif

                        </div>

                    </div>

                    <div class="col-md-2">

                        <label for="priority" class="form-label">Priority</label>

                        <select class="form-select" id="priority" name="priority"></select>

                    </div>

                    <div class="col-12">

                        <label for="description" class="form-label">Description</label>

                        <textarea class="form-control" id="description" name="description" rows="6" maxlength="5000" required placeholder="Provide details so HR can help you faster"></textarea>

                    </div>

                    <div class="col-12">

                        <label for="attachments" class="form-label">Attachments</label>

                        <input type="file" class="form-control" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">

                        <div class="form-text">Optional. Up to 5 files (images or PDF, max 5 MB each).</div>

                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">

                        <a href="{{ route('web.helpdesk.index') }}" class="btn btn-outline-secondary">Cancel</a>

                        <button type="submit" class="btn btn-primary" id="helpdeskCreateBtn">Submit Ticket</button>

                    </div>

                </form>

            </div>

        </div>

    </div>



    @if (Auth::user()->canManageHelpdesk())

        <div class="modal fade" id="helpdeskCategoryModal" tabindex="-1" aria-hidden="true">

            <div class="modal-dialog">

                <div class="modal-content">

                    <form id="helpdeskCategoryForm">

                        <div class="modal-header">

                            <h5 class="modal-title">Add Helpdesk Category</h5>

                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

                        </div>

                        <div class="modal-body">

                            <label for="helpdeskCategoryName" class="form-label">Category name</label>

                            <input type="text" class="form-control" id="helpdeskCategoryName" maxlength="100" required placeholder="e.g. Benefits">

                        </div>

                        <div class="modal-footer">

                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>

                            <button type="submit" class="btn btn-primary" id="helpdeskCategorySaveBtn">Save Category</button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    @endif



    @vite(['resources/js/helpdesk-create.js'])

@endsection

