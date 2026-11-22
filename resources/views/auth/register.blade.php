@extends('layouts.public')

@section('title', 'Create account')
@section('meta-description', 'Create a HoopFlow account to register a team, build a roster, and follow the tournament from registration to the final buzzer.')
@section('body-class', 'hf-auth')
@section('main-class', 'hf-auth-main')

@section('content')
    <div class="hf-auth-shell">
        <aside class="hf-auth-aside" aria-hidden="true">
            <div class="hf-auth-aside__top">
                <x-brand size="lg" on-dark tagline="Tournament ops" />
            </div>

            <div class="hf-auth-aside__copy">
                <p class="hf-eyebrow hf-eyebrow--on-dark"><span class="hf-auth-dot"></span> Your season starts here</p>
                <h2 class="hf-auth-aside__title">Register the team, then let the <em>bracket</em> do the talking.</h2>
                <ul class="hf-auth-points">
                    <li><i class="bi bi-people" aria-hidden="true"></i> Build a roster with eligibility in view</li>
                    <li><i class="bi bi-credit-card" aria-hidden="true"></i> Submit one payment reference per entry</li>
                    <li><i class="bi bi-bell" aria-hidden="true"></i> Follow approval and schedule updates</li>
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

        <div class="hf-auth-card" data-testid="register-card" x-data="hfPasswordMeter">
            <header class="hf-auth-head">
                <span class="hf-auth-kicker"><span class="hf-auth-pulse" aria-hidden="true"></span> New account</span>
                <h1 class="hf-auth-title">Create account</h1>
                <p class="hf-auth-sub">Set up your pass to register teams and follow every tournament.</p>
            </header>

            <form id="registerForm" method="POST" action="{{ route('register.store') }}" novalidate x-on:submit="submitting = true">
                @csrf

                <x-form-errors :targets="[
                    'name' => 'registerName',
                    'email' => 'registerEmail',
                    'password' => 'registerPassword',
                    'role' => 'registerRole',
                    'organization' => 'registerOrganization',
                ]" />
                <x-required-note class="hf-mb-2" />

                <div class="hf-form-grid">
                    <div class="hf-field hf-field--full">
                        <label class="hf-label" for="registerName">Full name</label>
                        <div class="hf-auth-control">
                            <i class="bi bi-person" aria-hidden="true"></i>
                            <input
                                class="hf-input"
                                id="registerName"
                                name="name"
                                autocomplete="name"
                                placeholder="Juan Dela Cruz"
                                value="{{ old('name') }}"
                                data-testid="register-name"
                                required
                                @error('name') aria-invalid="true" aria-describedby="registerName-error" @enderror
                            >
                        </div>
                        @error('name')
                            <div class="hf-field-error" id="registerName-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="hf-field hf-field--full">
                        <label class="hf-label" for="registerEmail">Email</label>
                        <div class="hf-auth-control">
                            <i class="bi bi-envelope" aria-hidden="true"></i>
                            <input
                                class="hf-input"
                                id="registerEmail"
                                name="email"
                                type="email"
                                autocomplete="email"
                                placeholder="you@club.ph"
                                value="{{ old('email') }}"
                                data-testid="register-email"
                                required
                                @error('email') aria-invalid="true" aria-describedby="registerEmail-error" @enderror
                            >
                        </div>
                        @error('email')
                            <div class="hf-field-error" id="registerEmail-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="hf-field hf-field--full">
                        <label class="hf-label" for="registerPassword">Password</label>
                        <div class="hf-auth-control">
                            <i class="bi bi-shield-lock" aria-hidden="true"></i>
                            <input
                                class="hf-input"
                                id="registerPassword"
                                name="password"
                                type="password"
                                x-bind:type="showPassword ? 'text' : 'password'"
                                x-model="password"
                                x-init="$el.value && (password = $el.value)"
                                x-on:focus="focused = true"
                                x-on:blur="focused = false"
                                autocomplete="new-password"
                                data-testid="register-password"
                                required
                                @error('password') aria-invalid="true" aria-describedby="registerPassword-error" @enderror
                            >
                            <button
                                type="button"
                                class="hf-auth-eye"
                                x-on:click="toggle()"
                                x-bind:aria-pressed="showPassword ? 'true' : 'false'"
                                x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'"
                                aria-label="Show password"
                                data-testid="register-password-toggle"
                            >
                                <i class="bi" x-bind:class="showPassword ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
                            </button>
                        </div>
                        @error('password')
                            <div class="hf-field-error" id="registerPassword-error">{{ $message }}</div>
                        @enderror

                        <x-password-meter />
                    </div>

                    <fieldset class="hf-field hf-field--full hf-auth-roles" data-testid="register-role">
                        <legend class="hf-label">Role</legend>
                        <div class="hf-auth-role-grid">
                            <label class="hf-auth-role">
                                <input type="radio" id="registerRole" name="role" value="coach" @checked(old('role', 'coach') === 'coach') required @error('role') aria-invalid="true" aria-describedby="registerRole-error" @enderror>
                                <span class="hf-auth-role__body">
                                    <i class="bi bi-person-badge" aria-hidden="true"></i>
                                    <strong>Coach</strong>
                                    <small>Run a team</small>
                                </span>
                            </label>
                            <label class="hf-auth-role">
                                <input type="radio" name="role" value="fan" @checked(old('role') === 'fan')>
                                <span class="hf-auth-role__body">
                                    <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
                                    <strong>Fan</strong>
                                    <small>Follow the run</small>
                                </span>
                            </label>
                        </div>
                        @error('role')
                            <div class="hf-field-error" id="registerRole-error">{{ $message }}</div>
                        @enderror
                    </fieldset>

                    <div class="hf-field hf-field--full">
                        <label class="hf-label" for="registerOrganization">Club or organization</label>
                        <div class="hf-auth-control">
                            <i class="bi bi-buildings" aria-hidden="true"></i>
                            <input
                                class="hf-input"
                                id="registerOrganization"
                                name="organization"
                                placeholder="Barangay San Antonio Youth Club"
                                value="{{ old('organization') }}"
                                data-testid="register-organization"
                                @error('organization') aria-invalid="true" aria-describedby="registerOrganization-error" @enderror
                            >
                        </div>
                        @error('organization')
                            <div class="hf-field-error" id="registerOrganization-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <button
                    type="submit"
                    class="hf-btn hf-btn--primary hf-full hf-mt-3 hf-auth-submit"
                    data-testid="register-submit"
                    x-bind:class="{ 'is-loading': submitting }"
                    x-bind:disabled="submitting"
                >
                    <span>Create account</span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    <span class="hf-auth-submit__shine" aria-hidden="true"></span>
                </button>
            </form>

            <p class="hf-auth-switch">
                Already have an account? <a href="{{ route('login') }}">Sign in</a>.
            </p>
        </div>
    </div>
@endsection
