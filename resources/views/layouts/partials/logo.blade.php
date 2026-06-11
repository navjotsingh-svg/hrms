@php
    $companyName = config('hrms.company_name', config('app.name', 'HRMS'));
    $logoPath = config('hrms.company_logo');
    $hasLogo = $logoPath && file_exists(public_path($logoPath));
@endphp

<a href="{{ route('web.dashboard') }}" class="company-brand text-decoration-none d-flex align-items-center gap-2">
    @if ($hasLogo)
        <img src="{{ asset($logoPath) }}" alt="{{ $companyName }}" class="company-logo-img">
    @else
        <div class="company-logo-default">HR</div>
    @endif
    <div class="company-brand-text">
        <span class="company-brand-name">{{ $companyName }}</span>
        @if (! $hasLogo)
            <span class="company-brand-tagline">Human Resource Management</span>
        @endif
    </div>
</a>
