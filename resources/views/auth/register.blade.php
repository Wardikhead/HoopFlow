@extends('layouts.public')

@section('title', 'Register')

@section('content')
    <div class="hf-auth-main" style="min-height: calc(100vh - 80px);">
        <div class="hf-auth-card" data-testid="register-card">
            <span class="hf-eyebrow">New account</span>
            <h1 class="hf-auth-title">Create account</h1>
            <p class="hf-auth-sub">Prototype registration creates a local demo session only.</p>

            <form id="registerForm" method="GET" action="{{ route('login') }}" novalidate>
                <div class="hf-form-grid">
                    <div class="hf-field hf-field--full">
                        <label class="hf-label" for="registerName">Full name</label>
                        <input class="hf-input" id="registerName" name="name" autocomplete="name" placeholder="Juan Dela Cruz" data-testid="register-name">
                    </div>
                    <div class="hf-field hf-field--full">
                        <label class="hf-label" for="registerEmail">Email</label>
                        <input class="hf-input" id="registerEmail" name="email" type="email" autocomplete="email" placeholder="you@club.ph" data-testid="register-email">
                    </div>
                    <div class="hf-field">
                        <label class="hf-label" for="registerPassword">Password</label>
                        <input class="hf-input" id="registerPassword" name="password" type="password" autocomplete="new-password" data-testid="register-password">
                    </div>
                    <div class="hf-field">
                        <label class="hf-label" for="registerRole">Role</label>
                        <select class="hf-select" id="registerRole" name="role" data-testid="register-role">
                            <option value="coach">Coach</option>
                            <option value="fan">Fan</option>
                        </select>
                    </div>
                    <div class="hf-field hf-field--full">
                        <label class="hf-label" for="registerOrganization">Club or organization</label>
                        <input class="hf-input" id="registerOrganization" name="organization" placeholder="Barangay San Antonio Youth Club" data-testid="register-organization">
                    </div>
                </div>
                <button type="submit" class="hf-btn hf-btn--primary hf-full hf-mt-3" data-testid="register-submit">
                    Create demo account
                </button>
            </form>

            <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
                Already have an account? <a href="{{ route('login') }}">Sign in</a>.
            </p>
        </div>
    </div>
@endsection