@extends('employee-experience.layout')

@section('experience-header-actions')
    <button type="button" class="btn btn-outline-secondary btn-sm" id="momentsRefreshBtn">Refresh</button>
@endsection

@section('experience-content')
    @include('home.partials.moments-feed')
@endsection

@push('scripts')
    @vite(['resources/js/moments.js'])
@endpush
