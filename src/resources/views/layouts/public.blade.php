<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <script>
        (function () {
            try {
                var t = localStorage.getItem('hoopflow_theme');
                var next = (t === 'dark' || t === 'light') ? t : 'light';
                document.documentElement.setAttribute('data-theme', next);
                document.documentElement.style.colorScheme = next;
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    @vite(['resources/css/app.scss', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <a class="visually-hidden-focusable" href="#main">Skip to content</a>

    @guest
        <header class="hf-auth-topbar">
            <a class="hf-auth-brand" href="{{ route('home') }}">
                <img src="{{ asset('img/logo.png') }}" alt="">
                HoopFlow
            </a>
            @if (! request()->routeIs('login'))
                <a class="hf-btn hf-btn--ghost hf-btn--sm" href="{{ route('login') }}">Sign in</a>
            @endif
        </header>

        <main id="main">
            @yield('content')
        </main>
    @endguest

    @auth
        @php
            $authUser = auth()->user();
            $role = $authUser->hasRole('organizer') ? 'organizer' : ($authUser->hasRole('coach') ? 'coach' : 'fan');

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
                'createTournament' => ['label' => 'Create tournament', 'icon' => 'plus-lg', 'route' => 'tournamentCreate'],
                'profile' => ['label' => 'Profile', 'icon' => 'person', 'route' => 'profile'],
            ];

            $matchdayKeys = ['dashboard', 'tournaments', 'teams', 'bracket', 'schedule', 'standings', 'announcements'];

            $roleTools = match ($role) {
                'organizer' => ['submissions', 'payments', 'audit', 'createTournament', 'gameStats'],
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
                'schedule' => ['schedule', 'matches', 'gameStats', 'game-stats'],
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

            $initials = collect(preg_split('/\s+/', $authUser->name))
                ->map(fn ($part) => mb_substr($part, 0, 1))
                ->slice(0, 2)
                ->implode('');
        @endphp

        <div class="hf-app">
            <aside class="hf-rail" aria-label="Primary navigation">
                <a class="hf-rail-brand" href="{{ route('dashboard') }}">
                    <img src="{{ asset('img/logo.png') }}" alt="">
                    <span>
                        <strong>HoopFlow</strong>
                        <small>Tournament ops</small>
                    </span>
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
                    <div class="hf-demo-row" style="border: 0; color: var(--hf-rail-muted);">
                        <span>{{ $authUser->name }}</span>
                        <span style="text-transform: capitalize;">{{ $role }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="hf-btn hf-btn--sm hf-btn--danger w-100" data-testid="logout">Logout</button>
                    </form>
                </div>
            </aside>

            <header class="hf-topbar">
                <div class="hf-topbar-context">
                    <h1 class="hf-topbar-title">@yield('title', 'HoopFlow')</h1>
                    <div class="hf-topbar-sub">No active tournament · {{ $role }}</div>
                </div>
                <div class="hf-topbar-actions">
                    <button type="button" class="hf-icon-btn" data-theme-toggle aria-label="Toggle color theme" data-testid="theme-toggle">
                        <i class="bi bi-moon-stars" aria-hidden="true"></i>
                    </button>
                    <a class="hf-icon-btn" href="{{ route('announcements') }}" aria-label="Announcements" data-testid="topbar-announcements">
                        <i class="bi bi-bell" aria-hidden="true"></i>
                    </a>
                    <a class="hf-user-chip" href="{{ route('profile') }}" data-testid="topbar-profile">
                        <span class="hf-avatar">{{ $initials }}</span>
                        <span class="hf-user-meta">
                            <strong>{{ $authUser->name }}</strong>
                            <span>{{ $role }}</span>
                        </span>
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
                @yield('content')
            </main>
        </div>
    @endauth

    @livewireScripts
</body>
</html>
