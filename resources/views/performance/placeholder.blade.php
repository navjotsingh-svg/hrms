@extends('performance.layout')

@section('performance-content')
    <div class="content-card">
        <div class="content-card-body text-center py-5 px-4">
            <div class="mb-3">
                <span class="badge bg-light text-secondary border">Coming soon</span>
            </div>
            <h2 class="h4 mb-2">{{ $featureTitle }}</h2>
            <p class="text-muted mb-0 mx-auto" style="max-width: 36rem;">{{ $featureDescription }}</p>
        </div>
    </div>
@endsection
