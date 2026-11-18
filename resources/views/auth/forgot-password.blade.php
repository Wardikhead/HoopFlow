@extends('layouts.public')

@php($resetExpiry = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60))

@section('title', 'Forgot password')
@section('meta-description', 'Request a secure HoopFlow password reset link for your account.')
@section('body-class', 'hf-auth')
@section('main-class', 'hf-auth-main')

@section('content')
    <div class="hf-auth-shell">
        <aside class="hf-auth-aside" aria-hidden="true">
            <x-brand size="lg" on-dark tagline="Tournament ops" />
            <p class="hf-eyebrow hf-eyebrow--on-dark">Account recovery</p>
            <h2 class="hf-auth-aside__title">Get back to the sideline without missing a tip-off.</h2>
            <ul class="hf-auth-points">
                <li><i class="bi bi-shield-lock" aria-hidden="true"></i> Reset links expire after {{ $resetExpiry }} minutes</li>
                <li><i class="bi bi-envelope-check" aria-hidden="true"></i> We only send to the address on your account</li>
                <li><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Signing back in takes one more step</li>
            </ul>
        </aside>

        <div class="hf-auth-card" data-testid="forgot-password-card">
            <span class="hf-eyebrow">Account recovery</span>
            <h1 class="hf-auth-title">Forgot password</h1>
            <p class="hf-auth-sub">Enter your account email and we'll send a secure reset link.</p>

            @if (session('status'))
                <x-callout tone="success" class="hf-mb-2">{{ session('status') }}</x-callout>
            @endif

            <form method="POST" action="{{ route('password.email') }}" novalidate>
                @csrf

                <x-form-errors :targets="['email' => 'forgotEmail']" />
                <x-required-note class="hf-mb-2" />

                <div class="hf-field">
                    <label class="hf-label" for="forgotEmail">Email</label>
                    <input
                        class="hf-input"
                        id="forgotEmail"
                        name="email"
                        type="email"
                        autocomplete="email"
                        value="{{ old('email') }}"
                        data-testid="forgot-password-email"
                        required
                        @error('email') aria-invalid="true" aria-describedby="forgotEmail-error" @enderror
                    >
                    @error('email')
                        <div class="hf-field-error" id="forgotEmail-error">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="hf-btn hf-btn--primary hf-full hf-mt-3" data-testid="forgot-password-submit">
                    Send reset link
                </button>
            </form>

            <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
                Remembered it? <a href="{{ route('login') }}">Sign in</a>.
            </p>
        </div>
    </div>
@endsection
