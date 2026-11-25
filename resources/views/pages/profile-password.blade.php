{{-- Blade view for Profile password. --}}
@extends('layouts.public')

@section('title', 'Change password')

@section('content')
@php
    $user = auth()->user();
@endphp

<header class="hf-page-head hf-mb-3">
    <div>
        <span class="hf-eyebrow">Account</span>
        <h1 class="hf-page-title">Change password</h1>
        <p class="hf-page-desc">Update your password. Other signed-in sessions will be signed out.</p>
    </div>
</header>

<section class="hf-card" data-testid="profile-password-form">
    @if (session('status'))
        <p class="hf-small hf-chip hf-chip--success hf-mb-2" role="status">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('profile.password.update') }}">
        @csrf
        @method('PUT')
        <div class="hf-field">
            <label class="hf-label" for="profileCurrentPassword">Current password</label>
            <input class="hf-input" id="profileCurrentPassword" type="password" name="current_password" autocomplete="current-password" required data-testid="profile-current-password">
            @error('current_password')
                <p class="hf-field-error" role="alert">{{ $message }}</p>
            @enderror
        </div>
        <div class="hf-field">
            <label class="hf-label" for="profileNewPassword">New password</label>
            <input class="hf-input" id="profileNewPassword" type="password" name="password" autocomplete="new-password" required minlength="8" data-testid="profile-new-password">
            @error('password')
                <p class="hf-field-error" role="alert">{{ $message }}</p>
            @enderror
        </div>
        <div class="hf-field">
            <label class="hf-label" for="profileConfirmPassword">Confirm new password</label>
            <input class="hf-input" id="profileConfirmPassword" type="password" name="password_confirmation" autocomplete="new-password" required data-testid="profile-confirm-password">
        </div>
        <div class="hf-btn-row hf-mt-2">
            <button type="submit" class="hf-btn hf-btn--primary" data-testid="profile-change-password-submit">
                <i class="bi bi-key" aria-hidden="true"></i> Change password
            </button>
            <a class="hf-btn" href="{{ route('profile') }}" data-testid="profile-change-password-cancel">Cancel</a>
        </div>
    </form>
</section>
@endsection