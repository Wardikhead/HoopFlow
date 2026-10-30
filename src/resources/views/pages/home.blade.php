@extends('layouts.public')

@section('title', 'HoopFlow — Youth Tournament Management')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <div class="hf-card p-4 p-md-5">
                    <span class="badge bg-warning-subtle text-warning-emphasis fw-bold mb-3">Walking skeleton</span>
                    <h1 class="display-6 fw-bold mb-2">HoopFlow</h1>
                    <p class="lead text-muted mb-4">
                        Tournament management for barangay and grassroots youth basketball leagues.
                    </p>

                    <ul class="list-group list-group-flush mb-4">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>Laravel</span>
                            <span class="text-muted small">{{ app()->version() }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>Livewire</span>
                            <span class="text-muted small">4.x</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>Bootstrap</span>
                            <span class="text-muted small">5.3 · compiled from SCSS</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>Database</span>
                            <span class="text-muted small">{{ config('database.default') }}</span>
                        </li>
                    </ul>

                    <p class="text-muted small mb-0">
                        The interface is being ported from <code>prototype/</code>. Pages land here as Blade and
                        Livewire components, one vertical slice at a time.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
