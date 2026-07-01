@extends('employee-experience.layout')

@section('experience-content')
    <div class="content-card mb-4">
        <div class="content-card-body text-center py-5 px-4">
            <div class="mb-3">
                <span class="badge bg-light text-secondary border">Coming soon</span>
            </div>
            <h2 class="h4 mb-2">Public Praise</h2>
            <p class="text-muted mb-4 mx-auto" style="max-width: 36rem;">
                Let employees praise their peers to foster collaboration and boost morale. Praise posts will appear on the social wall for the whole company to see.
            </p>
            @if (Auth::user()->hasPermission('home.moments.post') && Auth::user()->canSeeMenu('experience.social_wall'))
                <a href="{{ route('web.employee-experience.social-wall') }}" class="btn btn-outline-primary btn-sm">
                    Share praise on Social Wall
                </a>
            @endif
        </div>
    </div>
@endsection
