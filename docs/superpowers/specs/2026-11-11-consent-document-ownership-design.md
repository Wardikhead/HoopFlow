# Consent & Document Ownership + Birthdate Validation

**Date:** 2026-11-11
**Status:** Approved design
**Scope:** `src/` Laravel application

## 1. Problem

The coach currently controls guardian consent and document status in two places:

- **Register team** — the coach ticks consent and can mark documents `verified`.
- **My Team** — the coach can still change consent and document status after submission.

That makes organizer verification meaningless, and it leaves no distinction between a status that has never been reviewed and one that is missing. The player age field is also a free number, so the stored `birthdate` is fabricated from an entered age and cannot be validated against the division's real age limits.

## 2. Decisions

1. **After submission, only the organizer (the app's "admin") controls consent and document status.** The coach edits player identity fields only.
2. **The coach enters no consent or document data during registration.** Every player starts with consent `pending` and documents `pending`; the organizer completes the review in Submissions.
3. **Birthdate replaces age** in both coach screens and is validated against the division's `age_min` / `age_max`, measured on the tournament start date.
4. **Editing a player's birthdate resets that player's document review** to `pending`. Name, jersey, and position edits do not.
5. **Approval warns but does not block** when consent/documents are incomplete. The organizer must tick an explicit override.
6. **The organizer account is the admin.** No new role or route access is introduced.

## 3. Data model

### 3.1 `players` additions

| Column | Type | Notes |
|---|---|---|
| `consent_status` | string, default `pending` | `pending`, `verified`, `missing` |
| `consent_recorded_by` | nullable FK → `users`, `nullOnDelete` | organizer who last recorded consent |
| `consent_recorded_at` | nullable timestamp | when consent was last recorded |

`consent_at` keeps its existing meaning as the timestamp consent was verified:

- `consent_status = verified` → `consent_at` is set.
- `consent_status = pending` or `missing` → `consent_at` is null.

Backfill in the migration:

- Existing rows with `consent_at` not null → `consent_status = verified`, `consent_recorded_at = consent_at`.
- All other rows → `consent_status = pending`.

### 3.2 `eligibility_documents`

- `file_url` becomes nullable. There is no upload flow yet, so a pending review row has no file.
- Add a unique index on `(player_id, type)`.
- Before adding the index, dedupe existing rows by keeping the highest `id` per `(player_id, type)`.
- `status` values remain `pending`, `approved`, `missing`.
- Reviewer metadata remains `reviewed_by`, `reviewed_at`, `notes`.

Down migration:

- Replace null `file_url` values with the placeholder `pending-review` before restoring the non-null constraint.
- Drop the unique index.
- Drop the consent columns.

### 3.3 Status meanings

| Layer | Values |
|---|---|
| Consent (`players.consent_status`) | `pending`, `verified`, `missing` |
| Document (`eligibility_documents.status`) | `pending`, `approved`, `missing` |
| Player eligibility (`players.eligibility_status`) | `pending`, `verified`, `missing` |

The UI labels document `approved` as **Verified**.

## 4. Domain service

`App\Services\EligibilityService` becomes the only writer of consent, document, and eligibility status.

### 4.1 Public API

- `initializePlayer(Player $player): void`
  - `consent_status = pending`, `consent_at = null`
  - creates one `birth_certificate` document row with `status = pending`, `file_url = null`
  - recalculates `eligibility_status`

- `recordConsent(Player $player, string $status, ?User $actor = null): void`
  - accepts `pending`, `verified`, `missing`
  - sets `consent_status`
  - sets `consent_at = now()` only for `verified`, otherwise null
  - sets `consent_recorded_by` / `consent_recorded_at`
  - recalculates eligibility

- `reviewDocument(Player $player, string $status, ?User $actor = null): void`
  - accepts `pending`, `approved`, `missing`
  - updates or creates the `birth_certificate` row
  - for `approved`: `reviewed_by = actor`, `reviewed_at = now()`
  - for `pending` / `missing`: clear `reviewed_by` and `reviewed_at`
  - recalculates eligibility

- `resetDocumentReview(Player $player): void`
  - sets the document row to `pending`
  - clears reviewer metadata
  - recalculates eligibility

- `recalculate(Player $player, ?User $actor = null): void`
  - `verified` only when `consent_status = verified` and the document is `approved`
  - `missing` when consent is `missing` or the document is `missing`
  - otherwise `pending`
  - when the result is `verified`, set `verified_by` / `verified_at` from the actor and current time if they were empty
  - when the result is not `verified`, clear `verified_by` / `verified_at`

- `status(Team $team): array{documentsComplete: bool, consentComplete: bool, verifiedAt: mixed}`
  - keeps its current return shape
  - `consentComplete` now checks `consent_status = verified`
  - `documentsComplete` checks the document row is `approved`

- `documentStatusForPlayer(Player $player): string`
  - keeps returning `verified`, `pending`, or `missing`
  - a player with no document row returns `pending` (it no longer trusts `players.eligibility_status`)

- `refresh(Team $team): void`
  - recalculates every player through `recalculate`

`syncDocumentStatus()` is removed. All callers move to the API above.

### 4.2 Audit

The Submissions component writes:

- `player.consent.update` — `entity_type = player`, before/after contain `consent_status`
- `player.document.review` — `entity_type = player`, before/after contain document `status`

Each entry includes the organizer as `actor_user_id` and the player's team organization.

## 5. Coach flows

### 5.1 Register team (`App\Livewire\Coach\TeamRegister`)

- Remove `guardianConsent` and `documentStatus` from the player array and validation.
- Replace `age` with `birthdate` (`Y-m-d`).
- Demo data generates birthdates from the demo ages relative to the tournament start date.
- On save:
  - create the player with name, jersey number, position, and birthdate
  - call `EligibilityService::initializePlayer()`
  - never write consent or document status from the coach request

UI:

- Step 1 player row: name, jersey number, position, birthdate (`<input type="date">`).
- Remove the Documents select and Guardian consent checkbox.
- Update helper copy to say consent and documents are reviewed by the organizer after submission.
- Review step shows the computed age and a single eligibility state of “Pending organizer review”.

### 5.2 My Team (`App\Livewire\Coach\MyTeam`)

- Replace `playerAge` with `playerBirthdate`.
- Remove `playerConsent` and `playerDocumentStatus` properties, validation rules, and dialog fields.
- The edit dialog contains name, jersey number, position, and birthdate only.
- The roster table keeps read-only Consent and Documents chips.
- New players are initialized through `EligibilityService::initializePlayer()`.
- On update:
  - save name, jersey number, position, and birthdate
  - if the birthdate changed, call `resetDocumentReview()` for that player
  - do not change consent or document status otherwise
- The existing roster lock after approval still blocks coach edits.

### 5.3 Birthdate validation

New `App\Rules\EligibleBirthdate` rule, used by both coach components:

- valid `Y-m-d` date;
- not in the future;
- age measured on the tournament start date (today if no start date);
- age between the division's `age_min` and `age_max`, falling back to 8–99 when either is null.

Error message example:

> Player must be 13–15 for Under 15 (U15) at tournament start.

The old `divisionLimit` value parsed from a division name is removed from both components.

## 6. Organizer flow

### 6.1 Submissions review dialog

`App\Livewire\Organizer\SubmissionsIndex` adds:

- `updateConsent(int $playerId, string $status)`
- `updateDocument(int $playerId, string $status)`

Behavior:

- validate the status against the allowed values;
- load the player through the reviewed registration so an organizer cannot edit an arbitrary player;
- call the matching `EligibilityService` method with the authenticated organizer;
- write the audit entry;
- clear the relevant computed properties so the dialog and list refresh;
- dispatch an `hf-toast` confirmation.

UI:

- The review roster table gains two inline selects per player:
  - **Consent** — Pending / Verified / Missing
  - **Documents** — Pending / Verified / Missing
- Selects save with `wire:change`; there is no separate save button.
- Status editing is available when the registration is `submitted` or `approved`; `rejected` is read-only.
- The dialog eligibility chips and the list-card chips read the updated state.

### 6.2 Approval warning

- `approvalIssues(int $registrationId): array` returns players whose consent is not `verified` or whose document is not `approved`.
- The approve confirmation dialog shows the issue list when non-empty.
- An `approveAnyway` checkbox is required before the confirm button enables.
- When there are no issues, the normal confirmation is shown.
- `doApprove()` sets `roster_locked_at = now()` in addition to `status`, `decided_by`, and `decided_at`.

## 7. Files expected to change

- `app/Livewire/Coach/TeamRegister.php`
- `app/Livewire/Coach/MyTeam.php`
- `app/Livewire/Organizer/SubmissionsIndex.php`
- `app/Services/EligibilityService.php`
- `app/Models/Player.php`
- `app/Models/EligibilityDocument.php` (if casts or helpers are needed)
- new `app/Rules/EligibleBirthdate.php`
- new migration under `database/migrations/`
- `resources/views/livewire/coach/team-register.blade.php`
- `resources/views/livewire/coach/my-team.blade.php`
- `resources/views/livewire/organizer/submissions-index.blade.php`
- affected tests under `tests/`

## 8. Tests

### Unit

- `EligibilityServiceTest`
  - initialize creates pending consent and one pending document
  - recordConsent verified/missing/pending updates timestamps and recorder
  - reviewDocument approved/missing/pending updates reviewer metadata
  - recalculate derives `verified` / `missing` / `pending` correctly
  - resetDocumentReview clears reviewer metadata
  - `status()` and `documentStatusForPlayer()` use the new states
  - no document row resolves to `pending`

### Feature

- `TeamRegisterTest`
  - persists birthdate instead of age
  - rejects a birthdate outside the division range at tournament start
  - creates pending consent and pending document rows
  - ignores/does not expose coach consent or document input
- `MyTeamTest`
  - coach edits name, jersey, position, and birthdate
  - coach cannot update consent or document status
  - birthdate change resets document review
  - out-of-range birthdate fails validation
- `SubmissionsPaymentsTest`
  - organizer updates consent inline
  - organizer updates document status inline
  - both write audit entries
  - coach is forbidden from Submissions
  - incomplete approval requires the override checkbox
  - approval sets `roster_locked_at`

## 9. Out of scope

- Document file uploads or storage.
- Consent versioning/history tables.
- New roles or route permissions.
- UI redesign beyond the fields and controls described above.

## 10. Quality gate

- Run `php artisan migrate` against the local PostgreSQL database.
- Run `composer quality` (Pint, Larastan level 6, Pest) and report the actual output.
- Keep the existing Bootstrap/Livewire patterns; no Tailwind, no JS framework, no Bootstrap JS.
