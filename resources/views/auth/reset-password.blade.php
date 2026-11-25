{{-- Blade view for Reset password. --}}
@extends('layouts.public')

@php($resetExpiry = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60))

@section('title', 'Reset password')
@section('meta-description', 'Choose a new password for your HoopFlow account.')
@section('body-class', 'hf-auth')
@section('main-class', 'hf-auth-main')

@section('content')
    <div class="hf-auth-shell">
        <aside class="hf-auth-aside" aria-hidden="true">
            <x-brand size="lg" on-dark tagline="Tournament ops" />
            <p class="hf-eyebrow hf-eyebrow--on-dark">Account recovery</p>
            <h2 class="hf-auth-aside__title">One new password, then back to the bracket.</h2>
            <ul class="hf-auth-points">
                <li><i class="bi bi-shield-check" aria-hidden="true"></i> Links expire after {{ $resetExpiry }} minutes</li>
                <li><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Every other session is signed out</li>
                <li><i class="bi bi-envelope-check" aria-hidden="true"></i> We email a confirmation when it is done</li>
            </ul>
        </aside>

        <div class="hf-auth-card" data-testid="reset-password-card" x-data="hfPasswordMeter">
            <span class="hf-eyebrow">Account recovery</span>
            <h1 class="hf-auth-title">New password</h1>
            <p class="hf-auth-sub">Choose a strong password. Resetting signs out every other session.</p>

            <form method="POST" action="{{ route('password.update') }}" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ old('token', $token) }}">

                <x-form-errors :targets="[
                    'email' => 'resetEmail',
                    'password' => 'resetPassword',
                    'token' => 'resetEmail',
                ]" />
                <x-required-note class="hf-mb-2" />

                <div class="hf-field">
                    <label class="hf-label" for="resetEmail">Email</label>
                    <input
                        class="hf-input"
                        id="resetEmail"
                        name="email"
                        type="email"
                        autocomplete="email"
                        value="{{ old('email', $email) }}"
                        data-testid="reset-password-email"
                        required
                        @error('email') aria-invalid="true" aria-describedby="resetEmail-error" @enderror
                    >
                    @error('email')
                        <div class="hf-field-error" id="resetEmail-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="hf-field hf-mt-2">
                    <label class="hf-label" for="resetPassword">New password</label>
                    <div class="hf-inline-form">
                        <input
                            class="hf-input"
                            id="resetPassword"
                            name="password"
                            type="password"
                            x-bind:type="showPassword ? 'text' : 'password'"
                            x-model="password"
                            x-init="$el.value && (password = $el.value)"
                            x-on:focus="focused = true"
                            x-on:blur="focused = false"
                            autocomplete="new-password"
                            data-testid="reset-password-password"
                            required
                            @error('password') aria-invalid="true" aria-describedby="resetPassword-error" @enderror
                        >
                        <button
                            type="button"
                            class="hf-btn hf-btn--sm"
                            x-on:click="toggle()"
                            x-bind:aria-pressed="showPassword ? 'true' : 'false'"
                            x-text="showPassword ? 'Hide' : 'Show'"
                            data-testid="reset-password-password-toggle"
                        >Show</button>
                    </div>
                    @error('password')
                        <div class="hf-field-error" id="resetPassword-error">{{ $message }}</div>
                    @enderror
                    <x-password-meter />
                </div>

                <div class="hf-field hf-mt-2" x-data="{ showConfirmation: false }">
                    <label class="hf-label" for="resetPasswordConfirmation">Confirm new password</label>
                    <div class="hf-inline-form">
                        <input
                            class="hf-input"
                            id="resetPasswordConfirmation"
                            name="password_confirmation"
                            type="password"
                            x-bind:type="showConfirmation ? 'text' : 'password'"
                            autocomplete="new-password"
                            data-testid="reset-password-confirmation"
                            required
                        >
                        <button
                            type="button"
                            class="hf-btn hf-btn--sm"
                            x-on:click="showConfirmation = ! showConfirmation"
                            x-bind:aria-pressed="showConfirmation ? 'true' : 'false'"
                            x-text="showConfirmation ? 'Hide' : 'Show'"
                            data-testid="reset-password-confirmation-toggle"
                        >Show</button>
                    </div>
                </div>

                <button type="submit" class="hf-btn hf-btn--primary hf-full hf-mt-3" data-testid="reset-password-submit">
                    Reset password
                </button>
            </form>

            <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
                Link expired? <a href="{{ route('password.request') }}">Request a new one</a>.
            </p>
        </div>
    </div>
@endsection
