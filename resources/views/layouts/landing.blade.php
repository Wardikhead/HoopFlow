<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="HoopFlow keeps registration, eligibility, brackets, schedules, and standings in one place for barangay and grassroots youth basketball.">

    <link rel="icon" type="image/png" href="{{ asset('img/hoopflow icon without background.png') }}">

    <script>
        (function () {
            try {
                var t = localStorage.getItem('hf-theme');
                var next = (t === 'dark' || t === 'light') ? t : 'light';
                document.documentElement.setAttribute('data-theme', next);
                document.documentElement.style.colorScheme = next;
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
            document.documentElement.classList.add('hf-js');
        })();
    </script>

    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
    @vite(['resources/css/landing.scss', 'resources/js/app.js', 'resources/js/landing.js'])
</head>
<body class="hf-lp">
    <a class="visually-hidden-focusable" href="#main">Skip to content</a>

    <header class="hf-lp-header" data-landing-header>
        <div class="hf-lp-shell hf-lp-header__inner">
            <a class="hf-lp-header__brand" href="{{ route('home') }}" aria-label="HoopFlow home">
                <x-brand size="sm" />
            </a>

            <nav class="hf-lp-nav" aria-label="Landing">
                <a href="#features">Features</a>
                <a href="#flow">Matchday</a>
                <a href="#roles">Roles</a>
                <a href="#eligibility">Eligibility</a>
            </nav>

            <div class="hf-lp-header__actions">
                <button type="button" class="hf-icon-btn" data-theme-toggle aria-label="Toggle color theme">
                    <i class="bi bi-moon-stars" aria-hidden="true"></i>
                </button>
                @auth
                    <a class="hf-btn hf-btn--primary hf-btn--sm" href="{{ route('dashboard') }}">Dashboard</a>
                @else
                    <a class="hf-btn hf-btn--ghost hf-btn--sm hf-lp-hide-sm" href="{{ route('login') }}">Sign in</a>
                    <a class="hf-btn hf-btn--primary hf-btn--sm" href="{{ route('register') }}">Create account</a>
                @endauth
                <button type="button" class="hf-lp-menu-btn" data-landing-menu aria-expanded="false" aria-controls="landing-menu" aria-label="Open menu">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="hf-lp-mobile" id="landing-menu" hidden>
            <nav class="hf-lp-mobile__nav" aria-label="Mobile landing">
                <a href="#features">Features</a>
                <a href="#flow">Matchday</a>
                <a href="#roles">Roles</a>
                <a href="#eligibility">Eligibility</a>
            </nav>
            <div class="hf-lp-mobile__actions">
                @auth
                    <a class="hf-btn hf-btn--primary" href="{{ route('dashboard') }}">Dashboard</a>
                @else
                    <a class="hf-btn hf-btn--ghost" href="{{ route('login') }}">Sign in</a>
                    <a class="hf-btn hf-btn--primary" href="{{ route('register') }}">Create account</a>
                @endauth
            </div>
        </div>
    </header>

    <main id="main" tabindex="-1">
        @yield('content')
    </main>

    <footer class="hf-lp-footer">
        <div class="hf-lp-shell hf-lp-footer__inner">
            <div class="hf-lp-footer__brand">
                <x-brand size="md" />
                <p class="hf-lp-footer__tagline">Let the games run themselves.</p>
                <p class="hf-lp-footer__note">Built for barangay and grassroots youth basketball.</p>
            </div>

            <div class="hf-lp-footer__columns">
                <div class="hf-lp-footer__col">
                    <strong>Product</strong>
                    <a href="#features">Features</a>
                    <a href="#features" data-demo-target="bracket">Bracket</a>
                    <a href="#features" data-demo-target="schedule">Schedule</a>
                    <a href="#features" data-demo-target="standings">Standings</a>
                </div>
                <div class="hf-lp-footer__col">
                    <strong>Account</strong>
                    @guest
                        <a href="{{ route('login') }}">Sign in</a>
                        <a href="{{ route('register') }}">Create account</a>
                    @endguest
                    @auth
                        <a href="{{ route('dashboard') }}">Dashboard</a>
                        <a href="{{ route('profile') }}">Profile</a>
                    @endauth
                </div>
                <div class="hf-lp-footer__col">
                    <strong>Matchday</strong>
                    <a href="#flow">How it flows</a>
                    <a href="#roles">Roles</a>
                    <a href="#eligibility">Eligibility</a>
                </div>
            </div>
        </div>

        <div class="hf-lp-shell hf-lp-footer__bottom">
            <span>© 2026 HoopFlow</span>
            <a href="#main">Back to top <i class="bi bi-arrow-up" aria-hidden="true"></i></a>
        </div>
    </footer>
</body>
</html>
