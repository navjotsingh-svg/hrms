@extends('employee-experience.layout')

@section('experience-content')
    <div class="content-card mb-4">
        <div class="content-card-body text-center py-5 px-4">
            <h2 class="h4 mb-2">Public Praise</h2>
            <p class="text-muted mb-4 mx-auto" style="max-width: 36rem;">
                Celebrate colleagues publicly and build a culture of recognition across your organization.
            </p>
            <a href="{{ route('web.performance.praise-recognition') }}" class="btn btn-primary btn-sm">
                Open Praise & Recognition
            </a>
        </div>
    </div>
@endsection
