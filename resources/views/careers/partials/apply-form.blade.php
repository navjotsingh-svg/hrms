@php
    $formAction = $formAction ?? route('careers.apply-general', $company->slug);
    $selectedJobId = $selectedJobId ?? old('job_id');
    $showJobSelect = $showJobSelect ?? false;
@endphp

<form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="careers-apply-form">
    @csrf
    <div class="row g-3">
        @if ($showJobSelect && $jobs->isNotEmpty())
            <div class="col-12">
                <label class="form-label" for="job_id">Position</label>
                <select class="form-select" id="job_id" name="job_id">
                    <option value="">General application</option>
                    @foreach ($jobs as $jobOption)
                        <option value="{{ $jobOption->id }}" @selected((string) $selectedJobId === (string) $jobOption->id)>
                            {{ $jobOption->title }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="col-md-6">
            <label class="form-label" for="first_name">First Name *</label>
            <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name') }}" required>
            @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="last_name">Last Name *</label>
            <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name') }}" required>
            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="email">Email *</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="phone">Phone</label>
            <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="resume">Resume *</label>
            <input type="file" class="form-control @error('resume') is-invalid @enderror" id="resume" name="resume" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
            <div class="form-text">Upload PDF, DOC, or DOCX (max 5 MB).</div>
            @error('resume')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary w-100">Submit Application</button>
        </div>
    </div>
</form>
