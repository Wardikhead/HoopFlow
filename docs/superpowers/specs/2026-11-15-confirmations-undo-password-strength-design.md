# Confirmations, Undo, and Password Strength - Design

- **Date:** 2026-11-15
- **Owner:** Jeward T. Mahinay
- **Status:** Draft for review
- **Scope:** `src/app`, `src/resources`, `src/routes`, `src/database/migrations`, `src/tests`
- **Related:** [[2026-11-13-hoopflow-ui-ux-enhancement-design]], [[2026-11-07-phase-2-auth-policies-design]]

## Problem

HoopFlow mutates important tournament data from many places, but the safety net is
inconsistent:

- Some destructive actions open a confirmation dialog (tournament archive/delete,
  announcement delete, player removal, score finalization). Others do not (logout,
  team registration, announcement publish, payment verification, consent and
  document review).
- No action can be undone. A mis-click on "Delete tournament" leaves the organizer
  with a soft-deleted record and no path back from the UI. A removed player has to
  be re-entered by hand.
- Each Livewire component copies the same `<dialog>` markup, so labels, focus
  behavior, and test attributes drift between screens.
- Account creation accepts any six-character password. There is no strength meter,
  no guidance, and no server-side rejection of obviously common passwords. The
  only feedback is a validation error after submit.

## Goals

1. Confirm high-impact actions before they run, using one reusable dialog component.
2. Offer a real, server-backed undo for deletes and reversible status/field changes.
3. Keep undo out of the permanent audit trail except where the trail would become
   misleading (tournament lifecycle and payment verification).
4. Coach password strength while typing, and make the server rule agree with the
   message shown to the user.
5. Preserve every existing route, policy, test id, and form contract unless this
   spec explicitly changes it.

## Non-goals

- No undo for bracket generation, bracket slot edits, schedule generation or
  publishing, score finalization, score correction, or overtime removal. Those keep
  their existing confirmations because a true revert is domain-deep.
- No redo stack, no undo history page, and no database-backed undo queue.
- No confirmation prompt for routine navigation, filters, wizard steps, preview
  builds, draft saves, or score +/-.
- No password confirmation field, password reset flow, or email verification.
- No new npm or Composer packages. No Bootstrap JavaScript, Tailwind, Vue, or React.
- No change to login, public standings, or the static prototype.

## Decisions

| Decision | Choice |
|---|---|
| Safety model | Confirm high-impact actions; undo reversible ones. |
| Undo storage | Session token, 40 random characters, 10-minute expiry, newest 10 kept. |
| Undo transport | `POST /undo/{token}` under `auth` + CSRF, then redirect to the recorded return path or back. |
| Undo ownership | Token bound to the user who performed the action. |
| Authorization | Re-checked by the handler at undo time; never trusted from the token. |
| Routine undo audit | No new audit row. The original action's before/after entry remains. |
| Audited undo | Tournament lifecycle and payment verification only. |
| Soft deletes | Add `deleted_at` to `players` and `announcements`; tournaments already have it. |
| Confirmation component | `resources/views/components/confirm-dialog.blade.php`. |
| Logout | One global dialog in `layouts/public.blade.php`; both logout buttons open it. |
| Password floor | 8 characters plus a local common-password blocklist. |
| Password classes | Lowercase, uppercase, number, symbol are advisory in the meter, not blocking. |
| Meter visibility | Appears when the password field is focused or has a value. |

## Architecture

### Undo pipeline

```
Livewire action succeeds
  -> UndoService::remember(actor, action, payload, title, message, returnTo?)
     returns a token
  -> toast payload: actionLabel "Undo", actionMethod POST,
     actionUrl /undo/{token}, duration 9000ms
       -> dispatch immediately, or session()->flash('hf_toast', payload)
          when the action redirects
User clicks Undo
  -> POST /undo/{token} (auth + CSRF)
      -> UndoService::execute(token, actor)
          -> token exists, belongs to this actor, has not expired, is unused
          -> consumed immediately, so a token cannot be replayed
          -> UndoRegistry picks a handler by action key
          -> handler re-checks policy, role, or team ownership
          -> handler compares current state with the recorded "after" state
          -> inverse operation inside a DB transaction
          -> audited actions append one "*.undo" row; all others stay silent
      -> redirect back with a flash toast
```

### New backend files

| File | Responsibility |
|---|---|
| `app/Services/Undo/UndoService.php` | Token lifecycle only: remember, prune, pull, execute. No domain knowledge. |
| `app/Services/Undo/UndoRegistry.php` | Resolves an action key to one of five handlers. |
| `app/Services/Undo/UndoResult.php` | Readonly result: `ok`, `title`, `message`, `tone`. |
| `app/Services/Undo/UndoException.php` | Thrown when state moved on or the record is gone. |
| `app/Services/Undo/Contracts/UndoHandler.php` | `supports()`, `authorize()`, `apply()`. |
| `app/Services/Undo/Handlers/TournamentUndo.php` | archive, restore, delete, create |
| `app/Services/Undo/Handlers/AnnouncementUndo.php` | delete, pin, publish |
| `app/Services/Undo/Handlers/PlayerUndo.php` | create, update, remove, consent, document |
| `app/Services/Undo/Handlers/PaymentUndo.php` | verify, reference |
| `app/Services/Undo/Handlers/RegistrationUndo.php` | approve, reject |
| `app/Http/Controllers/UndoController.php` | Invokable POST action; redirects back with a flash toast. |
| `app/Livewire/Concerns/HasUndoToasts.php` | `toastWithUndo()` and `flashUndoToast()` helpers. |
| `app/Rules/NotCommonPassword.php` | Local common-password blocklist. |

### Token envelope

```php
[
    'user_id' => int,
    'action' => string,
    'payload' => array<string, mixed>,
    'title' => string,
    'message' => string,
    'return_to' => string|null,
    'created_at' => int,
    'expires_at' => int,
]
```

Stored in the session at `hf_undo.{token}`. `remember()` prunes expired entries,
keeps the newest 10, and returns the token. `execute()` removes the entry before
running the handler, then returns an `UndoResult`. The controller flashes
`hf_toast` with the result and redirects to `return_to` when it is a local URL,
otherwise with `back()`.

`return_to` exists for the one case where the current page would disappear:
undoing a tournament creation soft-deletes the tournament whose desk page is open.
That token passes `route('tournaments')`, so the user lands on a working list.
Every other token leaves `return_to` null and uses `back()`.

### Handler contract

```php
interface UndoHandler
{
    public function supports(string $action): bool;

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws AuthorizationException
     */
    public function authorize(User $actor, string $action, array $payload): void;

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws UndoException
     */
    public function apply(User $actor, string $action, array $payload): string;
}
```

`apply()` returns the user-facing success message and wraps its writes in
`DB::transaction()`. `UndoService` catches `UndoException` and
`AuthorizationException` and returns a warning `UndoResult`; the controller turns
that into the flash toast.

### Toast POST support

`resources/js/ui/toasts.js` adds `actionMethod` (default `GET`) and `actionUrl`
(default empty) to each toast. `layouts/public.blade.php` renders:

- an `<a class="hf-toast__action">` when the method is `GET` (Retry, Reload), and
- a small `<form method="POST">` with `@csrf` and a
  `<button class="hf-toast__action">` when the method is `POST` (Undo).

`resources/css/hf/_components.scss` resets the form/button chrome so both look
identical. Existing toast behavior (max 3, pause on hover/focus, timer bar) does
not change. The layout reads `session('hf_toast')` first and falls back to
`session('announcement_toast')` for existing callers.

### Confirmation component

`resources/views/components/confirm-dialog.blade.php` props:

| Prop | Default | Purpose |
|---|---|---|
| `open` | `false` | Renders only when true. |
| `title` | `Confirm action` | Dialog heading. |
| `message` | empty | Consequence copy. |
| `confirmLabel` | `Confirm` | Destructive/solid button label. |
| `cancelLabel` | `Cancel` | Safe button label. |
| `tone` | `primary` | `primary` or `danger`. |
| `confirm` | `confirm` | Livewire method called by the primary button. |
| `cancel` | `cancel` | Livewire method called by Cancel, Escape, or backdrop. |
| `dialogId` | `hf-confirm-dialog` | Unique element id on the page. |
| `testid` | `confirm-dialog` | Preserves existing test selectors. |

The component keeps native `<dialog>` behavior, `x-init` showModal, Escape and
backdrop cancel, `wire:loading` on the primary button, and the existing
`data-testid="confirm-cancel"` / `data-testid="confirm-ok"` attributes. Cancel is
focused by default. The four simple existing dialogs migrate to it, keeping their current test ids:

- `tournaments-index.blade.php` -> `tournament-confirm-dialog`
- `tournament-show.blade.php` -> `tournament-confirm-dialog`
- `announcements-index.blade.php` -> `confirm-dialog`
- `my-team.blade.php` -> `my-team-remove-dialog`

Bracket, schedule, game manage, game stats, and score-console dialogs stay as they
are because they carry dynamic bodies.

### Logout dialog

One global dialog in `layouts/public.blade.php`:

- id `hf-logout-dialog`, title "Log out of HoopFlow?"
- body: "You'll need to sign in again to manage tournaments or your team."
- Cancel "Stay signed in" (focused), primary "Log out" with `hf-btn--danger`.
- Primary is a real `<form method="POST" action="{{ route('logout') }}">` with
  `@csrf`.

Both logout controls (rail user menu and mobile sheet) remain submit buttons inside
their existing forms, but gain
`x-on:click.prevent="$dispatch('hf-logout-confirm')"`. Without JavaScript the form
still posts directly. The dialog listens with
`x-on:hf-logout-confirm.window`. This preserves the `data-testid="logout"`
contract and works with the existing focus-restore code.

### Password strength module

`resources/js/ui/password-strength.js` exports:

```js
export function evaluatePassword(password) {
    // returns { checks, met, score, label, hint, missing }
}
```

- `checks` = `length`, `lowercase`, `uppercase`, `number`, `symbol`.
- `score` = `min(4, met + (password.length >= 12 ? 1 : 0))`, `0` for empty.
- Labels: `''`, `Very weak`, `Weak`, `Fair`, `Strong`, `Very strong`.
- `missing` lists the unmet checks in display order.
- `hint` is the empty-state copy, the missing-items copy, or the strong-password
  copy. It never mentions the server rule.

`registerPasswordMeter()` adds an Alpine `hfPasswordMeter` data component with
`password`, `showPassword`, `focused`, `evaluation`, and a `toggle()` helper. It is
initialized from `resources/js/app.js` with the other UI modules.

The register card gets `x-data="hfPasswordMeter"`; the password input uses
`x-model="password"`, `x-bind:type`, and an `x-init` seed from `$el.value` so a
browser-autofilled password is measured immediately. The meter panel:

- four decorative bars in a `role="meter"` wrapper with `aria-valuemin="0"`,
  `aria-valuemax="4"`, `:aria-valuenow`, and `:aria-valuetext` falling back to
  "Empty" when no password is typed;
- a `role="status" aria-live="polite"` hint;
- a `<ul aria-label="Password requirements">` with five items, each carrying a
  visually hidden "Met" or "Not met" prefix and an `is-met` class;
- `x-show="focused || password.length > 0"` plus `x-cloak` (already defined in
  `_base.scss`).

The password field becomes `hf-field--full`, includes the Show/Hide toggle from
the login page, and keeps `data-testid="register-password"`. Role moves to a full
row so the meter has the width it needs.

### Password backend

`AuthController::register()` changes the password rule to:

```php
'password' => ['required', 'string', 'min:8', 'max:255', new NotCommonPassword],
```

Messages:

- `password.required` and `password.min`: "Use at least 8 characters."
- `password.max`: "Keep the password under 255 characters."
- `NotCommonPassword`: "That password is too common. Add a word only you would use."

`NotCommonPassword` lowercases the value and rejects an exact match against a
local list of roughly 40 common passwords plus the pattern
`^(password|qwerty|letmein|welcome|admin|iloveyou)\d*$`. It makes no network
request. `secret123`, used by the existing registration tests, is not on the list
and remains valid.

## Action matrix

### Confirmation dialogs

| Action | Where | Status |
|---|---|---|
| Log out | rail + mobile sheet | New |
| Submit team registration | team register, final step | New |
| Publish announcement | announcement composer | New |
| Delete / archive / restore tournament | tournaments index + tournament desk | Existing, migrated |
| Delete announcement | announcements feed | Existing, migrated |
| Remove player | my team | Existing, migrated |
| Approve / reject team | submissions | Existing, unchanged |
| Generate / regenerate bracket, generate playoffs | bracket | Existing, unchanged |
| Publish / rebuild schedule | schedule | Existing, unchanged |
| Finalize / correct score, remove non-empty overtime | score console, game stats, game manage | Existing, unchanged |

Team registration confirms because it creates a team, a roster, a registration, and
a payment in one transaction. It is not safely undoable in a single step, so it gets
a summary dialog instead. The form submit switches from `wire:submit="save"` to
`wire:submit="requestSave"`; `requestSave()` runs the cheap tournament/status guards,
stores the summary fields, and opens the dialog. The full validation and the
existing save transaction move to `confirmSave()`, which the dialog's primary button
calls.

The announcement composer follows the same pattern: `requestPublish()` opens the
dialog and `confirmPublish()` runs the existing save. The live preview stays
visible behind the dialog.

> **Submit this entry?**
> San Antonio Vipers enters the CDO Classic (12U) with 10 players. The entry fee is
> PHP 1,500. You can still edit the roster until the organizer approves it.

Announcement publish confirms and also offers undo: the confirmation catches a
wrong audience, and the undo catches a typo or duplicate after the fact.

### Undo actions

| Action key | Where | Inverse | Audit row? |
|---|---|---|---|
| `tournament.archive` | index, desk | Clear `archived_at` | Yes |
| `tournament.restore` | index, desk | Set `archived_at` | Yes |
| `tournament.delete` | index, desk | Restore soft-deleted row | Yes |
| `tournament.create` | create wizard | Soft-delete the new tournament if it has no registrations or matches | Yes |
| `announcement.delete` | announcements feed | Restore soft-deleted row | No |
| `announcement.pin` | announcements feed | Restore previous `pinned` value | No |
| `announcement.publish` | announcement composer | Soft-delete the published row | No |
| `player.create` | my team | Soft-delete the new player | No |
| `player.update` | my team | Restore previous name, number, birthdate, position | No |
| `player.remove` | my team | Restore soft-deleted row | No |
| `player.consent` | submissions | Restore consent and derived eligibility fields | No |
| `player.document` | submissions | Restore or delete the document and eligibility fields | No |
| `payment.verify` | payments | Restore method, reference, amount, status, paid date | Yes |
| `payment.reference` | my team | Restore previous payment, or delete a newly created one | No |
| `registration.approve` | submissions | Restore previous status, decision fields, roster lock | No |
| `registration.reject` | submissions | Restore previous status and clear the rejection reason | No |

### No extra prompt

Login, filters, wizard steps, preview build/reset, bracket slot draft edits, score
+/-, overtime add, and draft saves stay immediate. This is where confirmation
fatigue would otherwise creep in.

## Undo payloads and stale-state checks

Each payload carries the identifiers plus a `before` and `after` snapshot where the
handler needs to detect concurrent changes.

| Action | Payload | Refuses when |
|---|---|---|
| `tournament.archive` | `id` | `archived_at` is already null |
| `tournament.restore` | `id` | `archived_at` is already set |
| `tournament.delete` | `id` | row is not trashed |
| `tournament.create` | `id` | row is missing, trashed, or has registrations/matches |
| `announcement.delete` | `id` | row is not trashed |
| `announcement.pin` | `id`, `before.pinned`, `after.pinned` | current `pinned` differs from `after.pinned` |
| `announcement.publish` | `id` | row is missing or already trashed |
| `player.create` | `id`, `team_id` | row is missing, not on that team, or roster is locked |
| `player.update` | `id`, `team_id`, `before`, `after` | current attributes differ from `after` or roster is locked |
| `player.remove` | `id`, `team_id` | row is not trashed or roster is locked |
| `player.consent` | `player_id`, `before`, `after` | current consent differs from `after` |
| `player.document` | `player_id`, `document_existed`, `before`, `after` | current document differs from `after` |
| `payment.verify` | `payment_id`, `before`, `after` | current payment differs from `after` |
| `payment.reference` | `payment_id`, `registration_id`, `team_id`, `before` (nullable), `after` | current payment differs from `after` |
| `registration.approve` | `registration_id`, `before`, `after` | current status is not `approved` |
| `registration.reject` | `registration_id`, `before`, `after` | current status is not `rejected` |

`player.document` stores `document_existed` so undo can delete a document that the
review created. `player.consent` and `player.document` store the derived
`eligibility_status`, `verified_by`, and `verified_at` fields because
`EligibilityService::recalculate()` changes them.

Undoing a tournament creation removes only the tournament row. The venue and courts
created by the same wizard transaction remain, because they are reusable records
and other tournaments may already share them.

## Data changes

Two additive migrations:

1. `add_soft_deletes_to_announcements_table` - `$table->softDeletes()` and
   `SoftDeletes` on `App\Models\Announcement`.
2. `add_soft_deletes_to_players_table` - `$table->softDeletes()` and
   `SoftDeletes` on `App\Models\Player`.

No new tables. No index changes. Tournaments already have `deleted_at`.

Effects to watch:

- Eloquent queries, `withCount`, and relationship loads exclude soft-deleted rows
  automatically. Roster minimums, feed counts, and team stats keep their current
  behavior.
- Raw SQL or `DB::table()` calls must be checked for soft-deleted rows. A repository
  search found none for `players` or `announcements`.
- Tests that asserted `assertDatabaseMissing` after a removal become
  `assertSoftDeleted`.
- Restoring a player does not restore the eligibility document automatically; the
  document was never deleted, only its player row. `player.document` undo restores
  the document fields it changed.

## Security

- Tokens are opaque, random, user-bound, single-use, and expire after 10 minutes.
- `POST` only, inside the `auth` middleware, CSRF-protected by the framework.
- Every handler re-checks authorization at execution time:
  - tournament actions use the existing `Gate` checks;
  - announcements, consent, document, payment verification, and registration
    decisions require the organizer role;
  - player and payment-reference actions require `coach_user_id` ownership of the
    team, and the player/payment must still belong to it.
- Handler state checks prevent an older undo from overwriting a newer decision.
- Invalid, expired, foreign, or reused tokens redirect back with a warning toast,
  not a 404 or 500. The message never reveals whether the token existed.
- The Undo URL is generated server-side; the token is never rendered into a
  JavaScript variable beyond the toast payload.
- Logout still invalidates the session, which discards all pending tokens.

## Error states

| Situation | Toast |
|---|---|
| Token older than 10 minutes | "That undo expired. The change is permanent." |
| Unknown, foreign, or already-used token | "That undo is no longer available." |
| Tournament has activity | "That tournament already has activity, so it can't be removed." |
| Roster locked | "The roster is locked, so this can't be undone." |
| Registration or payment changed again | "Someone already changed this. Nothing was overwritten." |
| Row already gone | "There is nothing left to undo." |
| Unauthorized handler | "You don't have permission to undo that action." |

## Audit rule

The original action keeps its existing audit entry (for example
`team.roster.remove`, `payment.verify`, `announcement.delete`). Undo does not add a
second entry for routine actions. This keeps the log readable and avoids duplicate
noise for pins, roster edits, consent toggles, and reference changes.

Exceptions, where the trail would otherwise be misleading:

- `tournament.archive.undo`, `tournament.restore.undo`, `tournament.delete.undo`,
  and `tournament.create.undo`
- `payment.verify.undo`

These use `AuditLogger::log()` with the same entity type and a before/after pair
that describes the reversal. All other handlers stay silent.

## Accessibility

- Confirm dialogs keep native `<dialog>` focus trapping, Escape to cancel, and the
  existing focus-restore logic. Cancel has focus, not the destructive button.
- Undo is a real `<button>`, not a link, and sits in the same place in every toast.
- Toasts keep `aria-live="polite"`; danger toasts keep `role="alert"`.
- The strength meter exposes `role="meter"`, `aria-valuemin/max/now`, and a readable
  `aria-valuetext` such as "Fair". The hint is a polite live region.
- Each checklist item announces "Met" or "Not met" through visually hidden text,
  not color alone.
- The Show/Hide password toggle uses `aria-pressed` and a text label, matching
  login.
- The logout dialog is labelled by its heading and described by its body.
- Reduced-motion rules already guard toast and dialog animation; no new motion is
  added.

## Testing

### New test files

| File | Cases |
|---|---|
| `tests/Feature/Undo/UndoTokenTest.php` | user binding, single use, 10-minute expiry via `travel()`, newest-10 pruning, unknown token |
| `tests/Feature/Undo/UndoHandlersTest.php` | happy path per action, state refusals, roster lock, tournament activity, document creation reversal |
| `tests/Feature/Undo/UndoEndpointTest.php` | POST restores state, redirects back, flashes `hf_toast`, warning for bad/reused/foreign tokens |
| `tests/Feature/Undo/UndoAuthorizationTest.php` | coach cannot undo tournaments, another coach's player, or another coach's payment reference; organizer-only actions stay organizer-only |
| `tests/Feature/Undo/UndoAuditTest.php` | routine undo writes no audit row; tournament and payment undo write exactly one |
| `tests/Feature/Auth/PasswordStrengthTest.php` | 7 characters rejected, `password` rejected as common, `secret123` accepted, meter and checklist render |
| `tests/js/password-strength.test.js` | each class, score, labels, missing list, hint copy, empty state |

### Existing test updates

- `tests/Feature/Pages/TeamsTest.php`: the remove-player assertion becomes
  `assertSoftDeleted`, and the undoable toast is asserted with
  `actionLabel: 'Undo'` and a `/undo/` URL.
- `tests/Feature/Pages/AnnouncementsTest.php`: delete becomes `assertSoftDeleted`,
  and the publish path asserts the new confirmation and undo payload.
- `tests/Feature/Pages/TournamentsTest.php`: archive, restore, and delete assert an
  undo action URL in the dispatched or flashed toast.
- `tests/Feature/Pages/SubmissionsPaymentsTest.php`: approve/reject/verify assert
  the toast's undo payload.
- `tests/Feature/Accessibility/FormsTest.php`: add the meter roles, checklist
  labels, and logout dialog title to the existing structure checks.
- Any test that counts players or announcements after removal is updated to use
  `withTrashed()` or `assertSoftDeleted`.

### Manual checks

- Register with `short`, `password`, `secret123`, and a 12+ mixed password; confirm
  the meter, hint, checklist, and server errors agree.
- Delete and undo each entity from its own screen.
- Hover and focus a toast; the timer pauses and the Undo button stays reachable.
- Trigger two undos in two tabs; the second token still works independently.
- Keyboard-only pass: open and close each dialog, confirm focus returns to the
  trigger, tab into a toast's Undo button.
- Check mobile toasts clear the bottom navigation, and dark theme keeps the meter
  tones legible.

### Gates

```powershell
cd src
composer quality        # Pint + Larastan level 6 + Pest
npm run build           # Vite production build
node --test tests/js    # password-strength + calendar
```

The actual output is reported. Environment-only failures (for example a
Postgres-only scheduling test on SQLite) are named explicitly.

## Rollout order

1. Undo core: service, registry, contract, result/exception, route, controller,
   toast POST support, token tests.
2. Soft deletes for players and announcements; five handlers and their tests.
3. Wire undo into the existing Livewire actions and migrate the four simple
   dialogs to `<x-confirm-dialog>`.
4. Logout dialog, team-registration confirmation, announcement publish
   confirmation.
5. Password rule, strength module, register layout, JS test.
6. Update affected tests, then run the full gates.

## Risks and trade-offs

- **Session-scoped undo.** A user who clears cookies or logs out loses pending
  undos. That is acceptable for a 10-minute convenience, and it avoids a new table
  plus cleanup job.
- **Soft deletes change test semantics.** `assertDatabaseMissing` no longer proves
  removal. The spec updates the affected assertions and adds `assertSoftDeleted`
  coverage.
- **Undo is not a rewind.** It restores the recorded fields; it does not replay
  downstream side effects. For example, undoing a player edit after a birthdate
  change does not re-approve a document that was reset. The handler's state check
  and the message keep this honest.
- **Token in the session can grow.** The service prunes expired entries and caps
  the list at 10, so the payload stays bounded.
- **Password classes are advisory.** A nine-character all-lowercase password can
  pass if it is not common. This matches the chosen policy; the meter still shows
  how to make it stronger.
