# Navigation Count Badges and Collapsed-Rail Account Menu — Design

- **Date:** 2026-11-18
- **Owner:** Jeward T. Mahinay
- **Status:** Approved for planning
- **Scope:** `src/app/Services`, `src/resources/views`, `src/resources/css/hf`, `src/tests/Feature`. No route, controller, policy, model, migration, or config changes.
- **Related:** [[2026-11-14-nav-toggle-design]], [[2026-11-17-spa-navigation-design]], [[2026-11-17-motion-and-dropdown-design]]

## Problem

The desktop rail, the More sheet, and the mobile bottom bar render every destination
identically. A destination with work waiting behind it looks no different from one
that is clear, so an organizer must open Submissions or Payments to discover whether
anything needs attention. Announcements in the last week and tournaments open for
registration are likewise invisible until their page is opened.

The collapsed rail also has a confirmed popup bug. `.hf-rail` is `position: sticky`
without a `z-index`, which creates a stacking context. The account panel is
`position: fixed` with `z-index: 80`, but that value is trapped inside the rail's
context. The rail is painted before `.hf-main`, so the expanded panel paints behind
the page cards and its links cannot be clicked. The menu is effectively unusable in
the 76px state.

## Goals

1. Show real counts on destinations with work or recent activity, on every
   authenticated navigation surface.
2. Never render a badge for a zero count and never invent unread state.
3. Keep the badge legible in the expanded rail, compact rail, More sheet, and mobile
   bottom bar, including screen-reader names.
4. Fix the collapsed-rail account panel so it paints above page content and every
   menu item is clickable.
5. Preserve every route, role filter, `data-testid`, form contract, and test-asserted
   string.

## Non-goals

- No per-user read tracking, no "seen" table, no notification inbox, and no
  read/unread clearing when a page is opened.
- No change to the topbar bell beyond its existing link.
- No live polling of badge counts. Counts refresh when the shell is re-rendered on
  navigation or full load.
- No new navigation destinations, no new dependencies, and no schema changes.
- No change to the guest layout.

## Decisions

| Decision | Choice |
|---|---|
| Badge semantics | Actionable or recent counts, not unread state |
| Announcements | Published in the last 7 days and visible to the viewer's role; targeted notices count only for their targets |
| Tournaments | Non-archived tournaments with status `registration-open` |
| Submissions | Registrations with status `submitted` or `pending` on non-archived tournaments; organizer only |
| Payments | Non-rejected registrations whose payment is `pending` or `partial` on non-archived tournaments; organizer only |
| Schedule | Distinct matches with a slot scheduled today in non-archived tournaments |
| Zero count | No badge markup at all |
| Large count | `99+` |
| Expanded rail | Gold count pill at the row's trailing edge |
| Compact rail | Pill becomes a small gold dot on the icon; tooltip gains the count and its item-specific suffix |
| More sheet | Gold count pill on each badged link |
| Mobile bottom bar | Schedule tab gets a count pill; More tab gets an aggregate dot for sheet-only badges |
| Refresh | Server-rendered counts on every full load and SPA navigation |
| Collapsed account menu | Raise the rail above main content, below topbar, bar, tooltip, and dialogs |

## Architecture

### 1. Badge source

New `src/app/Services/NavigationBadgeService.php` exposes one method:

```php
/** @return array<string, int> */
public function forUser(User $user): array
```

It returns only keys whose count is greater than zero:

```php
[
    'announcements' => 4,
    'tournaments' => 1,
    'submissions' => 0, // omitted
    'payments' => 2,
    'schedule' => 3,
]
```

Counts are computed with indexed aggregate queries. No results are hydrated for
badge counts, and no cache is introduced.

#### Announcements

The count matches the visibility rules already used by `AnnouncementsIndex`:

- Organizer: audiences `all`, `coaches`, `organizers`, and `fans`.
- Coach: audiences `all`, `coaches`, and `fans`.
- Fan: audiences `all` and `fans`.
- Audience `specific`: the viewer's id must appear in `target_user_ids`.

The query adds `status = 'published'` and
`published_at >= now()->subDays(7)`. The audience predicate is:

```php
$query->where(function ($q) use ($allowedAudiences, $userId) {
    $q->whereIn('audience', $allowedAudiences)
        ->orWhere(function ($q) use ($userId) {
            $q->where('audience', 'specific')
                ->whereJsonContains('target_user_ids', $userId);
        });
});
```

`whereJsonContains` is supported on PostgreSQL and on the SQLite test database.
Records with a null `published_at` are excluded because they are not published
bulletins.

#### Tournaments

```php
Tournament::query()
    ->whereNull('archived_at')
    ->where('status', 'registration-open')
    ->count();
```

#### Submissions

```php
Registration::query()
    ->whereIn('status', ['submitted', 'pending'])
    ->whereHas('tournament', fn ($q) => $q->whereNull('archived_at'))
    ->count();
```

This mirrors the `pending` KPI on `SubmissionsIndex`.

#### Payments

```php
Registration::query()
    ->where('status', '!=', 'rejected')
    ->whereHas('tournament', fn ($q) => $q->whereNull('archived_at'))
    ->whereHas('payment', fn ($q) => $q->whereIn('status', ['pending', 'partial']))
    ->count();
```

This mirrors the union of the `pendingCount` and `partialCount` KPIs on
`PaymentsIndex`, restricted to tournaments that have not been archived.

#### Schedule

```php
MatchSlot::query()
    ->whereDate('start_at', now()->toDateString())
    ->whereHas('tournament', fn ($q) => $q->whereNull('archived_at'))
    ->distinct()
    ->count('match_id');
```

"Today" uses the application timezone, the same clock used by the rest of the
server-rendered app. The query counts matches, not slots, so a match with more than
one slot is counted once.

### 2. Layout wiring

Inside the authenticated block of `layouts/public.blade.php`, after `$navItems` and
the role lists are built:

```php
$navBadges = app(\App\Services\NavigationBadgeService::class)->forUser($authUser);
```

`$navItems` gains a `badge_sr` suffix per destination for the accessible name:

| Key | `badge_sr` | Screen-reader result |
|---|---|---|
| `announcements` | `new` | “Announcements, 4 new” |
| `tournaments` | `open for registration` | “Tournaments, 2 open for registration” |
| `submissions` | `pending review` | “Submissions, 5 pending review” |
| `payments` | `unpaid` | “Payments, 3 unpaid” |
| `schedule` | `matches today` | “Schedule, 2 matches today” |

Both `<x-app-nav>` calls receive `:badges="$navBadges"`. The mobile Schedule tab
reads `$navBadges['schedule'] ?? 0`. The More tab receives a dot when any of
`announcements`, `tournaments`, `submissions`, or `payments` has a count, because
those destinations live only in the sheet on mobile.

The service runs once per request. The rail, sheet, and bottom bar reuse the same
array.

### 3. Badge component and markup

New `src/resources/views/components/nav-badge.blade.php`:

```blade
@props(['count' => 0, 'dot' => false])

@if ($dot)
    <span class="hf-nav-badge hf-nav-badge--dot" aria-hidden="true"></span>
@elseif ($count > 0)
    <span class="hf-nav-badge" aria-hidden="true">{{ $count > 99 ? '99+' : $count }}</span>
@endif
```

`app-nav.blade.php` accepts a `badges` prop. For each link:

- `$count = (int) ($badges[$key] ?? 0)`.
- A count greater than zero renders `<x-nav-badge :count="$count" />`.
- The same count adds `<span class="hf-sr-only">, {{ $count }} {{ $item['badge_sr'] }}</span>`
  inside the link so the accessible name carries the count.
- The rail link's `data-label` becomes
  `{{ $item['label'] }}{{ $count ? ' · '.$count.' '.$item['badge_sr'] : '' }}`, which
  feeds the existing collapsed-rail tooltip without new JavaScript.

The bottom-bar Schedule tab and More button use the same component. The More button
adds a visually hidden “New items in More” suffix when its dot is present.

Rendered output is unchanged for zero counts: no badge span, no hidden suffix, and
no `data-label` change.

### 4. Badge styling

`_shell.scss` replaces the unused `.hf-rail-badge` rule with `.hf-nav-badge`:

- Gold pill, dark ink, 11px bold tabular numerals, minimum 18px, 2px × 7px padding.
- `margin-left: auto` in rail rows and sheet rows.
- `pointer-events: none` so it never intercepts a click.
- `[data-nav='collapsed'] .hf-rail .hf-nav-badge` becomes an 8px gold dot at the
  icon's top-right. The numeral is visually hidden by zero font size and clipping;
  the badge is `aria-hidden` in every state.
- `.hf-nav-tab` becomes `position: relative`; a badge inside it sits at the icon's
  top-right without shifting the tab's flex column.
- `.hf-nav-badge--dot` is an 8px round dot used by the mobile More aggregate.

### 5. Collapsed-rail account menu fix

The rail gets a stacking level of its own:

```css
.hf-rail {
    z-index: 20;
}
```

Because the rail already has `position: sticky`, this creates an explicit stacking
context above `.hf-main` while staying below:

- `.hf-topbar` at 30,
- `.hf-bottom-nav` at 40,
- `.hf-rail-tooltip` at 90,
- native modal dialogs in the top layer.

The fixed account panel's `z-index: 80` remains relative to the rail, so it now
paints above page content and accepts clicks. `positionFloatingMenu()` already
anchors the panel beside the collapsed chip and closes it on scroll or resize, so no
JavaScript change is required.

The fix is verified in both states at 768px, 943px, and 1440px. The panel must be
fully inside the viewport, its Profile/Appearance/Keyboard shortcuts/Log out items
must receive clicks, and the rail must not cover the topbar or the mobile bar.

### 6. Refresh behavior

Badge counts are part of the server-rendered shell. SPA navigation re-renders that
shell, so a visit to Submissions after a new registration updates the count on the
next navigation. Counts are not updated by in-page Livewire morphs that do not
touch the layout; this is accepted for the scope and documented in the plan.

## Accessibility

- Visual badges are `aria-hidden`; the link's name carries the count through a
  `.hf-sr-only` suffix.
- Screen-reader wording is item-specific, not a bare number.
- Color is never the only signal: the badge is a shape with a numeral in the
  expanded state and a dot with a tooltip elsewhere.
- The collapsed account menu remains a native `<details>`; raising the rail changes
  only paint order.
- Focus-visible outlines and `aria-current="page"` are untouched.
- The badge dot does not affect the 44px target of rail links or bottom tabs.

## Files

| File | Change |
|---|---|
| `src/app/Services/NavigationBadgeService.php` | New role-aware count service. |
| `src/resources/views/components/nav-badge.blade.php` | New pill/dot badge. |
| `src/resources/views/components/app-nav.blade.php` | Accept `badges`, render pills and screen-reader suffixes. |
| `src/resources/views/layouts/public.blade.php` | Compute counts once, pass to rail/sheet, badge Schedule and More tabs. |
| `src/resources/css/hf/_shell.scss` | Badge styles, compact dot, mobile tab placement, rail `z-index: 20`. |
| `src/tests/Feature/Accessibility/NavigationBadgesTest.php` | Service counts, role filtering, rendering, zero suppression. |
| `docs/superpowers/specs/2026-11-18-navigation-badges-design.md` | This spec. |

## Contracts Preserved

- Every `data-testid` (`nav-*`, `mobile-nav-*`, `topbar-announcements`, and others)
  is unchanged.
- Role filtering, group order, link labels, icons, routes, and active states are
  unchanged.
- Guest pages render no badges and no navigation shell.
- The account menu keeps its native disclosure behavior and its existing motion.
- No test file is edited; the new test file is additive.
- `.obsidian/` and `.kilo/` are never staged.

## Testing

### Service tests

- Organizer receives all five keys when every count is positive.
- Coach receives announcements, tournaments, and schedule only.
- Fan receives announcements, tournaments, and schedule only.
- A zero count is omitted from the returned array.
- An announcement older than seven days is excluded.
- An organizers-only announcement counts for the organizer and not for a fan.
- A `specific` announcement counts only for a user in `target_user_ids`.
- A pending registration on an archived tournament is excluded.
- A pending or partial payment counts toward payments; verified and rejected
  payments do not.
- Today's slots count matches once; a slot yesterday or tomorrow is excluded.

### Rendering tests

- The organizer dashboard rail contains a count badge on Announcements,
  Tournaments, Submissions, Payments, and Schedule.
- The fan dashboard does not render Submissions or Payments rows at all.
- A zero-count destination renders no `.hf-nav-badge` and no screen-reader suffix.
- The mobile Schedule tab carries the count; the More tab carries a dot when a
  sheet-only destination has a count.
- The accessible name contains the count and the item-specific suffix.

### Browser verification

1. Expanded rail at 1440px: badges align at the trailing edge; counts match the
   destination pages.
2. Compact rail at 943px: badges are dots; hovering shows a tooltip with the count and
   its item-specific suffix; no horizontal overflow.
3. Collapsed rail: open the account menu and click Profile, Appearance, Keyboard
   shortcuts, and Log out; each is fully visible and clickable. Repeat at 768px.
4. Expanded rail: the account menu still opens upward, animates, and closes on
   outside click, Escape, scroll, and resize.
5. Mobile width: Schedule shows a count; More shows a dot; the More sheet shows
   the counts on its links.
6. Zero-count pages render no badges and no layout shift.

### Gates

- `npm run build` passes.
- `composer quality` passes and the actual output is reported.

## Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Layout-level queries add cost to every authenticated request. | Five indexed aggregate counts only; no model hydration. Measure while verifying, and cache later only if a real page-regression appears. |
| Badge counts become stale after an in-page action. | Accepted for this scope; counts refresh on the next navigation or full load. Documented in the plan. |
| Audience filtering drifts from `AnnouncementsIndex`. | The service uses the same three audience lists; a test covers organizer, coach, fan, old, and targeted notices. |
| `whereJsonContains` behaves differently on SQLite. | Covered by a `specific`-audience test running on the SQLite test database. |
| Compact rail badge overlaps the icon or clips. | Dot is absolutely positioned top-right with `pointer-events: none`; verified at 768px and 943px. |
| Raising the rail hides the topbar or dialogs. | Rail `z-index` is 20, below the topbar at 30 and the bar at 40; dialogs stay in the top layer. Browser pass covers both rails and the menu. |
| A phone tab's badge shifts the icon. | Badge is absolutely positioned inside a relative tab, outside the flex flow. |
