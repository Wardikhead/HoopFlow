# Navigation Count Badges and Collapsed-Rail Account Menu — Implementation Plan

- **Date:** 2026-11-18
- **Spec:** `docs/superpowers/specs/2026-11-18-navigation-badges-design.md`
- **Goal:** Render real, role-filtered counts on five navigation destinations and fix the collapsed-rail account menu so it paints above page content and accepts clicks.
- **Stack:** Laravel 13, PHP 8.4, Blade, Livewire 4, Pest, Bootstrap 5.3 SCSS, Vite.

> **For the implementing agent:** Work task by task, run the command after each step, and commit
> only the files named in that task. Never stage `.obsidian/` or `.kilo/`. All PHP, Vite, and
> Composer commands run from `src/`.

## Constraints

- No route, controller, policy, model, migration, or config changes.
- The service returns only positive counts; a zero count produces no badge markup.
- Keep every existing `data-testid`, label, icon, route, role filter, and active-state behavior.
- No polling, no read-state table, no JavaScript changes.
- Counts refresh on full load and SPA navigation because the layout is re-rendered.
- Do not edit existing test files; the new test file is additive.

## File map

| File | Change |
|---|---|
| `src/app/Services/NavigationBadgeService.php` | New role-aware count service. |
| `src/resources/views/components/nav-badge.blade.php` | New pill/dot component. |
| `src/resources/views/components/app-nav.blade.php` | Accept `badges`, render pills and screen-reader suffixes. |
| `src/resources/views/layouts/public.blade.php` | Compute counts once, pass `badge_sr`, badge mobile Schedule/More. |
| `src/resources/css/hf/_shell.scss` | Badge styles, compact dot, mobile tab placement, rail `z-index: 20`. |
| `src/tests/Feature/Accessibility/NavigationBadgesTest.php` | Service and rendering tests. |

---

## Task 1: Badge count service

### Step 1: Write the failing service tests

Create `src/tests/Feature/Accessibility/NavigationBadgesTest.php`:

```php
<?php

use App\Models\Announcement;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Services\NavigationBadgeService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);

    $this->badgeService = app(NavigationBadgeService::class);
    $this->organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->fan = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
});

/**
 * Seeded data is not deterministic, so every assertion is expressed as a delta
 * from the count before the fixture was created.
 */
function navBadgeCount(NavigationBadgeService $service, User $user, string $key): int
{
    return (int) ($service->forUser($user)[$key] ?? 0);
}

it('returns a count for every destination with work waiting for an organizer', function () {
    Announcement::factory()->create([
        'audience' => 'all',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    Tournament::factory()->create(['status' => 'registration-open']);

    $registration = Registration::factory()->create(['status' => 'submitted']);
    Payment::factory()->create([
        'registration_id' => $registration->id,
        'status' => 'pending',
    ]);

    $match = MatchModel::factory()->create();
    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'tournament_id' => $match->tournament_id,
        'start_at' => now()->setTime(12, 0),
        'end_at' => now()->setTime(13, 0),
    ]);

    $badges = $this->badgeService->forUser($this->organizer);

    expect($badges)->toHaveKeys([
        'announcements',
        'tournaments',
        'submissions',
        'payments',
        'schedule',
    ]);
});

it('gives coaches only the shared destinations', function () {
    Announcement::factory()->create([
        'audience' => 'coaches',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $badges = $this->badgeService->forUser($this->coach);

    expect($badges)->toHaveKey('announcements')
        ->not->toHaveKey('submissions')
        ->not->toHaveKey('payments');
});

it('gives fans only the shared destinations', function () {
    Announcement::factory()->create([
        'audience' => 'fans',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $badges = $this->badgeService->forUser($this->fan);

    expect($badges)->toHaveKey('announcements')
        ->not->toHaveKey('submissions')
        ->not->toHaveKey('payments');
});

it('omits zero counts from the result', function () {
    $badges = $this->badgeService->forUser($this->organizer);

    foreach ($badges as $count) {
        expect($count)->toBeGreaterThan(0);
    }
});

it('counts only published announcements from the last seven days', function () {
    $before = navBadgeCount($this->badgeService, $this->fan, 'announcements');

    Announcement::factory()->create([
        'audience' => 'fans',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    Announcement::factory()->create([
        'audience' => 'fans',
        'status' => 'published',
        'published_at' => now()->subDays(8),
    ]);
    Announcement::factory()->create([
        'audience' => 'fans',
        'status' => 'draft',
        'published_at' => now()->subDay(),
    ]);

    expect(navBadgeCount($this->badgeService, $this->fan, 'announcements'))
        ->toBe($before + 1);
});

it('applies the audience rules to announcements', function () {
    $organizerBefore = navBadgeCount($this->badgeService, $this->organizer, 'announcements');
    $fanBefore = navBadgeCount($this->badgeService, $this->fan, 'announcements');

    Announcement::factory()->create([
        'audience' => 'organizers',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    expect(navBadgeCount($this->badgeService, $this->organizer, 'announcements'))
        ->toBe($organizerBefore + 1);
    expect(navBadgeCount($this->badgeService, $this->fan, 'announcements'))
        ->toBe($fanBefore);
});

it('counts a specific announcement only for targeted users', function () {
    $coachBefore = navBadgeCount($this->badgeService, $this->coach, 'announcements');
    $fanBefore = navBadgeCount($this->badgeService, $this->fan, 'announcements');

    Announcement::factory()->create([
        'audience' => 'specific',
        'target_user_ids' => [$this->coach->id],
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    expect(navBadgeCount($this->badgeService, $this->coach, 'announcements'))
        ->toBe($coachBefore + 1);
    expect(navBadgeCount($this->badgeService, $this->fan, 'announcements'))
        ->toBe($fanBefore);
});

it('counts only pending submissions on non-archived tournaments', function () {
    $before = navBadgeCount($this->badgeService, $this->organizer, 'submissions');

    Registration::factory()->create(['status' => 'pending']);

    expect(navBadgeCount($this->badgeService, $this->organizer, 'submissions'))
        ->toBe($before + 1);

    $archived = Tournament::factory()->create(['archived_at' => now()]);
    Registration::factory()->create([
        'status' => 'submitted',
        'tournament_id' => $archived->id,
    ]);

    expect(navBadgeCount($this->badgeService, $this->organizer, 'submissions'))
        ->toBe($before + 1);
});

it('counts pending and partial payments but not verified or rejected ones', function () {
    $before = navBadgeCount($this->badgeService, $this->organizer, 'payments');

    $partial = Registration::factory()->create(['status' => 'approved']);
    Payment::factory()->create([
        'registration_id' => $partial->id,
        'status' => 'partial',
    ]);

    $pending = Registration::factory()->create(['status' => 'submitted']);
    Payment::factory()->create([
        'registration_id' => $pending->id,
        'status' => 'pending',
    ]);

    expect(navBadgeCount($this->badgeService, $this->organizer, 'payments'))
        ->toBe($before + 2);

    $verified = Registration::factory()->create(['status' => 'approved']);
    Payment::factory()->create([
        'registration_id' => $verified->id,
        'status' => 'verified',
    ]);

    $rejected = Registration::factory()->create(['status' => 'rejected']);
    Payment::factory()->create([
        'registration_id' => $rejected->id,
        'status' => 'pending',
    ]);

    expect(navBadgeCount($this->badgeService, $this->organizer, 'payments'))
        ->toBe($before + 2);
});

it('excludes pending payments on archived tournaments', function () {
    $before = navBadgeCount($this->badgeService, $this->organizer, 'payments');

    $archived = Tournament::factory()->create(['archived_at' => now()]);
    $registration = Registration::factory()->create([
        'status' => 'approved',
        'tournament_id' => $archived->id,
    ]);
    Payment::factory()->create([
        'registration_id' => $registration->id,
        'status' => 'pending',
    ]);

    expect(navBadgeCount($this->badgeService, $this->organizer, 'payments'))
        ->toBe($before);
});

it('counts today\'s matches and ignores other days', function () {
    $before = navBadgeCount($this->badgeService, $this->organizer, 'schedule');

    MatchSlot::factory()->create([
        'start_at' => now()->setTime(12, 0),
        'end_at' => now()->setTime(13, 0),
    ]);
    MatchSlot::factory()->create([
        'start_at' => now()->subDay()->setTime(12, 0),
        'end_at' => now()->subDay()->setTime(13, 0),
    ]);
    MatchSlot::factory()->create([
        'start_at' => now()->addDay()->setTime(12, 0),
        'end_at' => now()->addDay()->setTime(13, 0),
    ]);

    expect(navBadgeCount($this->badgeService, $this->organizer, 'schedule'))
        ->toBe($before + 1);
});
```

### Step 2: Run the tests and confirm they fail

```powershell
cd src
php artisan test tests/Feature/Accessibility/NavigationBadgesTest.php
```

Expected: every test errors because `App\Services\NavigationBadgeService` does not exist. Do not
change the assertions.

### Step 3: Implement the service

Create `src/app/Services/NavigationBadgeService.php`:

```php
<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\MatchSlot;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;

class NavigationBadgeService
{
    /**
     * Counts for destinations that currently have work or recent activity.
     * Destinations with a zero count are omitted so the shell can skip them.
     *
     * @return array<string, int>
     */
    public function forUser(User $user): array
    {
        $role = $this->role($user);

        $counts = [
            'announcements' => $this->announcementCount($user, $role),
            'tournaments' => $this->tournamentCount(),
            'schedule' => $this->scheduleCount(),
        ];

        if ($role === 'organizer') {
            $counts['submissions'] = $this->submissionCount();
            $counts['payments'] = $this->paymentCount();
        }

        return array_filter($counts, fn (int $count): bool => $count > 0);
    }

    private function role(User $user): string
    {
        return $user->hasRole('organizer')
            ? 'organizer'
            : ($user->hasRole('coach') ? 'coach' : 'fan');
    }

    private function announcementCount(User $user, string $role): int
    {
        $allowedAudiences = match ($role) {
            'organizer' => ['all', 'coaches', 'organizers', 'fans'],
            'coach' => ['all', 'coaches', 'fans'],
            default => ['all', 'fans'],
        };

        return Announcement::query()
            ->where('status', 'published')
            ->where('published_at', '>=', now()->subDays(7))
            ->where(function ($query) use ($allowedAudiences, $user): void {
                $query->whereIn('audience', $allowedAudiences)
                    ->orWhere(function ($query) use ($user): void {
                        $query->where('audience', 'specific')
                            ->whereJsonContains('target_user_ids', (int) $user->id);
                    });
            })
            ->count();
    }

    private function tournamentCount(): int
    {
        return Tournament::query()
            ->whereNull('archived_at')
            ->where('status', 'registration-open')
            ->count();
    }

    private function submissionCount(): int
    {
        return Registration::query()
            ->whereIn('status', ['submitted', 'pending'])
            ->whereHas('tournament', fn ($query) => $query->whereNull('archived_at'))
            ->count();
    }

    private function paymentCount(): int
    {
        return Registration::query()
            ->where('status', '!=', 'rejected')
            ->whereHas('tournament', fn ($query) => $query->whereNull('archived_at'))
            ->whereHas('payment', fn ($query) => $query->whereIn('status', ['pending', 'partial']))
            ->count();
    }

    private function scheduleCount(): int
    {
        return MatchSlot::query()
            ->whereDate('start_at', now()->toDateString())
            ->whereHas('tournament', fn ($query) => $query->whereNull('archived_at'))
            ->distinct()
            ->count('match_id');
    }
}
```

Notes:

- `whereJsonContains` is supported by Laravel on both PostgreSQL and the SQLite test database.
- `whereDate` uses the application timezone, the same clock as every server-rendered page.
- `distinct()->count('match_id')` is defensive; `match_slots.match_id` is currently unique.

### Step 4: Run the tests again

```powershell
cd src
php artisan test tests/Feature/Accessibility/NavigationBadgesTest.php
```

Expected: all service tests pass.

### Step 5: Commit

```powershell
git add src/app/Services/NavigationBadgeService.php src/tests/Feature/Accessibility/NavigationBadgesTest.php
git commit -m "feat(nav): add navigation badge count service"
```

---

## Task 2: Badge component and navigation markup

### Step 1: Append the failing rendering tests

Append to `src/tests/Feature/Accessibility/NavigationBadgesTest.php`:

```php
function navBadgesXPath(string $html): DOMXPath
{
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($dom);
}

it('renders count badges in the organizer rail', function () {
    Announcement::factory()->create([
        'audience' => 'all',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    Tournament::factory()->create(['status' => 'registration-open']);

    $registration = Registration::factory()->create(['status' => 'submitted']);
    Payment::factory()->create([
        'registration_id' => $registration->id,
        'status' => 'pending',
    ]);

    MatchSlot::factory()->create([
        'start_at' => now()->setTime(12, 0),
        'end_at' => now()->setTime(13, 0),
    ]);

    $response = $this->actingAs($this->organizer)->get(route('dashboard'))->assertOk();
    $xpath = navBadgesXPath((string) $response->getContent());

    foreach (['announcements', 'tournaments', 'submissions', 'payments', 'schedule'] as $key) {
        $badge = $xpath->query(
            "//a[@data-testid='nav-{$key}']//span[contains(@class, 'hf-nav-badge')]"
        )->item(0);

        expect($badge)->not->toBeNull("Missing badge on nav-{$key}");
        expect(trim($badge->textContent))->toMatch('/^\d+\+?$/');
    }
});

it('renders badges in the More sheet links', function () {
    Announcement::factory()->create([
        'audience' => 'all',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->organizer)->get(route('dashboard'))->assertOk();
    $xpath = navBadgesXPath((string) $response->getContent());
    $badge = $xpath->query(
        "//a[@data-more-link='announcements']//span[contains(@class, 'hf-nav-badge')]"
    )->item(0);

    expect($badge)->not->toBeNull();
});

it('hides submissions and payments from a fan', function () {
    $response = $this->actingAs($this->fan)->get(route('dashboard'))->assertOk();
    $xpath = navBadgesXPath((string) $response->getContent());

    expect($xpath->query("//a[@data-testid='nav-submissions']")->length)->toBe(0);
    expect($xpath->query("//a[@data-testid='nav-payments']")->length)->toBe(0);
});

it('renders no badge or suffix for a zero count', function () {
    $response = $this->actingAs($this->fan)->get(route('dashboard'))->assertOk();
    $xpath = navBadgesXPath((string) $response->getContent());
    $link = $xpath->query("//a[@data-testid='nav-announcements']")->item(0);

    expect($link)->not->toBeNull();
    expect($xpath->query(".//span[contains(@class, 'hf-nav-badge')]", $link)->length)->toBe(0);
    expect($xpath->query(".//span[contains(@class, 'hf-sr-only')]", $link)->length)->toBe(0);
    expect($link->getAttribute('data-label'))->toBe('Announcements');
});

it('names the count in the screen-reader suffix and tooltip', function () {
    Announcement::factory()->create([
        'audience' => 'all',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->organizer)->get(route('dashboard'))->assertOk();
    $xpath = navBadgesXPath((string) $response->getContent());
    $link = $xpath->query("//a[@data-testid='nav-announcements']")->item(0);
    $suffix = $xpath->query(".//span[contains(@class, 'hf-sr-only')]", $link)->item(0);

    expect($suffix)->not->toBeNull();
    expect(trim($suffix->textContent))->toMatch('/^, \d+ new$/');
    expect($link->getAttribute('data-label'))->toMatch('/^Announcements · \d+ new$/');
});

it('badges the mobile schedule tab', function () {
    MatchSlot::factory()->create([
        'start_at' => now()->setTime(12, 0),
        'end_at' => now()->setTime(13, 0),
    ]);

    $response = $this->actingAs($this->fan)->get(route('dashboard'))->assertOk();
    $xpath = navBadgesXPath((string) $response->getContent());
    $tab = $xpath->query("//a[@data-testid='mobile-nav-schedule']")->item(0);

    expect($tab)->not->toBeNull();
    expect($xpath->query(".//span[contains(@class, 'hf-nav-badge')]", $tab)->length)->toBe(1);

    $suffix = $xpath->query(".//span[contains(@class, 'hf-sr-only')]", $tab)->item(0);
    expect($suffix)->not->toBeNull();
    expect(trim($suffix->textContent))->toMatch('/^, \d+ matches today$/');
});

it('marks the More tab with a dot when a sheet destination has a count', function () {
    Announcement::factory()->create([
        'audience' => 'all',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->organizer)->get(route('dashboard'))->assertOk();
    $xpath = navBadgesXPath((string) $response->getContent());
    $more = $xpath->query("//button[@data-testid='mobile-nav-more']")->item(0);

    expect($more)->not->toBeNull();
    expect($xpath->query(".//span[contains(@class, 'hf-nav-badge--dot')]", $more)->length)->toBe(1);

    $suffix = $xpath->query(".//span[contains(@class, 'hf-sr-only')]", $more)->item(0);
    expect($suffix)->not->toBeNull();
    expect(trim($suffix->textContent))->toBe(', New items in More');
});
```

Run them and confirm the new tests fail:

```powershell
cd src
php artisan test tests/Feature/Accessibility/NavigationBadgesTest.php
```

Expected: the service tests still pass; the rendering tests fail because no badge markup exists.

### Step 2: Create the badge component

Create `src/resources/views/components/nav-badge.blade.php`:

```blade
@props(['count' => 0, 'dot' => false])

@if ($dot)
    <span class="hf-nav-badge hf-nav-badge--dot" aria-hidden="true"></span>
@elseif ($count > 0)
    <span class="hf-nav-badge" aria-hidden="true">{{ $count > 99 ? '99+' : $count }}</span>
@endif
```

### Step 3: Teach `app-nav` about badges

Replace `src/resources/views/components/app-nav.blade.php` with:

```blade
@props([
    'groups' => [],
    'items' => [],
    'activeKeys' => [],
    'variant' => 'rail',
    'badges' => [],
])

@php
    $isSheet = $variant === 'sheet';
@endphp

@foreach ($groups as $group)
    @php
        $keys = array_values(array_filter($group['keys'] ?? [], fn ($key) => isset($items[$key])));
    @endphp

    @if (count($keys))
        @if ($isSheet)
            <div class="hf-sheet__group">
                <span class="hf-rail-label">{{ $group['label'] }}</span>
        @else
            <span class="hf-rail-label">{{ $group['label'] }}</span>
        @endif

        @foreach ($keys as $key)
            @php
                $item = $items[$key];
                $active = in_array($key, $activeKeys, true);
                $count = (int) ($badges[$key] ?? 0);
                $badgeSr = $item['badge_sr'] ?? '';
                $tooltipLabel = $item['label'].($count > 0 && $badgeSr !== '' ? ' · '.$count.' '.$badgeSr : '');
            @endphp

            @if ($isSheet)
                <a href="{{ route($item['route']) }}"
                   wire:navigate
                   data-more-link="{{ $key }}"
                   @if ($active) aria-current="page" @endif>
                    <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>{{ $item['label'] }}
                    @if ($count > 0)
                        <x-nav-badge :count="$count" />
                        @if ($badgeSr !== '')
                            <span class="hf-sr-only">, {{ $count }} {{ $badgeSr }}</span>
                        @endif
                    @endif
                </a>
            @else
                <a class="hf-rail-link{{ $active ? ' is-active' : '' }}"
                   href="{{ route($item['route']) }}"
                   wire:navigate
                   data-testid="nav-{{ $key }}"
                   data-label="{{ $tooltipLabel }}"
                   data-nav-link
                   @if ($active) aria-current="page" @endif>
                    <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>
                    <span class="hf-nav-link__label">{{ $item['label'] }}</span>
                    @if ($count > 0)
                        <x-nav-badge :count="$count" />
                        @if ($badgeSr !== '')
                            <span class="hf-sr-only">, {{ $count }} {{ $badgeSr }}</span>
                        @endif
                    @endif
                </a>
            @endif
        @endforeach

        @if ($isSheet)
            </div>
        @endif
    @endif
@endforeach
```

Markup rules:

- Zero count renders nothing new; `data-label` stays the plain label.
- The visual badge is `aria-hidden`; the hidden suffix carries the count and its noun.
- The pill uses `margin-left: auto` from the stylesheet, so the markup order stays text-first.

### Step 4: Wire the layout

Edit `src/resources/views/layouts/public.blade.php` inside the `@auth` block.

1. After the `$role = ...` line, add:

```php
$navBadges = app(\App\Services\NavigationBadgeService::class)->forUser($authUser);
```

2. Add `badge_sr` to the five destinations in `$navItems`:

```php
'tournaments' => ['label' => 'Tournaments', 'icon' => 'trophy', 'route' => 'tournaments', 'badge_sr' => 'open for registration'],
'schedule' => ['label' => 'Schedule', 'icon' => 'calendar3', 'route' => 'schedule', 'badge_sr' => 'matches today'],
'announcements' => ['label' => 'Announcements', 'icon' => 'megaphone', 'route' => 'announcements', 'badge_sr' => 'new'],
'submissions' => ['label' => 'Submissions', 'icon' => 'clipboard-check', 'route' => 'submissions', 'badge_sr' => 'pending review'],
'payments' => ['label' => 'Payments', 'icon' => 'credit-card', 'route' => 'payments', 'badge_sr' => 'unpaid'],
```

3. After `$navItems`, add:

```php
$moreBadgeCount = collect(['announcements', 'tournaments', 'submissions', 'payments'])
    ->sum(fn (string $key): int => (int) ($navBadges[$key] ?? 0));
```

4. Pass the badges to both navigation surfaces:

```blade
<x-app-nav variant="rail" :groups="$navGroups" :items="$navItems" :active-keys="$activeKeys" :badges="$navBadges" />
```

```blade
<x-app-nav variant="sheet" :groups="$navGroups" :items="$navItems" :active-keys="$activeKeys" :badges="$navBadges" />
```

5. Inside the bottom-nav loop, after `<span>{{ $tab['label'] }}</span>`, add:

```blade
@if ($tab['key'] === 'schedule')
    <x-nav-badge :count="$navBadges['schedule'] ?? 0" />
    @if (($navBadges['schedule'] ?? 0) > 0)
        <span class="hf-sr-only">, {{ $navBadges['schedule'] }} matches today</span>
    @endif
@endif
```

6. Inside the More button, after `<span>More</span>`, add:

```blade
@if ($moreBadgeCount > 0)
    <x-nav-badge dot />
    <span class="hf-sr-only">, New items in More</span>
@endif
```

The service runs once per render; rail, sheet, and bottom bar share `$navBadges`.

### Step 5: Run the rendering tests

```powershell
cd src
php artisan test tests/Feature/Accessibility/NavigationBadgesTest.php
```

Expected: all tests pass.

### Step 6: Commit

```powershell
git add src/resources/views/components/nav-badge.blade.php src/resources/views/components/app-nav.blade.php src/resources/views/layouts/public.blade.php src/tests/Feature/Accessibility/NavigationBadgesTest.php
git commit -m "feat(nav): render count badges in navigation"
```

---

## Task 3: Badge styling and collapsed-rail stacking fix

### Step 1: Add the rail stacking level

In `src/resources/css/hf/_shell.scss`, inside `.hf-rail`, add `z-index: 20;` after `top: 0;`:

```scss
.hf-rail {
  grid-row: 1 / span 2;
  position: sticky;
  top: 0;
  z-index: 20;
  ...
}
```

Why: the rail is a sticky stacking context. The fixed account panel is trapped inside it, so the
rail must paint above `.hf-main`. Level 20 stays below the topbar (30), bottom bar (40), tooltip
(90), and native dialogs in the top layer.

### Step 2: Replace the unused rail badge rule

Replace `.hf-rail-badge` (around line 233) with:

```scss
.hf-nav-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  margin-left: auto;
  padding: 2px 7px;
  border-radius: var(--hf-pill);
  background: var(--hf-gold);
  color: #1a1300;
  font-size: 11px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  line-height: 1.15;
  pointer-events: none;
}

.hf-nav-badge--dot {
  width: 8px;
  height: 8px;
  min-width: 8px;
  padding: 0;
  border-radius: 50%;
}
```

### Step 3: Turn the pill into a dot in the compact rail

Inside `@media (min-width: 768px)`, after the existing
`[data-nav='collapsed'] .hf-rail-link { ... }` rule, add:

```scss
[data-nav='collapsed'] .hf-rail .hf-nav-badge:not(.hf-nav-badge--dot) {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 8px;
  height: 8px;
  min-width: 0;
  margin: 0;
  padding: 0;
  overflow: hidden;
  border-radius: 50%;
  color: transparent;
  font-size: 0;
  line-height: 0;
}
```

The dot anchors to `.hf-rail-link`, which is already `position: relative`, and overlaps the icon's
top-right corner. The numeral is hidden by zero font size, clipped overflow, and transparency; it is
`aria-hidden` in every state, so nothing is lost.

### Step 4: Position badges in the mobile bottom bar

Add `position: relative;` to `.hf-nav-tab`, then add after `.hf-nav-tab .bi`:

```scss
.hf-nav-tab > .hf-nav-badge {
  position: absolute;
  top: 5px;
  left: calc(50% + 5px);
  min-width: 16px;
  margin-left: 0;
  padding: 1px 5px;
  font-size: 10px;
  line-height: 1.2;
}

.hf-nav-tab > .hf-nav-badge--dot {
  top: 7px;
  left: calc(50% + 6px);
  width: 8px;
  height: 8px;
  min-width: 8px;
  padding: 0;
}
```

Absolute positioning keeps the tab's flex column from shifting. The base rule's
`pointer-events: none` keeps the whole 44px target clickable.

### Step 5: Build the stylesheet

```powershell
cd src
npm run build
```

Expected: Vite reports a successful build with no errors.

### Step 6: Verify in the browser

Use the desktop browser at `http://localhost:8000`.

1. **Expanded rail at 1440×900.** Log in with `[data-testid='quick-login-organizer']`. Confirm the
   badge pills sit at the trailing edge of Announcements, Tournaments, Submissions, Payments, and
   Schedule. No pill for a zero count. No horizontal scroll:
   `document.documentElement.scrollWidth <= window.innerWidth`.
2. **Compact rail at 943×718.** Clear the stored preference or toggle the rail. Confirm pills become
   dots on the icons. Hover Announcements: the tooltip reads `Announcements · N new`. Repeat for the
   other four links and confirm the noun matches (`open for registration`, `pending review`,
   `unpaid`, `matches today`).
3. **Collapsed account menu.** At 943px and 768px, open the account chip. Confirm the panel is fully
   inside the viewport and, for each item, that
   `document.elementFromPoint(centerX, centerY)` returns that item or a descendant. Click Profile,
   Appearance, Keyboard shortcuts, and Log out. Log back in when needed. The last click may be Log
   out.
4. **Expanded account menu at 1440px.** Confirm it opens upward, closes on outside click, Escape,
   scroll, and resize.
5. **Mobile at 390×844.** Confirm the Schedule tab shows a count pill at the icon's top-right and the
   More tab shows a dot. Open More: each badged link shows a pill. Confirm no layout shift and no
   horizontal scroll.
6. **Role check.** Log out, log in as the fan (`[data-testid='quick-login-fan']`), and confirm the
   rail has no Submissions or Payments rows and no badges on zero-count destinations.

Expected console result: no JavaScript errors, and the network panel shows the built assets.

### Step 7: Commit

```powershell
git add src/resources/css/hf/_shell.scss
git commit -m "style(nav): polish badge states and fix collapsed rail stacking"
```

---

## Task 4: Quality gates

Run the full gates from `src/` and report the actual output, not a summary:

```powershell
cd src
npm run build
composer quality
```

`composer quality` runs Pint, PHPStan level 6, and the full Pest suite. Expected: all pass.

If Pint reports style issues in the new files, run `vendor/bin/pint app/Services/NavigationBadgeService.php tests/Feature/Accessibility/NavigationBadgesTest.php`,
re-run `composer quality`, and amend the task commit that introduced the file. Do not reformat
unrelated files.

Then verify the working tree:

```powershell
git status --short
```

Expected: no modifications to `.obsidian/` or `.kilo/` in the commit history. Unrelated pre-existing
changes may remain in the working tree but must not be staged.

---

## Acceptance checklist

- [ ] Positive counts appear for announcements, tournaments, submissions, payments, and today's schedule on the rail.
- [ ] Zero counts render no badge, no hidden suffix, and an unchanged `data-label`.
- [ ] Counts are role-aware; fans and coaches never see submissions or payments.
- [ ] The compact rail shows dots and count-bearing tooltips.
- [ ] The More sheet shows pills; the More tab shows a dot when a sheet destination has a count.
- [ ] The mobile Schedule tab shows the count.
- [ ] Screen-reader names read the count with an item-specific noun.
- [ ] The collapsed account menu paints above page content and every item is clickable at 768px, 943px, and 1440px.
- [ ] `npm run build` and `composer quality` pass.
- [ ] No existing test file was edited; no route, model, migration, or config changed.
