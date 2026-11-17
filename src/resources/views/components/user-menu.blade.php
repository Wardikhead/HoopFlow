@php
    $user = auth()->user();
    $role = $user?->hasRole('organizer') ? 'organizer' : ($user?->hasRole('coach') ? 'coach' : 'fan');
    $initials = collect(preg_split('/\s+/', $user?->name ?? ''))
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->slice(0, 2)
        ->implode('');
@endphp

<details class="hf-user-menu">
    <summary class="hf-user-chip">
        <span class="hf-sr-only">Account menu for</span>
        <span class="hf-avatar">{{ $initials }}</span>
        <span class="hf-user-meta">
            <strong>{{ $user?->name }}</strong>
            <span>{{ $role }}</span>
        </span>
        <i class="bi bi-chevron-down" aria-hidden="true"></i>
    </summary>
    <div class="hf-user-menu__panel">
        <a href="{{ route('profile') }}" wire:navigate data-testid="topbar-profile"><i class="bi bi-person" aria-hidden="true"></i> Profile</a>
        <a href="{{ route('profile') }}#appearance" wire:navigate><i class="bi bi-palette" aria-hidden="true"></i> Appearance</a>
        <a href="{{ route('profile') }}#shortcuts" wire:navigate><i class="bi bi-keyboard" aria-hidden="true"></i> Keyboard shortcuts</a>
        <form method="POST" action="{{ route('logout') }}" data-hf-logout-form>
            @csrf
            <button type="submit" data-testid="logout"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Log out</button>
        </form>
    </div>
</details>
