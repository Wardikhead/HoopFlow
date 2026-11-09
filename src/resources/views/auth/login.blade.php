@extends('layouts.public')

@section('title', 'Login')

@section('content')
    <div class="hf-auth-main" style="min-height: calc(100vh - 80px);">
        <div class="hf-auth-card">
            <span class="hf-eyebrow">Welcome back</span>
            <h1 class="hf-auth-title">Sign in</h1>
            <p class="hf-auth-sub">Demo accounts only. Password hint: <code>password</code>.</p>

            @if ($errors->any())
                <div class="hf-card hf-card--flat hf-mb-3" role="alert">
                    @foreach ($errors->all() as $error)
                        <div class="hf-small" style="color: var(--hf-danger);">{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="hf-field">
                    <label class="hf-label" for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" class="hf-input" required autofocus autocomplete="username" data-testid="login-email">
                    @error('email')
                        <div class="hf-small hf-mt-1" style="color: var(--hf-danger);" data-testid="login-email-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="hf-field hf-mt-2">
                    <label class="hf-label" for="password">Password</label>
                    <input id="password" type="password" name="password" class="hf-input" required autocomplete="current-password" data-testid="login-password">
                    @error('password')
                        <div class="hf-small hf-mt-1" style="color: var(--hf-danger);">{{ $message }}</div>
                    @enderror
                </div>

                <div class="hf-flex hf-items-center hf-gap-2 hf-mt-2">
                    <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <label for="remember" class="hf-small">Remember me</label>
                </div>

                <div class="hf-btn-row hf-mt-3">
                    <button type="submit" class="hf-btn hf-btn--primary" data-testid="login-submit">Sign in</button>
                </div>
            </form>

            <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
                New here? <a href="{{ route('register') }}">Create an account</a>.
            </p>

            <p class="hf-small hf-muted fw-bold mt-4 mb-2">Quick sign-in (demo accounts)</p>
            <div class="hf-quick-accounts">
                <form method="POST" action="{{ route('login') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="email" value="organizer@hoopflow.ph">
                    <input type="hidden" name="password" value="password">
                    <button type="submit" class="hf-btn hf-btn--sm" data-testid="quick-login-organizer">Organizer</button>
                </form>
                <form method="POST" action="{{ route('login') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="email" value="coach.marcus@hoopflow.ph">
                    <input type="hidden" name="password" value="password">
                    <button type="submit" class="hf-btn hf-btn--sm" data-testid="quick-login-coach">Coach</button>
                </form>
                <form method="POST" action="{{ route('login') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="email" value="fan.juan@hoopflow.ph">
                    <input type="hidden" name="password" value="password">
                    <button type="submit" class="hf-btn hf-btn--sm" data-testid="quick-login-fan">Fan</button>
                </form>
            </div>
        </div>
    </div>
@endsection