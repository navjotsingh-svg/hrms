@php
    $showMyBalance = auth()->user()?->canSeeMenu('leave.balances');
    $showTeamBalance = auth()->user()?->canSeeMenu('masters.leave_balances');
    $active = $active ?? 'mine';
@endphp

@if ($showMyBalance && $showTeamBalance)
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <a
                class="nav-link {{ $active === 'mine' ? 'active' : '' }}"
                href="{{ route('web.leave.balances') }}"
                role="tab"
                @if ($active === 'mine') aria-current="page" @endif
            >My Balance</a>
        </li>
        <li class="nav-item" role="presentation">
            <a
                class="nav-link {{ $active === 'team' ? 'active' : '' }}"
                href="{{ route('web.leave.manage-balances') }}"
                role="tab"
                @if ($active === 'team') aria-current="page" @endif
            >Team Balance</a>
        </li>
    </ul>
@endif
