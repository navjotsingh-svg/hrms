@extends('layouts.app')



@section('title', 'Dashboard - ' . config('app.name', 'HRMS'))



@section('header')

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">

        <div>

            <h1 class="page-title mb-1">Dashboard</h1>

            <p class="page-subtitle mb-0">Analytics widgets for employees, leave, attendance, expenses, hiring, and performance.</p>

        </div>

    </div>

@endsection



@section('content')

    @php

        $homeUrl = route('web.home.index').'#home-analytics';

    @endphp

    <div class="alert alert-info">

        The analytics dashboard now lives on the unified Home page.

        <a href="{{ $homeUrl }}" class="alert-link">Go to Home &rarr; Analytics</a>

    </div>

@endsection

