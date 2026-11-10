@extends('layouts.public')

@section('title', 'Team not available')

@section('content')
    <div class="hf-empty" data-testid="team-unavailable">
        <i class="bi bi-shield-lock" aria-hidden="true"></i>
        <h3>Team not available</h3>
        <p>Pending and rejected registrations are visible only to the organizer and the team coach.</p>
        <a class="hf-btn hf-btn--primary" href="{{ route('teams') }}" data-testid="team-unavailable-back">Back to public teams</a>
    </div>
@endsection
