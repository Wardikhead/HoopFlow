<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $title ?? config('app.name'))</title>

    @hasSection('meta-description')
        <meta name="description" content="@yield('meta-description')">
    @endif

    <link rel="icon" type="image/png" href="{{ asset('img/hoopflow icon without background.png') }}">

    <script>
        (function () {
            try {
                var theme = localStorage.getItem('hf-theme') || 'system';
                var dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
                document.documentElement.dataset.themePreference = theme;
                document.documentElement.dataset.density = localStorage.getItem('hf-density') || 'comfortable';
                document.documentElement.dataset.motion = localStorage.getItem('hf-motion') || 'system';
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }

            try {
                var nav = localStorage.getItem('hf-nav');
                if (nav !== 'expanded' && nav !== 'collapsed') {
                    nav = window.matchMedia('(min-width: 1200px)').matches ? 'expanded' : 'collapsed';
                }
                document.documentElement.dataset.nav = nav;
            } catch (e) {
                document.documentElement.dataset.nav = window.matchMedia('(min-width: 1200px)').matches ? 'expanded' : 'collapsed';
            }
        })();
    </script>

    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="@yield('body-class')">
    <div class="hf-progress" data-hf-progress aria-hidden="true"><span></span></div>

    <x-page-loader />

    <div class="hf-offline" data-hf-offline role="status" hidden>
        <i class="bi bi-wifi-off" aria-hidden="true"></i>
        You're offline. Changes won't save until you reconnect.
    </div>

    @php
        $flashToast = session('announcement_toast');
    @endphp

    <div class="hf-toast-region"
         aria-live="polite"
         aria-atomic="false"
         x-data="hfToasts"
         @hf-toast.window="push($event.detail)"
         @if ($flashToast) data-flash-toast="{{ json_encode($flashToast) }}" @endif>
        <template x-for="toast in toasts" :key="toast.id">
            <div class="hf-toast"
                 :class="`hf-toast--${toast.tone}${toast.paused ? ' is-paused' : ''}`"
                 :role="toast.tone === 'danger' ? 'alert' : 'status'"
                 @mouseenter="pause(toast.id)"
                 @mouseleave="resume(toast.id)"
                 @focusin="pause(toast.id)"
                 @focusout="resume(toast.id)">
                <i class="bi"
                   :class="toast.tone === 'danger' ? 'bi-x-circle-fill' : toast.tone === 'warning' ? 'bi-exclamation-triangle-fill' : toast.tone === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill'"
                   aria-hidden="true"></i>
                <div>
                    <strong x-show="toast.title" x-text="toast.title"></strong>
                    <p x-text="toast.message"></p>
                    <a x-show="toast.actionLabel" class="hf-toast__action" :href="toast.actionHref" x-text="toast.actionLabel"></a>
                </div>
                <button type="button" aria-label="Dismiss notification" data-testid="toast-dismiss" @click="dismiss(toast.id)">&times;</button>
                <span class="hf-toast__timer" :style="`animation-duration:${toast.duration}ms`" aria-hidden="true"></span>
            </div>
        </template>
    </div>

    <a class="visually-hidden-focusable" href="#main">Skip to content</a>
    <p class="hf-sr-only" role="status" aria-live="polite" aria-atomic="true" data-hf-page-announcer></p>

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
            $activeTournament = \App\Models\Tournament::whereNull('archived_at')->where('status', 'active')->first()
                ?? \App\Models\Tournament::whereNull('archived_at')->first();

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

            $navGroups = [
                ['label' => 'Matchday', 'keys' => $matchdayKeys],
                ['label' => 'Workspace', 'keys' => $roleTools],
            ];

            $activeKeys = collect(array_merge($matchdayKeys, $roleTools))
                ->filter(fn (string $key): bool => $isActive($key))
                ->values()
                ->all();

            $initials = collect(preg_split('/\s+/', $authUser->name))
                ->map(fn ($part) => mb_substr($part, 0, 1))
                ->slice(0, 2)
                ->implode('');
        @endphp

        <div class="hf-app">
            <aside class="hf-rail" id="hf-rail" aria-label="Primary navigation">
                <a class="hf-rail-brand" href="{{ route('dashboard') }}" aria-label="HoopFlow dashboard">
                    <x-brand size="md" on-dark tagline="Tournament ops" />
                </a>

                <x-app-nav variant="rail" :groups="$navGroups" :items="$navItems" :active-keys="$activeKeys" />

                <div class="hf-rail-footer">
                    <x-user-menu />
                </div>
            </aside>

            <header class="hf-topbar">
                <button type="button"
                        class="hf-icon-btn hf-nav-toggle"
                        data-nav-toggle
                        aria-controls="hf-rail"
                        aria-expanded="true"
                        aria-label="Collapse navigation">
                    <i class="bi bi-chevron-double-left" aria-hidden="true"></i>
                </button>
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
                    <a class="hf-nav-tab{{ $isActive($tab['key']) ? ' is-active' : '' }}"
                       href="{{ route($tab['route']) }}"
                       data-testid="mobile-nav-{{ $tab['key'] }}"
                       @if ($isActive($tab['key'])) aria-current="page" @endif>
                        <i class="bi bi-{{ $tab['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $tab['label'] }}</span>
                    </a>
                @endforeach
                <button type="button" class="hf-nav-tab" data-more-sheet-open aria-haspopup="dialog" aria-controls="hf-more-sheet" data-testid="mobile-nav-more">
                    <i class="bi bi-grid" aria-hidden="true"></i>
                    <span>More</span>
                </button>
            </nav>

            <dialog id="hf-more-sheet" class="hf-sheet" data-more-sheet aria-labelledby="hf-more-title">
                <div class="hf-sheet__handle" aria-hidden="true"></div>
                <div class="hf-sheet__head">
                    <h2 class="hf-dialog__title" id="hf-more-title">More</h2>
                    <button type="button" class="hf-icon-btn" data-more-sheet-close aria-label="Close menu">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="hf-sheet__body">
                    <details class="hf-user-menu hf-sheet__account" open>
                        <summary class="hf-user-chip">
                            <span class="hf-sr-only">Account menu for</span>
                            <span class="hf-avatar">{{ $initials }}</span>
                            <span class="hf-user-meta">
                                <strong>{{ $authUser->name }}</strong>
                                <span>{{ $role }}</span>
                            </span>
                        </summary>
                        <div class="hf-user-menu__panel">
                            <a href="{{ route('profile') }}"><i class="bi bi-person" aria-hidden="true"></i> Profile</a>
                            <a href="{{ route('profile') }}#appearance"><i class="bi bi-palette" aria-hidden="true"></i> Appearance</a>
                            <a href="{{ route('profile') }}#shortcuts"><i class="bi bi-keyboard" aria-hidden="true"></i> Keyboard shortcuts</a>
                            <form method="POST" action="{{ route('logout') }}" data-hf-logout-form>
                                @csrf
                                <button type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Log out</button>
                            </form>
                        </div>
                    </details>

                    <x-app-nav variant="sheet" :groups="$navGroups" :items="$navItems" :active-keys="$activeKeys" />

                    <div class="hf-sheet__group">
                        <span class="hf-rail-label">Account</span>
                        <a href="{{ route('profile') }}" data-more-link="profile"><i class="bi bi-person" aria-hidden="true"></i> Profile</a>
                    </div>
                </div>
            </dialog>

            <main id="main" class="hf-main" tabindex="-1">
                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>

        <x-logout-dialog />
    @endauth

    @livewireScripts
</body>
</html>
