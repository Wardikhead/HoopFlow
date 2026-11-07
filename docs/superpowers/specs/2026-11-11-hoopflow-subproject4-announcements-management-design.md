# Sub-project 4: Announcements management

- **Date:** 2026-11-11
- **Owner:** Jeward T. Mahinay
- **Status:** Draft for review
- **Parent:** [[2026-10-30-prototype-ui-interactivity-design]]
- **Scope:** prototype only (`prototype/`, `docs/superpowers/`).

## Problem

The announcements feed is read-only for most roles and only organizers can publish. There is no way to view a single announcement in detail, delete an announcement, or unpin one. Audience targeting is limited to role buckets (`all`, `coaches`, `organizers`) — there is no way to send a notice to a specific coach or to fans as a distinct group.

## Goals

1. **View single announcement:** Clicking an announcement card opens a modal with full title/body/meta and action buttons.
2. **Pin/unpin:** Toggle the pinned state from the modal (and optionally from the card). Writes an audit entry.
3. **Delete:** Delete an announcement with confirmation. Writes an audit entry.
4. **Audience targeting:** Add `fans` as a role bucket and `specific` (target individual users via `targetUserIds`). Feed filters by target.

## Non-goals

- No rich text / attachments.
- No change to `src/`.

## Decisions

### 1. Data model

Announcement gains:
- `audience: 'all' | 'coaches' | 'organizers' | 'fans' | 'specific'`
- `targetUserIds: string[]` (empty unless audience === 'specific')

`ANNOUNCEMENT_AUDIENCES` updated to include `'fans'` and `'specific'`.

### 2. Store actions

- `publishAnnouncement` (existing): accept `targetUserIds` array; validate when audience === 'specific' that at least one valid user id is provided.
- `deleteAnnouncement(id)`: requires `announcement.publish` (organizer only). Removes the announcement. Audit entry `announcement.delete`.
- `pinAnnouncement(id, pinned)`: requires `announcement.publish`. Toggles pinned. Audit entry `announcement.pin` / `announcement.unpin`.

### 3. Feed filtering

An announcement is visible to the current user if:
- `audience === 'all'`, OR
- `audience === 'coaches'` && role === 'coach', OR
- `audience === 'organizers'` && role === 'organizer', OR
- `audience === 'fans'` && role === 'fan', OR
- `audience === 'specific'` && `targetUserIds.includes(currentUserId)`.

`AUDIENCE_BY_ROLE` updated: organizer sees all; coach sees all/coaches/fans/specific (if targeted); fan sees all/fans/specific (if targeted).

### 4. View single announcement modal

- Clicking an announcement card (or a "View" button) opens a wide dialog with full title, body, category, audience, author, date, pinned status, tournament name.
- Footer actions (organizer only): Pin/Unpin toggle, Delete. Non-organizers see "Close" only.

### 5. Composer changes

- Audience select gains `Fans` and `Specific user` options.
- When "Specific user" selected, reveal a multi-select list of users (organizers, coaches, fans) grouped by role. Selected ids stored in `targetUserIds`.
- Live preview updates to show the selected audience.

### 6. Acceptance criteria

- Organizer can view, pin/unpin, and delete announcements from the modal.
- Audience targeting works: specific-user announcements only appear for targeted users.
- Fans see fan-targeted and all announcements.
- All existing tests pass; `node --test` green; static sweeps clean.

## Open questions

- Should delete be soft (mark deleted) or hard (remove)? (Recommend: hard remove, consistent with prototype simplicity.)
- Should pin/unpin be available from the card (quick action) or only the modal? (Recommend: modal only, to keep cards clean.)

---

Spec written for review. Confirm or request changes before I write the implementation plan.