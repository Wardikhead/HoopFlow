@extends('layouts.public')

@section('title', 'Sign in')
@section('meta-description', 'Sign in to HoopFlow to manage tournaments, rosters, brackets, schedules, scores, and payments in one organizer workspace.')
@section('body-class', 'hf-auth')
@section('main-class', 'hf-auth-main')

@section('content')
    <div class="hf-auth-shell">
        <aside class="hf-auth-aside" aria-hidden="true">
            <div class="hf-auth-aside__top">
                <x-brand size="lg" on-dark tagline="Tournament ops" />
            </div>

            <div class="hf-auth-aside__copy">
                <p class="hf-eyebrow hf-eyebrow--on-dark"><span class="hf-auth-dot"></span> Courtside, organized</p>
                <h2 class="hf-auth-aside__title">Every roster, bracket, and <em>final score</em> in one place.</h2>
                <ul class="hf-auth-points">
                    <li><i class="bi bi-clipboard-check" aria-hidden="true"></i> Review eligibility before seeding</li>
                    <li><i class="bi bi-diagram-3" aria-hidden="true"></i> Run the bracket from the first tip-off</li>
                    <li><i class="bi bi-megaphone" aria-hidden="true"></i> Keep coaches and fans on the same page</li>
                </ul>
            </div>

            <div class="hf-auth-aside__foot">
                <span>One workspace</span>
                <span>Three roles</span>
                <span>Less group chat</span>
            </div>

            <svg class="hf-auth-court" viewBox="0 0 360 360" fill="none" aria-hidden="true" focusable="false">
                <g stroke="currentColor" stroke-width="1.5">
                    <circle cx="180" cy="180" r="150" />
                    <circle cx="180" cy="180" r="44" />
                    <path d="M30 0v360M330 0v360M0 30h360M0 330h360" />
                    <path d="M120 0v118h120V0" />
                    <path d="M30 30h300v300H30z" />
                </g>
                <path class="hf-auth-court__play" d="M86 296c34-58 84-92 142-84 40 5 58 34 82 26" stroke="#fb7a1e" stroke-width="2" stroke-linecap="round" stroke-dasharray="7 9" />
            </svg>
        </aside>

        <div
            class="hf-auth-card"
            data-testid="login-card"
            x-data="{
                showPassword: false,
                submitting: false,
                email: @js(old('email', '')),
                password: '',
            }"
        >
            <header class="hf-auth-head">
                <span class="hf-auth-kicker"><span class="hf-auth-pulse" aria-hidden="true"></span> Welcome back</span>
                <h1 class="hf-auth-title">Sign in</h1>
                <p class="hf-auth-sub">Pick up your tournaments, teams, and matchday where you left off.</p>
            </header>

            @if (session('status'))
                <x-callout tone="success" class="hf-mb-2">{{ session('status') }}</x-callout>
            @endif

            <form method="POST" action="{{ route('login.store') }}" novalidate x-on:submit="submitting = true">
                @csrf

                <x-form-errors :targets="['email' => 'loginEmail', 'password' => 'loginPassword']" />
                <x-required-note class="hf-mb-2" />

                <div class="hf-field">
                    <label class="hf-label" for="loginEmail">Email</label>
                    <div class="hf-auth-control">
                        <i class="bi bi-envelope" aria-hidden="true"></i>
                        <input
                            class="hf-input"
                            id="loginEmail"
                            name="email"
                            type="email"
                            autocomplete="username"
                            x-model="email"
                            value="{{ old('email') }}"
                            data-testid="login-email"
                            required
                            @error('email') aria-invalid="true" aria-describedby="loginEmail-error" @enderror
                        >
                    </div>
                    @error('email')
                        <div class="hf-field-error" id="loginEmail-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="hf-field hf-mt-2">
                    <div class="hf-between">
                        <label class="hf-label" for="loginPassword">Password</label>
                        <a class="hf-small" href="{{ route('password.request') }}" data-testid="login-forgot-password">Forgot password?</a>
                    </div>
                    <div class="hf-auth-control">
                        <i class="bi bi-shield-lock" aria-hidden="true"></i>
                        <input
                            class="hf-input"
                            id="loginPassword"
                            name="password"
                            x-bind:type="showPassword ? 'text' : 'password'"
                            type="password"
                            autocomplete="current-password"
                            x-model="password"
                            data-testid="login-password"
                            required
                            @error('password') aria-invalid="true" aria-describedby="loginPassword-error" @enderror
                        >
                        <button
                            type="button"
                            class="hf-auth-eye"
                            x-on:click="showPassword = ! showPassword"
                            x-bind:aria-pressed="showPassword ? 'true' : 'false'"
                            x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'"
                            aria-label="Show password"
                            data-testid="login-password-toggle"
                        >
                            <i class="bi" x-bind:class="showPassword ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="hf-field-error" id="loginPassword-error">{{ $message }}</div>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="hf-btn hf-btn--primary hf-full hf-mt-3 hf-auth-submit"
                    data-testid="login-submit"
                    x-bind:class="{ 'is-loading': submitting }"
                    x-bind:disabled="submitting"
                >
                    <span>Sign in</span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    <span class="hf-auth-submit__shine" aria-hidden="true"></span>
                </button>
            </form>

            @if (config('app.demo_accounts'))
                <p class="hf-demo-title hf-mt-3">Quick-fill accounts</p>
                <div class="hf-quick-accounts">
                    <button
                        type="button"
                        x-on:click="email = 'organizer@hoopflow.ph'; password = 'password'"
                        data-testid="quick-login-organizer"
                    >
                        <i class="bi bi-kanban" aria-hidden="true"></i>
                        <strong>Organizer</strong>
                        <span>Tournament ops</span>
                    </button>
                    <button
                        type="button"
                        x-on:click="email = 'coach.marcus@hoopflow.ph'; password = 'password'"
                        data-testid="quick-login-coach"
                    >
                        <i class="bi bi-people" aria-hidden="true"></i>
                        <strong>Coach</strong>
                        <span>San Antonio Vipers</span>
                    </button>
                    <button
                        type="button"
                        x-on:click="email = 'fan.juan@hoopflow.ph'; password = 'password'"
                        data-testid="quick-login-fan"
                    >
                        <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
                        <strong>Fan</strong>
                        <span>Public view</span>
                    </button>
                </div>
            @endif

            <p class="hf-auth-switch">
                No account? <a href="{{ route('register') }}">Create one</a>.
            </p>
        </div>
    </div>
@endsection
