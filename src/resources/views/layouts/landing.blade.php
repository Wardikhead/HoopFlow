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
</body>
</html>
