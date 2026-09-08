@props([
    'label',
    'valueId' => null,
    'variant' => 'brand',
    'icon' => 'chart',
])

@php
    $icons = [
        'cycle' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z"/><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0"/></svg>',
        'review' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M14 1a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H4.414L2 13.414V2a1 1 0 0 1 1-1zM2 0a2 2 0 0 0-2 2v12.793a1 1 0 0 0 1.707.707L3.414 11H14a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z"/></svg>',
        'goal' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05"/></svg>',
        'pip' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16M4.285 9.567a.5.5 0 0 1 .683.183A3.5 3.5 0 0 0 8 11.5a3.5 3.5 0 0 0 3.502-2.667.5.5 0 0 1 .866-.5A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1-4.898-3.667.5.5 0 0 1 .183-.683M7 6.5C7 7.328 6.552 8 6 8s-1-.672-1-1.5S5.448 5 6 5s1 .672 1 1.5m4 0c0 .828-.448 1.5-1 1.5s-1-.672-1-1.5S9.448 5 10 5s1 .672 1 1.5"/></svg>',
        'feedback' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M2.678 11.894a1 1 0 0 1 .287.801 11 11 0 0 1-.398 2c1.395-.323 2.674-.698 3.597-1.15a12 12 0 0 1 .921-.616 1 1 0 0 1 .707.293l2.675 2.675a1 1 0 0 0 1.414 0l2.675-2.675a1 1 0 0 1 .707-.293 12 12 0 0 1 .921.616c.923.452 2.202.827 3.597 1.15a11 11 0 0 1-.398-2 1 1 0 0 1 .287-.801 11 11 0 0 0-1.697-1.697 1 1 0 0 1-.801-.287 11 11 0 0 0-2.127-1.078 1 1 0 0 1-.287-.801 11 11 0 0 0-1.078-2.127 1 1 0 0 1-.287-.801A11 11 0 0 0 5.678 2.894a1 1 0 0 1-.287.801 11 11 0 0 0-1.697 1.697 1 1 0 0 1-.801.287 11 11 0 0 0-2.127 1.078 1 1 0 0 1-.287.801 11 11 0 0 0-1.078 2.127 1 1 0 0 1-.287.801A11 11 0 0 0 2.678 11.894"/></svg>',
        'chart' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M4 11H2v3h2zm5-4H7v7h2zm5-5v12h-2V2zm-2-1H14v14H2V1h10z"/></svg>',
    ];
@endphp

<div {{ $attributes->merge(['class' => "hrms-stat-card hrms-stat-card--{$variant}"]) }}>
    <div class="hrms-stat-card__icon" aria-hidden="true">{!! $icons[$icon] ?? $icons['chart'] !!}</div>
    <div class="hrms-stat-card__body">
        <div class="hrms-stat-card__label">{{ $label }}</div>
        <div class="hrms-stat-card__value" @if($valueId) id="{{ $valueId }}" @endif>—</div>
    </div>
</div>
