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
@endphp

<header class="hf-page-head hf-mb-3">
    <div>
        <span class="hf-eyebrow">Account</span>
        <h1 class="hf-page-title">Profile</h1>
        <p class="hf-page-desc">Your identity, role scope, and appearance preferences.</p>
    </div>
</header>

<section class="hf-card hf-profile-head hf-mb-3" data-testid="profile-identity">
    <span class="hf-avatar hf-avatar--xl">{{ $initials }}</span>
    <div class="hf-profile-meta">
        <span class="hf-flex hf-wrap hf-gap-1">
            <span class="hf-chip hf-chip--primary" style="text-transform: capitalize">{{ $role }}</span>
        </span>
        <h2>{{ $user->name }}</h2>
        <p>{{ $user->email }}</p>
    </div>
</section>

<div class="hf-grid hf-grid--2 hf-mb-3">
    <section class="hf-card" data-testid="profile-role-card">
        <span class="hf-kpi-label">Role context</span>
        <h2 class="hf-section-title hf-mt-1" style="text-transform: capitalize">{{ $role }}</h2>
        <p class="hf-small hf-muted">{{ $roleCopy }}</p>
        <div class="hf-btn-row hf-mt-2">
            @foreach ($roleLinks as $index => [$key, $label])
                <a class="hf-btn hf-btn--sm {{ $index === 0 ? 'hf-btn--primary' : '' }}"
                   href="{{ route($key) }}" data-testid="profile-link-{{ $key }}">{{ $label }}</a>
            @endforeach
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
</div>

<section id="shortcuts" class="hf-card hf-mt-3">
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
@endsection