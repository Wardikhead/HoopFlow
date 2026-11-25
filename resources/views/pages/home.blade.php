{{-- Blade view for Home. --}}
@extends('layouts.landing')

@section('title', 'HoopFlow — Run the league, not the spreadsheet')

@section('content')
    @include('landing.splash')
    @php
        $teams = collect($showcase->teams())->keyBy('id');
    @endphp

    @include('landing.hero', [
        'tournament' => $showcase->tournament(),
        'live' => $showcase->liveGame(),
        'next' => $showcase->nextGame(),
        'teams' => $teams,
    ])

    @include('landing.lab', [
        'tournament' => $showcase->tournament(),
        'bracket' => $showcase->bracket(),
        'schedule' => $showcase->schedule(),
        'calendar' => $showcase->calendar(),
        'standings' => $showcase->standings(),
        'teams' => $teams,
    ])

    @include('landing.story', [
        'bracket' => $showcase->bracket(),
        'teams' => $teams,
    ])

    @include('landing.compare', [
        'tournament' => $showcase->tournament(),
        'standings' => $showcase->standings(),
        'teams' => $teams,
    ])

    @include('landing.numbers', ['stats' => [
        ['value' => $teams->count(), 'label' => 'Teams on one record', 'icon' => 'people'],
        ['value' => collect($showcase->bracket()['rounds'])->sum(fn ($round) => count($round['matches'])), 'label' => 'Matches on the board', 'icon' => 'diagram-3'],
        ['value' => count($showcase->schedule()), 'label' => 'Matchdays scheduled', 'icon' => 'calendar3'],
        ['value' => count($showcase->tournament()['courts']), 'label' => 'Courts running in parallel', 'icon' => 'grid-3x3-gap'],
    ]])

    @include('landing.flow', ['steps' => $showcase->flow()])
    @include('landing.roles', ['roles' => $showcase->roles()])
    @include('landing.eligibility', ['eligibility' => $showcase->eligibility()])
    @include('landing.wire', ['announcements' => $showcase->announcements()])

    @include('landing.crests', [
        'teams' => $teams,
        'standings' => $showcase->standings(),
    ])

    @include('landing.faq')
    @include('landing.closing')
    @include('landing.developers', ['developers' => $showcase->developers()])
@endsection
