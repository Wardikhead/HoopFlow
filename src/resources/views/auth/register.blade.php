@extends('layouts.public')

@section('title', 'Create account')
@section('meta-description', 'Create a HoopFlow account to register a team, build a roster, and follow the tournament from registration to the final buzzer.')
@section('body-class', 'hf-auth')
@section('main-class', 'hf-auth-main')

@section('content')
    <div class="hf-auth-shell">
        <aside class="hf-auth-aside" aria-hidden="true">
            <x-brand size="lg" on-dark tagline="Tournament ops" />
            <p class="hf-eyebrow hf-eyebrow--on-dark">Your season starts here</p>
            <h2 class="hf-auth-aside__title">Register the team, then let the bracket do the talking.</h2>
            <ul class="hf-auth-points">
                <li><i class="bi bi-people" aria-hidden="true"></i> Build a roster with eligibility in view</li>
                <li><i class="bi bi-credit-card" aria-hidden="true"></i> Submit one payment reference per entry</li>
                <li><i class="bi bi-bell" aria-hidden="true"></i> Follow approval and schedule updates</li>
            </ul>
        </aside>

        <div class="hf-auth-card" data-testid="register-card" x-data="hfPasswordMeter">
            <span class="hf-eyebrow">New account</span>
            <h1 class="hf-auth-title">Create account</h1>
            <p class="hf-auth-sub">Create your HoopFlow account to register teams and follow tournaments.</p>

            <form id="registerForm" method="POST" action="{{ route('register.store') }}" novalidate>
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
                        @error('name')
                            <div class="hf-field-error" id="registerName-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="hf-field hf-field--full">
                        <label class="hf-label" for="registerEmail">Email</label>
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
                        @error('email')
                            <div class="hf-field-error" id="registerEmail-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="hf-field hf-field--full">
                        <label class="hf-label" for="registerPassword">Password</label>
                        <div class="hf-inline-form">
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
                                class="hf-btn hf-btn--sm"
                                x-on:click="toggle()"
                                x-bind:aria-pressed="showPassword ? 'true' : 'false'"
                                x-text="showPassword ? 'Hide' : 'Show'"
                                data-testid="register-password-toggle"
                            >Show</button>
                        </div>
                        @error('password')
                            <div class="hf-field-error" id="registerPassword-error">{{ $message }}</div>
                        @enderror

                        <x-password-meter />
                    </div>

                    <div class="hf-field hf-field--full">
                        <label class="hf-label" for="registerRole">Role</label>
                        <select
                            class="hf-select"
                            id="registerRole"
                            name="role"
                            data-testid="register-role"
                            required
                            @error('role') aria-invalid="true" aria-describedby="registerRole-error" @enderror
                        >
                            <option value="coach" @selected(old('role', 'coach') === 'coach')>Coach</option>
                            <option value="fan" @selected(old('role') === 'fan')>Fan</option>
                        </select>
                        @error('role')
                            <div class="hf-field-error" id="registerRole-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="hf-field hf-field--full">
                        <label class="hf-label" for="registerOrganization">Club or organization</label>
                        <input
                            class="hf-input"
                            id="registerOrganization"
                            name="organization"
                            placeholder="Barangay San Antonio Youth Club"
                            value="{{ old('organization') }}"
                            data-testid="register-organization"
                            @error('organization') aria-invalid="true" aria-describedby="registerOrganization-error" @enderror
                        >
                        @error('organization')
                            <div class="hf-field-error" id="registerOrganization-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <button type="submit" class="hf-btn hf-btn--primary hf-full hf-mt-3" data-testid="register-submit">
                    Create account
                </button>
            </form>

            <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
                Already have an account? <a href="{{ route('login') }}">Sign in</a>.
            </p>
        </div>
    </div>
@endsection
