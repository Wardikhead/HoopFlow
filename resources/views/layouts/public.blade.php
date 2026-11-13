<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $title ?? config('app.name'))</title>

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
        })();
    </script>

    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="@yield('body-class')">
    <a class="visually-hidden-focusable" href="#main">Skip to content</a>

    @guest
        <header class="hf-auth-topbar">
            <a class="hf-auth-brand" href="{{ route('home') }}">
                <x-brand size="sm" />
            </a>
            <a class="hf-btn hf-btn--ghost hf-btn--sm" href="{{ route('login') }}">Sign in</a>
        </header>

        <main id="main" class="@yield('main-class')" tabindex="-1">
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    @endguest

    @auth
        @php
            $authUser = auth()->user();
            $role = $authUser->hasRole('organizer') ? 'organizer' : ($authUser->hasRole('coach') ? 'coach' : 'fan');
            $activeTournament = \App\Models\Tournament::where('status', 'active')->first() ?? \App\Models\Tournament::first();

            $navItems = [
                'dashboard' => ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'dashboard'],
                'tournaments' => ['label' => 'Tournaments', 'icon' => 'trophy', 'route' => 'tournaments'],
                'teams' => ['label' => 'Teams', 'icon' => 'people', 'route' => 'teams'],
                'bracket' => ['label' => 'Bracket', 'icon' => 'diagram-3', 'route' => 'bracket'],
                'schedule' => ['label' => 'Schedule', 'icon' => 'calendar3', 'route' => 'schedule'],
                'standings' => ['label' => 'Standings', 'icon' => 'bar-chart', 'route' => 'standings'],
                'announcements' => ['label' => 'Announcements', 'icon' => 'megaphone', 'route' => 'announcements'],
                'submissions' => ['label' => 'Submissions', 'icon' => 'clipboard-check', 'route' => 'submissions'],
                'payments' => ['label' => 'Payments', 'icon' => 'credit-card', 'route' => 'payments'],
                'audit' => ['label' => 'Audit log', 'icon' => 'clock-history', 'route' => 'audit'],
                'myTeam' => ['label' => 'My team', 'icon' => 'person-lines-fill', 'route' => 'myTeam'],
                'teamRegister' => ['label' => 'Register team', 'icon' => 'plus-circle', 'route' => 'teamRegister'],
                'gameStats' => ['label' => 'Game stats', 'icon' => 'graph-up', 'route' => 'gameStats'],
                'gameManage' => ['label' => 'Game scoring', 'icon' => 'clipboard-pulse', 'route' => 'gameManage'],
                'createTournament' => ['label' => 'Create tournament', 'icon' => 'plus-lg', 'route' => 'tournamentCreate'],
                'profile' => ['label' => 'Profile', 'icon' => 'person', 'route' => 'profile'],
            ];

            $matchdayKeys = ['dashboard', 'tournaments', 'teams', 'bracket', 'schedule', 'standings', 'announcements'];

            $roleTools = match ($role) {
                'organizer' => ['submissions', 'payments', 'audit', 'createTournament', 'gameStats', 'gameManage'],
                'coach' => ['myTeam', 'teamRegister'],
                default => [],
            };

            $roleTabs = [
                ['key' => 'dashboard', 'label' => 'Home', 'icon' => 'house-door', 'route' => 'dashboard'],
                ['key' => 'bracket', 'label' => 'Bracket', 'icon' => 'diagram-3', 'route' => 'bracket'],
                ['key' => 'schedule', 'label' => 'Schedule', 'icon' => 'calendar3', 'route' => 'schedule'],
                ['key' => 'standings', 'label' => 'Standings', 'icon' => 'bar-chart', 'route' => 'standings'],
            ];
            $roleTabs[] = $role === 'coach'
                ? ['key' => 'myTeam', 'label' => 'My team', 'icon' => 'people', 'route' => 'myTeam']
                : ['key' => 'profile', 'label' => 'Profile', 'icon' => 'person', 'route' => 'profile'];

            $currentRoute = request()->route()?->getName();

            $groupedActive = [
                'tournaments' => ['tournaments', 'tournamentShow', 'tournament-create', 'tournamentCreate', 'tournament-show'],
                'teams' => ['teams', 'teamShow', 'team-show', 'teamRegister', 'team-register', 'myTeam', 'my-team'],
                'schedule' => ['schedule', 'matches', 'gameStats', 'game-stats', 'gameManage'],
                'announcements' => ['announcements', 'announcementCreate', 'announcement-create'],
                'profile' => ['profile', 'audit'],
            ];

            $isActive = function (string $key) use ($currentRoute, $groupedActive, $navItems): bool {
                if (isset($groupedActive[$key])) {
                    return in_array($currentRoute, $groupedActive[$key], true);
                }
                $routeName = $navItems[$key]['route'] ?? $key;
                return $currentRoute === $routeName;
            };
        @endphp

        <div class="hf-app">
            <aside class="hf-rail" aria-label="Primary navigation">
                <a class="hf-rail-brand" href="{{ route('dashboard') }}">
                    <x-brand size="md" on-dark tagline="Tournament ops" />
                </a>

                <span class="hf-rail-label">Matchday</span>
                @foreach ($matchdayKeys as $key)
                    @php $item = $navItems[$key]; @endphp
                    <a class="hf-rail-link{{ $isActive($key) ? ' is-active' : '' }}" href="{{ route($item['route']) }}" data-testid="nav-{{ $key }}">
                        <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach

                @if (count($roleTools))
                    <span class="hf-rail-label">Workspace</span>
                    @foreach ($roleTools as $key)
                        @php $item = $navItems[$key]; @endphp
                        <a class="hf-rail-link{{ $isActive($key) ? ' is-active' : '' }}" href="{{ route($item['route']) }}" data-testid="nav-{{ $key }}">
                            <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                @endif

                <div class="hf-rail-footer">
                    <x-user-menu />
                </div>
            </aside>

            <header class="hf-topbar">
                <div class="hf-topbar-context">
                    @hasSection('breadcrumbs')
                        @yield('breadcrumbs')
                    @else
                        <p class="hf-topbar-title">@yield('title', $title ?? 'HoopFlow')</p>
                        <div class="hf-topbar-sub">{{ $activeTournament?->name ?? 'No active tournament' }} · {{ $role }}</div>
                    @endif
                </div>
                <div class="hf-topbar-actions">
                    <button type="button" class="hf-icon-btn" data-theme-toggle aria-label="Toggle color theme" data-testid="theme-toggle">
                        <i class="bi bi-moon-stars" aria-hidden="true"></i>
                    </button>
                    <a class="hf-icon-btn" href="{{ route('announcements') }}" aria-label="Announcements" data-testid="topbar-announcements">
                        <i class="bi bi-bell" aria-hidden="true"></i>
                    </a>
                </div>
            </header>

            <nav class="hf-bottom-nav" aria-label="Mobile navigation">
                @foreach ($roleTabs as $tab)
                    <a class="hf-nav-tab{{ $isActive($tab['key']) ? ' is-active' : '' }}" href="{{ route($tab['route']) }}" data-testid="mobile-nav-{{ $tab['key'] }}">
                        <i class="bi bi-{{ $tab['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $tab['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <main id="main" class="hf-main">
                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    @endauth

    @livewireScripts
</body>
</html>
