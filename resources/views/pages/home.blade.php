@extends('layouts.landing')

@section('title', 'HoopFlow — Run the league, not the spreadsheet')

@section('content')
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
        'teams' => $teams,
    ])
@endsection
