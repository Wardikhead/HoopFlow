@extends('layouts.public')

@section('title', 'Login')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-6">
                <div class="hf-card p-4">
                    <h1 class="h4 fw-bold mb-1">Sign in</h1>
                    <p class="text-muted small mb-3">Demo accounts only. Password hint: <code>password</code>.</p>

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus autocomplete="username" data-testid="login-email">
                            @error('email')
                                <div class="text-danger small mt-1" data-testid="login-email-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input id="password" type="password" name="password" class="form-control" required autocomplete="current-password" data-testid="login-password">
                            @error('password')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3 form-check">
                            <input id="remember" type="checkbox" name="remember" value="1" class="form-check-input" @checked(old('remember'))>
                            <label for="remember" class="form-check-label">Remember me</label>
                        </div>

                        <button type="submit" class="btn btn-primary" data-testid="login-submit">Sign in</button>
                    </form>

                    <p class="text-muted small fw-bold mt-4 mb-2">Quick sign-in (demo accounts)</p>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('login') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="email" value="organizer@hoopflow.ph">
                            <input type="hidden" name="password" value="password">
                            <button type="submit" class="btn btn-outline-primary" data-testid="quick-login-organizer">Organizer</button>
                        </form>
                        <form method="POST" action="{{ route('login') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="email" value="coach.marcus@hoopflow.ph">
                            <input type="hidden" name="password" value="password">
                            <button type="submit" class="btn btn-outline-primary" data-testid="quick-login-coach">Coach</button>
                        </form>
                        <form method="POST" action="{{ route('login') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="email" value="fan.juan@hoopflow.ph">
                            <input type="hidden" name="password" value="password">
                            <button type="submit" class="btn btn-outline-primary" data-testid="quick-login-fan">Fan</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
