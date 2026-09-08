@extends('employee-experience.layout')

@section('experience-content')
    <div class="content-card mb-4">
        <div class="content-card-body text-center py-5 px-4">
            <div class="mb-3">
                <span class="badge bg-light text-secondary border">Coming soon</span>
            </div>
            <h2 class="h4 mb-2">Polls and Announcements</h2>
            <p class="text-muted mb-4 mx-auto" style="max-width: 36rem;">
                Create polls and track response analytics in real-time. Publish announcements on the social wall to reach out to all employees.
            </p>
            @if (Auth::user()->canSeeMenu('experience.social_wall'))
                <a href="{{ route('web.employee-experience.social-wall') }}" class="btn btn-outline-primary btn-sm">
                    Go to Social Wall
                </a>
            @endif
        </div>
    </div>
@endsection
