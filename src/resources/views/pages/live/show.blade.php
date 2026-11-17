@extends('layouts.public')

@section('title', 'Live scores · '.$tournament->name)

@section('meta-description', 'Live basketball scores for '.$tournament->name.'. Refreshes automatically every 20 seconds.')

@section('content')
    <div class="hf-page"
         data-live-scores="{{ route('api.public.tournaments.live', $tournament->slug) }}"
         data-live-interval="20000"
         data-live-state="loading">

        <x-matchday-strip
            eyebrow="Live scores"
            :title="$tournament->name"
            :status="$tournament->statusLabel()"
            statusTone="gold"
            description="Current scores refresh automatically. This page pauses polling while the tab is in the background."
            :meta="[
                ['icon' => 'broadcast', 'label' => 'Updates every 20 seconds'],
                ['icon' => 'shield-check', 'label' => 'Scores are audited'],
            ]"
            class="hf-mb-3"
        >
            <a class="hf-btn" href="{{ route('home') }}"><i class="bi bi-house-door" aria-hidden="true"></i> Home</a>
            @auth
                <a class="hf-btn hf-btn--primary" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2" aria-hidden="true"></i> Dashboard</a>
            @endauth
        </x-matchday-strip>

        <section class="hf-card hf-card--flat" aria-labelledby="live-scores-heading">
            <header class="hf-live-head">
                <div class="hf-live-head__title">
                    <span class="hf-live-dot" aria-hidden="true"></span>
                    <h2 class="hf-live-head__heading" id="live-scores-heading">Scoreboard</h2>
                </div>
                <p class="hf-small hf-muted" role="status" data-live-status>Connecting…</p>
            </header>

            <div class="hf-live-list" data-live-list aria-live="polite" aria-atomic="false">
                @forelse ($snapshot['matches'] as $match)
                    @include('pages.live._match', ['match' => $match])
                @empty
                    <p class="hf-empty">
                        <i class="bi bi-broadcast" aria-hidden="true"></i>
                        No matches have been scheduled yet.
                    </p>
                @endforelse
            </div>

            <p class="hf-small hf-muted hf-mt-2">
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                Updated <time data-live-updated>{{ now()->format('g:i:s A') }}</time>.
                Polling pauses when this tab is hidden.
            </p>
        </section>
    </div>
@endsection
