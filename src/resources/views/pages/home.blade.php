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

    @include('landing.flow', ['steps' => $showcase->flow()])
    @include('landing.roles', ['roles' => $showcase->roles()])
    @include('landing.eligibility', ['eligibility' => $showcase->eligibility()])
    @include('landing.wire', ['announcements' => $showcase->announcements()])
    @include('landing.closing')
@endsection
