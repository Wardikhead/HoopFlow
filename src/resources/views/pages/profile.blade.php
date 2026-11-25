{{-- Blade view for Profile. --}}
@extends('layouts.public')

@section('title', 'Profile')

@section('content')
@php
    $user = auth()->user();
    $role = 'fan';
    if ($user && $user->hasRole('organizer')) {
        $role = 'organizer';
    } elseif ($user && $user->hasRole('coach')) {
        $role = 'coach';
    }

    $roleLinks = match ($role) {
        'organizer' => [
            ['dashboard', 'Dashboard'],
            ['submissions', 'Review queue'],
            ['payments', 'Payments'],
            ['audit', 'Audit log'],
        ],
        'coach' => [
            ['myTeam', 'My team'],
            ['tournaments', 'Tournaments'],
        ],
        default => [
            ['dashboard', 'Courtside pass'],
            ['bracket', 'Bracket'],
            ['schedule', 'Schedule'],
        ],
    };

    $roleCopy = match ($role) {
        'organizer' => 'Full tournament operations: approvals, payments, brackets, schedules, scores, announcements, and audit.',
        'coach' => 'Register a team, manage its roster and eligibility, submit a payment reference, and follow its status.',
        default => 'Read-only public pass: bracket, schedule, results, standings, and announcements. No private payment or eligibility data.',
    };

    $initials = collect(preg_split('/\s+/', $user->name ?? ''))
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->slice(0, 2)
        ->implode('');

    $activeSessions = $activeSessions ?? collect();
    $otherSessions = $activeSessions->where('current', false);
@endphp

<header class="hf-page-head hf-mb-3">
    <div>
        <span class="hf-eyebrow">Account</span>
        <h1 class="hf-page-title">Profile</h1>
        <p class="hf-page-desc">Your identity, role scope, and appearance preferences.</p>
    </div>
</header>

<div class="hf-profile-grid">
    <section class="hf-card hf-profile-head" data-testid="profile-identity">
        <span class="hf-avatar hf-avatar--xl">{{ $initials }}</span>
        <div class="hf-profile-meta">
            <span class="hf-chip hf-chip--primary">{{ ucfirst($role) }}</span>
            <h2>{{ $user->name }}</h2>
            <p class="hf-mb-0">{{ $user->email }}</p>
        </div>
    </section>

    <section id="appearance" class="hf-card" data-testid="profile-appearance">
        <span class="hf-kpi-label">Appearance</span>
        <h2 class="hf-section-title hf-mt-1">Theme</h2>
        <p class="hf-small hf-muted">Choose how the workspace looks. System follows your device setting.</p>
        <div class="hf-segmented" role="group" aria-label="Theme">
            @foreach (['light' => 'Light', 'system' => 'System', 'dark' => 'Dark'] as $value => $label)
                <button type="button" class="hf-segmented__item" data-hf-pref="theme" data-hf-value="{{ $value }}" aria-pressed="false">{{ $label }}</button>
            @endforeach
        </div>

        <h2 class="hf-section-title hf-mt-3">Density</h2>
        <div class="hf-segmented" role="group" aria-label="Density">
            @foreach (['comfortable' => 'Comfortable', 'compact' => 'Compact'] as $value => $label)
                <button type="button" class="hf-segmented__item" data-hf-pref="density" data-hf-value="{{ $value }}" aria-pressed="false">{{ $label }}</button>
            @endforeach
        </div>

        <h2 class="hf-section-title hf-mt-3">Motion</h2>
        <div class="hf-segmented" role="group" aria-label="Motion">
            @foreach (['system' => 'System', 'reduced' => 'Reduced'] as $value => $label)
                <button type="button" class="hf-segmented__item" data-hf-pref="motion" data-hf-value="{{ $value }}" aria-pressed="false">{{ $label }}</button>
            @endforeach
        </div>
    </section>

    <section id="password" class="hf-card" data-testid="profile-password">
        <span class="hf-kpi-label">Security</span>
        <h2 class="hf-section-title hf-mt-1">Password</h2>
        <p class="hf-small hf-muted">Change your password. Other signed-in sessions will be signed out.</p>
        @if (session('status') && str_contains(session('status'), 'password'))
            <p class="hf-small hf-chip hf-chip--success hf-mb-2" role="status">{{ session('status') }}</p>
        @endif
        <div class="hf-btn-row hf-mt-2">
            <a class="hf-btn hf-btn--primary" href="{{ route('profile.password') }}" data-testid="profile-change-password">
                <i class="bi bi-key" aria-hidden="true"></i> Change password
            </a>
        </div>
    </section>

    <section id="sessions" class="hf-card" data-testid="profile-sessions">
        <span class="hf-kpi-label">Security</span>
        <h2 class="hf-section-title hf-mt-1">Active sessions</h2>
        <p class="hf-small hf-muted">Devices currently signed in to your account. Sign out anything you do not recognize.</p>

        @if ($activeSessions->isEmpty())
            <p class="hf-small hf-muted hf-mb-0">No active session records yet.</p>
        @else
            <ul class="hf-session-list">
                @foreach ($activeSessions as $session)
                    <li>
                        <div>
                            <strong>{{ $session['device'] }}</strong>
                            <span class="hf-small hf-muted">{{ $session['ip'] }} · {{ $session['last_active'] }}</span>
                        </div>
                        @if ($session['current'])
                            <span class="hf-chip hf-chip--primary">This device</span>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($otherSessions->isNotEmpty())
                <form method="POST" action="{{ route('profile.sessions.destroyOthers') }}" class="hf-mt-3">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="hf-btn hf-btn--sm" data-testid="profile-revoke-sessions">
                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sign out other sessions
                    </button>
                </form>
            @endif
        @endif
    </section>

    <section class="hf-card" data-testid="profile-role-card">
        <span class="hf-kpi-label">Role context</span>
        <h2 class="hf-section-title hf-mt-1">{{ ucfirst($role) }}</h2>
        <p class="hf-small hf-muted">{{ $roleCopy }}</p>
        <div class="hf-btn-row hf-mt-2">
            @foreach ($roleLinks as $index => [$key, $label])
                <a class="hf-btn hf-btn--sm {{ $index === 0 ? 'hf-btn--primary' : '' }}"
                   href="{{ route($key) }}" data-testid="profile-link-{{ $key }}">{{ $label }}</a>
            @endforeach
        </div>
    </section>

    <section id="shortcuts" class="hf-card">
        <span class="hf-kpi-label">Keyboard</span>
        <h2 class="hf-section-title hf-mt-1">Shortcuts</h2>
        <dl class="hf-shortcuts">
            <div><dt><kbd>1</kbd> <kbd>2</kbd> <kbd>3</kbd></dt><dd>Add points to the active team in a score console</dd></div>
            <div><dt><kbd>U</kbd></dt><dd>Undo the last score</dd></div>
            <div><dt><kbd>Enter</kbd></dt><dd>Finalize the score dialog</dd></div>
            <div><dt><kbd>←</kbd> <kbd>→</kbd></dt><dd>Switch the active team in a score console</dd></div>
            <div><dt><kbd>Esc</kbd></dt><dd>Close a dialog or the More sheet</dd></div>
        </dl>
    </section>
</div>
@endsection