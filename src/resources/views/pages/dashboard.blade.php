@extends('layouts.public')

@section('title', 'Dashboard')

@section('content')
    <div class="container py-5">
        <h1 class="h4 fw-bold mb-3">Dashboard</h1>
        <p class="text-muted">Welcome to HoopFlow.</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">Logout</button>
        </form>
    </div>
@endsection
