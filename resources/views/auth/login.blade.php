@extends('layouts.public')

@section('title', 'Sign in')
@section('meta-description', 'Sign in to HoopFlow to manage tournaments, rosters, brackets, schedules, scores, and payments in one organizer workspace.')
@section('body-class', 'hf-auth')
@section('main-class', 'hf-auth-main')

@section('content')
    <div class="hf-auth-shell">
        <aside class="hf-auth-aside" aria-hidden="true">
            <x-brand size="lg" on-dark tagline="Tournament ops" />
            <p class="hf-eyebrow hf-eyebrow--on-dark">Courtside, organized</p>
            <h2 class="hf-auth-aside__title">Every roster, bracket, and final score in one place.</h2>
            <ul class="hf-auth-points">
                <li><i class="bi bi-clipboard-check" aria-hidden="true"></i> Review eligibility before seeding</li>
                <li><i class="bi bi-diagram-3" aria-hidden="true"></i> Run the bracket from the first tip-off</li>
                <li><i class="bi bi-megaphone" aria-hidden="true"></i> Keep coaches and fans on the same page</li>
            </ul>
        </aside>

        <div
            class="hf-auth-card"
            data-testid="login-card"
            x-data="{
                showPassword: false,
                email: @js(old('email', '')),
                password: '',
            }"
        >
            <span class="hf-eyebrow">Welcome back</span>
            <h1 class="hf-auth-title">Sign in</h1>
            <p class="hf-auth-sub">Sign in to manage your tournaments, teams, and matchday.</p>

            <form method="POST" action="{{ route('login.store') }}" novalidate>
                @csrf

                <x-form-errors :targets="['email' => 'loginEmail', 'password' => 'loginPassword']" />

                <div class="hf-field">
                    <label class="hf-label" for="loginEmail">Email</label>
                    <input
                        class="hf-input"
                        id="loginEmail"
                        name="email"
                        type="email"
                        autocomplete="username"
                        x-model="email"
                        value="{{ old('email') }}"
                        data-testid="login-email"
                        @error('email') aria-invalid="true" aria-describedby="loginEmail-error" @enderror
                    >
                    @error('email')
                        <div class="hf-field-error" id="loginEmail-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="hf-field hf-mt-2">
                    <label class="hf-label" for="loginPassword">Password</label>
                    <div class="hf-inline-form">
                        <input
                            class="hf-input"
                            id="loginPassword"
                            name="password"
                            x-bind:type="showPassword ? 'text' : 'password'"
                            type="password"
                            autocomplete="current-password"
                            x-model="password"
                            data-testid="login-password"
                            @error('password') aria-invalid="true" aria-describedby="loginPassword-error" @enderror
                        >
                        <button
                            type="button"
                            class="hf-btn hf-btn--sm"
                            x-on:click="showPassword = ! showPassword"
                            x-bind:aria-pressed="showPassword ? 'true' : 'false'"
                            x-text="showPassword ? 'Hide' : 'Show'"
                            data-testid="login-password-toggle"
                        >Show</button>
                    </div>
                    @error('password')
                        <div class="hf-field-error" id="loginPassword-error">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="hf-btn hf-btn--primary hf-full hf-mt-3" data-testid="login-submit">
                    Sign in
                </button>
            </form>

            <p class="hf-demo-title hf-mt-3">Quick-fill accounts</p>
            <div class="hf-quick-accounts">
                <button
                    type="button"
                    x-on:click="email = 'organizer@hoopflow.ph'; password = 'password'"
                    data-testid="quick-login-organizer"
                >
                    <strong>Organizer</strong>
                    <span>Tournament operations</span>
                </button>
                <button
                    type="button"
                    x-on:click="email = 'coach.marcus@hoopflow.ph'; password = 'password'"
                    data-testid="quick-login-coach"
                >
                    <strong>Coach</strong>
                    <span>San Antonio Vipers</span>
                </button>
                <button
                    type="button"
                    x-on:click="email = 'fan.juan@hoopflow.ph'; password = 'password'"
                    data-testid="quick-login-fan"
                >
                    <strong>Fan</strong>
                    <span>Public view</span>
                </button>
            </div>

            <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
                No account? <a href="{{ route('register') }}">Create one</a>.
            </p>
        </div>
    </div>
@endsection
