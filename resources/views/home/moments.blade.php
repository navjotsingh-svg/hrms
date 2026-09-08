@extends('layouts.app')



@section('title', 'Moments - ' . config('app.name', 'HRMS'))



@section('header')

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">

        <div>

            <h1 class="page-title mb-1">Company Moments</h1>

            <p class="page-subtitle mb-0">Celebrate milestones, share updates, and stay connected with your team.</p>

        </div>

    </div>

@endsection



@section('content')

    @php

        $homeUrl = route('web.home.index').'#home-moments';

    @endphp

    <div class="alert alert-info">

        Company Moments now lives on the unified Home page.

        <a href="{{ $homeUrl }}" class="alert-link">Go to Home &rarr; Moments</a>

    </div>

@endsection

