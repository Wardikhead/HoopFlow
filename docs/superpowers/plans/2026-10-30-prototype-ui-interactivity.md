# Prototype UI & Interactivity Enhancement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the static HoopFlow prototype into a demo-safe, courtside-broadcast click-through with one normalized store, four roles, and every journey in the 10-step demo script.

**Architecture:** Build-free ES modules under `prototype/assets/js/`. `seed.js` exports deterministic demo data. `store.js` owns schema v2, permissions, actions, selectors, and audit. `ui.js` and `components.js` own shared rendering. `shell.js` renders navigation and demo controls. `app.js` dynamically loads one page module per `data-page`. Pages never touch `localStorage` directly. Bootstrap CSS/JS, icons, and fonts are vendored locally.

**Tech Stack:** Static HTML, CSS custom properties, vanilla ES modules, Node's built-in test runner, Bootstrap 5.3 (vendored), Bootstrap Icons (vendored), Barlow Condensed + Inter (self-hosted), `php -S` for serving.

## Global Constraints

- Work only in `prototype/`, `tools/`, and `docs/superpowers/`. Do not touch `src/`, `docs/wireframes/`, `HoopFlow/`, `logo/`, `video/`, or `_archive/`.
- Start by creating branch `feat/prototype-ui-interactivity` from the current HEAD.
- All existing prototype routes stay valid. Add only `teams/register.html` and `profile/audit.html`.
- Runtime must work offline. No `https://` asset references in prototype HTML or CSS.
- No `alert()`, `confirm()`, or `prompt()`. Use `ui.toast()` and `ui.confirm()`.
- No client-side framework, no build step, no runtime npm dependencies. `prototype/package.json` exists only for Node tests and sets `"type": "module"`.
- Store key is `hoopflow_db`, `schemaVersion: 2`, `seedVersion: '2026-10-30'`.
- Store status values are lowercase slugs: team `pending|approved|rejected`; payment `paid|partial|pending`; match `scheduled|final|forfeit`; tournament `draft|registration-open|active|upcoming`.
- Four roles: `organizer`, `scorekeeper`, `coach`, `fan`. Organizer has `*`. Scorekeeper has `score.save`, `score.correct`, `stats.view`. Coach has `team.register`, `team.edit`, `payment.submit`, `team.view.own`. Fan has `public.view`.
- Single elimination only. The round-robin option is disabled with a "v2" note.
- Every demo-critical interactive control has a `data-testid`: all buttons (including shared dialog and toast buttons), every form field, and every link on a ten-step demo path. Navigation-only anchors outside the demo path do not need test IDs.
- 44px minimum touch targets, visible focus rings, AA contrast, and `prefers-reduced-motion` support.
- Demo resolutions are 1366×768 and 390×844.
- Every task ends with `git add` and `git commit`.
- Node tests: `cd prototype` then `node --test tests` inside the same command block. Every command block starts at the repository root, so never carry a `cd` into a later block; stage root-relative paths directly.
- Browser checks: serve with `php -S 127.0.0.1:8001 -t prototype`, then open `http://127.0.0.1:8001`.
- Commit only files touched by the task. Never stage `docs/wireframes/`.

---

## Task 1: Vendor runtime assets

**Files:**
- Create: `tools/vendor-prototype-assets.ps1`
- Create: `prototype/assets/vendor/bootstrap/bootstrap.min.css`
- Create: `prototype/assets/vendor/bootstrap/bootstrap.bundle.min.js`
- Create: `prototype/assets/vendor/bootstrap-icons/bootstrap-icons.min.css`
- Create: `prototype/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2`
- Create: `prototype/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff`
- Create: `prototype/assets/vendor/fonts/fonts.css`
- Create: `prototype/assets/vendor/fonts/font-*.woff2`

**Interfaces:**
- Produces: local stylesheet URLs `../assets/vendor/bootstrap/bootstrap.min.css`, `../assets/vendor/bootstrap-icons/bootstrap-icons.min.css`, `../assets/vendor/bootstrap/bootstrap.bundle.min.js`, and the `@font-face` rules imported by `hoopflow.css`.

- [ ] **Step 1: Create the feature branch**

```powershell
git switch -c feat/prototype-ui-interactivity
```

- [ ] **Step 2: Write the vendoring script**

Create `tools/vendor-prototype-assets.ps1` with this exact content:

```powershell
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$vendor = Join-Path $root 'prototype/assets/vendor'
New-Item -ItemType Directory -Force -Path "$vendor/bootstrap" | Out-Null
New-Item -ItemType Directory -Force -Path "$vendor/bootstrap-icons/fonts" | Out-Null
New-Item -ItemType Directory -Force -Path "$vendor/fonts" | Out-Null

$files = [ordered]@{
  'bootstrap/bootstrap.min.css' = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'
  'bootstrap/bootstrap.bundle.min.js' = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'
  'bootstrap-icons/bootstrap-icons.min.css' = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css'
  'bootstrap-icons/fonts/bootstrap-icons.woff2' = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2'
  'bootstrap-icons/fonts/bootstrap-icons.woff' = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff'
}
foreach ($pair in $files.GetEnumerator()) {
  Invoke-WebRequest -Uri $pair.Value -OutFile (Join-Path $vendor $pair.Key)
}

$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36'
$families = @(
  'Barlow+Condensed:wght@600;700;800',
  'Inter:wght@400;500;600;700'
)
$cssParts = foreach ($family in $families) {
  $url = "https://fonts.googleapis.com/css2?family=$family&display=swap&subset=latin"
  (Invoke-WebRequest -Uri $url -Headers @{ 'User-Agent' = $ua }).Content
}
$fontCss = $cssParts -join "`n"
$script:fontIndex = 0
$fontCss = [regex]::Replace($fontCss, 'url\((https://[^)]+)\)', {
  param($match)
  $script:fontIndex++
  $source = $match.Groups[1].Value
  $extension = if ($source -match '\.woff2') { 'woff2' } else { 'woff' }
  $name = "font-$script:fontIndex.$extension"
  Invoke-WebRequest -Uri $source -OutFile (Join-Path $vendor "fonts/$name")
  "url('./$name')"
})
$fontCss | Set-Content -Path (Join-Path $vendor 'fonts/fonts.css') -Encoding utf8
```

- [ ] **Step 3: Run the script**

```powershell
powershell -ExecutionPolicy Bypass -File tools/vendor-prototype-assets.ps1
```

- [ ] **Step 4: Verify the assets are local**

```powershell
Get-ChildItem prototype/assets/vendor -Recurse -File | Select-Object FullName,Length
Select-String -Path prototype/assets/vendor/**/*.css -Pattern 'https?://'
```

Expected: every file has a non-zero length; the second command returns no matches except comments if any. If Bootstrap Icons CSS references `./fonts/...`, confirm the two font files exist at that relative path.

- [ ] **Step 5: Commit**

```powershell
git add tools/vendor-prototype-assets.ps1 prototype/assets/vendor
git commit -m "chore(prototype): vendor runtime assets for offline demos"
```

---

## Task 2: Seed data and Node test harness

**Files:**
- Create: `prototype/package.json`
- Create: `prototype/assets/js/seed.js`
- Create: `prototype/tests/helpers.mjs`
- Create: `prototype/tests/seed.test.mjs`

**Interfaces:**
- Produces: `SEED` (complete store object) and `SEED_VERSION = '2026-10-30'` from `seed.js`; `memoryStorage(initial = {})` from `tests/helpers.mjs`.

- [ ] **Step 1: Write the test harness and failing seed tests**

Create `prototype/package.json`:

```json
{
  "name": "hoopflow-prototype",
  "private": true,
  "type": "module",
  "scripts": {
    "test": "node --test tests"
  }
}
```

Create `prototype/tests/helpers.mjs`:

```js
export function memoryStorage(initial = {}) {
  const map = new Map(Object.entries(initial));
  return {
    getItem: (key) => (map.has(key) ? map.get(key) : null),
    setItem: (key, value) => map.set(key, String(value)),
    removeItem: (key) => map.delete(key),
    clear: () => map.clear(),
    key: (index) => [...map.keys()][index] ?? null,
    get length() {
      return map.size;
    },
  };
}
```

Create `prototype/tests/seed.test.mjs`:

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { SEED, SEED_VERSION } from '../assets/js/seed.js';

test('seed has the versioned schema and three tournaments', () => {
  assert.equal(SEED_VERSION, '2026-10-30');
  assert.equal(SEED.schemaVersion, 2);
  assert.equal(SEED.seedVersion, SEED_VERSION);
  assert.equal(SEED.tournaments.length, 3);
});

test('active cup has 16 registrations, 15 eligible, and one bye', () => {
  const active = SEED.tournaments.find((t) => t.id === 'tournament-1');
  const teams = SEED.teams.filter((t) => t.tournamentId === active.id);
  const approved = teams.filter((t) => t.status === 'approved');
  const rejected = teams.filter((t) => t.status === 'rejected');
  assert.equal(teams.length, 16);
  assert.equal(approved.length, 15);
  assert.equal(rejected.length, 1);
  assert.equal(SEED.matches.filter((m) => m.tournamentId === active.id && m.isBye).length, 1);
});

test('active cup has one forfeit and one corrected score', () => {
  const matches = SEED.matches.filter((m) => m.tournamentId === 'tournament-1');
  assert.equal(matches.filter((m) => m.status === 'forfeit').length, 1);
  assert.equal(matches.filter((m) => Boolean(m.correction)).length, 1);
  assert.equal(matches.length, 15);
});

test('open cup has a non-empty review queue and no bracket', () => {
  const teams = SEED.teams.filter((t) => t.tournamentId === 'tournament-2');
  assert.equal(teams.filter((t) => t.status === 'pending').length, 2);
  assert.equal(SEED.matches.filter((m) => m.tournamentId === 'tournament-2').length, 0);
});

test('CDO has eight approved teams and no bracket', () => {
  const teams = SEED.teams.filter((t) => t.tournamentId === 'tournament-3');
  assert.equal(teams.length, 8);
  assert.equal(teams.every((t) => t.status === 'approved'), true);
  assert.equal(SEED.matches.filter((m) => m.tournamentId === 'tournament-3').length, 0);
});

test('seed has four role users and audit history', () => {
  assert.deepEqual(
    SEED.users.map((u) => u.role).sort(),
    ['coach', 'fan', 'organizer', 'scorekeeper']
  );
  assert.ok(SEED.audit.length >= 6);
  assert.ok(SEED.announcements.length >= 4);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```powershell
cd prototype
node --test tests
```

Expected: FAIL because `../assets/js/seed.js` does not exist.

- [ ] **Step 3: Implement `seed.js`**

Create `prototype/assets/js/seed.js`. Use these exact IDs, names, and relationships:

```js
export const SEED_VERSION = '2026-10-30';

const ACTIVE = 'tournament-1';
const OPEN = 'tournament-2';
const CDO = 'tournament-3';

const POSITIONS = [
  'Point Guard (PG)',
  'Shooting Guard (SG)',
  'Small Forward (SF)',
  'Power Forward (PF)',
  'Center (C)',
];

const FIRST_NAMES = [
  'Jayson', 'Kobe', 'Carl', 'Kai', 'June Mar', 'Gabe', 'CJ', 'Dwight',
  'Calvin', 'RR', 'Marc', 'Prince', 'Andre', 'Jolo', 'Troy', 'Luis',
  'Paolo', 'Nico', 'Miguel', 'Rafael',
];
const LAST_NAMES = [
  'Castro', 'Ramos', 'Cruz', 'Gomez', 'Fajardo', 'Diaz', 'Santos', 'Reyes',
  'Bautista', 'Villanueva', 'Macaraeg', 'Torres', 'Aquino', 'Navarro',
  'Mendoza', 'Del Rosario', 'Garcia', 'Lim', 'Tan', 'Pascual',
];

function makePlayers(teamId, teamIndex, divisionMax, count, pending = false) {
  return Array.from({ length: count }, (_, index) => ({
    id: `${teamId}-player-${index + 1}`,
    name: `${FIRST_NAMES[(teamIndex * 7 + index) % FIRST_NAMES.length]} ${LAST_NAMES[(teamIndex * 3 + index) % LAST_NAMES.length]}`,
    number: index + 1,
    position: POSITIONS[index % POSITIONS.length],
    age: divisionMax - (index % 3),
    guardianConsent: pending ? index !== count - 1 : true,
    documentStatus: pending && index === count - 1 ? 'missing' : pending ? 'pending' : 'verified',
  }));
}

function makeTeam({
  id, tournamentId, name, shortName, coach, city, color, seed,
  status = 'approved', paymentStatus = 'paid', paymentRef = '',
  divisionMax, pending = false, submittedAt, rejectionReason = '',
}) {
  return {
    id,
    tournamentId,
    name,
    shortName,
    coach,
    city,
    avatarColor: color,
    seed,
    status,
    rejectionReason,
    submittedAt,
    payment: {
      status: paymentStatus,
      method: paymentStatus === 'paid' ? 'GCash' : '',
      ref: paymentRef,
      amount: tournamentId === CDO ? 1000 : 1500,
      verifiedAt: paymentStatus === 'paid' ? submittedAt : null,
    },
    eligibility: {
      documentsComplete: !pending,
      consentComplete: !pending,
      verifiedAt: status === 'approved' ? submittedAt : null,
    },
    players: makePlayers(id, seed, divisionMax, 10, pending),
  };
}

const activeTeams = [
  ['San Antonio Vipers', 'VPR', 'Marcus Rivera', 'Pasig City', '#ea580c', 'paid', 'GCASH-9823412'],
  ['Kapitolyo Knights', 'KNT', 'Danilo Santos', 'Pasig City', '#2563eb', 'paid', 'MAYA-4412093'],
  ['Mandaluyong Tigers', 'TGR', 'Ramon Bautista', 'Mandaluyong City', '#d97706', 'paid', 'CASH-REC-001'],
  ['Ortigas Raptors', 'RPT', 'Kenneth Cruz', 'Pasig City', '#7c3aed', 'paid', 'BANK-BDO-771'],
  ['Ugong Blue Hawks', 'HWK', 'Allan Gomez', 'Pasig City', '#0284c7', 'paid', 'GCASH-1123498'],
  ['Maybunga Titans', 'TTN', 'Eric Villanueva', 'Pasig City', '#059669', 'pending', ''],
  ['Rosario Strikers', 'STR', 'Joel Macaraeg', 'Pasig City', '#dc2626', 'paid', 'GCASH-5512034'],
  ['Pinagbuhatan Warriors', 'WRR', 'Ferdinand Reyes', 'Pasig City', '#475569', 'pending', ''],
  ['Bagong Ilog Falcons', 'BIF', 'Noel Aquino', 'Pasig City', '#0891b2', 'paid', 'MAYA-7731120'],
  ['Kalawaan Comets', 'KLC', 'Edwin Navarro', 'Pasig City', '#9333ea', 'paid', 'GCASH-4409821'],
  ['Manggahan Jets', 'MGJ', 'Rodel Mendoza', 'Pasig City', '#ca8a04', 'paid', 'CASH-REC-002'],
  ['Santolan Saints', 'STL', 'Arnel Garcia', 'Pasig City', '#16a34a', 'paid', 'GCASH-3321908'],
  ['Dela Paz Dragons', 'DPZ', 'Marlon Lim', 'Pasig City', '#e11d48', 'paid', 'BANK-BPI-204'],
  ['Caniogan Cardinals', 'CNG', 'Boyet Tan', 'Pasig City', '#be123c', 'paid', 'GCASH-9988123'],
  ['Sumilang Spartans', 'SMS', 'Dennis Pascual', 'Pasig City', '#334155', 'paid', 'MAYA-1200345'],
];

const teams = [];
activeTeams.forEach((row, index) => {
  const [name, shortName, coach, city, color, paymentStatus, paymentRef] = row;
  teams.push(makeTeam({
    id: `team-${index + 1}`,
    tournamentId: ACTIVE,
    name,
    shortName,
    coach,
    city,
    color,
    seed: index + 1,
    paymentStatus,
    paymentRef,
    divisionMax: 15,
    submittedAt: '2026-05-02T09:00:00+08:00',
  }));
});
teams.push(makeTeam({
  id: 'team-16',
  tournamentId: ACTIVE,
  name: 'Napico Nuggets',
  shortName: 'NPC',
  coach: 'Vic Sotto',
  city: 'Pasig City',
  color: '#64748b',
  seed: 16,
  status: 'rejected',
  rejectionReason: 'Withdrew before seeding.',
  paymentStatus: 'pending',
  divisionMax: 15,
  submittedAt: '2026-05-02T09:00:00+08:00',
}));

const openTeams = [
  ['Pasig Pirates', 'PSG', 'Alvin Cruz', 'Pasig City', '#0e7490', 'approved', 'paid', 'GCASH-1010101'],
  ['Kapitolyo Kings', 'KNG', 'Bong Reyes', 'Pasig City', '#1d4ed8', 'approved', 'paid', 'MAYA-2020202'],
  ['Ugong United', 'UGU', 'Carlo Santos', 'Pasig City', '#047857', 'approved', 'pending', ''],
  ['Rosario Rockets', 'RCK', 'Dante Lim', 'Pasig City', '#b91c1c', 'approved', 'paid', 'CASH-REC-003'],
  ['Maybunga Mavericks', 'MVR', 'Elmer Tan', 'Pasig City', '#7e22ce', 'approved', 'pending', ''],
  ['Pinagbuhatan Pilots', 'PLT', 'Francisco Diaz', 'Pasig City', '#475569', 'approved', 'paid', 'GCASH-3030303'],
  ['Bagong Ilog Bears', 'BRS', 'Glen Aquino', 'Pasig City', '#a16207', 'pending', 'pending', ''],
  ['Kalawaan Kestrels', 'KST', 'Hector Navarro', 'Pasig City', '#0f766e', 'pending', 'pending', ''],
];
openTeams.forEach((row, index) => {
  const [name, shortName, coach, city, color, status, paymentStatus, paymentRef] = row;
  teams.push(makeTeam({
    id: `team-open-${index + 1}`,
    tournamentId: OPEN,
    name,
    shortName,
    coach,
    city,
    color,
    seed: index + 1,
    status,
    paymentStatus,
    paymentRef,
    divisionMax: 17,
    pending: status === 'pending',
    submittedAt: '2026-05-20T09:00:00+08:00',
  }));
});

const cdoTeams = [
  ['CDO Stags', 'CDS', 'Rico Bautista', 'Cagayan de Oro', '#b45309', '#f59e0b'],
  ['Cagayan Cobras', 'CAG', 'Nestor Lim', 'Cagayan de Oro', '#1e3a8a', '#3b82f6'],
  ['Bukidnon Braves', 'BKB', 'Oscar Reyes', 'Malaybalay', '#166534', '#22c55e'],
  ['Misamis Mariners', 'MSM', 'Paeng Cruz', 'Oroquieta', '#0e7490', '#06b6d4'],
  ['Iligan Ironhawks', 'ILG', 'Quincy Tan', 'Iligan', '#7f1d1d', '#ef4444'],
  ['Gingoog Giants', 'GGG', 'Rene Santos', 'Gingoog', '#4c1d95', '#8b5cf6'],
  ['Valencia Vipers', 'VLV', 'Sandy Gomez', 'Valencia', '#9a3412', '#fb923c'],
  ['Malaybalay Monarchs', 'MBM', 'Tito Diaz', 'Malaybalay', '#334155', '#64748b'],
];
cdoTeams.forEach((row, index) => {
  const [name, shortName, coach, city, color] = row;
  teams.push(makeTeam({
    id: `team-cdo-${index + 1}`,
    tournamentId: CDO,
    name,
    shortName,
    coach,
    city,
    color,
    seed: index + 1,
    divisionMax: 14,
    submittedAt: '2026-06-01T09:00:00+08:00',
  }));
});

const teamId = (name) => teams.find((t) => t.name === name).id;

function makeMatch({
  id, round, roundLabel, bracketSlot, team1Id, team2Id,
  score1 = null, score2 = null, winnerId = null, status = 'scheduled',
  courtId = null, scheduledAt = null, nextMatchId = null, nextSlot = null,
  isBye = false, correction = null, boxScore = null,
}) {
  return {
    id,
    tournamentId: ACTIVE,
    round,
    roundLabel,
    bracketSlot,
    team1Id,
    team2Id,
    score1,
    score2,
    winnerId,
    status,
    courtId,
    scheduledAt,
    nextMatchId,
    nextSlot,
    isBye,
    correction,
    boxScore: boxScore || { team1: [], team2: [] },
  };
}

function splitPoints(total, count = 10) {
  const base = Math.floor(total / count);
  const remainder = total % count;
  return Array.from({ length: count }, (_, index) => base + (index < remainder ? 1 : 0));
}

function makeBoxScore(team, total) {
  const points = splitPoints(total, team.players.length);
  return team.players.map((player, index) => ({
    playerId: player.id,
    points: points[index],
    assists: (index * 3 + 1) % 7,
    rebounds: (index * 2 + 2) % 9,
  }));
}

const vipers = teams.find((t) => t.name === 'San Antonio Vipers');
const warriors = teams.find((t) => t.name === 'Pinagbuhatan Warriors');
const dragons = teams.find((t) => t.name === 'Dela Paz Dragons');
const hawks = teams.find((t) => t.name === 'Ugong Blue Hawks');
const knights = teams.find((t) => t.name === 'Kapitolyo Knights');
const comets = teams.find((t) => t.name === 'Kalawaan Comets');
const tigers = teams.find((t) => t.name === 'Mandaluyong Tigers');
const titans = teams.find((t) => t.name === 'Maybunga Titans');

const matches = [
  makeMatch({ id: 'r16-1', round: '16', roundLabel: 'Round of 16', bracketSlot: 1, team1Id: vipers.id, team2Id: null, status: 'final', winnerId: vipers.id, isBye: true, nextMatchId: 'qf-1', nextSlot: 1 }),
  makeMatch({ id: 'r16-2', round: '16', roundLabel: 'Round of 16', bracketSlot: 2, team1Id: warriors.id, team2Id: teamId('Bagong Ilog Falcons'), score1: 66, score2: 61, winnerId: warriors.id, status: 'final', courtId: 'court-1', scheduledAt: '2026-05-16T09:00:00+08:00', nextMatchId: 'qf-1', nextSlot: 2 }),
  makeMatch({ id: 'r16-3', round: '16', roundLabel: 'Round of 16', bracketSlot: 3, team1Id: teamId('Ortigas Raptors'), team2Id: dragons.id, score1: 69, score2: 72, winnerId: dragons.id, status: 'final', courtId: 'court-2', scheduledAt: '2026-05-16T09:00:00+08:00', nextMatchId: 'qf-2', nextSlot: 1 }),
  makeMatch({ id: 'r16-4', round: '16', roundLabel: 'Round of 16', bracketSlot: 4, team1Id: hawks.id, team2Id: teamId('Santolan Saints'), score1: 70, score2: 64, winnerId: hawks.id, status: 'final', courtId: 'court-3', scheduledAt: '2026-05-16T09:00:00+08:00', nextMatchId: 'qf-2', nextSlot: 2 }),
  makeMatch({ id: 'r16-5', round: '16', roundLabel: 'Round of 16', bracketSlot: 5, team1Id: knights.id, team2Id: teamId('Sumilang Spartans'), score1: 77, score2: 70, winnerId: knights.id, status: 'final', courtId: 'court-4', scheduledAt: '2026-05-16T09:00:00+08:00', nextMatchId: 'qf-3', nextSlot: 1 }),
  makeMatch({ id: 'r16-6', round: '16', roundLabel: 'Round of 16', bracketSlot: 6, team1Id: teamId('Rosario Strikers'), team2Id: comets.id, score1: 66, score2: 68, winnerId: comets.id, status: 'final', courtId: 'court-1', scheduledAt: '2026-05-16T09:30:00+08:00', nextMatchId: 'qf-3', nextSlot: 2 }),
  makeMatch({ id: 'r16-7', round: '16', roundLabel: 'Round of 16', bracketSlot: 7, team1Id: tigers.id, team2Id: teamId('Caniogan Cardinals'), score1: 20, score2: 0, winnerId: tigers.id, status: 'forfeit', courtId: 'court-2', scheduledAt: '2026-05-16T09:30:00+08:00', nextMatchId: 'qf-4', nextSlot: 1 }),
  makeMatch({ id: 'r16-8', round: '16', roundLabel: 'Round of 16', bracketSlot: 8, team1Id: titans.id, team2Id: teamId('Manggahan Jets'), score1: 65, score2: 60, winnerId: titans.id, status: 'final', courtId: 'court-3', scheduledAt: '2026-05-16T09:30:00+08:00', nextMatchId: 'qf-4', nextSlot: 2 }),
  makeMatch({ id: 'qf-1', round: '8', roundLabel: 'Quarterfinals', bracketSlot: 1, team1Id: vipers.id, team2Id: warriors.id, score1: 78, score2: 70, winnerId: vipers.id, status: 'final', courtId: 'court-1', scheduledAt: '2026-05-18T13:00:00+08:00', nextMatchId: 'sf-1', nextSlot: 1, correction: { reason: 'Shot-clock correction after referee review.', previousScore1: 74, previousScore2: 70, correctedAt: '2026-05-18T16:20:00+08:00', correctedBy: 'user-organizer' }, boxScore: { team1: makeBoxScore(vipers, 78), team2: makeBoxScore(warriors, 70) } }),
  makeMatch({ id: 'qf-2', round: '8', roundLabel: 'Quarterfinals', bracketSlot: 2, team1Id: dragons.id, team2Id: hawks.id, score1: 64, score2: 69, winnerId: hawks.id, status: 'final', courtId: 'court-2', scheduledAt: '2026-05-18T13:00:00+08:00', nextMatchId: 'sf-1', nextSlot: 2, boxScore: { team1: makeBoxScore(dragons, 64), team2: makeBoxScore(hawks, 69) } }),
  makeMatch({ id: 'qf-3', round: '8', roundLabel: 'Quarterfinals', bracketSlot: 3, team1Id: knights.id, team2Id: comets.id, status: 'scheduled', courtId: 'court-1', scheduledAt: '2026-05-18T14:00:00+08:00', nextMatchId: 'sf-2', nextSlot: 1 }),
  makeMatch({ id: 'qf-4', round: '8', roundLabel: 'Quarterfinals', bracketSlot: 4, team1Id: tigers.id, team2Id: titans.id, status: 'scheduled', courtId: 'court-2', scheduledAt: '2026-05-18T14:00:00+08:00', nextMatchId: 'sf-2', nextSlot: 2 }),
  makeMatch({ id: 'sf-1', round: '4', roundLabel: 'Semifinals', bracketSlot: 1, team1Id: vipers.id, team2Id: hawks.id, status: 'scheduled', courtId: 'court-1', scheduledAt: '2026-05-20T15:00:00+08:00', nextMatchId: 'final-1', nextSlot: 1 }),
  makeMatch({ id: 'sf-2', round: '4', roundLabel: 'Semifinals', bracketSlot: 2, team1Id: null, team2Id: null, status: 'scheduled', courtId: 'court-1', scheduledAt: '2026-05-20T16:00:00+08:00', nextMatchId: 'final-1', nextSlot: 2 }),
  makeMatch({ id: 'final-1', round: '2', roundLabel: 'Championship Final', bracketSlot: 1, team1Id: null, team2Id: null, status: 'scheduled', courtId: 'court-1', scheduledAt: '2026-05-22T16:00:00+08:00' }),
];

const announcements = [
  {
    id: 'announcement-1',
    tournamentId: ACTIVE,
    title: 'Championship week moves to Court 1',
    body: 'All semifinal and final games will be played on Court 1. Gate opens 45 minutes before tip-off.',
    category: 'venue',
    audience: 'all',
    pinned: true,
    author: 'Tournament Committee',
    createdAt: '2026-05-18T18:00:00+08:00',
  },
  {
    id: 'announcement-2',
    tournamentId: ACTIVE,
    title: 'Roster verification deadline is May 19',
    body: 'Coaches must upload the final PSA birth certificate and guardian consent for every player before the semifinals.',
    category: 'eligibility',
    audience: 'coaches',
    pinned: false,
    author: 'Eligibility Committee',
    createdAt: '2026-05-17T10:00:00+08:00',
  },
  {
    id: 'announcement-3',
    tournamentId: OPEN,
    title: 'Pasig Youth Hoopfest registration closes June 1',
    body: 'Submit your team registration, twelve-player roster, and GCash reference before the deadline.',
    category: 'schedule',
    audience: 'all',
    pinned: false,
    author: 'Tournament Committee',
    createdAt: '2026-05-20T09:00:00+08:00',
  },
  {
    id: 'announcement-4',
    tournamentId: CDO,
    title: 'Court assignments posted for the CDO Classic',
    body: 'The preliminary court assignments are available on the tournament page. Brackets will be generated after the seeding meeting.',
    category: 'venue',
    audience: 'all',
    pinned: false,
    author: 'Venue Operations',
    createdAt: '2026-06-02T11:00:00+08:00',
  },
];

const users = [
  { id: 'user-organizer', name: 'Jeward Mahinay', email: 'organizer@hoopflow.ph', role: 'organizer', organization: 'HoopFlow Tournament Operations', teamId: null },
  { id: 'user-scorekeeper', name: 'Rusty Japos', email: 'scorekeeper@hoopflow.ph', role: 'scorekeeper', organization: 'HoopFlow Tournament Operations', teamId: null },
  { id: 'user-coach', name: 'Coach Marcus Rivera', email: 'coach.marcus@hoopflow.ph', role: 'coach', organization: 'Barangay San Antonio Youth Club', teamId: vipers.id },
  { id: 'user-fan', name: 'Juan Dela Cruz', email: 'fan.juan@hoopflow.ph', role: 'fan', organization: 'Barangay San Antonio', teamId: null },
];

const audit = [
  { id: 'audit-1', at: '2026-05-18T16:20:00+08:00', actorId: 'user-organizer', actorRole: 'organizer', action: 'score.correct', entityType: 'match', entityId: 'qf-1', summary: 'Corrected QF1 from 74-70 to 78-70', changes: { previousScore1: 74, previousScore2: 70, score1: 78, score2: 70 } },
  { id: 'audit-2', at: '2026-05-18T15:40:00+08:00', actorId: 'user-organizer', actorRole: 'organizer', action: 'payment.verify', entityType: 'team', entityId: vipers.id, summary: 'Verified GCash payment for San Antonio Vipers', changes: { status: 'paid', ref: 'GCASH-9823412' } },
  { id: 'audit-3', at: '2026-05-16T08:00:00+08:00', actorId: 'user-organizer', actorRole: 'organizer', action: 'bracket.generate', entityType: 'tournament', entityId: ACTIVE, summary: 'Generated the 16-slot single-elimination bracket', changes: { teams: 15, byes: 1 } },
  { id: 'audit-4', at: '2026-05-15T17:00:00+08:00', actorId: 'user-organizer', actorRole: 'organizer', action: 'schedule.generate', entityType: 'tournament', entityId: ACTIVE, summary: 'Generated the court schedule with four courts', changes: { courts: 4, slotMinutes: 30, restMinutes: 15 } },
  { id: 'audit-5', at: '2026-05-14T09:30:00+08:00', actorId: 'user-organizer', actorRole: 'organizer', action: 'team.approve', entityType: 'team', entityId: 'team-5', summary: 'Approved Ugong Blue Hawks', changes: { status: 'approved' } },
  { id: 'audit-6', at: '2026-05-13T14:00:00+08:00', actorId: 'user-organizer', actorRole: 'organizer', action: 'announcement.publish', entityType: 'announcement', entityId: 'announcement-1', summary: 'Published championship week venue update', changes: { pinned: true } },
  { id: 'audit-7', at: '2026-05-01T10:00:00+08:00', actorId: 'user-organizer', actorRole: 'organizer', action: 'tournament.create', entityType: 'tournament', entityId: ACTIVE, summary: 'Created HoopFlow Summer Cup', changes: { slots: 16, division: 'Under 15 (U15)' } },
  { id: 'audit-8', at: '2026-05-02T09:00:00+08:00', actorId: 'user-coach', actorRole: 'coach', action: 'team.register', entityType: 'team', entityId: vipers.id, summary: 'Registered San Antonio Vipers', changes: { players: 10 } },
];

export const SEED = {
  schemaVersion: 2,
  seedVersion: SEED_VERSION,
  currentUserId: 'user-organizer',
  role: 'organizer',
  users,
  tournaments: [
    {
      id: ACTIVE,
      name: 'HoopFlow Summer Cup',
      division: 'Under 15 (U15)',
      format: 'single-elimination',
      slots: 16,
      status: 'active',
      startDate: '2026-05-15',
      endDate: '2026-05-22',
      venue: 'San Antonio Central Sports Complex',
      courts: [
        { id: 'court-1', name: 'Court 1 (Main Gym)' },
        { id: 'court-2', name: 'Court 2 (Covered)' },
        { id: 'court-3', name: 'Court 3' },
        { id: 'court-4', name: 'Court 4' },
      ],
      slotMinutes: 30,
      restMinutes: 15,
      fee: 1500,
      description: 'Annual inter-barangay grassroots youth tournament with accredited SBP referees.',
      registrationOpen: false,
      seedLocked: true,
    },
    {
      id: OPEN,
      name: 'Pasig Youth Hoopfest',
      division: 'Under 17 (U17)',
      format: 'single-elimination',
      slots: 16,
      status: 'registration-open',
      startDate: '2026-06-01',
      endDate: '2026-06-10',
      venue: 'Ynares Sports Arena, Pasig',
      courts: [
        { id: 'court-1', name: 'Court A' },
        { id: 'court-2', name: 'Court B' },
        { id: 'court-3', name: 'Court C' },
      ],
      slotMinutes: 30,
      restMinutes: 15,
      fee: 1500,
      description: 'Pre-season invitational for competitive secondary school teams.',
      registrationOpen: true,
      seedLocked: false,
    },
    {
      id: CDO,
      name: 'CDO Grassroots Classic',
      division: 'Under 14 (U14)',
      format: 'single-elimination',
      slots: 8,
      status: 'draft',
      startDate: '2026-07-10',
      endDate: '2026-07-18',
      venue: 'CDO City Coliseum',
      courts: [
        { id: 'court-1', name: 'Main Court' },
        { id: 'court-2', name: 'Side Court' },
      ],
      slotMinutes: 30,
      restMinutes: 15,
      fee: 1000,
      description: 'Regional developmental tournament focused on fundamentals and sportsmanship.',
      registrationOpen: false,
      seedLocked: false,
    },
  ],
  teams,
  matches,
  announcements,
  audit,
  meta: {
    lastSeenAnnouncementsAt: '2026-05-17T12:00:00+08:00',
  },
};
```

- [ ] **Step 4: Run the seed tests**

```powershell
cd prototype
node --test tests
```

Expected: all seed tests PASS.

- [ ] **Step 5: Commit**

```powershell
git add prototype/package.json prototype/assets/js/seed.js prototype/tests
git commit -m "feat(prototype): add versioned demo seed and node test harness"
```

---

## Task 3: Store core, permissions, and base selectors

**Files:**
- Create: `prototype/assets/js/store.js`
- Create: `prototype/tests/store.test.mjs`

**Interfaces:**
- Produces: `createStore({ storage, seed })`, `StoreError`, `STORAGE_KEY`, `SCHEMA_VERSION`; store methods `getState()`, `subscribe(fn)`, `getRole()`, `getCurrentUser()`, `can(permission)`, `canRole(roles)`, `select.tournaments()`, `select.tournament(id)`, `select.teams(tournamentId, { status })`, `select.team(id)`, `select.matches(tournamentId)`, `select.match(id)`, `select.announcements(tournamentId)`, `select.audit(filters)`, `actions.reset()`, `actions.setRole(role)`.

- [ ] **Step 1: Write the failing store tests**

Create `prototype/tests/store.test.mjs`:

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { createStore, SCHEMA_VERSION, STORAGE_KEY, StoreError } from '../assets/js/store.js';
import { SEED } from '../assets/js/seed.js';
import { memoryStorage } from './helpers.mjs';

function makeStore(initial) {
  return createStore({ storage: memoryStorage(initial) });
}

test('loads the seeded store and exposes the organizer', () => {
  const store = makeStore();
  const state = store.getState();
  assert.equal(state.schemaVersion, SCHEMA_VERSION);
  assert.equal(store.getRole(), 'organizer');
  assert.equal(store.getCurrentUser().name, 'Jeward Mahinay');
  assert.equal(store.select.tournaments().length, 3);
});

test('persists the store under hoopflow_db', () => {
  const storage = memoryStorage();
  createStore({ storage });
  const raw = storage.getItem(STORAGE_KEY);
  assert.ok(raw);
  assert.equal(JSON.parse(raw).schemaVersion, SCHEMA_VERSION);
});

test('reseeds when the schema version is stale', () => {
  const storage = memoryStorage({
    [STORAGE_KEY]: JSON.stringify({ schemaVersion: 1, seedVersion: 'old', tournaments: [] }),
  });
  const store = createStore({ storage });
  assert.equal(store.getState().schemaVersion, SCHEMA_VERSION);
  assert.equal(store.getState().tournaments.length, 3);
});

test('reset restores the exact seed', () => {
  const store = makeStore();
  const state = store.getState();
  state.tournaments[0].name = 'Mutated';
  store.actions.reset();
  assert.equal(store.getState().tournaments[0].name, SEED.tournaments[0].name);
});

test('setRole switches the current user and persists', () => {
  const store = makeStore();
  store.actions.setRole('coach');
  assert.equal(store.getRole(), 'coach');
  assert.equal(store.getCurrentUser().email, 'coach.marcus@hoopflow.ph');
  assert.throws(() => store.actions.setRole('referee'), (error) => error instanceof StoreError && error.code === 'INVALID_ROLE');
});

test('permissions are role-based', () => {
  const store = makeStore();
  assert.equal(store.can('team.approve'), true);
  store.actions.setRole('scorekeeper');
  assert.equal(store.can('score.save'), true);
  assert.equal(store.can('team.approve'), false);
  assert.equal(store.canRole(['organizer', 'scorekeeper']), true);
  assert.equal(store.canRole(['coach']), false);
});

test('selectors are tournament-scoped', () => {
  const store = makeStore();
  const activeTeams = store.select.teams('tournament-1');
  const openTeams = store.select.teams('tournament-2');
  assert.equal(activeTeams.length, 16);
  assert.equal(openTeams.length, 8);
  assert.equal(store.select.teams('tournament-2', { status: 'pending' }).length, 2);
  assert.equal(store.select.matches('tournament-1').length, 15);
  assert.equal(store.select.matches('tournament-2').length, 0);
  assert.equal(store.select.match('qf-1').roundLabel, 'Quarterfinals');
  assert.equal(store.select.team('team-1').name, 'San Antonio Vipers');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```powershell
cd prototype
node --test tests/store.test.mjs
```

Expected: FAIL because `../assets/js/store.js` does not exist.

- [ ] **Step 3: Implement the store core**

Create `prototype/assets/js/store.js`:

```js
import { SEED, SEED_VERSION } from './seed.js';

export const STORAGE_KEY = 'hoopflow_db';
export const SCHEMA_VERSION = 2;

export class StoreError extends Error {
  constructor(code, message, field = null) {
    super(message);
    this.name = 'StoreError';
    this.code = code;
    this.field = field;
  }
}

const ROLES = ['organizer', 'scorekeeper', 'coach', 'fan'];
const PERMISSIONS = {
  organizer: new Set(['*']),
  scorekeeper: new Set(['score.save', 'score.correct', 'stats.view']),
  coach: new Set(['team.register', 'team.edit', 'payment.submit', 'team.view.own']),
  fan: new Set(['public.view']),
};

const clone = (value) => JSON.parse(JSON.stringify(value));
const isoNow = () => new Date().toISOString();

function nextId(prefix, items) {
  const highest = items.reduce((max, item) => {
    const match = String(item.id).match(new RegExp(`^${prefix}-(\\d+)$`));
    return match ? Math.max(max, Number(match[1])) : max;
  }, 0);
  return `${prefix}-${highest + 1}`;
}

function findTournament(state, tournamentId) {
  const tournament = state.tournaments.find((item) => item.id === tournamentId);
  if (!tournament) throw new StoreError('NOT_FOUND', 'Tournament not found.');
  return tournament;
}

function findTeam(state, teamId) {
  const team = state.teams.find((item) => item.id === teamId);
  if (!team) throw new StoreError('NOT_FOUND', 'Team not found.');
  return team;
}

function findMatch(state, matchId) {
  const match = state.matches.find((item) => item.id === matchId);
  if (!match) throw new StoreError('NOT_FOUND', 'Match not found.');
  return match;
}

function currentUser(state) {
  return state.users.find((user) => user.id === state.currentUserId) || state.users[0];
}

function requirePermission(state, permission) {
  const role = state.role;
  const allowed = PERMISSIONS[role];
  if (!allowed || (!allowed.has('*') && !allowed.has(permission))) {
    throw new StoreError('FORBIDDEN', `The ${role} role cannot perform this action.`);
  }
}

function appendAudit(state, { action, entityType, entityId, summary, changes = null }) {
  const actor = currentUser(state);
  state.audit.unshift({
    id: nextId('audit', state.audit),
    at: isoNow(),
    actorId: actor.id,
    actorRole: actor.role,
    action,
    entityType,
    entityId,
    summary,
    changes,
  });
}

export function createStore({ storage = globalThis.localStorage, seed = SEED } = {}) {
  if (!storage) throw new Error('createStore requires a storage adapter.');

  let state = load();
  const listeners = new Set();

  function load() {
    const raw = storage.getItem(STORAGE_KEY);
    if (raw) {
      try {
        const parsed = JSON.parse(raw);
        if (parsed.schemaVersion === SCHEMA_VERSION && parsed.seedVersion === SEED_VERSION) {
          return parsed;
        }
      } catch {
        // Fall through to a clean seed.
      }
    }
    const fresh = clone(seed);
    storage.setItem(STORAGE_KEY, JSON.stringify(fresh));
    return fresh;
  }

  function commit(next, event = 'update') {
    state = next;
    storage.setItem(STORAGE_KEY, JSON.stringify(state));
    listeners.forEach((listener) => listener(state, event));
    if (typeof document !== 'undefined') {
      document.dispatchEvent(new CustomEvent('hf:store-changed', { detail: { event } }));
    }
  }

  function subscribe(listener) {
    listeners.add(listener);
    return () => listeners.delete(listener);
  }

  function getState() {
    return clone(state);
  }

  function getRole() {
    return state.role;
  }

  function getCurrentUser() {
    return clone(currentUser(state));
  }

  function can(permission) {
    const allowed = PERMISSIONS[state.role];
    return Boolean(allowed && (allowed.has('*') || allowed.has(permission)));
  }

  function canRole(roles) {
    return roles.includes(state.role);
  }

  function reset() {
    commit(clone(seed), 'reset');
  }

  function setRole(role) {
    if (!ROLES.includes(role)) {
      throw new StoreError('INVALID_ROLE', `Unknown role: ${role}.`);
    }
    const next = clone(state);
    const user = next.users.find((item) => item.role === role);
    if (!user) throw new StoreError('INVALID_ROLE', `No demo user exists for ${role}.`);
    next.role = role;
    next.currentUserId = user.id;
    commit(next, 'role');
  }

  const select = {
    tournaments: () => clone(state.tournaments),
    tournament: (id) => {
      const tournament = state.tournaments.find((item) => item.id === id);
      return tournament ? clone(tournament) : null;
    },
    teams: (tournamentId, { status } = {}) =>
      clone(
        state.teams.filter(
          (team) =>
            team.tournamentId === tournamentId && (!status || team.status === status)
        )
      ),
    team: (id) => {
      const team = state.teams.find((item) => item.id === id);
      return team ? clone(team) : null;
    },
    matches: (tournamentId) =>
      clone(state.matches.filter((match) => match.tournamentId === tournamentId)),
    match: (id) => {
      const match = state.matches.find((item) => item.id === id);
      return match ? clone(match) : null;
    },
    announcements: (tournamentId) =>
      clone(
        state.announcements
          .filter((item) => !tournamentId || item.tournamentId === tournamentId)
          .sort((a, b) => Number(b.pinned) - Number(a.pinned) || b.createdAt.localeCompare(a.createdAt))
      ),
    audit: ({ entityType, actorId } = {}) =>
      clone(
        state.audit.filter(
          (entry) =>
            (!entityType || entry.entityType === entityType) &&
            (!actorId || entry.actorId === actorId)
        )
      ),
  };

  return {
    getState,
    subscribe,
    getRole,
    getCurrentUser,
    can,
    canRole,
    select,
    actions: {
      reset,
      setRole,
    },
  };
}
```

- [ ] **Step 4: Run the tests**

```powershell
cd prototype
node --test tests
```

Expected: all tests PASS.

- [ ] **Step 5: Commit**

```powershell
git add prototype/assets/js/store.js prototype/tests/store.test.mjs
git commit -m "feat(prototype): add normalized store core and permissions"
```

## Task 4: Tournament, team, payment, announcement, and roster actions

**Files:**
- Modify: `prototype/assets/js/seed.js` (export `POSITIONS`)
- Modify: `prototype/assets/js/store.js`
- Create: `prototype/tests/actions.test.mjs`

**Interfaces:**
- Consumes: `createStore`, `StoreError`, `findTournament`, `findTeam`, `appendAudit`, `commit`, `clone`, `currentUser` from Task 3.
- Produces: `actions.createTournament(input) -> tournamentId`, `actions.registerTeam(tournamentId, input) -> teamId`, `actions.reviewTeam(teamId, decision, reason) -> team`, `actions.verifyPayment(teamId, payment) -> payment`, `actions.publishAnnouncement(input) -> announcementId`, `actions.addPlayer(teamId, input) -> player`, `actions.updatePlayer(teamId, playerId, input) -> player`, `actions.removePlayer(teamId, playerId) -> void`, `actions.submitPayment(teamId, input) -> payment`, `actions.markAnnouncementsSeen() -> void`, and `AUDIENCE_BY_ROLE` (role to visible announcement audiences).

- [ ] **Step 1: Export the position list from the seed**

In `prototype/assets/js/seed.js`, change:

```js
const POSITIONS = [
```

to:

```js
export const POSITIONS = [
```

- [ ] **Step 2: Write the failing action tests**

Create `prototype/tests/actions.test.mjs`:

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { createStore } from '../assets/js/store.js';
import { memoryStorage } from './helpers.mjs';

function makeStore() {
  return createStore({ storage: memoryStorage() });
}

function roster(count = 8) {
  return Array.from({ length: count }, (_, index) => ({
    name: `Player ${index + 1}`,
    number: index + 1,
    position: 'Point Guard (PG)',
    age: 16,
    guardianConsent: true,
    documentStatus: 'verified',
  }));
}

function registration(overrides = {}) {
  return {
    name: 'Pasig Panthers',
    shortName: 'PNT',
    city: 'Pasig City',
    coach: 'Marcus Rivera',
    players: roster(),
    payment: { method: 'GCash', ref: 'GCASH-7778888' },
    ...overrides,
  };
}

test('organizer creates a tournament and writes audit', () => {
  const store = makeStore();
  const id = store.actions.createTournament({
    name: 'Navotas Night League',
    division: 'Under 16 (U16)',
    startDate: '2026-08-01',
    endDate: '2026-08-09',
    venue: 'Navotas Sports Complex',
    slots: 8,
    format: 'single-elimination',
    courts: ['Court A', 'Court B'],
    slotMinutes: 25,
    restMinutes: 10,
    fee: 1200,
    description: 'Night league for barangay teams.',
  });
  assert.equal(id, 'tournament-4');
  const created = store.select.tournament(id);
  assert.equal(created.status, 'registration-open');
  assert.equal(created.registrationOpen, true);
  assert.equal(created.courts.length, 2);
  assert.equal(store.select.tournaments().length, 4);
  assert.equal(store.select.audit({ entityType: 'tournament' })[0].action, 'tournament.create');
});

test('createTournament rejects invalid dates and slots', () => {
  const store = makeStore();
  assert.throws(
    () => store.actions.createTournament({
      name: 'Bad Dates', division: 'U15', startDate: '2026-08-09', endDate: '2026-08-01',
      venue: 'Gym', slots: 8, courts: ['Court A'],
    }),
    (error) => error.code === 'VALIDATION' && error.field === 'endDate'
  );
  assert.throws(
    () => store.actions.createTournament({
      name: 'Bad Slots', division: 'U15', startDate: '2026-08-01', endDate: '2026-08-09',
      venue: 'Gym', slots: 12, courts: ['Court A'],
    }),
    (error) => error.code === 'VALIDATION' && error.field === 'slots'
  );
});

test('coach registers a team as pending with a pending payment', () => {
  const store = makeStore();
  store.actions.setRole('coach');
  const id = store.actions.registerTeam('tournament-2', registration());
  const team = store.select.team(id);
  assert.equal(team.status, 'pending');
  assert.equal(team.tournamentId, 'tournament-2');
  assert.equal(team.players.length, 8);
  assert.equal(team.payment.status, 'pending');
  assert.equal(team.payment.ref, 'GCASH-7778888');
  assert.equal(store.select.teams('tournament-2').length, 9);
  assert.equal(store.select.audit({ entityType: 'team' })[0].action, 'team.register');
});

test('registerTeam rejects duplicates, closed registration, bad ages, and duplicate jerseys', () => {
  const store = makeStore();
  store.actions.setRole('coach');
  store.actions.registerTeam('tournament-2', registration());
  assert.throws(
    () => store.actions.registerTeam('tournament-2', registration()),
    (error) => error.code === 'DUPLICATE_TEAM' && error.field === 'name'
  );
  assert.throws(
    () => store.actions.registerTeam('tournament-1', registration({ name: 'New Team' })),
    (error) => error.code === 'REGISTRATION_CLOSED'
  );
  assert.throws(
    () => store.actions.registerTeam('tournament-2', registration({
      name: 'Too Old', players: roster().map((player, index) => index === 0 ? { ...player, age: 18 } : player),
    })),
    (error) => error.code === 'VALIDATION' && error.field === 'age'
  );
  assert.throws(
    () => store.actions.registerTeam('tournament-2', registration({
      name: 'Bad Numbers', players: roster().map((player, index) => index === 1 ? { ...player, number: 1 } : player),
    })),
    (error) => error.code === 'DUPLICATE_NUMBER' && error.field === 'number'
  );
});

test('organizer approves and rejects teams; coach cannot review', () => {
  const store = makeStore();
  const approved = store.actions.reviewTeam('team-open-7', 'approve');
  assert.equal(approved.status, 'approved');
  assert.ok(approved.eligibility.verifiedAt);
  assert.equal(store.select.audit({ entityType: 'team' })[0].action, 'team.approve');
  assert.throws(
    () => store.actions.reviewTeam('team-open-8', 'reject', ''),
    (error) => error.code === 'VALIDATION' && error.field === 'reason'
  );
  const rejected = store.actions.reviewTeam('team-open-8', 'reject', 'Incomplete documents.');
  assert.equal(rejected.status, 'rejected');
  assert.equal(rejected.rejectionReason, 'Incomplete documents.');
  store.actions.setRole('coach');
  assert.throws(
    () => store.actions.reviewTeam('team-open-7', 'approve'),
    (error) => error.code === 'FORBIDDEN'
  );
});

test('verifyPayment updates the team payment and audit', () => {
  const store = makeStore();
  const payment = store.actions.verifyPayment('team-open-3', {
    method: 'GCash', ref: 'GCASH-9990001', amount: 1500,
  });
  assert.equal(payment.status, 'paid');
  assert.equal(payment.ref, 'GCASH-9990001');
  assert.equal(store.select.team('team-open-3').payment.status, 'paid');
  assert.equal(store.select.audit({ entityType: 'team' })[0].action, 'payment.verify');
  assert.throws(
    () => store.actions.verifyPayment('team-open-4', { method: 'GCash', ref: 'x' }),
    (error) => error.code === 'VALIDATION' && error.field === 'payment.ref'
  );
});

test('publishAnnouncement adds a pinned item and updates read state', () => {
  const store = makeStore();
  const id = store.actions.publishAnnouncement({
    tournamentId: 'tournament-1',
    title: 'Semifinal gate opens at 2 PM',
    body: 'Players and coaches enter through the east gate with their accreditation.',
    category: 'venue',
    audience: 'coaches',
    pinned: true,
  });
  const announcement = store.select.announcements('tournament-1').find((item) => item.id === id);
  assert.equal(announcement.pinned, true);
  assert.equal(announcement.audience, 'coaches');
  assert.equal(store.select.notifications().length, 0);
  assert.equal(store.select.audit({ entityType: 'announcement' })[0].action, 'announcement.publish');
  store.actions.setRole('coach');
  assert.throws(
    () => store.actions.publishAnnouncement({
      tournamentId: 'tournament-1', title: 'Nope', body: 'Coaches cannot publish this.',
      category: 'general', audience: 'all',
    }),
    (error) => error.code === 'FORBIDDEN'
  );
});

test('coach edits a pending roster but cannot edit a verified roster', () => {
  const store = makeStore();
  store.actions.setRole('coach');
  const teamId = store.actions.registerTeam('tournament-2', registration());
  const player = store.actions.addPlayer(teamId, {
    name: 'Bench Guard', number: 9, position: 'Shooting Guard (SG)',
    age: 16, guardianConsent: true, documentStatus: 'pending',
  });
  assert.equal(store.select.team(teamId).players.length, 9);
  assert.equal(player.number, 9);
  assert.throws(
    () => store.actions.addPlayer(teamId, {
      name: 'Duplicate', number: 9, position: 'Center (C)',
      age: 16, guardianConsent: true, documentStatus: 'pending',
    }),
    (error) => error.code === 'DUPLICATE_NUMBER'
  );
  store.actions.updatePlayer(teamId, player.id, {
    name: 'Bench Guard', number: 10, position: 'Shooting Guard (SG)',
    age: 16, guardianConsent: true, documentStatus: 'verified',
  });
  assert.equal(store.select.team(teamId).players.find((item) => item.id === player.id).number, 10);
  store.actions.removePlayer(teamId, player.id);
  assert.equal(store.select.team(teamId).players.length, 8);
  assert.throws(
    () => store.actions.addPlayer('team-1', {
      name: 'Locked', number: 55, position: 'Center (C)',
      age: 15, guardianConsent: true, documentStatus: 'verified',
    }),
    (error) => error.code === 'ROSTER_LOCKED'
  );
});

test('coach submits a payment reference for the own team', () => {
  const store = makeStore();
  store.actions.setRole('coach');
  const teamId = store.actions.registerTeam('tournament-2', registration());
  const payment = store.actions.submitPayment(teamId, { method: 'Maya', ref: 'MAYA-5556667' });
  assert.equal(payment.status, 'pending');
  assert.equal(payment.ref, 'MAYA-5556667');
  assert.equal(store.select.audit({ entityType: 'team' })[0].action, 'payment.submit');
});
```

- [ ] **Step 3: Run the tests to verify they fail**

```powershell
cd prototype
node --test tests/actions.test.mjs
```

Expected: FAIL because the action methods do not exist.

- [ ] **Step 4: Add validation helpers to `store.js`**

Add these imports and helpers at the top of `prototype/assets/js/store.js`, after the existing `PERMISSIONS` constant:

```js
import { POSITIONS } from './seed.js';

const PAYMENT_METHODS = ['GCash', 'Maya', 'Bank transfer', 'Cash'];
const ANNOUNCEMENT_CATEGORIES = ['schedule', 'venue', 'eligibility', 'general'];
const ANNOUNCEMENT_AUDIENCES = ['all', 'coaches', 'organizers'];

export const AUDIENCE_BY_ROLE = {
  organizer: ['all', 'coaches', 'organizers'],
  scorekeeper: ['all', 'organizers'],
  coach: ['all', 'coaches'],
  fan: ['all'],
};

function requireText(value, field, label, min = 1) {
  if (typeof value !== 'string' || value.trim().length < min) {
    throw new StoreError('VALIDATION', `${label} is required.`, field);
  }
  return value.trim();
}

function requireDate(value, field, label) {
  if (
    typeof value !== 'string' ||
    !/^\d{4}-\d{2}-\d{2}$/.test(value) ||
    Number.isNaN(Date.parse(`${value}T00:00:00Z`))
  ) {
    throw new StoreError('VALIDATION', `${label} must be a valid date.`, field);
  }
  return value;
}

function requireChoice(value, field, label, choices) {
  if (!choices.includes(value)) {
    throw new StoreError('VALIDATION', `${label} is invalid.`, field);
  }
  return value;
}

function requirePaymentRef(value, field = 'payment.ref') {
  if (typeof value !== 'string' || !/^[A-Za-z0-9-]{6,24}$/.test(value.trim())) {
    throw new StoreError(
      'VALIDATION',
      'Enter a valid payment reference (letters, numbers, and dashes).',
      field
    );
  }
  return value.trim().toUpperCase();
}

function divisionLimit(division) {
  const match = String(division).match(/(\d+)/);
  return match ? Number(match[1]) : 99;
}

function validatePlayer(input, { division, numbers, ignoreNumber = null }) {
  const name = requireText(input.name, 'name', 'Player name', 2);
  const number = Number(input.number);
  if (!Number.isInteger(number) || number < 1 || number > 99) {
    throw new StoreError('VALIDATION', 'Jersey number must be between 1 and 99.', 'number');
  }
  if (number !== ignoreNumber && numbers.includes(number)) {
    throw new StoreError('DUPLICATE_NUMBER', `Jersey number ${number} is already taken.`, 'number');
  }
  if (!POSITIONS.includes(input.position)) {
    throw new StoreError('VALIDATION', 'Choose a valid position.', 'position');
  }
  const age = Number(input.age);
  const limit = divisionLimit(division);
  if (!Number.isInteger(age) || age < 8 || age > limit) {
    throw new StoreError('VALIDATION', `Age must be between 8 and ${limit} for ${division}.`, 'age');
  }
  if (input.guardianConsent !== true) {
    throw new StoreError('VALIDATION', 'Guardian consent is required.', 'guardianConsent');
  }
  const documentStatus = input.documentStatus || 'pending';
  if (!['verified', 'pending', 'missing'].includes(documentStatus)) {
    throw new StoreError('VALIDATION', 'Choose a document status.', 'documentStatus');
  }
  return { name, number, position: input.position, age, guardianConsent: true, documentStatus };
}
```

The existing import line `import { SEED, SEED_VERSION } from './seed.js';` becomes:

```js
import { SEED, SEED_VERSION, POSITIONS } from './seed.js';
```

Delete the separate import shown above if it would create a duplicate import.

- [ ] **Step 5: Add tournament, team, payment, and announcement actions**

Inside `createStore`, immediately after the `setRole` function and before `const select = {`, insert:

```js
  function coachOwnsTeam(teamId) {
    if (state.role === 'organizer') return true;
    const user = currentUser(state);
    const team = findTeam(state, teamId);
    const coachName = user.name.replace(/^Coach\s+/i, '').trim();
    return team.coach === coachName || team.coach === user.name;
  }

  function createTournament(input = {}) {
    requirePermission(state, 'tournament.create');
    const name = requireText(input.name, 'name', 'Tournament name', 3);
    const division = requireText(input.division, 'division', 'Division', 3);
    const startDate = requireDate(input.startDate, 'startDate', 'Start date');
    const endDate = requireDate(input.endDate, 'endDate', 'End date');
    if (endDate < startDate) {
      throw new StoreError('VALIDATION', 'End date must be on or after the start date.', 'endDate');
    }
    const slots = Number(input.slots);
    if (![8, 16].includes(slots)) {
      throw new StoreError('VALIDATION', 'Slots must be 8 or 16.', 'slots');
    }
    requireChoice(input.format || 'single-elimination', 'format', 'Format', ['single-elimination']);
    const venue = requireText(input.venue, 'venue', 'Venue', 3);
    const courts = Array.isArray(input.courts)
      ? input.courts.map((court, index) => {
          const courtName = typeof court === 'string' ? court : court?.name;
          return {
            id: `court-${index + 1}`,
            name: requireText(courtName, 'courts', 'Court name', 2),
          };
        })
      : [];
    if (courts.length < 1) {
      throw new StoreError('VALIDATION', 'Add at least one court.', 'courts');
    }
    const slotMinutes = Number(input.slotMinutes ?? 30);
    const restMinutes = Number(input.restMinutes ?? 15);
    if (!Number.isInteger(slotMinutes) || slotMinutes < 10) {
      throw new StoreError('VALIDATION', 'Slot length must be at least 10 minutes.', 'slotMinutes');
    }
    if (!Number.isInteger(restMinutes) || restMinutes < 0) {
      throw new StoreError('VALIDATION', 'Rest must be zero or more minutes.', 'restMinutes');
    }
    const fee = Number(input.fee ?? 0);
    if (!Number.isFinite(fee) || fee < 0) {
      throw new StoreError('VALIDATION', 'Fee must be zero or more.', 'fee');
    }
    const next = clone(state);
    const id = nextId('tournament', next.tournaments);
    next.tournaments.push({
      id,
      name,
      division,
      format: 'single-elimination',
      slots,
      status: 'registration-open',
      startDate,
      endDate,
      venue,
      courts,
      slotMinutes,
      restMinutes,
      fee,
      description: String(input.description || '').trim(),
      registrationOpen: true,
      seedLocked: false,
    });
    appendAudit(next, {
      action: 'tournament.create',
      entityType: 'tournament',
      entityId: id,
      summary: `Created ${name}`,
      changes: { slots, division, fee },
    });
    commit(next, 'tournament.create');
    return id;
  }

  function registerTeam(tournamentId, input = {}) {
    requirePermission(state, 'team.register');
    const tournament = findTournament(state, tournamentId);
    if (!tournament.registrationOpen || tournament.status !== 'registration-open') {
      throw new StoreError('REGISTRATION_CLOSED', 'Registration is not open for this tournament.');
    }
    const name = requireText(input.name, 'name', 'Team name', 3);
    const duplicate = state.teams.some(
      (team) => team.tournamentId === tournamentId && team.name.toLowerCase() === name.toLowerCase()
    );
    if (duplicate) {
      throw new StoreError('DUPLICATE_TEAM', 'A team with that name is already registered.', 'name');
    }
    const shortName = requireText(input.shortName, 'shortName', 'Short name', 2)
      .slice(0, 3)
      .toUpperCase();
    const city = requireText(input.city, 'city', 'City', 2);
    const coach = requireText(
      input.coach || currentUser(state).name.replace(/^Coach\s+/i, ''),
      'coach',
      'Coach name',
      3
    );
    const playersInput = Array.isArray(input.players) ? input.players : [];
    if (playersInput.length < 8 || playersInput.length > 12) {
      throw new StoreError('VALIDATION', 'A roster needs 8 to 12 players.', 'players');
    }
    const numbers = [];
    const players = playersInput.map((player, index) => {
      const clean = validatePlayer(player, { division: tournament.division, numbers });
      if (clean.documentStatus === 'missing') {
        throw new StoreError(
          'VALIDATION',
          `Player ${clean.name} is missing a document.`,
          `players.${index}.documentStatus`
        );
      }
      numbers.push(clean.number);
      return { id: `${tournamentId}-player-${Date.now()}-${index + 1}`, ...clean };
    });
    const method = requireChoice(
      input.payment?.method || 'GCash',
      'payment.method',
      'Payment method',
      PAYMENT_METHODS
    );
    const ref = requirePaymentRef(input.payment?.ref, 'payment.ref');
    const next = clone(state);
    const id = nextId('team', next.teams);
    const submittedAt = isoNow();
    next.teams.push({
      id,
      tournamentId,
      name,
      shortName,
      coach,
      city,
      avatarColor: input.avatarColor || '#ea580c',
      seed: null,
      status: 'pending',
      rejectionReason: '',
      submittedAt,
      payment: { status: 'pending', method, ref, amount: tournament.fee, verifiedAt: null },
      eligibility: { documentsComplete: true, consentComplete: true, verifiedAt: null },
      players,
    });
    appendAudit(next, {
      action: 'team.register',
      entityType: 'team',
      entityId: id,
      summary: `Registered ${name} for ${tournament.name}`,
      changes: { players: players.length, ref },
    });
    commit(next, 'team.register');
    return id;
  }

  function reviewTeam(teamId, decision, reason = '') {
    requirePermission(state, 'team.approve');
    const team = findTeam(state, teamId);
    const tournament = findTournament(state, team.tournamentId);
    if (tournament.seedLocked) {
      throw new StoreError('SEED_LOCKED', 'Seeds are locked for this tournament.');
    }
    const action = String(decision || '').toLowerCase();
    if (!['approve', 'reject'].includes(action)) {
      throw new StoreError('VALIDATION', 'Decision must be approve or reject.', 'decision');
    }
    const next = clone(state);
    const target = findTeam(next, teamId);
    if (action === 'reject') {
      target.status = 'rejected';
      target.rejectionReason = requireText(reason, 'reason', 'Rejection reason', 5);
      target.eligibility.verifiedAt = null;
    } else {
      target.status = 'approved';
      target.rejectionReason = '';
      target.eligibility.verifiedAt = isoNow();
    }
    appendAudit(next, {
      action: `team.${action}`,
      entityType: 'team',
      entityId: teamId,
      summary: `${action === 'approve' ? 'Approved' : 'Rejected'} ${target.name}`,
      changes: { status: target.status },
    });
    commit(next, 'team.review');
    return clone(target);
  }

  function verifyPayment(teamId, payment = {}) {
    requirePermission(state, 'payment.verify');
    const team = findTeam(state, teamId);
    const method = requireChoice(payment.method, 'payment.method', 'Payment method', PAYMENT_METHODS);
    const ref = requirePaymentRef(payment.ref);
    const amount = Number(payment.amount ?? team.payment.amount);
    if (!Number.isFinite(amount) || amount <= 0) {
      throw new StoreError('VALIDATION', 'Amount must be greater than zero.', 'payment.amount');
    }
    const next = clone(state);
    const target = findTeam(next, teamId);
    target.payment = {
      status: payment.status === 'partial' ? 'partial' : 'paid',
      method,
      ref,
      amount,
      verifiedAt: isoNow(),
    };
    appendAudit(next, {
      action: 'payment.verify',
      entityType: 'team',
      entityId: teamId,
      summary: `Verified ${method} payment for ${target.name}`,
      changes: { status: target.payment.status, ref, amount },
    });
    commit(next, 'payment.verify');
    return clone(target.payment);
  }

  function publishAnnouncement(input = {}) {
    requirePermission(state, 'announcement.publish');
    const tournamentId = requireText(input.tournamentId, 'tournamentId', 'Tournament');
    findTournament(state, tournamentId);
    const title = requireText(input.title, 'title', 'Title', 4);
    const body = requireText(input.body, 'body', 'Message', 10);
    const category = requireChoice(input.category, 'category', 'Category', ANNOUNCEMENT_CATEGORIES);
    const audience = requireChoice(
      input.audience || 'all',
      'audience',
      'Audience',
      ANNOUNCEMENT_AUDIENCES
    );
    const next = clone(state);
    const id = nextId('announcement', next.announcements);
    const author = currentUser(state);
    next.announcements.push({
      id,
      tournamentId,
      title,
      body,
      category,
      audience,
      pinned: Boolean(input.pinned),
      author: author.organization || author.name,
      createdAt: isoNow(),
    });
    next.meta.lastSeenAnnouncementsAt = isoNow();
    appendAudit(next, {
      action: 'announcement.publish',
      entityType: 'announcement',
      entityId: id,
      summary: `Published ${title}`,
      changes: { category, audience, pinned: Boolean(input.pinned) },
    });
    commit(next, 'announcement.publish');
    return id;
  }
```

- [ ] **Step 6: Add roster and coach-payment actions**

Immediately after `publishAnnouncement`, still inside `createStore`, insert:

```js
  function assertRosterEditable(team) {
    if (team.eligibility.verifiedAt) {
      throw new StoreError('ROSTER_LOCKED', 'The roster is locked after verification.');
    }
    if (!coachOwnsTeam(team.id)) {
      throw new StoreError('FORBIDDEN', 'You can only edit your own team.');
    }
  }

  function refreshEligibility(team) {
    team.eligibility.documentsComplete = team.players.every(
      (player) => player.documentStatus === 'verified'
    );
    team.eligibility.consentComplete = team.players.every((player) => player.guardianConsent);
  }

  function addPlayer(teamId, input = {}) {
    requirePermission(state, 'team.edit');
    const team = findTeam(state, teamId);
    assertRosterEditable(team);
    if (team.players.length >= 12) {
      throw new StoreError('ROSTER_FULL', 'A roster can have at most 12 players.');
    }
    const tournament = findTournament(state, team.tournamentId);
    const clean = validatePlayer(input, {
      division: tournament.division,
      numbers: team.players.map((player) => player.number),
    });
    const next = clone(state);
    const target = findTeam(next, teamId);
    const player = { id: `${teamId}-player-${Date.now()}`, ...clean };
    target.players.push(player);
    refreshEligibility(target);
    appendAudit(next, {
      action: 'team.roster.add',
      entityType: 'team',
      entityId: teamId,
      summary: `Added ${player.name} to ${target.name}`,
      changes: { number: player.number },
    });
    commit(next, 'team.roster');
    return clone(player);
  }

  function updatePlayer(teamId, playerId, input = {}) {
    requirePermission(state, 'team.edit');
    const team = findTeam(state, teamId);
    assertRosterEditable(team);
    const existing = team.players.find((player) => player.id === playerId);
    if (!existing) throw new StoreError('NOT_FOUND', 'Player not found.');
    const tournament = findTournament(state, team.tournamentId);
    const clean = validatePlayer(input, {
      division: tournament.division,
      numbers: team.players.filter((player) => player.id !== playerId).map((player) => player.number),
    });
    const next = clone(state);
    const target = findTeam(next, teamId);
    const player = target.players.find((item) => item.id === playerId);
    Object.assign(player, clean);
    refreshEligibility(target);
    appendAudit(next, {
      action: 'team.roster.update',
      entityType: 'team',
      entityId: teamId,
      summary: `Updated ${player.name} on ${target.name}`,
      changes: { number: player.number, documentStatus: player.documentStatus },
    });
    commit(next, 'team.roster');
    return clone(player);
  }

  function removePlayer(teamId, playerId) {
    requirePermission(state, 'team.edit');
    const team = findTeam(state, teamId);
    assertRosterEditable(team);
    const player = team.players.find((item) => item.id === playerId);
    if (!player) throw new StoreError('NOT_FOUND', 'Player not found.');
    if (team.players.length <= 5) {
      throw new StoreError('ROSTER_TOO_SMALL', 'A team must keep at least five players.');
    }
    const next = clone(state);
    const target = findTeam(next, teamId);
    target.players = target.players.filter((item) => item.id !== playerId);
    refreshEligibility(target);
    appendAudit(next, {
      action: 'team.roster.remove',
      entityType: 'team',
      entityId: teamId,
      summary: `Removed ${player.name} from ${target.name}`,
      changes: { number: player.number },
    });
    commit(next, 'team.roster');
  }

  function submitPayment(teamId, input = {}) {
    requirePermission(state, 'payment.submit');
    const team = findTeam(state, teamId);
    if (!coachOwnsTeam(teamId)) {
      throw new StoreError('FORBIDDEN', 'You can only submit payment for your own team.');
    }
    const method = requireChoice(input.method, 'method', 'Payment method', PAYMENT_METHODS);
    const ref = requirePaymentRef(input.ref, 'ref');
    const next = clone(state);
    const target = findTeam(next, teamId);
    target.payment = {
      status: 'pending',
      method,
      ref,
      amount: findTournament(next, target.tournamentId).fee,
      verifiedAt: null,
    };
    appendAudit(next, {
      action: 'payment.submit',
      entityType: 'team',
      entityId: teamId,
      summary: `Submitted ${method} reference for ${target.name}`,
      changes: { ref },
    });
    commit(next, 'payment.submit');
    return clone(target.payment);
  }

  function markAnnouncementsSeen() {
    const next = clone(state);
    next.meta.lastSeenAnnouncementsAt = isoNow();
    commit(next, 'announcements.seen');
  }
```

- [ ] **Step 7: Register the new actions**

Replace the `actions` object at the end of `createStore` with:

```js
    actions: {
      reset,
      setRole,
      createTournament,
      registerTeam,
      reviewTeam,
      verifyPayment,
      publishAnnouncement,
      addPlayer,
      updatePlayer,
      removePlayer,
      submitPayment,
      markAnnouncementsSeen,
    },
```

- [ ] **Step 8: Run the action tests**

```powershell
cd prototype
node --test tests/actions.test.mjs
```

Expected: all 10 tests PASS.

- [ ] **Step 9: Run the full suite**

```powershell
cd prototype
node --test tests
```

Expected: seed, store, and action tests PASS.

- [ ] **Step 10: Commit**

```powershell
git add prototype/assets/js/seed.js prototype/assets/js/store.js prototype/tests/actions.test.mjs
git commit -m "feat(prototype): add tournament, team, payment, and roster actions"
```

## Task 5: Bracket, schedule, scoring, and computed selectors

**Files:**
- Modify: `prototype/assets/js/store.js`
- Create: `prototype/tests/competition.test.mjs`

**Interfaces:**
- Consumes: `createStore`, `StoreError`, `findTournament`, `findTeam`, `findMatch`, `appendAudit`, `commit`, `clone`, `currentUser` from Tasks 3–4.
- Produces: `actions.generateBracket(tournamentId) -> { matches, byes }`, `actions.generateSchedule(tournamentId, options) -> number`, `store.planSchedule(tournamentId, options) -> { assignments, courts, slotMinutes, restMinutes, startDate }`, `actions.saveScore(matchId, score1, score2, options) -> match`, `actions.correctScore(matchId, score1, score2, reason) -> match`, `select.bracket(tournamentId)`, `select.standings(tournamentId)`, `select.kpis(tournamentId)`, `select.paymentSummary(tournamentId)`, `select.teamRecord(teamId)`, `select.recentResults(tournamentId, limit)`, `select.upcomingMatches(tournamentId, limit)`, `select.teamMatches(teamId)`, `select.nextMatch(teamId)`, `select.coachTeams()`, `select.notifications()`.

- [ ] **Step 1: Write the failing competition tests**

Create `prototype/tests/competition.test.mjs`:

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { createStore } from '../assets/js/store.js';
import { SEED } from '../assets/js/seed.js';
import { memoryStorage } from './helpers.mjs';

function makeStore() {
  return createStore({ storage: memoryStorage() });
}

test('generateBracket builds the CDO bracket and locks seeds', () => {
  const store = makeStore();
  const result = store.actions.generateBracket('tournament-3');
  assert.equal(result.matches, 7);
  assert.equal(result.byes, 0);
  assert.equal(store.select.matches('tournament-3').length, 7);
  assert.equal(store.select.tournament('tournament-3').seedLocked, true);
  assert.equal(store.select.tournament('tournament-3').status, 'upcoming');
  const bracket = store.select.bracket('tournament-3');
  assert.equal(bracket.rounds.length, 3);
  assert.equal(bracket.rounds[0].label, 'Quarterfinals');
  assert.equal(bracket.champion, null);
  assert.equal(store.select.audit({ entityType: 'tournament' })[0].action, 'bracket.generate');
});

test('generateBracket refuses to disturb a bracket with results', () => {
  const store = makeStore();
  assert.throws(
    () => store.actions.generateBracket('tournament-1'),
    (error) => error.code === 'BRACKET_LOCKED'
  );
});

test('planSchedule and generateSchedule assign courts and times', () => {
  const store = makeStore();
  store.actions.generateBracket('tournament-3');
  const plan = store.planSchedule('tournament-3');
  assert.equal(plan.assignments.length, 7);
  assert.equal(new Set(plan.assignments.map((item) => `${item.courtId}|${item.scheduledAt}`)).size, 7);
  assert.ok(plan.assignments.every((item) => item.scheduledAt.endsWith('+08:00')));
  const count = store.actions.generateSchedule('tournament-3');
  assert.equal(count, 7);
  const scheduled = store.select.matches('tournament-3');
  assert.ok(scheduled.every((match) => match.courtId && match.scheduledAt));
  assert.equal(store.select.audit({ entityType: 'tournament' })[0].action, 'schedule.generate');
});

test('generateSchedule requires a bracket', () => {
  const store = makeStore();
  assert.throws(
    () => store.actions.generateSchedule('tournament-2'),
    (error) => error.code === 'NO_BRACKET'
  );
});

test('saveScore advances the winner and updates standings', () => {
  const store = makeStore();
  store.actions.saveScore('qf-3', 70, 66);
  const match = store.select.match('qf-3');
  assert.equal(match.status, 'final');
  assert.equal(match.score1, 70);
  assert.equal(match.score2, 66);
  assert.equal(match.winnerId, 'team-2');
  assert.equal(store.select.match('sf-2').team1Id, 'team-2');
  const knights = store.select.standings('tournament-1').find((row) => row.teamId === 'team-2');
  assert.equal(knights.wins, 2);
  assert.equal(knights.losses, 0);
  assert.equal(store.select.audit({ entityType: 'match' })[0].action, 'score.save');
});

test('saveScore rejects ties, unknown teams, and repeated results', () => {
  const store = makeStore();
  assert.throws(
    () => store.actions.saveScore('qf-4', 60, 60),
    (error) => error.code === 'TIE'
  );
  assert.throws(
    () => store.actions.saveScore('sf-2', 60, 50),
    (error) => error.code === 'TEAMS_PENDING'
  );
  assert.throws(
    () => store.actions.saveScore('qf-1', 80, 70),
    (error) => error.code === 'MATCH_FINAL'
  );
});

test('saveScore records a FIBA forfeit as 20-0', () => {
  const store = makeStore();
  store.actions.saveScore('qf-4', 0, 0, { forfeitTeam: 1 });
  const match = store.select.match('qf-4');
  assert.equal(match.status, 'forfeit');
  assert.equal(match.score1, 0);
  assert.equal(match.score2, 20);
  assert.equal(match.winnerId, 'team-6');
  assert.equal(store.select.match('sf-2').team2Id, 'team-6');
});

test('correctScore records a reason and re-propagates a changed winner', () => {
  const store = makeStore();
  const corrected = store.actions.correctScore('qf-2', 70, 64, 'Clock review');
  assert.equal(corrected.correction.previousScore1, 64);
  assert.equal(corrected.correction.previousScore2, 69);
  assert.equal(corrected.winnerId, 'team-13');
  assert.equal(store.select.match('sf-1').team2Id, 'team-13');
  assert.equal(store.select.match('sf-1').status, 'scheduled');
  assert.equal(store.select.audit({ entityType: 'match' })[0].action, 'score.correct');
  assert.throws(
    () => store.actions.correctScore('qf-2', 70, 64, ''),
    (error) => error.code === 'VALIDATION' && error.field === 'reason'
  );
});

test('a completed CDO bracket produces a champion', () => {
  const store = makeStore();
  store.actions.generateBracket('tournament-3');
  const byRound = (round) => store.select
    .matches('tournament-3')
    .filter((match) => match.round === round)
    .sort((a, b) => a.bracketSlot - b.bracketSlot);
  byRound('8').forEach((match, index) => {
    store.actions.saveScore(match.id, 60 + index, 50);
  });
  byRound('4').forEach((match, index) => {
    store.actions.saveScore(match.id, 55, 50 + index);
  });
  const final = byRound('2')[0];
  store.actions.saveScore(final.id, 70, 65);
  const bracket = store.select.bracket('tournament-3');
  assert.ok(bracket.champion);
  assert.equal(bracket.champion.id, store.select.match(final.id).winnerId);
});

test('kpis and payment summary are computed from teams and matches', () => {
  const store = makeStore();
  const kpis = store.select.kpis('tournament-1');
  assert.equal(kpis.registered, 16);
  assert.equal(kpis.approved, 15);
  assert.equal(kpis.pending, 0);
  assert.equal(kpis.rejected, 1);
  assert.equal(kpis.courts, 4);
  assert.equal(kpis.matchesPlayed, 10);
  assert.equal(kpis.matchesScheduled, 5);
  assert.equal(kpis.collected, 19500);
  assert.equal(kpis.expected, 22500);
  assert.equal(kpis.outstanding, 3000);
  assert.equal(kpis.collectionRate, 87);
  const summary = store.select.paymentSummary('tournament-1');
  assert.equal(summary.paidCount, 13);
  assert.equal(summary.pendingCount, 2);
});

test('standings sort by wins, differential, and seed', () => {
  const store = makeStore();
  const rows = store.select.standings('tournament-1');
  assert.equal(rows.length, 15);
  assert.equal(rows[0].teamId, 'team-5');
  assert.equal(rows[0].wins, 2);
  assert.equal(rows.filter((row) => row.qualified).length, 8);
  assert.equal(rows[1].teamId, 'team-3');
  assert.equal(rows[0].rank, 1);
});

test('notifications follow the last-seen timestamp', () => {
  const store = makeStore();
  assert.equal(store.select.notifications().length, 3);
  store.actions.markAnnouncementsSeen();
  assert.equal(store.select.notifications().length, 0);
});

test('notifications respect the announcement audience', () => {
  const seed = structuredClone(SEED);
  seed.meta.lastSeenAnnouncementsAt = '2026-05-17T09:00:00+08:00';
  const store = createStore({ storage: memoryStorage(), seed });
  assert.equal(store.select.notifications().length, 4);
  store.actions.setRole('fan');
  assert.equal(store.select.notifications().length, 3);
  assert.equal(
    store.select.notifications().every((item) => item.audience === 'all'),
    true
  );
  store.actions.setRole('coach');
  assert.equal(store.select.notifications().length, 4);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```powershell
cd prototype
node --test tests/competition.test.mjs
```

Expected: FAIL because the bracket, schedule, score, and selector methods do not exist.

- [ ] **Step 3: Add bracket and schedule generation**

Add these constants above `createStore`, next to the other validation constants:

```js
const ROUND_LABELS = {
  2: 'Championship Final',
  4: 'Semifinals',
  8: 'Quarterfinals',
  16: 'Round of 16',
  32: 'Round of 32',
};
const ROUND_START_HOUR = { 32: 8, 16: 9, 8: 13, 4: 15, 2: 16 };

function seedOrder(size) {
  let order = [1, 2];
  while (order.length < size) {
    const complement = order.length * 2 + 1;
    order = order.flatMap((seed) => [seed, complement - seed]);
  }
  return order;
}

function pad(value) {
  return String(value).padStart(2, '0');
}

function buildBoxScore(team, total) {
  const count = team.players.length || 1;
  const base = Math.floor(total / count);
  const remainder = total % count;
  return team.players.map((player, index) => ({
    playerId: player.id,
    points: base + (index < remainder ? 1 : 0),
    assists: (index * 3 + 1) % 7,
    rebounds: (index * 2 + 2) % 9,
  }));
}
```

Inside `createStore`, after `markAnnouncementsSeen`, insert:

```js
  function generateBracket(tournamentId) {
    requirePermission(state, 'bracket.generate');
    const tournament = findTournament(state, tournamentId);
    const approved = state.teams.filter(
      (team) => team.tournamentId === tournamentId && team.status === 'approved'
    );
    if (approved.length < 2) {
      throw new StoreError('NOT_ENOUGH_TEAMS', 'At least two approved teams are required.');
    }
    if (approved.length > tournament.slots) {
      throw new StoreError('TOO_MANY_TEAMS', 'There are more approved teams than bracket slots.');
    }
    const hasResults = state.matches.some(
      (match) =>
        match.tournamentId === tournamentId &&
        (match.status === 'final' || match.status === 'forfeit')
    );
    if (hasResults) {
      throw new StoreError('BRACKET_LOCKED', 'This bracket already has recorded results.');
    }
    const size = tournament.slots;
    if (![2, 4, 8, 16, 32].includes(size)) {
      throw new StoreError('VALIDATION', 'Bracket size must be 8 or 16.', 'slots');
    }
    const ordered = [...approved].sort((a, b) => (a.seed ?? 999) - (b.seed ?? 999));
    const orderedSlots = seedOrder(size).map((seedNumber) => ordered[seedNumber - 1] || null);
    const next = clone(state);
    next.matches = next.matches.filter((match) => match.tournamentId !== tournamentId);
    const rounds = [];
    for (let roundSize = size; roundSize >= 2; roundSize /= 2) {
      rounds.push({ round: String(roundSize), roundSize });
    }
    const created = [];
    const byRound = new Map();
    rounds.forEach(({ round, roundSize }, roundIndex) => {
      const nextRound = rounds[roundIndex + 1];
      const count = roundSize / 2;
      const roundMatches = [];
      for (let slot = 1; slot <= count; slot += 1) {
        roundMatches.push({
          id: `${tournamentId}-${round}-${slot}`,
          tournamentId,
          round,
          roundLabel: ROUND_LABELS[roundSize],
          bracketSlot: slot,
          team1Id: null,
          team2Id: null,
          score1: null,
          score2: null,
          winnerId: null,
          status: 'scheduled',
          courtId: null,
          scheduledAt: null,
          nextMatchId: nextRound ? `${tournamentId}-${nextRound.round}-${Math.ceil(slot / 2)}` : null,
          nextSlot: nextRound ? (slot % 2 === 1 ? 1 : 2) : null,
          isBye: false,
          correction: null,
          boxScore: { team1: [], team2: [] },
        });
      }
      byRound.set(round, roundMatches);
      created.push(...roundMatches);
    });
    const firstRound = byRound.get(String(size));
    orderedSlots.forEach((team, index) => {
      const match = firstRound[Math.floor(index / 2)];
      if (index % 2 === 0) match.team1Id = team ? team.id : null;
      else match.team2Id = team ? team.id : null;
    });
    firstRound.forEach((match) => {
      if (match.team1Id && !match.team2Id) {
        match.isBye = true;
        match.status = 'final';
        match.winnerId = match.team1Id;
      }
      if (!match.team1Id && match.team2Id) {
        match.isBye = true;
        match.status = 'final';
        match.winnerId = match.team2Id;
      }
      if (!match.winnerId) return;
      const nextMatch = created.find((item) => item.id === match.nextMatchId);
      if (!nextMatch) return;
      if (match.nextSlot === 1) nextMatch.team1Id = match.winnerId;
      else nextMatch.team2Id = match.winnerId;
    });
    next.matches.push(...created);
    const targetTournament = findTournament(next, tournamentId);
    targetTournament.seedLocked = true;
    if (targetTournament.status === 'draft') targetTournament.status = 'upcoming';
    appendAudit(next, {
      action: 'bracket.generate',
      entityType: 'tournament',
      entityId: tournamentId,
      summary: `Generated a ${size}-slot single-elimination bracket`,
      changes: { teams: approved.length, byes: firstRound.filter((match) => match.isBye).length },
    });
    commit(next, 'bracket.generate');
    return {
      matches: created.length,
      byes: firstRound.filter((match) => match.isBye).length,
    };
  }

  function planSchedule(tournamentId, options = {}) {
    const tournament = findTournament(state, tournamentId);
    const matches = state.matches.filter(
      (match) => match.tournamentId === tournamentId && !match.isBye
    );
    if (!matches.length) {
      throw new StoreError('NO_BRACKET', 'Generate a bracket before scheduling.');
    }
    const hasResults = state.matches.some(
      (match) =>
        match.tournamentId === tournamentId &&
        (match.status === 'final' || match.status === 'forfeit')
    );
    if (hasResults) {
      throw new StoreError('SCHEDULE_LOCKED', 'The schedule is locked because results exist.');
    }
    const courts = Array.isArray(options.courtIds) && options.courtIds.length
      ? tournament.courts.filter((court) => options.courtIds.includes(court.id))
      : tournament.courts;
    if (!courts.length) {
      throw new StoreError('VALIDATION', 'Choose at least one court.', 'courtIds');
    }
    const startDate = options.startDate || tournament.startDate;
    const slotMinutes = Number(options.slotMinutes ?? tournament.slotMinutes);
    const restMinutes = Number(options.restMinutes ?? tournament.restMinutes);
    if (!Number.isInteger(slotMinutes) || slotMinutes < 10) {
      throw new StoreError('VALIDATION', 'Slot length must be at least 10 minutes.', 'slotMinutes');
    }
    const rounds = [...new Set(matches.map((match) => match.round))].sort(
      (a, b) => Number(b) - Number(a)
    );
    const courtSlots = new Set();
    const teamTimes = new Map();
    const assignments = [];
    rounds.forEach((round, roundIndex) => {
      const roundMatches = matches
        .filter((match) => match.round === round)
        .sort((a, b) => a.bracketSlot - b.bracketSlot);
      const date = new Date(`${startDate}T00:00:00Z`);
      date.setUTCDate(date.getUTCDate() + roundIndex);
      const dateLabel = date.toISOString().slice(0, 10);
      const baseHour = ROUND_START_HOUR[Number(round)] ?? 9;
      roundMatches.forEach((match, index) => {
        const wave = Math.floor(index / courts.length);
        const court = courts[index % courts.length];
        const totalMinutes = baseHour * 60 + wave * slotMinutes;
        const scheduledAt = `${dateLabel}T${pad(Math.floor(totalMinutes / 60))}:${pad(
          totalMinutes % 60
        )}:00+08:00`;
        const key = `${court.id}|${scheduledAt}`;
        if (courtSlots.has(key)) {
          throw new StoreError('COURT_CONFLICT', `${court.name} is double-booked at ${scheduledAt}.`);
        }
        courtSlots.add(key);
        [match.team1Id, match.team2Id].filter(Boolean).forEach((teamId) => {
          const times = teamTimes.get(teamId) || [];
          const startMs = Date.parse(scheduledAt);
          const clash = times.some(
            (other) => Math.abs(other - startMs) < (slotMinutes + restMinutes) * 60000
          );
          if (clash) {
            throw new StoreError('REST_VIOLATION', 'A team would play without the required rest.');
          }
          times.push(startMs);
          teamTimes.set(teamId, times);
        });
        assignments.push({ matchId: match.id, courtId: court.id, scheduledAt });
      });
    });
    return { assignments, courts, slotMinutes, restMinutes, startDate };
  }

  function generateSchedule(tournamentId, options = {}) {
    requirePermission(state, 'schedule.generate');
    const plan = planSchedule(tournamentId, options);
    const next = clone(state);
    plan.assignments.forEach(({ matchId, courtId, scheduledAt }) => {
      const match = next.matches.find((item) => item.id === matchId);
      if (match) {
        match.courtId = courtId;
        match.scheduledAt = scheduledAt;
      }
    });
    appendAudit(next, {
      action: 'schedule.generate',
      entityType: 'tournament',
      entityId: tournamentId,
      summary: `Scheduled ${plan.assignments.length} matches across ${plan.courts.length} courts`,
      changes: {
        courts: plan.courts.length,
        slotMinutes: plan.slotMinutes,
        restMinutes: plan.restMinutes,
      },
    });
    commit(next, 'schedule.generate');
    return plan.assignments.length;
  }
```

- [ ] **Step 4: Add score entry and correction**

After `generateSchedule`, still inside `createStore`, insert:

```js
  function placeWinner(next, match, winnerId) {
    if (!match.nextMatchId) return;
    const nextMatch = next.matches.find((item) => item.id === match.nextMatchId);
    if (!nextMatch) return;
    if (nextMatch.status === 'final' || nextMatch.status === 'forfeit') {
      throw new StoreError('DOWNSTREAM_LOCKED', 'The next-round match already has a result.');
    }
    if (match.nextSlot === 1) nextMatch.team1Id = winnerId;
    else nextMatch.team2Id = winnerId;
  }

  function saveScore(matchId, score1, score2, options = {}) {
    requirePermission(state, 'score.save');
    const match = findMatch(state, matchId);
    if (match.status === 'final' || match.status === 'forfeit') {
      throw new StoreError('MATCH_FINAL', 'Use correction to change a recorded result.');
    }
    if (!match.team1Id || !match.team2Id) {
      throw new StoreError('TEAMS_PENDING', 'Both teams must be known before entering a score.');
    }
    const forfeitTeam = Number(options.forfeitTeam || 0);
    let first = Number(score1);
    let second = Number(score2);
    if (!Number.isInteger(first) || !Number.isInteger(second) || first < 0 || second < 0) {
      throw new StoreError('VALIDATION', 'Scores must be zero or positive whole numbers.', 'score');
    }
    if (forfeitTeam && ![1, 2].includes(forfeitTeam)) {
      throw new StoreError('VALIDATION', 'Choose which team forfeited.', 'forfeitTeam');
    }
    if (!forfeitTeam && first === second) {
      throw new StoreError('TIE', 'Elimination games cannot end in a tie.', 'score');
    }
    const next = clone(state);
    const target = findMatch(next, matchId);
    const team1 = findTeam(next, target.team1Id);
    const team2 = findTeam(next, target.team2Id);
    if (forfeitTeam) {
      first = forfeitTeam === 1 ? 0 : 20;
      second = forfeitTeam === 2 ? 0 : 20;
      target.status = 'forfeit';
      target.winnerId = forfeitTeam === 1 ? team2.id : team1.id;
    } else {
      target.status = 'final';
      target.winnerId = first > second ? team1.id : team2.id;
    }
    target.score1 = first;
    target.score2 = second;
    target.boxScore = {
      team1: buildBoxScore(team1, first),
      team2: buildBoxScore(team2, second),
    };
    placeWinner(next, target, target.winnerId);
    appendAudit(next, {
      action: 'score.save',
      entityType: 'match',
      entityId: matchId,
      summary: `${team1.shortName} ${first}-${second} ${team2.shortName}`,
      changes: { status: target.status, score1: first, score2: second, winnerId: target.winnerId },
    });
    commit(next, 'score.save');
    return clone(target);
  }

  function correctScore(matchId, score1, score2, reason) {
    requirePermission(state, 'score.correct');
    const match = findMatch(state, matchId);
    if (match.status !== 'final' && match.status !== 'forfeit') {
      throw new StoreError('MATCH_NOT_FINAL', 'Only recorded results can be corrected.');
    }
    const cleanReason = requireText(reason, 'reason', 'Correction reason', 5);
    const first = Number(score1);
    const second = Number(score2);
    if (!Number.isInteger(first) || !Number.isInteger(second) || first < 0 || second < 0) {
      throw new StoreError('VALIDATION', 'Scores must be zero or positive whole numbers.', 'score');
    }
    if (first === second) {
      throw new StoreError('TIE', 'Elimination games cannot end in a tie.', 'score');
    }
    const next = clone(state);
    const target = findMatch(next, matchId);
    const team1 = findTeam(next, target.team1Id);
    const team2 = findTeam(next, target.team2Id);
    const previousScore1 = target.score1;
    const previousScore2 = target.score2;
    const previousWinnerId = target.winnerId;
    target.score1 = first;
    target.score2 = second;
    target.winnerId = first > second ? team1.id : team2.id;
    target.status = 'final';
    target.correction = {
      reason: cleanReason,
      previousScore1,
      previousScore2,
      correctedAt: isoNow(),
      correctedBy: currentUser(state).id,
    };
    target.boxScore = {
      team1: buildBoxScore(team1, first),
      team2: buildBoxScore(team2, second),
    };
    if (previousWinnerId !== target.winnerId) {
      placeWinner(next, target, target.winnerId);
    }
    appendAudit(next, {
      action: 'score.correct',
      entityType: 'match',
      entityId: matchId,
      summary: `Corrected ${team1.shortName} ${previousScore1}-${previousScore2} ${team2.shortName} to ${first}-${second}`,
      changes: { previousScore1, previousScore2, score1: first, score2: second, reason: cleanReason },
    });
    commit(next, 'score.correct');
    return clone(target);
  }
```

- [ ] **Step 5: Add computed selectors**

Inside `createStore`, immediately before `const select = {`, insert:

```js
  function bracketFor(tournamentId) {
    const matches = state.matches.filter((match) => match.tournamentId === tournamentId);
    const rounds = [...new Set(matches.map((match) => match.round))].sort(
      (a, b) => Number(b) - Number(a)
    );
    const finalMatch = matches.find((match) => !match.nextMatchId) || null;
    const championId =
      finalMatch && (finalMatch.status === 'final' || finalMatch.status === 'forfeit')
        ? finalMatch.winnerId
        : null;
    return {
      hasBracket: matches.length > 0,
      rounds: rounds.map((round) => ({
        round,
        label: matches.find((match) => match.round === round)?.roundLabel || round,
        matches: matches
          .filter((match) => match.round === round)
          .sort((a, b) => a.bracketSlot - b.bracketSlot)
          .map(clone),
      })),
      final: finalMatch ? clone(finalMatch) : null,
      champion: championId
        ? clone(state.teams.find((team) => team.id === championId) || null)
        : null,
    };
  }

  function standingsFor(tournamentId) {
    const tournament = state.tournaments.find((item) => item.id === tournamentId);
    if (!tournament) return [];
    const teams = state.teams.filter(
      (team) => team.tournamentId === tournamentId && team.status === 'approved'
    );
    const rows = teams.map((team) => {
      const played = state.matches.filter(
        (match) =>
          match.tournamentId === tournamentId &&
          !match.isBye &&
          (match.status === 'final' || match.status === 'forfeit') &&
          (match.team1Id === team.id || match.team2Id === team.id)
      );
      let wins = 0;
      let losses = 0;
      let pointsFor = 0;
      let pointsAgainst = 0;
      played.forEach((match) => {
        const isTeam1 = match.team1Id === team.id;
        const own = isTeam1 ? match.score1 : match.score2;
        const other = isTeam1 ? match.score2 : match.score1;
        pointsFor += own ?? 0;
        pointsAgainst += other ?? 0;
        if (match.winnerId === team.id) wins += 1;
        else losses += 1;
      });
      return {
        teamId: team.id,
        team: clone(team),
        wins,
        losses,
        played: wins + losses,
        pointsFor,
        pointsAgainst,
        diff: pointsFor - pointsAgainst,
        seed: team.seed,
      };
    });
    rows.sort(
      (a, b) =>
        b.wins - a.wins ||
        b.diff - a.diff ||
        b.pointsFor - a.pointsFor ||
        (a.seed ?? 999) - (b.seed ?? 999)
    );
    const cut = Math.ceil(tournament.slots / 2);
    return rows.map((row, index) => ({ ...row, rank: index + 1, qualified: index < cut }));
  }

  function kpisFor(tournamentId) {
    const tournament = state.tournaments.find((item) => item.id === tournamentId);
    const teams = state.teams.filter((team) => team.tournamentId === tournamentId);
    const matches = state.matches.filter((match) => match.tournamentId === tournamentId);
    const billable = teams.filter((team) => team.status !== 'rejected');
    const paid = billable.filter((team) => team.payment.status === 'paid');
    const expected = billable.length * (tournament?.fee || 0);
    const collected = paid.reduce((sum, team) => sum + (team.payment.amount || 0), 0);
    return {
      registered: teams.length,
      approved: teams.filter((team) => team.status === 'approved').length,
      pending: teams.filter((team) => team.status === 'pending').length,
      rejected: teams.filter((team) => team.status === 'rejected').length,
      collected,
      outstanding: Math.max(0, expected - collected),
      expected,
      collectionRate: expected ? Math.round((collected / expected) * 100) : 0,
      matchesPlayed: matches.filter(
        (match) => match.status === 'final' || match.status === 'forfeit'
      ).length,
      matchesScheduled: matches.filter((match) => match.status === 'scheduled').length,
      courts: tournament?.courts.length || 0,
      pendingPayments: billable.filter((team) => team.payment.status !== 'paid').length,
    };
  }

  function paymentSummaryFor(tournamentId) {
    const teams = state.teams.filter(
      (team) => team.tournamentId === tournamentId && team.status !== 'rejected'
    );
    const kpis = kpisFor(tournamentId);
    return {
      collected: kpis.collected,
      outstanding: kpis.outstanding,
      expected: kpis.expected,
      rate: kpis.collectionRate,
      paidCount: teams.filter((team) => team.payment.status === 'paid').length,
      pendingCount: teams.filter((team) => team.payment.status === 'pending').length,
      partialCount: teams.filter((team) => team.payment.status === 'partial').length,
    };
  }

  function recentResultsFor(tournamentId, limit = 4) {
    return state.matches
      .filter(
        (match) =>
          match.tournamentId === tournamentId &&
          (match.status === 'final' || match.status === 'forfeit') &&
          !match.isBye
      )
      .sort((a, b) => String(b.scheduledAt || '').localeCompare(String(a.scheduledAt || '')))
      .slice(0, limit)
      .map(clone);
  }

  function upcomingFor(tournamentId, limit = 4) {
    return state.matches
      .filter((match) => match.tournamentId === tournamentId && match.status === 'scheduled')
      .sort((a, b) => {
        if (!a.scheduledAt) return 1;
        if (!b.scheduledAt) return -1;
        return a.scheduledAt.localeCompare(b.scheduledAt);
      })
      .slice(0, limit)
      .map(clone);
  }

  function teamMatchesFor(teamId) {
    return state.matches
      .filter((match) => match.team1Id === teamId || match.team2Id === teamId)
      .sort((a, b) => String(a.scheduledAt || '').localeCompare(String(b.scheduledAt || '')))
      .map(clone);
  }

  function nextMatchFor(teamId) {
    const match = teamMatchesFor(teamId).find((item) => item.status === 'scheduled');
    return match || null;
  }

  function coachTeamsFor() {
    const user = currentUser(state);
    const coachName = user.name.replace(/^Coach\s+/i, '').trim();
    return clone(
      state.teams.filter((team) => team.coach === coachName || team.coach === user.name)
    );
  }

  function notificationsFor() {
    const lastSeen = state.meta?.lastSeenAnnouncementsAt || '1970-01-01T00:00:00+08:00';
    const allowed = AUDIENCE_BY_ROLE[getRole()] || ['all'];
    return clone(
      state.announcements.filter(
        (item) => allowed.includes(item.audience) && item.createdAt > lastSeen
      )
    );
  }
```

- [ ] **Step 6: Add the selectors and expose `planSchedule`**

Inside the `select` object, after `audit`, add:

```js
    bracket: (tournamentId) => bracketFor(tournamentId),
    standings: (tournamentId) => standingsFor(tournamentId),
    kpis: (tournamentId) => kpisFor(tournamentId),
    paymentSummary: (tournamentId) => paymentSummaryFor(tournamentId),
    teamRecord: (teamId) => {
      const team = state.teams.find((item) => item.id === teamId);
      if (!team) return null;
      const row = standingsFor(team.tournamentId).find((item) => item.teamId === teamId);
      return row
        ? {
            wins: row.wins,
            losses: row.losses,
            pointsFor: row.pointsFor,
            pointsAgainst: row.pointsAgainst,
            diff: row.diff,
            rank: row.rank,
          }
        : { wins: 0, losses: 0, pointsFor: 0, pointsAgainst: 0, diff: 0, rank: null };
    },
    recentResults: (tournamentId, limit = 4) => recentResultsFor(tournamentId, limit),
    upcomingMatches: (tournamentId, limit = 4) => upcomingFor(tournamentId, limit),
    teamMatches: (teamId) => teamMatchesFor(teamId),
    nextMatch: (teamId) => nextMatchFor(teamId),
    coachTeams: () => coachTeamsFor(),
    notifications: () => notificationsFor(),
```

In the returned store object, add `planSchedule` next to `select`:

```js
  return {
    getState,
    subscribe,
    getRole,
    getCurrentUser,
    can,
    canRole,
    reload() {
      state = load();
      listeners.forEach((listener) => listener(state, 'reload'));
    },
    planSchedule,
    select,
    actions: {
```

Replace the existing `actions` object with:

```js
    actions: {
      reset,
      setRole,
      createTournament,
      registerTeam,
      reviewTeam,
      verifyPayment,
      publishAnnouncement,
      addPlayer,
      updatePlayer,
      removePlayer,
      submitPayment,
      markAnnouncementsSeen,
      generateBracket,
      generateSchedule,
      saveScore,
      correctScore,
    },
  };
```

- [ ] **Step 7: Run the competition tests**

```powershell
cd prototype
node --test tests/competition.test.mjs
```

Expected: all 11 tests PASS.

- [ ] **Step 8: Run the full suite**

```powershell
cd prototype
node --test tests
```

Expected: all tests PASS.

- [ ] **Step 9: Commit**

```powershell
git add prototype/assets/js/store.js prototype/tests/competition.test.mjs
git commit -m "feat(prototype): add bracket, schedule, scoring, and standings selectors"
```

## Task 6: Courtside broadcast visual system

**Files:**
- Rewrite: `prototype/assets/css/hoopflow.css`

**Interfaces:**
- Consumes: vendored `assets/vendor/fonts/fonts.css` from Task 1.
- Produces: the complete `hf-*` class vocabulary used by `ui.js`, `components.js`, `shell.js`, and every page module.

- [ ] **Step 1: Replace `hoopflow.css` with the token and layout layer**

Write the following as the first section of `prototype/assets/css/hoopflow.css`:

```css
@import url('../vendor/fonts/fonts.css');

:root {
  --hf-ground: #f2f0ea;
  --hf-surface: #fffdf8;
  --hf-surface-2: #ffffff;
  --hf-ink: #0b1220;
  --hf-muted: #5b6472;
  --hf-primary: #ea580c;
  --hf-primary-pressed: #c2410c;
  --hf-gold: #e0a100;
  --hf-alert: #d61f3f;
  --hf-success: #0e7a5f;
  --hf-warning: #b45309;
  --hf-slate: #475569;
  --hf-border: color-mix(in srgb, var(--hf-ink) 12%, transparent);
  --hf-border-strong: color-mix(in srgb, var(--hf-ink) 24%, transparent);
  --hf-rail: #0b1220;
  --hf-rail-ink: #f3f5f9;
  --hf-rail-muted: #9aa6b8;
  --hf-radius: 14px;
  --hf-radius-sm: 10px;
  --hf-pill: 999px;
  --hf-shadow: 0 1px 2px rgba(11, 18, 32, 0.06), 0 8px 24px rgba(11, 18, 32, 0.05);
  --hf-font-display: 'Barlow Condensed', 'Arial Narrow', sans-serif;
  --hf-font-body: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
  --hf-focus: 0 0 0 3px color-mix(in srgb, var(--hf-primary) 40%, transparent);
  --bs-body-font-family: var(--hf-font-body);
  --bs-body-bg: var(--hf-ground);
  --bs-body-color: var(--hf-ink);
  --bs-border-color: var(--hf-border);
}

[data-theme='dark'] {
  --hf-ground: #080d17;
  --hf-surface: #101827;
  --hf-surface-2: #162033;
  --hf-ink: #f3f5f9;
  --hf-muted: #9aa6b8;
  --hf-primary: #fb7a1e;
  --hf-primary-pressed: #ea580c;
  --hf-gold: #f5b301;
  --hf-alert: #ff5c7a;
  --hf-success: #34d399;
  --hf-warning: #fbbf24;
  --hf-slate: #94a3b8;
  --hf-border: rgba(243, 245, 249, 0.12);
  --hf-border-strong: rgba(243, 245, 249, 0.24);
  --hf-rail: #060a12;
  --hf-shadow: 0 1px 2px rgba(0, 0, 0, 0.4), 0 12px 32px rgba(0, 0, 0, 0.35);
}

*,
*::before,
*::after {
  box-sizing: border-box;
}

html {
  scroll-behavior: smooth;
}

body {
  margin: 0;
  min-height: 100vh;
  background: var(--hf-ground);
  color: var(--hf-ink);
  font-family: var(--hf-font-body);
  font-size: 15px;
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
}

a {
  color: inherit;
  text-decoration: none;
}

a:hover {
  text-decoration: underline;
  text-decoration-color: var(--hf-primary);
  text-underline-offset: 3px;
}

button,
input,
select,
textarea {
  font: inherit;
}

:focus-visible {
  outline: 3px solid var(--hf-primary);
  outline-offset: 2px;
}

.hf-skip-link {
  position: absolute;
  left: 12px;
  top: -60px;
  z-index: 200;
  background: var(--hf-ink);
  color: var(--hf-ground);
  padding: 10px 14px;
  border-radius: 8px;
}

.hf-skip-link:focus {
  top: 12px;
}

.hf-app {
  display: grid;
  grid-template-columns: 248px minmax(0, 1fr);
  grid-template-rows: auto 1fr;
  min-height: 100vh;
}

#hfShell {
  display: contents;
}

.hf-main {
  grid-column: 2;
  min-width: 0;
  padding: 24px 32px 56px;
}

.hf-page-loading {
  display: grid;
  place-items: center;
  min-height: 50vh;
  color: var(--hf-muted);
  font-weight: 600;
}

/* Press-box rail */
.hf-rail {
  grid-row: 1 / span 2;
  position: sticky;
  top: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  height: 100vh;
  overflow-y: auto;
  padding: 18px 14px;
  background: var(--hf-rail);
  color: var(--hf-rail-ink);
}

.hf-rail-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px 8px 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  margin-bottom: 8px;
}

.hf-rail-brand img {
  width: 40px;
  height: 40px;
  object-fit: contain;
  background: #fff;
  border-radius: 9px;
  padding: 4px;
}

.hf-rail-brand strong {
  display: block;
  font-family: var(--hf-font-display);
  font-size: 25px;
  font-weight: 800;
  line-height: 0.9;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.hf-rail-brand small {
  display: block;
  margin-top: 4px;
  color: var(--hf-rail-muted);
  font-size: 10px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.hf-rail-label {
  margin: 12px 10px 4px;
  color: var(--hf-rail-muted);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.hf-rail-link {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  padding: 9px 10px;
  border-radius: 10px;
  color: var(--hf-rail-muted);
  font-size: 14px;
  font-weight: 600;
  transition: background 160ms ease, color 160ms ease;
}

.hf-rail-link:hover {
  background: rgba(255, 255, 255, 0.07);
  color: #fff;
  text-decoration: none;
}

.hf-rail-link.is-active {
  background: var(--hf-primary);
  color: #fff;
  box-shadow: inset 0 -2px 0 rgba(0, 0, 0, 0.25);
}

.hf-rail-link .bi {
  width: 20px;
  font-size: 17px;
  text-align: center;
}

.hf-rail-badge {
  margin-left: auto;
  padding: 2px 7px;
  border-radius: var(--hf-pill);
  background: var(--hf-gold);
  color: #1a1300;
  font-size: 11px;
  font-weight: 800;
}

.hf-rail-footer {
  margin-top: auto;
  padding-top: 12px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

/* Topbar */
.hf-topbar {
  grid-column: 2;
  position: sticky;
  top: 0;
  z-index: 30;
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 64px;
  padding: 10px 24px;
  background: color-mix(in srgb, var(--hf-surface) 92%, transparent);
  border-bottom: 1px solid var(--hf-border);
  backdrop-filter: blur(10px);
}

.hf-topbar-context {
  min-width: 0;
}

.hf-topbar-title {
  margin: 0;
  overflow: hidden;
  font-family: var(--hf-font-display);
  font-size: 19px;
  font-weight: 800;
  line-height: 1;
  letter-spacing: 0.04em;
  text-overflow: ellipsis;
  text-transform: uppercase;
  white-space: nowrap;
}

.hf-topbar-sub {
  margin-top: 3px;
  color: var(--hf-muted);
  font-size: 12px;
}

.hf-topbar-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: auto;
}

.hf-icon-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border: 1px solid var(--hf-border);
  border-radius: 10px;
  background: var(--hf-surface-2);
  color: var(--hf-ink);
  cursor: pointer;
  transition: background 160ms ease, transform 160ms ease;
}

.hf-icon-btn:hover {
  background: color-mix(in srgb, var(--hf-primary) 10%, var(--hf-surface-2));
  transform: translateY(-1px);
  text-decoration: none;
}

.hf-notification-dot {
  position: absolute;
  top: 9px;
  right: 10px;
  width: 8px;
  height: 8px;
  border: 2px solid var(--hf-surface-2);
  border-radius: 50%;
  background: var(--hf-alert);
}

.hf-user-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 4px 10px 4px 4px;
  border: 1px solid var(--hf-border);
  border-radius: var(--hf-pill);
  background: var(--hf-surface-2);
}

.hf-user-chip:hover {
  text-decoration: none;
}

.hf-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--hf-primary);
  color: #fff;
  font-family: var(--hf-font-display);
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 0.04em;
}

.hf-user-meta {
  display: none;
  line-height: 1.1;
}

.hf-user-meta strong {
  display: block;
  font-size: 12px;
}

.hf-user-meta span {
  color: var(--hf-muted);
  font-size: 10px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

@media (min-width: 1200px) {
  .hf-user-meta {
    display: block;
  }
}

/* Demo menu */
.hf-demo-menu {
  position: relative;
}

.hf-topbar-actions > .hf-demo-menu {
  display: none;
}

.hf-demo-menu summary {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 12px;
  border: 1px solid var(--hf-border);
  border-radius: 10px;
  background: var(--hf-surface-2);
  color: var(--hf-ink);
  cursor: pointer;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.08em;
  list-style: none;
  text-transform: uppercase;
}

.hf-demo-menu summary::-webkit-details-marker {
  display: none;
}

.hf-demo-panel {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  z-index: 60;
  width: min(290px, calc(100vw - 24px));
  padding: 12px;
  border: 1px solid var(--hf-border);
  border-radius: 12px;
  background: var(--hf-surface-2);
  box-shadow: var(--hf-shadow);
}

.hf-demo-panel--up {
  top: auto;
  bottom: calc(100% + 8px);
  right: auto;
  left: 0;
}

.hf-demo-title {
  margin: 0 0 8px;
  color: var(--hf-muted);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.hf-demo-roles {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
  margin-bottom: 10px;
}

.hf-demo-roles button {
  min-height: 40px;
  border: 1px solid var(--hf-border);
  border-radius: 8px;
  background: var(--hf-surface);
  color: var(--hf-ink);
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
  text-transform: capitalize;
}

.hf-demo-roles button.is-active {
  border-color: var(--hf-primary);
  background: var(--hf-primary);
  color: #fff;
}

.hf-demo-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 7px 0;
  border-top: 1px solid var(--hf-border);
  font-size: 12px;
}

.hf-demo-row .hf-btn {
  min-height: 36px;
}

.hf-demo-seed {
  color: var(--hf-muted);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 11px;
}

/* Mobile navigation */
.hf-bottom-nav {
  display: none;
  position: fixed;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 40;
  grid-template-columns: repeat(5, 1fr);
  background: var(--hf-surface-2);
  border-top: 1px solid var(--hf-border);
  padding-bottom: env(safe-area-inset-bottom);
}

.hf-nav-tab {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;
  min-height: 58px;
  color: var(--hf-muted);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.hf-nav-tab .bi {
  font-size: 20px;
}

.hf-nav-tab.is-active {
  color: var(--hf-primary);
}

.hf-nav-tab.is-active .bi::after {
  display: block;
  width: 18px;
  height: 3px;
  margin: 4px auto 0;
  border-radius: 3px;
  background: var(--hf-primary);
  content: '';
}

@media (max-width: 991.98px) {
  .hf-app {
    grid-template-columns: minmax(0, 1fr);
    grid-template-rows: auto 1fr;
  }

  .hf-rail {
    display: none;
  }

  .hf-topbar {
    grid-column: 1;
    min-height: 58px;
    padding: 8px 14px;
  }

  .hf-topbar-actions > .hf-demo-menu {
    display: block;
  }

  .hf-main {
    grid-column: 1;
    padding: 16px 14px 92px;
  }

  .hf-bottom-nav {
    display: grid;
  }

  .hf-topbar-title {
    font-size: 16px;
  }

  .hf-demo-menu summary span {
    display: none;
  }
}
```

- [ ] **Step 2: Add the page, card, match, bracket, schedule, and standings layer**

Append to `hoopflow.css`:

```css
/* Page headers */
.hf-page-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 20px;
  padding: 20px;
  border: 1px solid var(--hf-border);
  border-left: 6px solid var(--hf-primary);
  border-radius: var(--hf-radius);
  background:
    linear-gradient(115deg, var(--hf-surface), color-mix(in srgb, var(--hf-primary) 6%, var(--hf-surface)));
}

.hf-eyebrow {
  color: var(--hf-primary);
  font-family: var(--hf-font-display);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.hf-page-title {
  margin: 4px 0 6px;
  font-family: var(--hf-font-display);
  font-size: clamp(30px, 3.4vw, 44px);
  font-weight: 800;
  line-height: 0.95;
  letter-spacing: 0.01em;
  text-transform: uppercase;
}

.hf-page-desc {
  max-width: 68ch;
  margin: 0;
  color: var(--hf-muted);
}

.hf-page-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 16px;
  margin-top: 12px;
  color: var(--hf-muted);
  font-size: 13px;
}

.hf-page-meta span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.hf-page-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  flex-shrink: 0;
}

@media (max-width: 767.98px) {
  .hf-page-head {
    align-items: stretch;
    flex-direction: column;
    padding: 16px;
  }

  .hf-page-actions .hf-btn {
    flex: 1;
  }
}

/* Buttons */
.hf-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 16px;
  border: 1px solid var(--hf-border-strong);
  border-radius: var(--hf-radius-sm);
  background: var(--hf-surface-2);
  box-shadow: 0 2px 0 var(--hf-border-strong);
  color: var(--hf-ink);
  cursor: pointer;
  font-size: 14px;
  font-weight: 700;
  line-height: 1;
  transition: transform 160ms ease, background 160ms ease, box-shadow 160ms ease;
}

.hf-btn:hover {
  background: color-mix(in srgb, var(--hf-primary) 8%, var(--hf-surface-2));
  text-decoration: none;
  transform: translateY(-1px);
}

.hf-btn:active {
  box-shadow: none;
  transform: translateY(0);
}

.hf-btn--primary {
  border-color: var(--hf-primary);
  background: var(--hf-primary);
  box-shadow: 0 2px 0 var(--hf-primary-pressed);
  color: #fff;
}

.hf-btn--primary:hover {
  background: var(--hf-primary-pressed);
}

.hf-btn--danger {
  border-color: var(--hf-alert);
  background: var(--hf-alert);
  box-shadow: 0 2px 0 color-mix(in srgb, var(--hf-alert) 70%, #000);
  color: #fff;
}

.hf-btn--ghost {
  border-color: transparent;
  background: transparent;
  box-shadow: none;
}

.hf-btn--sm {
  min-height: 36px;
  padding: 0 12px;
  font-size: 13px;
}

.hf-btn[disabled],
.hf-btn[aria-disabled='true'] {
  cursor: not-allowed;
  opacity: 0.5;
  transform: none;
}

.hf-btn-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

/* Cards and grids */
.hf-card {
  padding: 18px;
  border: 1px solid var(--hf-border);
  border-radius: var(--hf-radius);
  background: var(--hf-surface);
  box-shadow: var(--hf-shadow);
}

.hf-card--flat {
  box-shadow: none;
}

.hf-card--ink {
  border-width: 2px;
  border-color: var(--hf-ink);
}

.hf-grid {
  display: grid;
  gap: 14px;
}

.hf-grid--2 {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.hf-grid--3 {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.hf-grid--4 {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.hf-split {
  display: grid;
  grid-template-columns: minmax(0, 1.7fr) minmax(280px, 1fr);
  gap: 20px;
  align-items: start;
}

@media (max-width: 1199.98px) {
  .hf-grid--4 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 991.98px) {
  .hf-split {
    grid-template-columns: minmax(0, 1fr);
  }

  .hf-grid--3 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 575.98px) {
  .hf-grid--2,
  .hf-grid--3,
  .hf-grid--4 {
    grid-template-columns: minmax(0, 1fr);
  }
}

.hf-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin: 0 0 10px;
}

.hf-section-title {
  margin: 0;
  font-family: var(--hf-font-display);
  font-size: 19px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.hf-section-link {
  color: var(--hf-muted);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.hf-section-link:hover {
  color: var(--hf-primary);
}

/* KPI scoreboard tiles */
.hf-kpi {
  position: relative;
  display: block;
  min-height: 118px;
  padding: 16px;
  overflow: hidden;
  border: 1px solid var(--hf-border);
  border-top: 3px solid var(--hf-ink);
  border-radius: var(--hf-radius);
  background: var(--hf-surface);
  box-shadow: var(--hf-shadow);
}

.hf-kpi:hover {
  border-color: var(--hf-border-strong);
  text-decoration: none;
  transform: translateY(-1px);
}

.hf-kpi--primary {
  border-top-color: var(--hf-primary);
}

.hf-kpi--gold {
  border-top-color: var(--hf-gold);
}

.hf-kpi--success {
  border-top-color: var(--hf-success);
}

.hf-kpi--alert {
  border-top-color: var(--hf-alert);
}

.hf-kpi-label {
  color: var(--hf-muted);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.hf-kpi-value {
  display: block;
  margin-top: 4px;
  font-family: var(--hf-font-display);
  font-size: 46px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  line-height: 0.9;
}

.hf-kpi-hint {
  display: block;
  margin-top: 8px;
  color: var(--hf-muted);
  font-size: 12px;
}

.hf-kpi::after {
  position: absolute;
  right: -12px;
  bottom: -18px;
  color: color-mix(in srgb, var(--hf-ink) 6%, transparent);
  font-family: var(--hf-font-display);
  font-size: 110px;
  font-weight: 800;
  line-height: 1;
  content: attr(data-mark);
  pointer-events: none;
}

/* Match cards */
.hf-match-card {
  display: grid;
  gap: 8px;
  padding: 12px;
  border: 1px solid var(--hf-border);
  border-left: 4px solid var(--hf-slate);
  border-radius: 12px;
  background: var(--hf-surface-2);
}

.hf-match-card--final {
  border-left-color: var(--hf-success);
}

.hf-match-card--forfeit {
  border-left-color: var(--hf-alert);
}

.hf-match-card--scheduled {
  border-left-color: var(--hf-slate);
}

.hf-match-card:hover {
  border-color: var(--hf-border-strong);
  text-decoration: none;
}

.hf-match-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  color: var(--hf-muted);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.09em;
  text-transform: uppercase;
}

.hf-match-team {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 8px;
  min-height: 34px;
}

.hf-match-team.is-winner {
  font-weight: 800;
}

.hf-match-team.is-loser {
  color: var(--hf-muted);
}

.hf-match-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hf-score {
  font-family: var(--hf-font-display);
  font-size: 27px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  line-height: 1;
}

.hf-score--muted {
  color: var(--hf-muted);
  font-size: 18px;
}

.hf-shield {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: var(--team-color, #334155);
  color: #fff;
  font-family: var(--hf-font-display);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.03em;
}

.hf-shield--lg {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  font-size: 20px;
}

.hf-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 9px;
  border: 1px solid transparent;
  border-radius: var(--hf-pill);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.05em;
  line-height: 1.5;
  text-transform: uppercase;
  white-space: nowrap;
}

.hf-chip--success {
  border-color: color-mix(in srgb, var(--hf-success) 30%, transparent);
  background: color-mix(in srgb, var(--hf-success) 13%, transparent);
  color: var(--hf-success);
}

.hf-chip--warning {
  border-color: color-mix(in srgb, var(--hf-warning) 30%, transparent);
  background: color-mix(in srgb, var(--hf-warning) 13%, transparent);
  color: var(--hf-warning);
}

.hf-chip--danger {
  border-color: color-mix(in srgb, var(--hf-alert) 30%, transparent);
  background: color-mix(in srgb, var(--hf-alert) 13%, transparent);
  color: var(--hf-alert);
}

.hf-chip--primary {
  border-color: color-mix(in srgb, var(--hf-primary) 30%, transparent);
  background: color-mix(in srgb, var(--hf-primary) 13%, transparent);
  color: var(--hf-primary);
}

.hf-chip--slate {
  border-color: color-mix(in srgb, var(--hf-slate) 30%, transparent);
  background: color-mix(in srgb, var(--hf-slate) 12%, transparent);
  color: var(--hf-slate);
}

.hf-chip--gold {
  border-color: color-mix(in srgb, var(--hf-gold) 40%, transparent);
  background: color-mix(in srgb, var(--hf-gold) 16%, transparent);
  color: var(--hf-warning);
}

/* Bracket */
.hf-bracket {
  display: flex;
  gap: 26px;
  overflow-x: auto;
  padding: 18px 6px 12px;
}

.hf-bracket-round {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 14px;
  min-width: 232px;
}

.hf-bracket-round-title {
  position: sticky;
  top: 0;
  z-index: 2;
  padding: 4px 0 8px;
  border-bottom: 2px solid var(--hf-ink);
  color: var(--hf-muted);
  font-family: var(--hf-font-display);
  font-size: 15px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.hf-bracket-match {
  position: relative;
  display: grid;
  gap: 6px;
  padding: 10px;
  border: 1px solid var(--hf-border);
  border-left: 4px solid var(--hf-slate);
  border-radius: 10px;
  background: var(--hf-surface-2);
  cursor: pointer;
  transition: border-color 160ms ease, box-shadow 160ms ease;
}

.hf-bracket-match:hover,
.hf-bracket-match.is-path {
  border-color: var(--hf-primary);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--hf-primary) 22%, transparent);
}

.hf-bracket-match--final {
  border-left-color: var(--hf-success);
}

.hf-bracket-match--forfeit {
  border-left-color: var(--hf-alert);
}

.hf-bracket-match__slot {
  color: var(--hf-muted);
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.hf-bracket-mobile {
  display: none;
}

@media (max-width: 991.98px) {
  .hf-bracket {
    display: none;
  }

  .hf-bracket-mobile {
    display: block;
  }
}

.hf-round-pager {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 8px;
  margin-bottom: 10px;
}

.hf-round-pager button {
  flex: 0 0 auto;
  min-height: 40px;
  padding: 0 12px;
  border: 1px solid var(--hf-border);
  border-radius: var(--hf-pill);
  background: var(--hf-surface-2);
  color: var(--hf-muted);
  cursor: pointer;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.hf-round-pager button.is-active {
  border-color: var(--hf-ink);
  background: var(--hf-ink);
  color: var(--hf-ground);
}

.hf-round-progress {
  height: 4px;
  margin-bottom: 12px;
  overflow: hidden;
  border-radius: 4px;
  background: var(--hf-border);
}

.hf-round-progress span {
  display: block;
  height: 100%;
  background: var(--hf-primary);
  transition: width 220ms ease;
}

.hf-bracket-champion {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  border: 2px solid var(--hf-gold);
  border-radius: var(--hf-radius);
  background: color-mix(in srgb, var(--hf-gold) 10%, var(--hf-surface));
  box-shadow: 0 0 28px color-mix(in srgb, var(--hf-gold) 30%, transparent);
}

.hf-bracket-champion strong {
  font-family: var(--hf-font-display);
  font-size: 26px;
  text-transform: uppercase;
}

/* Schedule */
.hf-calendar {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 4px;
}

.hf-calendar-head {
  padding: 4px;
  color: var(--hf-muted);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-align: center;
  text-transform: uppercase;
}

.hf-calendar-cell {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 52px;
  padding: 4px;
  border: 1px solid transparent;
  border-radius: 8px;
  background: transparent;
  color: var(--hf-ink);
  cursor: pointer;
  font-family: var(--hf-font-display);
  font-size: 18px;
  font-weight: 700;
}

.hf-calendar-cell:hover {
  background: color-mix(in srgb, var(--hf-primary) 8%, transparent);
}

.hf-calendar-cell.is-blank {
  cursor: default;
  opacity: 0.3;
}

.hf-calendar-cell.has-events {
  border-color: color-mix(in srgb, var(--hf-primary) 24%, transparent);
  background: color-mix(in srgb, var(--hf-primary) 7%, transparent);
}

.hf-calendar-cell.is-selected {
  border-color: var(--hf-ink);
  background: var(--hf-ink);
  color: var(--hf-ground);
}

.hf-calendar-count {
  display: block;
  margin-top: 2px;
  font-family: var(--hf-font-body);
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.03em;
}

.hf-week-strip {
  display: none;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 8px;
}

.hf-week-strip button {
  flex: 0 0 64px;
  min-height: 64px;
  border: 1px solid var(--hf-border);
  border-radius: 10px;
  background: var(--hf-surface-2);
  color: var(--hf-ink);
  cursor: pointer;
  text-align: center;
}

.hf-week-strip button.is-selected {
  border-color: var(--hf-ink);
  background: var(--hf-ink);
  color: var(--hf-ground);
}

.hf-week-strip small {
  display: block;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.hf-week-strip strong {
  display: block;
  font-family: var(--hf-font-display);
  font-size: 22px;
  line-height: 1;
}

@media (max-width: 767.98px) {
  .hf-calendar {
    display: none;
  }

  .hf-week-strip {
    display: flex;
  }
}

.hf-filter-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 14px;
}

.hf-filter {
  min-height: 38px;
  padding: 0 12px;
  border: 1px solid var(--hf-border);
  border-radius: var(--hf-pill);
  background: var(--hf-surface-2);
  color: var(--hf-muted);
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
}

.hf-filter.is-active {
  border-color: var(--hf-primary);
  background: var(--hf-primary);
  color: #fff;
}

/* Standings */
.hf-table-wrap {
  overflow-x: auto;
  border: 1px solid var(--hf-border);
  border-radius: var(--hf-radius);
  background: var(--hf-surface);
}

.hf-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.hf-table th {
  padding: 10px 8px;
  border-bottom: 2px solid var(--hf-ink);
  color: var(--hf-muted);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-align: left;
  text-transform: uppercase;
  white-space: nowrap;
}

.hf-table td {
  padding: 10px 8px;
  border-top: 1px solid var(--hf-border);
  vertical-align: middle;
}

.hf-table tbody tr:hover {
  background: color-mix(in srgb, var(--hf-primary) 4%, transparent);
}

.hf-table .is-num {
  font-family: var(--hf-font-display);
  font-size: 17px;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  text-align: right;
}

.hf-table .is-qualified {
  box-shadow: inset 3px 0 0 var(--hf-gold);
}

.hf-table .hf-qual-line td {
  height: 3px;
  padding: 0;
  border: 0;
  background: var(--hf-gold);
}

.hf-standings-cards {
  display: none;
  gap: 8px;
}

@media (max-width: 767.98px) {
  .hf-table--standings {
    display: none;
  }

  .hf-standings-cards {
    display: grid;
  }
}
```

- [ ] **Step 3: Add forms, dialogs, toasts, empty states, steppers, auth, and motion**

Append the final section to `hoopflow.css`:

```css
/* Forms */
.hf-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.hf-field {
  display: grid;
  gap: 5px;
}

.hf-field--full {
  grid-column: 1 / -1;
}

.hf-label {
  color: var(--hf-ink);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.hf-help {
  color: var(--hf-muted);
  font-size: 12px;
}

.hf-input,
.hf-select,
.hf-textarea {
  width: 100%;
  min-height: 44px;
  padding: 10px 12px;
  border: 1px solid var(--hf-border-strong);
  border-radius: var(--hf-radius-sm);
  background: var(--hf-surface-2);
  color: var(--hf-ink);
}

.hf-textarea {
  min-height: 110px;
  resize: vertical;
}

.hf-input:focus,
.hf-select:focus,
.hf-textarea:focus {
  border-color: var(--hf-primary);
  box-shadow: var(--hf-focus);
  outline: none;
}

.hf-input[aria-invalid='true'],
.hf-select[aria-invalid='true'],
.hf-textarea[aria-invalid='true'] {
  border-color: var(--hf-alert);
}

.hf-field-error {
  color: var(--hf-alert);
  font-size: 12px;
  font-weight: 600;
}

.hf-check {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  min-height: 44px;
  padding: 6px 0;
  font-size: 13px;
}

.hf-check input {
  width: 18px;
  height: 18px;
  margin-top: 2px;
  accent-color: var(--hf-primary);
}

.hf-inline-form {
  display: flex;
  gap: 8px;
  align-items: end;
}

.hf-inline-form .hf-field {
  flex: 1;
}

@media (max-width: 575.98px) {
  .hf-form-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .hf-inline-form {
    align-items: stretch;
    flex-direction: column;
  }
}

/* Stepper */
.hf-stepper {
  display: flex;
  gap: 6px;
  margin-bottom: 18px;
  overflow-x: auto;
}

.hf-step {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 6px;
  min-width: 110px;
  padding: 8px 10px;
  border-bottom: 3px solid var(--hf-border);
  color: var(--hf-muted);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.hf-step span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border: 1px solid currentColor;
  border-radius: 50%;
  font-size: 10px;
}

.hf-step.is-active {
  border-bottom-color: var(--hf-primary);
  color: var(--hf-primary);
}

.hf-step.is-done {
  border-bottom-color: var(--hf-success);
  color: var(--hf-success);
}

/* Dialogs */
.hf-dialog {
  width: min(660px, calc(100vw - 24px));
  max-width: 660px;
  max-height: calc(100vh - 48px);
  padding: 0;
  overflow: hidden;
  border: 0;
  border-radius: 16px;
  background: var(--hf-surface-2);
  color: var(--hf-ink);
  box-shadow: 0 28px 90px rgba(0, 0, 0, 0.38);
}

.hf-dialog--wide {
  width: min(900px, calc(100vw - 24px));
  max-width: 900px;
}

.hf-dialog::backdrop {
  background: rgba(8, 13, 23, 0.62);
  backdrop-filter: blur(3px);
}

.hf-dialog__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 18px 20px 12px;
  border-bottom: 1px solid var(--hf-border);
}

.hf-dialog__title {
  margin: 0;
  font-family: var(--hf-font-display);
  font-size: 24px;
  font-weight: 800;
  line-height: 1;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.hf-dialog__body {
  max-height: min(68vh, 640px);
  overflow-y: auto;
  padding: 18px 20px;
}

.hf-dialog__foot {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
  padding: 12px 20px 18px;
  border-top: 1px solid var(--hf-border);
}

@media (max-width: 575.98px) {
  .hf-dialog__foot .hf-btn {
    flex: 1;
  }
}

/* Toasts */
.hf-toast-region {
  position: fixed;
  right: 18px;
  bottom: 18px;
  z-index: 120;
  display: grid;
  gap: 8px;
  width: min(380px, calc(100vw - 24px));
}

.hf-toast {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 10px;
  align-items: start;
  padding: 12px;
  border: 1px solid var(--hf-border);
  border-left: 4px solid var(--hf-primary);
  border-radius: 12px;
  background: var(--hf-surface-2);
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.22);
  animation: hf-toast-in 200ms ease-out;
}

.hf-toast--success {
  border-left-color: var(--hf-success);
}

.hf-toast--warning {
  border-left-color: var(--hf-warning);
}

.hf-toast--danger {
  border-left-color: var(--hf-alert);
}

.hf-toast strong {
  display: block;
  font-size: 13px;
}

.hf-toast p {
  margin: 3px 0 0;
  color: var(--hf-muted);
  font-size: 12px;
}

.hf-toast button {
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--hf-muted);
  cursor: pointer;
  font-size: 18px;
  line-height: 1;
}

@keyframes hf-toast-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 991.98px) {
  .hf-toast-region {
    right: 12px;
    bottom: 76px;
    left: 12px;
    width: auto;
  }
}

/* Empty states */
.hf-empty {
  display: grid;
  place-items: center;
  gap: 8px;
  min-height: 220px;
  padding: 28px 18px;
  border: 1px dashed var(--hf-border-strong);
  border-radius: var(--hf-radius);
  background: color-mix(in srgb, var(--hf-surface) 60%, transparent);
  text-align: center;
}

.hf-empty .bi {
  color: var(--hf-muted);
  font-size: 34px;
}

.hf-empty h3 {
  margin: 0;
  font-family: var(--hf-font-display);
  font-size: 24px;
  text-transform: uppercase;
}

.hf-empty p {
  max-width: 46ch;
  margin: 0;
  color: var(--hf-muted);
  font-size: 13px;
}

/* Timeline and audit */
.hf-timeline {
  display: grid;
  gap: 0;
  padding: 4px 0 0 18px;
  border-left: 2px solid var(--hf-border);
}

.hf-timeline-item {
  position: relative;
  padding: 0 0 18px 18px;
}

.hf-timeline-item::before {
  position: absolute;
  top: 4px;
  left: -25px;
  width: 12px;
  height: 12px;
  border: 2px solid var(--hf-surface);
  border-radius: 50%;
  background: var(--hf-primary);
  box-shadow: 0 0 0 2px var(--hf-primary);
  content: '';
}

.hf-timeline-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 12px;
  color: var(--hf-muted);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.hf-timeline-item h3 {
  margin: 4px 0;
  font-size: 14px;
}

.hf-timeline-item p {
  margin: 0;
  color: var(--hf-muted);
  font-size: 13px;
}

.hf-change {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 6px;
  padding: 4px 8px;
  border-radius: 6px;
  background: var(--hf-ground);
  color: var(--hf-muted);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 11px;
}

/* Auth */
.hf-auth {
  min-height: 100vh;
  display: grid;
  grid-template-rows: auto 1fr;
  background:
    radial-gradient(circle at 18% 20%, color-mix(in srgb, var(--hf-primary) 18%, transparent), transparent 42%),
    var(--hf-ground);
}

.hf-auth-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 20px;
}

.hf-auth-brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-family: var(--hf-font-display);
  font-size: 24px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.hf-auth-brand img {
  width: 38px;
  height: 38px;
  border-radius: 8px;
  background: #fff;
  padding: 3px;
}

.hf-auth-main {
  display: grid;
  place-items: center;
  padding: 18px;
}

.hf-auth-card {
  width: min(460px, 100%);
  padding: 28px;
  border: 1px solid var(--hf-border);
  border-top: 4px solid var(--hf-primary);
  border-radius: 16px;
  background: var(--hf-surface);
  box-shadow: 0 24px 70px rgba(11, 18, 32, 0.14);
}

.hf-auth-card--wide {
  width: min(720px, 100%);
}

.hf-auth-title {
  margin: 4px 0 4px;
  font-family: var(--hf-font-display);
  font-size: 42px;
  font-weight: 800;
  line-height: 0.95;
  text-transform: uppercase;
}

.hf-auth-sub {
  margin: 0 0 18px;
  color: var(--hf-muted);
  font-size: 14px;
}

.hf-quick-accounts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin: 14px 0;
}

.hf-quick-accounts button {
  display: grid;
  gap: 2px;
  min-height: 54px;
  padding: 8px 10px;
  border: 1px solid var(--hf-border);
  border-radius: 10px;
  background: var(--hf-surface-2);
  color: var(--hf-ink);
  cursor: pointer;
  text-align: left;
}

.hf-quick-accounts button:hover {
  border-color: var(--hf-primary);
}

.hf-quick-accounts strong {
  font-size: 12px;
  text-transform: capitalize;
}

.hf-quick-accounts span {
  color: var(--hf-muted);
  font-size: 10px;
}

/* Hero and stat blocks */
.hf-hero {
  position: relative;
  overflow: hidden;
  padding: 24px;
  border: 1px solid var(--hf-border);
  border-radius: var(--hf-radius);
  background: var(--hf-rail);
  color: var(--hf-rail-ink);
}

.hf-hero::after {
  position: absolute;
  right: -40px;
  bottom: -60px;
  width: 260px;
  height: 260px;
  border: 2px solid rgba(255, 255, 255, 0.12);
  border-radius: 50%;
  content: '';
}

.hf-hero h1 {
  position: relative;
  margin: 4px 0;
  font-family: var(--hf-font-display);
  font-size: clamp(34px, 4vw, 54px);
  font-weight: 800;
  line-height: 0.92;
  text-transform: uppercase;
}

.hf-hero p {
  position: relative;
  max-width: 62ch;
  margin: 8px 0 0;
  color: var(--hf-rail-muted);
}

.hf-stat-strip {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 24px;
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid rgba(255, 255, 255, 0.14);
}

.hf-stat-strip div {
  display: grid;
  gap: 1px;
}

.hf-stat-strip span {
  color: var(--hf-rail-muted);
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.hf-stat-strip strong {
  font-family: var(--hf-font-display);
  font-size: 22px;
  font-weight: 800;
  line-height: 1;
}

/* Utility */
.hf-muted {
  color: var(--hf-muted);
}

.hf-small {
  font-size: 12px;
}

.hf-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 12px;
}

.hf-nowrap {
  white-space: nowrap;
}

.hf-mt-0 { margin-top: 0; }
.hf-mt-1 { margin-top: 6px; }
.hf-mt-2 { margin-top: 12px; }
.hf-mt-3 { margin-top: 18px; }
.hf-mt-4 { margin-top: 24px; }
.hf-mb-0 { margin-bottom: 0; }
.hf-mb-1 { margin-bottom: 6px; }
.hf-mb-2 { margin-bottom: 12px; }
.hf-mb-3 { margin-bottom: 18px; }
.hf-mb-4 { margin-bottom: 24px; }
.hf-flex { display: flex; }
.hf-between { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.hf-items-center { align-items: center; }
.hf-gap-1 { gap: 6px; }
.hf-gap-2 { gap: 12px; }
.hf-gap-3 { gap: 18px; }
.hf-wrap { flex-wrap: wrap; }
.hf-full { width: 100%; }
.hf-hide-mobile { display: initial; }
.hf-only-mobile { display: none; }
.hf-text-right { text-align: right; }

@media (max-width: 767.98px) {
  .hf-hide-mobile {
    display: none !important;
  }

  .hf-only-mobile {
    display: initial;
  }
}

/* Motion */
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    scroll-behavior: auto !important;
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
```

- [ ] **Step 4: Verify the stylesheet has no syntax errors**

```powershell
Select-String -Path prototype/assets/css/hoopflow.css -Pattern 'var\([^)]*;' 
```

Expected: no matches. Open `http://127.0.0.1:8001/auth/splash.html` later and confirm the page renders with Barlow Condensed headings and no missing CSS.

- [ ] **Step 5: Commit**

```powershell
git add prototype/assets/css/hoopflow.css
git commit -m "feat(prototype): add courtside broadcast visual system"
```

## Task 7: UI service

**Files:**
- Create: `prototype/assets/js/ui.js`
- Create: `prototype/tests/ui.test.mjs`

**Interfaces:**
- Produces: `createUI({ store }) -> { toast, dialog, confirm, emptyState, statusChip, statusMeta, setFieldError, clearFieldError, setBusy, announce, focusMain, reducedMotion, debounce }` and pure exports `escapeHtml`, `initials`, `formatMoney`, `formatDate`, `formatTime`, `formatDateTime`, `formatDay`, `formatShortDate`, `statusMeta`.

- [ ] **Step 1: Write the failing formatter tests**

Create `prototype/tests/ui.test.mjs`:

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import {
  escapeHtml,
  initials,
  formatMoney,
  formatDate,
  formatTime,
  statusMeta,
} from '../assets/js/ui.js';

test('escapeHtml escapes markup', () => {
  assert.equal(escapeHtml('<img src=x onerror="alert(1)">'), '&lt;img src=x onerror=&quot;alert(1)&quot;&gt;');
  assert.equal(escapeHtml("O'Neil & Sons"), 'O&#039;Neil &amp; Sons');
});

test('initials takes the first two words', () => {
  assert.equal(initials('San Antonio Vipers'), 'SA');
  assert.equal(initials('Jeward'), 'J');
  assert.equal(initials(''), '?');
});

test('money and dates use Philippine formatting', () => {
  assert.equal(formatMoney(1500), '₱1,500');
  assert.equal(formatDate('2026-05-18T13:00:00+08:00'), 'May 18, 2026');
  assert.equal(formatTime('2026-05-18T13:00:00+08:00'), '1:00 PM');
});

test('statusMeta maps every store status to a label and tone', () => {
  assert.deepEqual(statusMeta('forfeit'), { tone: 'danger', label: 'Forfeit', icon: 'shield-x' });
  assert.deepEqual(statusMeta('registration-open'), {
    tone: 'success',
    label: 'Registration open',
    icon: 'unlock',
  });
  assert.deepEqual(statusMeta('unknown'), { tone: 'slate', label: 'Unknown', icon: 'circle' });
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```powershell
cd prototype
node --test tests/ui.test.mjs
```

Expected: FAIL because `../assets/js/ui.js` does not exist.

- [ ] **Step 3: Implement the pure helpers and UI service**

Create `prototype/assets/js/ui.js`:

```js
const STATUS_MAP = {
  approved: { tone: 'success', label: 'Approved', icon: 'check-circle-fill' },
  pending: { tone: 'warning', label: 'Pending', icon: 'hourglass-split' },
  rejected: { tone: 'danger', label: 'Rejected', icon: 'x-circle-fill' },
  paid: { tone: 'success', label: 'Paid', icon: 'cash-coin' },
  partial: { tone: 'warning', label: 'Partial', icon: 'circle-half' },
  final: { tone: 'success', label: 'Final', icon: 'flag-fill' },
  forfeit: { tone: 'danger', label: 'Forfeit', icon: 'shield-x' },
  scheduled: { tone: 'slate', label: 'Scheduled', icon: 'clock' },
  draft: { tone: 'slate', label: 'Draft', icon: 'pencil' },
  'registration-open': { tone: 'success', label: 'Registration open', icon: 'unlock' },
  active: { tone: 'primary', label: 'Active', icon: 'broadcast' },
  upcoming: { tone: 'slate', label: 'Upcoming', icon: 'calendar-event' },
  verified: { tone: 'success', label: 'Verified', icon: 'patch-check-fill' },
  missing: { tone: 'danger', label: 'Missing', icon: 'file-earmark-x' },
};

const MONEY = new Intl.NumberFormat('en-PH', {
  style: 'currency',
  currency: 'PHP',
  maximumFractionDigits: 0,
});

export function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

export function initials(name) {
  const parts = String(name || '?')
    .trim()
    .split(/\s+/)
    .filter(Boolean);
  if (!parts.length) return '?';
  return parts
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase();
}

export function formatMoney(value) {
  return MONEY.format(Number(value) || 0);
}

export function formatDate(value, options = {}) {
  if (!value) return 'TBD';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'TBD';
  return new Intl.DateTimeFormat('en-PH', {
    timeZone: 'Asia/Manila',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    ...options,
  }).format(date);
}

export function formatShortDate(value) {
  return formatDate(value, { year: undefined });
}

export function formatTime(value) {
  if (!value) return 'TBD';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'TBD';
  return new Intl.DateTimeFormat('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
  }).format(date);
}

export function formatDateTime(value) {
  if (!value) return 'TBD';
  return `${formatDate(value)} · ${formatTime(value)}`;
}

export function formatDay(value) {
  if (!value) return 'TBD';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'TBD';
  return new Intl.DateTimeFormat('en-PH', {
    timeZone: 'Asia/Manila',
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  }).format(date);
}

export function statusMeta(status) {
  return STATUS_MAP[status] || {
    tone: 'slate',
    label: String(status || 'Unknown').replaceAll('-', ' '),
    icon: 'circle',
  };
}
```

Append the DOM service to `ui.js`:

```js
function ensureToastRegion() {
  let region = document.querySelector('.hf-toast-region');
  if (!region) {
    region = document.createElement('div');
    region.className = 'hf-toast-region';
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'false');
    document.body.appendChild(region);
  }
  return region;
}

function ensureLiveRegion() {
  let region = document.getElementById('hfLiveRegion');
  if (!region) {
    region = document.createElement('div');
    region.id = 'hfLiveRegion';
    region.className = 'hf-sr-only';
    region.setAttribute('aria-live', 'polite');
    document.body.appendChild(region);
  }
  return region;
}

export function createUI() {
  function toast(message, { title = '', tone = 'primary', duration = 4200 } = {}) {
    const region = ensureToastRegion();
    const element = document.createElement('div');
    const icon =
      tone === 'danger'
        ? 'x-circle-fill'
        : tone === 'warning'
          ? 'exclamation-triangle-fill'
          : tone === 'success'
            ? 'check-circle-fill'
            : 'info-circle-fill';
    element.className = `hf-toast hf-toast--${tone}`;
    element.setAttribute('role', tone === 'danger' ? 'alert' : 'status');
    element.innerHTML = `
      <i class="bi bi-${icon}" aria-hidden="true"></i>
      <div>
        ${title ? `<strong>${escapeHtml(title)}</strong>` : ''}
        <p>${escapeHtml(message)}</p>
      </div>
      <button type="button" aria-label="Dismiss notification" data-testid="toast-dismiss">&times;</button>
    `;
    region.appendChild(element);
    const remove = () => element.remove();
    element.querySelector('button').addEventListener('click', remove);
    if (duration > 0) window.setTimeout(remove, duration);
    return remove;
  }

  function dialog({ title, body, footer = '', size = 'normal', onMount, onClose } = {}) {
    const trigger = document.activeElement;
    const element = document.createElement('dialog');
    element.className = `hf-dialog${size === 'wide' ? ' hf-dialog--wide' : ''}`;
    element.innerHTML = `
      <div class="hf-dialog__head">
        <h2 class="hf-dialog__title">${escapeHtml(title)}</h2>
        <button type="button" class="hf-icon-btn" data-dialog-close aria-label="Close dialog" data-testid="dialog-close">
          <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
      </div>
      <div class="hf-dialog__body">${body}</div>
      ${footer ? `<div class="hf-dialog__foot">${footer}</div>` : ''}
    `;
    document.body.appendChild(element);
    const close = () => {
      if (element.open) element.close();
    };
    element.querySelector('[data-dialog-close]')?.addEventListener('click', close);
    element.addEventListener('click', (event) => {
      if (event.target === element) close();
    });
    element.addEventListener(
      'close',
      () => {
        element.remove();
        if (trigger instanceof HTMLElement) trigger.focus();
        onClose?.();
      },
      { once: true }
    );
    element.showModal();
    onMount?.(element, close);
    return { element, close };
  }

  function confirm({
    title = 'Confirm action',
    message = '',
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    tone = 'primary',
  } = {}) {
    return new Promise((resolve) => {
      let settled = false;
      const finish = (value) => {
        if (settled) return;
        settled = true;
        resolve(value);
      };
      const { element, close } = dialog({
        title,
        body: `<p class="hf-mb-0">${escapeHtml(message)}</p>`,
        footer: `
          <button type="button" class="hf-btn" data-confirm-cancel data-testid="confirm-cancel">${escapeHtml(cancelLabel)}</button>
          <button type="button" class="hf-btn hf-btn--${tone}" data-confirm-ok data-testid="confirm-ok">${escapeHtml(confirmLabel)}</button>
        `,
        onClose: () => finish(false),
      });
      element.querySelector('[data-confirm-cancel]').addEventListener('click', () => {
        finish(false);
        close();
      });
      element.querySelector('[data-confirm-ok]').addEventListener('click', () => {
        finish(true);
        close();
      });
    });
  }

  function statusChip(status, label = null) {
    const meta = statusMeta(status);
    return `<span class="hf-chip hf-chip--${meta.tone}"><i class="bi bi-${meta.icon}" aria-hidden="true"></i>${escapeHtml(label || meta.label)}</span>`;
  }

  function emptyState({ icon = 'inbox', title = 'Nothing here yet', message = '', action = '' } = {}) {
    return `
      <div class="hf-empty">
        <i class="bi bi-${icon}" aria-hidden="true"></i>
        <h3>${escapeHtml(title)}</h3>
        ${message ? `<p>${escapeHtml(message)}</p>` : ''}
        ${action}
      </div>
    `;
  }

  function setFieldError(input, message) {
    if (!input) return;
    input.setAttribute('aria-invalid', 'true');
    const errorId = `${input.id || input.name || 'field'}-error`;
    let error = document.getElementById(errorId);
    if (!error) {
      error = document.createElement('div');
      error.id = errorId;
      error.className = 'hf-field-error';
      input.insertAdjacentElement('afterend', error);
    }
    error.textContent = message;
    input.setAttribute('aria-describedby', errorId);
  }

  function clearFieldError(input) {
    if (!input) return;
    input.removeAttribute('aria-invalid');
    input.removeAttribute('aria-describedby');
    document.getElementById(`${input.id || input.name || 'field'}-error`)?.remove();
  }

  function setBusy(button, busy, label = 'Working...') {
    if (!button) return;
    if (busy) {
      button.dataset.originalHtml = button.innerHTML;
      button.disabled = true;
      button.innerHTML = `<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> ${escapeHtml(label)}`;
    } else {
      button.disabled = false;
      if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
    }
  }

  function announce(message) {
    const region = ensureLiveRegion();
    region.textContent = '';
    window.setTimeout(() => {
      region.textContent = message;
    }, 20);
  }

  function focusMain() {
    const main = document.getElementById('main');
    if (main) main.focus({ preventScroll: true });
  }

  function reducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function debounce(fn, wait = 180) {
    let timer = 0;
    return (...args) => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => fn(...args), wait);
    };
  }

  return {
    toast,
    dialog,
    confirm,
    statusChip,
    emptyState,
    setFieldError,
    clearFieldError,
    setBusy,
    announce,
    focusMain,
    reducedMotion,
    debounce,
  };
}
```

Add this accessibility rule to the end of `hoopflow.css`:

```css
.hf-sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
```

- [ ] **Step 4: Run the UI tests**

```powershell
cd prototype
node --test tests/ui.test.mjs
```

Expected: all 4 tests PASS.

- [ ] **Step 5: Commit**

```powershell
git add prototype/assets/js/ui.js prototype/assets/css/hoopflow.css prototype/tests/ui.test.mjs
git commit -m "feat(prototype): add shared UI service and formatters"
```

## Task 8: Shared components

**Files:**
- Create: `prototype/assets/js/components.js`

**Interfaces:**
- Consumes: `createUI`, `escapeHtml`, `initials`, `formatDate`, `formatTime`, `statusMeta` from Task 7; `store.select.*` from Tasks 3–5.
- Produces: `createComponents({ store, ui }) -> { shield, matchCard, bracketMatch, kpiTile, pageHeader, sectionHeader, announcementCard, standingsTable, scoreboard, statusChip, emptyState }`.

- [ ] **Step 1: Implement `components.js`**

Create `prototype/assets/js/components.js`:

```js
import {
  escapeHtml,
  initials,
  formatDate,
  formatTime,
  statusMeta,
} from './ui.js';

export function createComponents({ store, ui }) {
  function shield(team, size = '') {
    const label = team?.shortName || initials(team?.name || 'TBD');
    const color = team?.avatarColor || 'var(--hf-slate)';
    return `<span class="hf-shield ${size ? `hf-shield--${size}` : ''}" style="--team-color: ${escapeHtml(color)}" aria-hidden="true">${escapeHtml(label)}</span>`;
  }

  function teamName(team) {
    return `<span class="hf-match-name">${escapeHtml(team?.name || 'TBD')}</span>`;
  }

  function matchCard(match, { compact = false, href = null, testid = null } = {}) {
    const team1 = match.team1Id ? store.select.team(match.team1Id) : null;
    const team2 = match.team2Id ? store.select.team(match.team2Id) : null;
    const tournament = store.select.tournament(match.tournamentId);
    const court = tournament?.courts.find((item) => item.id === match.courtId);
    const winner1 = Boolean(match.winnerId && match.winnerId === match.team1Id);
    const winner2 = Boolean(match.winnerId && match.winnerId === match.team2Id);
    const score1 = match.isBye ? 'BYE' : match.score1 ?? '–';
    const score2 = match.isBye ? '–' : match.score2 ?? '–';
    const when = match.scheduledAt
      ? `${formatDate(match.scheduledAt)} · ${formatTime(match.scheduledAt)}`
      : 'Time TBD';
    const body = `
      <div class="hf-match-head">
        <span>${escapeHtml(match.roundLabel)}${court ? ` · ${escapeHtml(court.name)}` : ''}</span>
        ${ui.statusChip(match.status)}
      </div>
      <div class="hf-match-team ${winner1 ? 'is-winner' : match.status !== 'scheduled' ? 'is-loser' : ''}">
        ${shield(team1)}
        ${teamName(team1)}
        <span class="hf-score ${match.score1 == null && !match.isBye ? 'hf-score--muted' : ''}">${escapeHtml(String(score1))}</span>
      </div>
      <div class="hf-match-team ${winner2 ? 'is-winner' : match.status !== 'scheduled' ? 'is-loser' : ''}">
        ${shield(team2)}
        ${teamName(team2)}
        <span class="hf-score ${match.score2 == null ? 'hf-score--muted' : ''}">${escapeHtml(String(score2))}</span>
      </div>
      ${compact ? '' : `<div class="hf-match-head"><span>${escapeHtml(when)}</span><span>${match.isBye ? 'Bye' : escapeHtml(match.roundLabel)}</span></div>`}
    `;
    const tag = href ? 'a' : 'article';
    const hrefAttr = href ? ` href="${escapeHtml(href)}"` : '';
    return `<${tag} class="hf-match-card hf-match-card--${escapeHtml(match.status)}" data-match-id="${escapeHtml(match.id)}" data-testid="${escapeHtml(testid || `match-card-${match.id}`)}"${hrefAttr}>${body}</${tag}>`;
  }

  function bracketMatch(match) {
    const team1 = match.team1Id ? store.select.team(match.team1Id) : null;
    const team2 = match.team2Id ? store.select.team(match.team2Id) : null;
    const winner1 = Boolean(match.winnerId && match.winnerId === match.team1Id);
    const winner2 = Boolean(match.winnerId && match.winnerId === match.team2Id);
    const label = `${match.roundLabel} slot ${match.bracketSlot}`;
    return `
      <div class="hf-bracket-match hf-bracket-match--${escapeHtml(match.status)}"
           data-match-id="${escapeHtml(match.id)}"
           data-testid="bracket-match-${escapeHtml(match.id)}"
           role="button" tabindex="0" aria-label="Open ${escapeHtml(label)}">
        <div class="hf-bracket-match__slot">${escapeHtml(match.roundLabel)} · Slot ${match.bracketSlot}</div>
        <div class="hf-match-team ${winner1 ? 'is-winner' : match.status !== 'scheduled' ? 'is-loser' : ''}">
          ${shield(team1)}${teamName(team1)}
          <span class="hf-score ${match.score1 == null && !match.isBye ? 'hf-score--muted' : ''}">${escapeHtml(match.isBye ? 'BYE' : String(match.score1 ?? '–'))}</span>
        </div>
        <div class="hf-match-team ${winner2 ? 'is-winner' : match.status !== 'scheduled' ? 'is-loser' : ''}">
          ${shield(team2)}${teamName(team2)}
          <span class="hf-score ${match.score2 == null ? 'hf-score--muted' : ''}">${escapeHtml(String(match.score2 ?? '–'))}</span>
        </div>
      </div>
    `;
  }

  function kpiTile({ label, value, hint = '', href = null, tone = '', testid = null }) {
    const tag = href ? 'a' : 'div';
    const hrefAttr = href ? ` href="${escapeHtml(href)}"` : '';
    const slug = label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    return `
      <${tag} class="hf-kpi ${tone ? `hf-kpi--${tone}` : ''}"
             data-mark="${escapeHtml(String(value))}"
             data-testid="${escapeHtml(testid || `kpi-${slug}`)}"${hrefAttr}>
        <span class="hf-kpi-label">${escapeHtml(label)}</span>
        <span class="hf-kpi-value">${escapeHtml(String(value))}</span>
        ${hint ? `<span class="hf-kpi-hint">${escapeHtml(hint)}</span>` : ''}
      </${tag}>
    `;
  }

  function pageHeader({ eyebrow, title, description = '', actions = '', meta = '' }) {
    return `
      <header class="hf-page-head">
        <div>
          <span class="hf-eyebrow">${escapeHtml(eyebrow)}</span>
          <h1 class="hf-page-title">${escapeHtml(title)}</h1>
          ${description ? `<p class="hf-page-desc">${escapeHtml(description)}</p>` : ''}
          ${meta ? `<div class="hf-page-meta">${meta}</div>` : ''}
        </div>
        ${actions ? `<div class="hf-page-actions">${actions}</div>` : ''}
      </header>
    `;
  }

  function sectionHeader({ title, action = '' }) {
    return `
      <div class="hf-section-head">
        <h2 class="hf-section-title">${escapeHtml(title)}</h2>
        ${action}
      </div>
    `;
  }

  function announcementCard(item, { compact = false } = {}) {
    return `
      <article class="hf-card hf-card--flat" data-testid="announcement-${escapeHtml(item.id)}">
        <div class="hf-between">
          <span class="hf-chip hf-chip--slate">${escapeHtml(item.category)}</span>
          ${item.pinned ? '<span class="hf-chip hf-chip--gold">Pinned</span>' : ''}
        </div>
        <h3 class="hf-section-title hf-mt-1">${escapeHtml(item.title)}</h3>
        <p class="hf-muted hf-small hf-mb-0">${escapeHtml(item.body)}</p>
        <div class="hf-match-head hf-mt-2">
          <span>${escapeHtml(item.author)}</span>
          <span>${escapeHtml(formatDate(item.createdAt))}</span>
        </div>
        ${compact ? '' : `<div class="hf-match-head"><span>Audience: ${escapeHtml(item.audience)}</span><span>${escapeHtml(item.tournamentId)}</span></div>`}
      </article>
    `;
  }

  function standingsTable(tournamentId, { limit = null } = {}) {
    const rows = store.select.standings(tournamentId);
    if (!rows.length) {
      return ui.emptyState({
        icon: 'bar-chart',
        title: 'No standings yet',
        message: 'Standings appear as soon as approved teams have recorded results.',
      });
    }
    const visible = limit ? rows.slice(0, limit) : rows;
    const lastQualified = visible.filter((row) => row.qualified).length;
    const table = `
      <div class="hf-table-wrap">
        <table class="hf-table hf-table--standings">
          <thead>
            <tr>
              <th scope="col">#</th>
              <th scope="col">Team</th>
              <th scope="col" class="is-num">W</th>
              <th scope="col" class="is-num">L</th>
              <th scope="col" class="is-num">PF</th>
              <th scope="col" class="is-num">PA</th>
              <th scope="col" class="is-num">+/-</th>
              <th scope="col" class="is-num">Seed</th>
            </tr>
          </thead>
          <tbody>
            ${visible
              .map(
                (row, index) => `
                  <tr class="${row.qualified ? 'is-qualified' : ''}" data-testid="standings-row-${escapeHtml(row.teamId)}">
                    <td class="is-num">${row.rank}</td>
                    <td>
                      <a class="hf-flex hf-items-center hf-gap-1" href="../teams/show.html?id=${encodeURIComponent(row.teamId)}">
                        ${shield(row.team)}
                        <strong>${escapeHtml(row.team.name)}</strong>
                      </a>
                    </td>
                    <td class="is-num">${row.wins}</td>
                    <td class="is-num">${row.losses}</td>
                    <td class="is-num">${row.pointsFor}</td>
                    <td class="is-num">${row.pointsAgainst}</td>
                    <td class="is-num">${row.diff > 0 ? '+' : ''}${row.diff}</td>
                    <td class="is-num">${row.seed ?? '–'}</td>
                  </tr>
                  ${lastQualified === index + 1 && !limit ? '<tr class="hf-qual-line"><td colspan="8"></td></tr>' : ''}
                `
              )
              .join('')}
          </tbody>
        </table>
      </div>
    `;
    const cards = `
      <div class="hf-standings-cards">
        ${visible
          .map(
            (row) => `
              <a class="hf-card hf-card--flat" href="../teams/show.html?id=${encodeURIComponent(row.teamId)}" data-testid="standings-card-${escapeHtml(row.teamId)}">
                <div class="hf-between">
                  <span class="hf-flex hf-items-center hf-gap-1">
                    <span class="hf-kpi-label">#${row.rank}</span>
                    ${shield(row.team)}
                    <strong>${escapeHtml(row.team.name)}</strong>
                  </span>
                  ${row.qualified ? '<span class="hf-chip hf-chip--gold">Playoff cut</span>' : ''}
                </div>
                <div class="hf-flex hf-gap-3 hf-mt-1 hf-small">
                  <span><strong>${row.wins}</strong> W</span>
                  <span><strong>${row.losses}</strong> L</span>
                  <span><strong>${row.diff > 0 ? '+' : ''}${row.diff}</strong> DIFF</span>
                </div>
              </a>
            `
          )
          .join('')}
      </div>
    `;
    return table + cards;
  }

  function scoreboard(match) {
    const team1 = match.team1Id ? store.select.team(match.team1Id) : null;
    const team2 = match.team2Id ? store.select.team(match.team2Id) : null;
    const tournament = store.select.tournament(match.tournamentId);
    const court = tournament?.courts.find((item) => item.id === match.courtId);
    const row = (team, score, winner) => `
      <div class="hf-flex hf-items-center hf-between hf-gap-2 ${winner ? 'is-winner' : ''}">
        <span class="hf-flex hf-items-center hf-gap-2">
          ${shield(team, 'lg')}
          <strong class="hf-page-title hf-mb-0" style="font-size: clamp(20px, 3vw, 34px)">${escapeHtml(team?.name || 'TBD')}</strong>
        </span>
        <span class="hf-score" style="font-size: clamp(34px, 6vw, 64px)">${escapeHtml(String(score ?? '–'))}</span>
      </div>
    `;
    return `
      <section class="hf-hero" data-testid="scoreboard-${escapeHtml(match.id)}">
        <span class="hf-eyebrow" style="color: var(--hf-gold)">${escapeHtml(match.roundLabel)} · ${escapeHtml(match.status)}</span>
        <div class="hf-mt-2">
          ${row(team1, match.score1, match.winnerId === match.team1Id)}
          <div class="hf-stat-strip"><div><span>Court</span><strong>${escapeHtml(court?.name || 'TBD')}</strong></div><div><span>Tip-off</span><strong>${match.scheduledAt ? escapeHtml(formatTime(match.scheduledAt)) : 'TBD'}</strong></div><div><span>Date</span><strong>${match.scheduledAt ? escapeHtml(formatDate(match.scheduledAt)) : 'TBD'}</strong></div></div>
          ${row(team2, match.score2, match.winnerId === match.team2Id)}
        </div>
      </section>
    `;
  }

  return {
    shield,
    matchCard,
    bracketMatch,
    kpiTile,
    pageHeader,
    sectionHeader,
    announcementCard,
    standingsTable,
    scoreboard,
    statusChip: ui.statusChip,
    emptyState: ui.emptyState,
  };
}
```

- [ ] **Step 2: Check the module parses**

```powershell
cd prototype
node --check assets/js/components.js
```

Expected: no output and exit code 0.

- [ ] **Step 3: Commit**

```powershell
git add prototype/assets/js/components.js
git commit -m "feat(prototype): add shared match, KPI, and standings components"
```

## Task 9: Routes, shell, app bootstrap, and auth

**Files:**
- Create: `prototype/assets/js/routes.js`
- Create: `prototype/assets/js/shell.js`
- Rewrite: `prototype/assets/js/app.js`
- Rewrite: `prototype/index.html`
- Rewrite: `prototype/auth/splash.html`
- Rewrite: `prototype/auth/login.html`
- Rewrite: `prototype/auth/register.html`
- Create: `prototype/assets/js/pages/splash.js`
- Create: `prototype/assets/js/pages/login.js`
- Create: `prototype/assets/js/pages/register.js`

**Interfaces:**
- Consumes: `createStore`, `createUI`, `createComponents`, `renderShell`, `route`.
- Produces: `route(name, params) -> '../path?query'`; `renderShell({ store, ui, root, theme })`; `app.js` loads `./pages/${document.body.dataset.page}.js` and calls `render({ store, ui, components, root })`.

- [ ] **Step 1: Create `routes.js`**

Create `prototype/assets/js/routes.js`:

```js
export const ROUTES = {
  dashboard: 'dashboard/index.html',
  tournaments: 'tournaments/index.html',
  tournamentShow: 'tournaments/show.html',
  tournamentCreate: 'tournaments/create.html',
  submissions: 'tournaments/submissions.html',
  payments: 'tournaments/payments.html',
  teams: 'teams/index.html',
  teamShow: 'teams/show.html',
  teamRegister: 'teams/register.html',
  myTeam: 'teams/my-team.html',
  bracket: 'brackets/view.html',
  schedule: 'schedule/index.html',
  matches: 'schedule/matches.html',
  standings: 'standings/table.html',
  gameStats: 'games/stats.html',
  announcements: 'announcements/index.html',
  announcementCreate: 'announcements/create.html',
  profile: 'profile/index.html',
  audit: 'profile/audit.html',
  login: 'auth/login.html',
  register: 'auth/register.html',
  splash: 'auth/splash.html',
};

export function route(name, params = {}) {
  const path = ROUTES[name];
  if (!path) throw new Error(`Unknown route: ${name}`);
  const query = new URLSearchParams(
    Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== '')
  );
  return `../${path}${query.toString() ? `?${query.toString()}` : ''}`;
}
```

- [ ] **Step 2: Create `shell.js`**

Create `prototype/assets/js/shell.js`:

```js
import { route } from './routes.js';
import { escapeHtml } from './ui.js';

const NAV = {
  dashboard: { label: 'Dashboard', icon: 'speedometer2', route: 'dashboard' },
  tournaments: { label: 'Tournaments', icon: 'trophy', route: 'tournaments' },
  teams: { label: 'Teams', icon: 'people', route: 'teams' },
  bracket: { label: 'Bracket', icon: 'diagram-3', route: 'bracket' },
  schedule: { label: 'Schedule', icon: 'calendar3', route: 'schedule' },
  standings: { label: 'Standings', icon: 'bar-chart', route: 'standings' },
  announcements: { label: 'Announcements', icon: 'megaphone', route: 'announcements' },
  submissions: { label: 'Submissions', icon: 'clipboard-check', route: 'submissions' },
  payments: { label: 'Payments', icon: 'credit-card', route: 'payments' },
  audit: { label: 'Audit log', icon: 'clock-history', route: 'audit' },
  myTeam: { label: 'My team', icon: 'person-lines-fill', route: 'myTeam' },
  teamRegister: { label: 'Register team', icon: 'plus-circle', route: 'teamRegister' },
  gameStats: { label: 'Game stats', icon: 'graph-up', route: 'gameStats' },
  createTournament: { label: 'Create tournament', icon: 'plus-lg', route: 'tournamentCreate' },
  profile: { label: 'Profile', icon: 'person', route: 'profile' },
};

const PAGE_LABELS = {
  dashboard: 'Matchday desk',
  tournaments: 'Tournaments',
  'tournament-show': 'Tournament',
  'tournament-create': 'Create tournament',
  submissions: 'Submissions',
  payments: 'Payments',
  teams: 'Teams',
  'team-show': 'Team',
  'team-register': 'Register team',
  'my-team': 'My team',
  bracket: 'Bracket',
  schedule: 'Schedule',
  matches: 'Matchups',
  standings: 'Standings',
  'game-stats': 'Game stats',
  announcements: 'Announcements',
  'announcement-create': 'New announcement',
  profile: 'Profile',
  audit: 'Audit log',
};

function activeFor(page, key) {
  if (key === 'tournaments') return ['tournaments', 'tournament-show', 'tournament-create'].includes(page);
  if (key === 'teams') return ['teams', 'team-show', 'team-register', 'my-team'].includes(page);
  if (key === 'schedule') return ['schedule', 'matches', 'game-stats'].includes(page);
  if (key === 'announcements') return ['announcements', 'announcement-create'].includes(page);
  if (key === 'profile') return ['profile', 'audit'].includes(page);
  return page === key;
}

function roleTools(role) {
  if (role === 'organizer') return ['submissions', 'payments', 'audit', 'createTournament'];
  if (role === 'coach') return ['myTeam', 'teamRegister'];
  if (role === 'scorekeeper') return ['gameStats'];
  return [];
}

function roleTabs(role) {
  const tabs = [
    { key: 'dashboard', label: 'Home', icon: 'house-door', route: 'dashboard' },
    { key: 'bracket', label: 'Bracket', icon: 'diagram-3', route: 'bracket' },
    { key: 'schedule', label: 'Schedule', icon: 'calendar3', route: 'schedule' },
    { key: 'standings', label: 'Standings', icon: 'bar-chart', route: 'standings' },
  ];
  tabs.push(
    role === 'coach'
      ? { key: 'myTeam', label: 'My team', icon: 'people', route: 'myTeam' }
      : { key: 'profile', label: 'Profile', icon: 'person', route: 'profile' }
  );
  return tabs;
}

export function renderShell({ store, ui, root, theme }) {
  const page = document.body.dataset.page || 'dashboard';
  const shell = document.body.dataset.shell || 'app';

  if (shell === 'auth') {
    root.innerHTML = `
      <header class="hf-auth-topbar">
        <a class="hf-auth-brand" href="${route('splash')}">
          <img src="../assets/img/logo.png" alt="">
          HoopFlow
        </a>
        <a class="hf-btn hf-btn--ghost hf-btn--sm" href="${route('login')}">Sign in</a>
      </header>
    `;
    return;
  }

  const role = store.getRole();
  const user = store.getCurrentUser();
  const activeTournament = store.select.tournament('tournament-1');
  const pending = store.getState().teams.filter((team) => team.status === 'pending').length;
  const unpaid = store
    .getState()
    .teams.filter((team) => team.status !== 'rejected' && team.payment.status !== 'paid').length;
  const unread = store.select.notifications().length;

  const railLink = (key, count = 0) => {
    const item = NAV[key];
    const active = activeFor(page, key) ? ' is-active' : '';
    const badge = item.route === 'submissions' || item.route === 'payments'
      ? `<span class="hf-rail-badge" data-shell-badge="${item.route}" ${count ? '' : 'hidden'}>${count}</span>`
      : '';
    return `
      <a class="hf-rail-link${active}" href="${route(item.route)}" data-testid="nav-${key}">
        <i class="bi bi-${item.icon}" aria-hidden="true"></i>
        <span>${escapeHtml(item.label)}</span>
        ${badge}
      </a>
    `;
  };

  const demoMenu = (up = false, scope = 'rail') => `
    <div class="hf-demo-menu" data-demo-menu="${scope}">
      <details>
        <summary aria-label="Demo controls"><i class="bi bi-sliders2" aria-hidden="true"></i><span>Demo</span></summary>
        <div class="hf-demo-panel ${up ? 'hf-demo-panel--up' : ''}">
          <p class="hf-demo-title">Demo role</p>
          <div class="hf-demo-roles">
            ${['organizer', 'scorekeeper', 'coach', 'fan']
              .map(
                (item) => `
                  <button type="button" class="${role === item ? 'is-active' : ''}"
                          data-demo-role="${item}" data-testid="demo-role-${item}-${scope}">
                    ${item}
                  </button>
                `
              )
              .join('')}
          </div>
          <div class="hf-demo-row">
            <span>Theme</span>
            <span class="hf-btn-row">
              <button type="button" class="hf-btn hf-btn--sm" data-demo-theme="light"
                      aria-pressed="${theme.get() === 'light'}" data-testid="demo-theme-light-${scope}">Light</button>
              <button type="button" class="hf-btn hf-btn--sm" data-demo-theme="dark"
                      aria-pressed="${theme.get() === 'dark'}" data-testid="demo-theme-dark-${scope}">Dark</button>
            </span>
          </div>
          <div class="hf-demo-row">
            <span>Seed</span>
            <span class="hf-demo-seed">${escapeHtml(store.getState().seedVersion)}</span>
          </div>
          <div class="hf-demo-row">
            <span>Reset demo</span>
            <button type="button" class="hf-btn hf-btn--sm hf-btn--danger"
                    data-demo-reset data-testid="demo-reset-${scope}">Reset</button>
          </div>
        </div>
      </details>
    </div>
  `;

  root.innerHTML = `
    <aside class="hf-rail" aria-label="Primary navigation">
      <a class="hf-rail-brand" href="${route('dashboard')}">
        <img src="../assets/img/logo.png" alt="">
        <span>
          <strong>HoopFlow</strong>
          <small>Tournament ops</small>
        </span>
      </a>
      <span class="hf-rail-label">Matchday</span>
      ${['dashboard', 'tournaments', 'teams', 'bracket', 'schedule', 'standings', 'announcements']
        .map((key) => railLink(key))
        .join('')}
      ${roleTools(role).length ? '<span class="hf-rail-label">Workspace</span>' : ''}
      ${roleTools(role)
        .map((key) =>
          railLink(key, key === 'submissions' ? pending : key === 'payments' ? unpaid : 0)
        )
        .join('')}
      <div class="hf-rail-footer">
        ${demoMenu(true, 'rail')}
        <div class="hf-demo-row" style="border: 0; color: var(--hf-rail-muted)">
          <span>${escapeHtml(user.name)}</span>
          <span style="text-transform: capitalize">${escapeHtml(role)}</span>
        </div>
      </div>
    </aside>

    <header class="hf-topbar">
      <div class="hf-topbar-context">
        <h1 class="hf-topbar-title">${escapeHtml(PAGE_LABELS[page] || 'HoopFlow')}</h1>
        <div class="hf-topbar-sub">${escapeHtml(activeTournament?.name || 'No active tournament')} · ${escapeHtml(role)}</div>
      </div>
      <div class="hf-topbar-actions">
        <button type="button" class="hf-icon-btn" data-theme-toggle
                aria-label="Toggle color theme" data-testid="theme-toggle">
          <i class="bi bi-${theme.get() === 'dark' ? 'sun' : 'moon-stars'}" aria-hidden="true"></i>
        </button>
        <a class="hf-icon-btn" href="${route('announcements')}" aria-label="Announcements"
           data-testid="topbar-announcements">
          <i class="bi bi-bell" aria-hidden="true"></i>
          <span class="hf-notification-dot" data-shell-notification ${unread ? '' : 'hidden'}></span>
        </a>
        ${demoMenu(false, 'topbar')}
        <a class="hf-user-chip" href="${route('profile')}" data-testid="topbar-profile">
          <span class="hf-avatar">${escapeHtml(user.name.split(/\s+/).map((part) => part[0]).slice(0, 2).join(''))}</span>
          <span class="hf-user-meta">
            <strong>${escapeHtml(user.name)}</strong>
            <span>${escapeHtml(role)}</span>
          </span>
        </a>
      </div>
    </header>

    <nav class="hf-bottom-nav" aria-label="Mobile navigation">
      ${roleTabs(role)
        .map(
          (tab) => `
            <a class="hf-nav-tab ${activeFor(page, tab.key) ? 'is-active' : ''}"
               href="${route(tab.route)}" data-testid="mobile-nav-${tab.key}">
              <i class="bi bi-${tab.icon}" aria-hidden="true"></i>
              <span>${escapeHtml(tab.label)}</span>
            </a>
          `
        )
        .join('')}
    </nav>
  `;

  const syncTheme = () => {
    document.querySelectorAll('[data-theme-toggle] i').forEach((icon) => {
      icon.className = `bi bi-${theme.get() === 'dark' ? 'sun' : 'moon-stars'}`;
    });
    document.querySelectorAll('[data-demo-theme]').forEach((button) => {
      button.setAttribute('aria-pressed', String(button.dataset.demoTheme === theme.get()));
    });
  };

  root.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      theme.set(theme.get() === 'dark' ? 'light' : 'dark');
      syncTheme();
    });
  });

  root.querySelectorAll('[data-demo-theme]').forEach((button) => {
    button.addEventListener('click', () => {
      theme.set(button.dataset.demoTheme);
      syncTheme();
    });
  });

  root.querySelectorAll('[data-demo-role]').forEach((button) => {
    button.addEventListener('click', () => {
      document.dispatchEvent(
        new CustomEvent('hf:role-change', { detail: { role: button.dataset.demoRole } })
      );
    });
  });

  root.querySelectorAll('[data-demo-reset]').forEach((button) => {
    button.addEventListener('click', async () => {
      const ok = await ui.confirm({
        title: 'Reset demo?',
        message: 'This restores the exact seeded tournaments, teams, matches, and audit trail.',
        confirmLabel: 'Reset demo',
        tone: 'danger',
      });
      if (!ok) return;
      store.actions.reset();
      ui.toast('Demo reset to the seeded matchday.', { tone: 'success', title: 'Reset complete' });
      window.setTimeout(() => {
        window.location.href = route('dashboard');
      }, 250);
    });
  });

  const refreshBadges = () => {
    const state = store.getState();
    const nextPending = state.teams.filter((team) => team.status === 'pending').length;
    const nextUnpaid = state.teams.filter(
      (team) => team.status !== 'rejected' && team.payment.status !== 'paid'
    ).length;
    const nextUnread = store.select.notifications().length;
    document.querySelectorAll('[data-shell-badge="submissions"]').forEach((element) => {
      element.textContent = nextPending;
      element.hidden = !nextPending;
    });
    document.querySelectorAll('[data-shell-badge="payments"]').forEach((element) => {
      element.textContent = nextUnpaid;
      element.hidden = !nextUnpaid;
    });
    document.querySelectorAll('[data-shell-notification]').forEach((element) => {
      element.hidden = !nextUnread;
    });
  };

  store.subscribe(refreshBadges);
  syncTheme();
  refreshBadges();
}
```

- [ ] **Step 3: Rewrite `app.js`**

Replace `prototype/assets/js/app.js` with:

```js
import { createStore } from './store.js';
import { createUI } from './ui.js';
import { createComponents } from './components.js';
import { renderShell } from './shell.js';
import { route } from './routes.js';

const THEME_KEY = 'hoopflow_theme';
const store = createStore();
const ui = createUI({ store });
const components = createComponents({ store, ui });

function applyTheme(value) {
  const next = value === 'dark' ? 'dark' : 'light';
  document.documentElement.dataset.theme = next;
  document.documentElement.style.colorScheme = next;
}

const theme = {
  get: () => (localStorage.getItem(THEME_KEY) === 'dark' ? 'dark' : 'light'),
  set: (value) => {
    const next = value === 'dark' ? 'dark' : 'light';
    localStorage.setItem(THEME_KEY, next);
    applyTheme(next);
    document.dispatchEvent(new CustomEvent('hf:theme-changed', { detail: { theme: next } }));
  },
};

applyTheme(theme.get());
renderShell({
  store,
  ui,
  root: document.getElementById('hfShell'),
  theme,
});

document.addEventListener('hf:role-change', (event) => {
  try {
    store.actions.setRole(event.detail.role);
  } catch (error) {
    ui.toast(error.message, { tone: 'danger', title: 'Role unavailable' });
    return;
  }
  ui.toast(`Now viewing as ${event.detail.role}.`, {
    tone: 'info',
    title: 'Demo role switched',
  });
  window.setTimeout(() => {
    window.location.href = route('dashboard');
  }, 250);
});

window.addEventListener('storage', (event) => {
  if (event.key === 'hoopflow_db') window.location.reload();
});

const page = document.body.dataset.page;
const root = document.getElementById('main');
if (page && root) {
  import(`./pages/${page}.js`)
    .then((module) => module.render({ store, ui, components, root }))
    .catch((error) => {
      console.error(error);
      root.innerHTML = `
        <div class="hf-empty">
          <i class="bi bi-cone-striped" aria-hidden="true"></i>
          <h3>Page failed to load</h3>
          <p>${error.message}</p>
        </div>
      `;
    });
}
```

- [ ] **Step 4: Rewrite the root redirect and splash page**

Replace `prototype/index.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="refresh" content="0; url=auth/splash.html">
  <title>HoopFlow</title>
</head>
<body>
  <p><a href="auth/splash.html">Open the HoopFlow demo</a></p>
</body>
</html>
```

Replace `prototype/auth/splash.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Welcome — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="splash" data-shell="auth">
  <div class="hf-auth">
    <div id="hfShell"></div>
    <main id="main" class="hf-auth-main" tabindex="-1"></main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

Create `prototype/assets/js/pages/splash.js`:

```js
import { route } from '../routes.js';

export function render({ root }) {
  root.innerHTML = `
    <div class="hf-auth-card hf-auth-card--wide" data-testid="splash-card">
      <span class="hf-eyebrow">Philippine grassroots basketball</span>
      <h1 class="hf-auth-title">Run the matchday, not the spreadsheet.</h1>
      <p class="hf-auth-sub">
        Team review, payments, brackets, court schedules, final scores, and standings
        in one courtside operations desk.
      </p>
      <div class="hf-btn-row">
        <a class="hf-btn hf-btn--primary hf-full" href="${route('login')}" data-testid="splash-enter">
          Enter the demo
        </a>
        <a class="hf-btn hf-full" href="${route('register')}" data-testid="splash-register">
          Create an account
        </a>
      </div>
      <div class="hf-flex hf-wrap hf-gap-3 hf-mt-4 hf-small hf-muted">
        <span><strong>3</strong> tournaments</span>
        <span><strong>16</strong> registered teams</span>
        <span><strong>4</strong> courts</span>
        <span><strong>10</strong> demo steps</span>
      </div>
      <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
        Prototype only. Data stays in this browser; no real accounts or payments.
      </p>
    </div>
  `;
}
```

- [ ] **Step 5: Rewrite login**

Replace `prototype/auth/login.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign in — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="login" data-shell="auth">
  <div class="hf-auth">
    <div id="hfShell"></div>
    <main id="main" class="hf-auth-main" tabindex="-1"></main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

Create `prototype/assets/js/pages/login.js`:

```js
import { route } from '../routes.js';

const ACCOUNTS = {
  'organizer@hoopflow.ph': 'organizer',
  'scorekeeper@hoopflow.ph': 'scorekeeper',
  'coach.marcus@hoopflow.ph': 'coach',
  'fan.juan@hoopflow.ph': 'fan',
};

const QUICK = [
  ['organizer', 'Organizer', 'Tournament operations'],
  ['scorekeeper', 'Scorekeeper', 'Scores and stats'],
  ['coach', 'Coach', 'San Antonio Vipers'],
  ['fan', 'Fan', 'Public view'],
];

export function render({ store, ui, root }) {
  root.innerHTML = `
    <div class="hf-auth-card" data-testid="login-card">
      <span class="hf-eyebrow">Welcome back</span>
      <h1 class="hf-auth-title">Sign in</h1>
      <p class="hf-auth-sub">Demo accounts only. Passwords are not checked in this prototype.</p>
      <form id="loginForm" novalidate>
        <div class="hf-field">
          <label class="hf-label" for="loginEmail">Email</label>
          <input class="hf-input" id="loginEmail" name="email" type="email"
                 autocomplete="username" value="organizer@hoopflow.ph" data-testid="login-email">
        </div>
        <div class="hf-field hf-mt-2">
          <label class="hf-label" for="loginPassword">Password</label>
          <div class="hf-inline-form">
            <input class="hf-input" id="loginPassword" name="password" type="password"
                   autocomplete="current-password" value="hoopflow" data-testid="login-password">
            <button type="button" class="hf-btn hf-btn--sm" data-toggle-password
                    aria-pressed="false" data-testid="login-password-toggle">Show</button>
          </div>
        </div>
        <button type="submit" class="hf-btn hf-btn--primary hf-full hf-mt-3" data-testid="login-submit">
          Sign in
        </button>
      </form>

      <p class="hf-demo-title hf-mt-3">Quick-fill accounts</p>
      <div class="hf-quick-accounts">
        ${QUICK.map(
          ([role, label, note]) => `
            <button type="button" data-quick-role="${role}" data-testid="quick-login-${role}">
              <strong>${label}</strong>
              <span>${note}</span>
            </button>
          `
        ).join('')}
      </div>
      <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
        No account? <a href="${route('register')}">Create one</a>.
      </p>
    </div>
  `;

  const form = root.querySelector('#loginForm');
  const emailInput = root.querySelector('#loginEmail');
  const passwordInput = root.querySelector('#loginPassword');
  const toggle = root.querySelector('[data-toggle-password]');

  toggle.addEventListener('click', () => {
    const show = passwordInput.type === 'password';
    passwordInput.type = show ? 'text' : 'password';
    toggle.textContent = show ? 'Hide' : 'Show';
    toggle.setAttribute('aria-pressed', String(show));
  });

  const enter = (role, message) => {
    store.actions.setRole(role);
    ui.toast(message, { tone: 'success', title: 'Signed in' });
    window.setTimeout(() => {
      window.location.href = route('dashboard');
    }, 250);
  };

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const email = emailInput.value.trim().toLowerCase();
    let valid = true;
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      ui.setFieldError(emailInput, 'Enter a valid email address.');
      valid = false;
    } else {
      ui.clearFieldError(emailInput);
    }
    if (passwordInput.value.length < 6) {
      ui.setFieldError(passwordInput, 'Use at least 6 characters.');
      valid = false;
    } else {
      ui.clearFieldError(passwordInput);
    }
    if (!valid) return;
    enter(ACCOUNTS[email] || 'fan', `Signed in as ${ACCOUNTS[email] || 'fan'}.`);
  });

  root.querySelectorAll('[data-quick-role]').forEach((button) => {
    button.addEventListener('click', () => {
      enter(button.dataset.quickRole, `Signed in as ${button.dataset.quickRole}.`);
    });
  });
}
```

- [ ] **Step 6: Rewrite registration**

Replace `prototype/auth/register.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create account — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="register" data-shell="auth">
  <div class="hf-auth">
    <div id="hfShell"></div>
    <main id="main" class="hf-auth-main" tabindex="-1"></main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

Create `prototype/assets/js/pages/register.js`:

```js
import { route } from '../routes.js';

export function render({ store, ui, root }) {
  root.innerHTML = `
    <div class="hf-auth-card" data-testid="register-card">
      <span class="hf-eyebrow">New account</span>
      <h1 class="hf-auth-title">Create account</h1>
      <p class="hf-auth-sub">Prototype registration creates a local demo session only.</p>
      <form id="registerForm" novalidate>
        <div class="hf-form-grid">
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="registerName">Full name</label>
            <input class="hf-input" id="registerName" name="name" autocomplete="name"
                   placeholder="Juan Dela Cruz" data-testid="register-name">
          </div>
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="registerEmail">Email</label>
            <input class="hf-input" id="registerEmail" name="email" type="email"
                   autocomplete="email" placeholder="you@club.ph" data-testid="register-email">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="registerPassword">Password</label>
            <input class="hf-input" id="registerPassword" name="password" type="password"
                   autocomplete="new-password" data-testid="register-password">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="registerRole">Role</label>
            <select class="hf-select" id="registerRole" name="role" data-testid="register-role">
              <option value="coach">Coach</option>
              <option value="fan">Fan</option>
            </select>
          </div>
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="registerOrganization">Club or organization</label>
            <input class="hf-input" id="registerOrganization" name="organization"
                   placeholder="Barangay San Antonio Youth Club" data-testid="register-organization">
          </div>
        </div>
        <button type="submit" class="hf-btn hf-btn--primary hf-full hf-mt-3" data-testid="register-submit">
          Create demo account
        </button>
      </form>
      <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
        Already have an account? <a href="${route('login')}">Sign in</a>.
      </p>
    </div>
  `;

  const form = root.querySelector('#registerForm');
  const fields = {
    name: root.querySelector('#registerName'),
    email: root.querySelector('#registerEmail'),
    password: root.querySelector('#registerPassword'),
    role: root.querySelector('#registerRole'),
    organization: root.querySelector('#registerOrganization'),
  };

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    let valid = true;
    if (fields.name.value.trim().length < 2) {
      ui.setFieldError(fields.name, 'Enter your full name.');
      valid = false;
    } else ui.clearFieldError(fields.name);
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fields.email.value.trim())) {
      ui.setFieldError(fields.email, 'Enter a valid email address.');
      valid = false;
    } else ui.clearFieldError(fields.email);
    if (fields.password.value.length < 6) {
      ui.setFieldError(fields.password, 'Use at least 6 characters.');
      valid = false;
    } else ui.clearFieldError(fields.password);
    if (!valid) return;
    store.actions.setRole(fields.role.value);
    ui.toast('Local demo account created.', { tone: 'success', title: 'Welcome to HoopFlow' });
    window.setTimeout(() => {
      window.location.href = route('dashboard');
    }, 250);
  });
}
```

- [ ] **Step 7: Verify the shell and auth pages in the browser**

```powershell
php -S 127.0.0.1:8001 -t prototype
```

Open `http://127.0.0.1:8001/auth/splash.html`, then `auth/login.html`. Expected: Barlow Condensed headings, a bordered auth card, four quick-fill buttons, working password toggle, and no console errors. Clicking a quick-fill account lands on `dashboard/index.html` with an empty main area until Task 10.

- [ ] **Step 8: Commit**

```powershell
git add prototype/index.html prototype/auth prototype/assets/js/routes.js prototype/assets/js/shell.js prototype/assets/js/app.js prototype/assets/js/pages/splash.js prototype/assets/js/pages/login.js prototype/assets/js/pages/register.js
git commit -m "feat(prototype): add shared shell, route map, and auth flow"
```

## Task 10: Role-aware dashboard

**Files:**
- Rewrite: `prototype/dashboard/index.html`
- Create: `prototype/assets/js/pages/dashboard.js`

**Interfaces:**
- Consumes: `route`, `createComponents`, `createUI`, `store.select.kpis/paymentSummary/standings/recentResults/upcomingMatches/coachTeams/nextMatch/announcements`.
- Produces: the dashboard screen for all four roles, with links into every seeded journey.

- [ ] **Step 1: Rewrite the dashboard HTML skeleton**

Replace `prototype/dashboard/index.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="dashboard" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading matchday desk...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 2: Implement the dashboard renderer**

Create `prototype/assets/js/pages/dashboard.js`:

```js
import { route } from '../routes.js';
import { escapeHtml, formatDate, formatMoney } from '../ui.js';

export function render({ store, ui, components, root }) {
  const role = store.getRole();
  const state = store.getState();
  const active = store.select.tournament('tournament-1');
  const pending = state.teams.filter((team) => team.status === 'pending').length;
  const openTournaments = state.tournaments.filter(
    (tournament) => tournament.status === 'registration-open'
  );

  const header = components.pageHeader({
    eyebrow: `Matchday desk · ${role}`,
    title:
      role === 'organizer'
        ? 'Run the tournament'
        : role === 'scorekeeper'
          ? 'Score console'
          : role === 'coach'
            ? 'Coach corner'
            : 'Courtside pass',
    description:
      role === 'organizer'
        ? 'Approvals, payments, bracket progress, and the next action that keeps the matchday moving.'
        : role === 'scorekeeper'
          ? 'Enter final scores, correct recorded results, and open the official box score.'
          : role === 'coach'
            ? 'Your registration status, roster readiness, payment, and next game.'
            : 'Follow the active tournament: bracket, schedule, results, standings, and announcements.',
    actions:
      role === 'organizer'
        ? `<a class="hf-btn hf-btn--primary" href="${route('tournamentCreate')}" data-testid="dashboard-create-tournament"><i class="bi bi-plus-lg"></i> New tournament</a>
           <a class="hf-btn" href="${route('submissions')}" data-testid="dashboard-open-submissions">Review queue</a>`
        : role === 'coach'
          ? `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}" data-testid="dashboard-register-cta"><i class="bi bi-trophy"></i> Find open tournament</a>`
          : `<a class="hf-btn hf-btn--primary" href="${route('bracket')}" data-testid="dashboard-open-bracket"><i class="bi bi-diagram-3"></i> Open bracket</a>`,
    meta: active
      ? `<span><i class="bi bi-calendar3"></i>${escapeHtml(formatDate(active.startDate))} – ${escapeHtml(formatDate(active.endDate))}</span>
         <span><i class="bi bi-geo-alt"></i>${escapeHtml(active.venue)}</span>
         <span><i class="bi bi-people"></i>${active.slots} slots · ${active.courts.length} courts</span>`
      : '',
  });

  root.innerHTML = `${header}<div id="dashboardBody"></div>`;
  const body = root.querySelector('#dashboardBody');
  body.innerHTML =
    role === 'organizer'
      ? organizerDashboard()
      : role === 'scorekeeper'
        ? scorekeeperDashboard()
        : role === 'coach'
          ? coachDashboard()
          : fanDashboard();

  function organizerDashboard() {
    const kpis = store.select.kpis('tournament-1');
    const bracket = store.select.bracket('tournament-1');
    const totalMatches = bracket.rounds.reduce((sum, round) => sum + round.matches.length, 0);
    const progress = totalMatches ? Math.round((kpis.matchesPlayed / totalMatches) * 100) : 0;
    const unpaid = store.select.paymentSummary('tournament-1');
    const recent = store.select.recentResults('tournament-1', 3);
    const announcements = store.select.announcements('tournament-1').slice(0, 2);
    return `
      <div class="hf-grid hf-grid--4 hf-mb-3">
        ${components.kpiTile({ label: 'Tournaments', value: state.tournaments.length, hint: '3 seeded venues', href: route('tournaments'), tone: 'primary', testid: 'kpi-tournaments' })}
        ${components.kpiTile({ label: 'Pending teams', value: pending, hint: 'Waiting for review', href: `${route('submissions')}?status=pending`, tone: 'gold', testid: 'kpi-pending' })}
        ${components.kpiTile({ label: 'Uncollected', value: formatMoney(unpaid.outstanding), hint: `${unpaid.pendingCount} teams unpaid`, href: `${route('payments')}?status=pending`, tone: 'alert', testid: 'kpi-uncollected' })}
        ${components.kpiTile({ label: 'Approved teams', value: kpis.approved, hint: `${kpis.registered} registered`, href: route('teams'), tone: 'success', testid: 'kpi-approved' })}
      </div>

      <div class="hf-split hf-mb-3">
        <div>
          ${components.sectionHeader({ title: 'Next actions', action: `<a class="hf-section-link" href="${route('submissions')}">Open queue</a>` })}
          <div class="hf-grid hf-grid--3 hf-mb-3">
            <a class="hf-card hf-card--flat" href="${route('submissions')}?status=pending" data-testid="dashboard-action-review">
              <span class="hf-chip hf-chip--warning">Review</span>
              <h3 class="hf-section-title hf-mt-2">${pending} pending teams</h3>
              <p class="hf-muted hf-small hf-mb-0">Roster, consent, and documents are waiting for an organizer decision.</p>
            </a>
            <a class="hf-card hf-card--flat" href="${route('payments')}?status=pending" data-testid="dashboard-action-payments">
              <span class="hf-chip hf-chip--danger">Verify</span>
              <h3 class="hf-section-title hf-mt-2">${unpaid.pendingCount} payments</h3>
              <p class="hf-muted hf-small hf-mb-0">Collected ${formatMoney(unpaid.collected)} of ${formatMoney(unpaid.expected)}.</p>
            </a>
            <a class="hf-card hf-card--flat" href="${route('bracket')}?match=qf-3" data-testid="dashboard-action-score">
              <span class="hf-chip hf-chip--primary">Score</span>
              <h3 class="hf-section-title hf-mt-2">${kpis.matchesScheduled} games left</h3>
              <p class="hf-muted hf-small hf-mb-0">Open the quarterfinal console and advance the winner.</p>
            </a>
          </div>

          ${components.sectionHeader({ title: 'Active tournament', action: `<a class="hf-section-link" href="${route('tournamentShow', { id: active.id })}">Open tournament</a>` })}
          <div class="hf-card">
            <div class="hf-between">
              <div>
                <span class="hf-chip hf-chip--primary">${escapeHtml(active.division)}</span>
                <h3 class="hf-page-title hf-mt-1 hf-mb-0" style="font-size: 30px">${escapeHtml(active.name)}</h3>
              </div>
              <div class="hf-text-right">
                <span class="hf-kpi-label">Bracket progress</span>
                <strong class="hf-kpi-value" style="font-size: 32px">${kpis.matchesPlayed}/${totalMatches}</strong>
              </div>
            </div>
            <div class="hf-round-progress hf-mt-2"><span style="width: ${progress}%"></span></div>
            <div class="hf-flex hf-wrap hf-gap-2 hf-mt-2">
              <a class="hf-btn hf-btn--sm" href="${route('bracket')}">Bracket</a>
              <a class="hf-btn hf-btn--sm" href="${route('schedule')}">Schedule</a>
              <a class="hf-btn hf-btn--sm" href="${route('standings')}">Standings</a>
              <a class="hf-btn hf-btn--sm" href="${route('announcements')}">Announcements</a>
            </div>
          </div>

          ${components.sectionHeader({ title: 'Recent results', action: `<a class="hf-section-link" href="${route('matches')}">All matchups</a>` })}
          <div class="hf-grid hf-grid--2">
            ${recent.length ? recent.map((match) => components.matchCard(match, { compact: true, href: route('gameStats', { match: match.id }) })).join('') : ui.emptyState({ icon: 'flag', title: 'No results yet', message: 'Final scores will appear here.' })}
          </div>
        </div>

        <aside>
          ${components.sectionHeader({ title: 'Standings snapshot', action: `<a class="hf-section-link" href="${route('standings')}">Full table</a>` })}
          <div class="hf-mb-3">${components.standingsTable('tournament-1', { limit: 4 })}</div>
          ${components.sectionHeader({ title: 'Announcements', action: `<a class="hf-section-link" href="${route('announcements')}">All</a>` })}
          <div class="hf-grid">${announcements.map((item) => components.announcementCard(item, { compact: true })).join('')}</div>
        </aside>
      </div>
    `;
  }

  function scorekeeperDashboard() {
    const kpis = store.select.kpis('tournament-1');
    const queue = store
      .select.matches('tournament-1')
      .filter((match) => match.status === 'scheduled' && match.team1Id && match.team2Id);
    const recent = store.select.recentResults('tournament-1', 4);
    return `
      <div class="hf-grid hf-grid--3 hf-mb-3">
        ${components.kpiTile({ label: 'Games to score', value: queue.length, hint: 'Both teams known', tone: 'primary', testid: 'kpi-score-queue' })}
        ${components.kpiTile({ label: 'Finals recorded', value: kpis.matchesPlayed, hint: 'Final and forfeit', tone: 'success', testid: 'kpi-finals' })}
        ${components.kpiTile({ label: 'Corrections', value: state.audit.filter((entry) => entry.action === 'score.correct').length, hint: 'Audited changes', tone: 'alert', testid: 'kpi-corrections' })}
      </div>
      ${components.sectionHeader({ title: 'Score queue', action: `<a class="hf-section-link" href="${route('bracket')}">Bracket</a>` })}
      <div class="hf-grid hf-grid--2 hf-mb-3" data-testid="scorekeeper-queue">
        ${queue.length ? queue.map((match) => components.matchCard(match, { href: `${route('bracket')}?match=${match.id}` })).join('') : ui.emptyState({ icon: 'check2-circle', title: 'Queue is clear', message: 'No scheduled game has both teams known.' })}
      </div>
      ${components.sectionHeader({ title: 'Recent finals' })}
      <div class="hf-grid hf-grid--2">
        ${recent.map((match) => components.matchCard(match, { compact: true, href: route('gameStats', { match: match.id }) })).join('')}
      </div>
      <p class="hf-small hf-muted hf-mt-3">Scorekeeper access allows score entry and correction only. Approvals, payments, and roster edits are blocked.</p>
    `;
  }

  function coachDashboard() {
    const teams = store.select.coachTeams();
    const team = teams[0];
    if (!team) {
      return ui.emptyState({
        icon: 'person-plus',
        title: 'No team attached',
        message: 'Register a team for an open tournament to start the coach journey.',
        action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}">Browse tournaments</a>`,
      });
    }
    const record = store.select.teamRecord(team.id);
    const next = store.select.nextMatch(team.id);
    const tournament = store.select.tournament(team.tournamentId);
    return `
      <section class="hf-hero hf-mb-3" data-testid="coach-hero">
        <span class="hf-eyebrow" style="color: var(--hf-gold)">${escapeHtml(tournament?.name || 'Tournament')} · ${escapeHtml(team.status)}</span>
        <h1>${escapeHtml(team.name)}</h1>
        <p>${escapeHtml(team.coach)} · ${escapeHtml(team.city)} · ${escapeHtml(tournament?.division || '')}</p>
        <div class="hf-stat-strip">
          <div><span>Record</span><strong>${record.wins}-${record.losses}</strong></div>
          <div><span>Seed</span><strong>${team.seed ?? 'TBD'}</strong></div>
          <div><span>Roster</span><strong>${team.players.length}</strong></div>
          <div><span>Payment</span><strong>${escapeHtml(team.payment.status)}</strong></div>
        </div>
      </section>
      <div class="hf-grid hf-grid--3 hf-mb-3">
        <div class="hf-card">
          <span class="hf-kpi-label">Roster readiness</span>
          <strong class="hf-kpi-value">${team.players.filter((player) => player.guardianConsent).length}/${team.players.length}</strong>
          <p class="hf-small hf-muted hf-mb-0">Guardian consent on file.</p>
          <a class="hf-btn hf-btn--sm hf-mt-2" href="${route('myTeam', { team: team.id })}">Manage roster</a>
        </div>
        <div class="hf-card">
          <span class="hf-kpi-label">Next game</span>
          <strong class="hf-section-title hf-mt-1">${next ? escapeHtml(next.roundLabel) : 'No game scheduled'}</strong>
          <p class="hf-small hf-muted hf-mb-0">${next?.scheduledAt ? escapeHtml(formatDate(next.scheduledAt)) : 'Waiting for the bracket.'}</p>
          ${next ? `<a class="hf-btn hf-btn--sm hf-mt-2" href="${route('gameStats', { match: next.id })}">Game details</a>` : ''}
        </div>
        <div class="hf-card">
          <span class="hf-kpi-label">Registration</span>
          <strong class="hf-section-title hf-mt-1">${escapeHtml(team.status)}</strong>
          <p class="hf-small hf-muted hf-mb-0">${escapeHtml(team.rejectionReason || 'Documents and payment are tracked by the organizer.')}</p>
        </div>
      </div>
      ${components.sectionHeader({ title: 'Open tournaments', action: `<a class="hf-section-link" href="${route('tournaments')}">All tournaments</a>` })}
      <div class="hf-grid hf-grid--2">
        ${openTournaments.length ? openTournaments.map((item) => `
          <div class="hf-card">
            <span class="hf-chip hf-chip--success">Registration open</span>
            <h3 class="hf-section-title hf-mt-1">${escapeHtml(item.name)}</h3>
            <p class="hf-small hf-muted">${escapeHtml(item.division)} · ${escapeHtml(item.venue)} · ${formatMoney(item.fee)} entry</p>
            <a class="hf-btn hf-btn--primary" href="${route('teamRegister', { tournament: item.id })}" data-testid="coach-register-${item.id}">Register a team</a>
          </div>
        `).join('') : ui.emptyState({ icon: 'trophy', title: 'No open registration', message: 'Check back after the organizer opens the next tournament.' })}
      </div>
    `;
  }

  function fanDashboard() {
    const upcoming = store.select.upcomingMatches('tournament-1', 4);
    const recent = store.select.recentResults('tournament-1', 4);
    const announcements = store
      .select.announcements('tournament-1')
      .filter((item) => item.audience === 'all')
      .slice(0, 3);
    return `
      <section class="hf-hero hf-mb-3" data-testid="fan-hero">
        <span class="hf-eyebrow" style="color: var(--hf-gold)">Courtside pass · ${escapeHtml(active.status)}</span>
        <h1>${escapeHtml(active.name)}</h1>
        <p>${escapeHtml(active.division)} · ${escapeHtml(active.venue)} · ${escapeHtml(formatDate(active.startDate))} to ${escapeHtml(formatDate(active.endDate))}</p>
        <div class="hf-btn-row hf-mt-3">
          <a class="hf-btn hf-btn--primary" href="${route('bracket')}">Bracket</a>
          <a class="hf-btn" href="${route('schedule')}">Schedule</a>
          <a class="hf-btn" href="${route('standings')}">Standings</a>
        </div>
      </section>
      <div class="hf-split">
        <div>
          ${components.sectionHeader({ title: 'Up next', action: `<a class="hf-section-link" href="${route('schedule')}">Full schedule</a>` })}
          <div class="hf-grid hf-grid--2 hf-mb-3">
            ${upcoming.length ? upcoming.map((match) => components.matchCard(match, { compact: true, href: route('gameStats', { match: match.id }) })).join('') : ui.emptyState({ icon: 'calendar3', title: 'No games scheduled', message: 'The next tip-off will be posted here.' })}
          </div>
          ${components.sectionHeader({ title: 'Recent results' })}
          <div class="hf-grid hf-grid--2">
            ${recent.map((match) => components.matchCard(match, { compact: true, href: route('gameStats', { match: match.id }) })).join('')}
          </div>
        </div>
        <aside>
          ${components.sectionHeader({ title: 'Top of the table', action: `<a class="hf-section-link" href="${route('standings')}">Full table</a>` })}
          <div class="hf-mb-3">${components.standingsTable('tournament-1', { limit: 5 })}</div>
          ${components.sectionHeader({ title: 'Announcements' })}
          <div class="hf-grid">${announcements.map((item) => components.announcementCard(item, { compact: true })).join('')}</div>
        </aside>
      </div>
    `;
  }
}
```

- [ ] **Step 3: Check the module parses**

```powershell
cd prototype
node --check assets/js/pages/dashboard.js
```

Expected: no output.

- [ ] **Step 4: Verify all four dashboards**

Open `http://127.0.0.1:8001/auth/login.html`. Sign in as organizer, scorekeeper, coach, and fan. Expected: each role sees a different dashboard, KPI tiles link to the correct filtered destination, and the coach sees the San Antonio Vipers hero.

- [ ] **Step 5: Commit**

```powershell
git add prototype/dashboard/index.html prototype/assets/js/pages/dashboard.js
git commit -m "feat(prototype): add role-aware dashboard"
```

## Task 11: Tournaments index

**Files:**
- Rewrite: `prototype/tournaments/index.html`
- Create: `prototype/assets/js/pages/tournaments.js`

**Interfaces:**
- Consumes: `route`, `formatDate`, `formatMoney`, `store.select.tournaments/kpis`.
- Produces: searchable, status-filtered, division-filtered tournament cards with role-aware actions.

- [ ] **Step 1: Rewrite the tournaments index HTML**

Replace `prototype/tournaments/index.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tournaments — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="tournaments" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading tournaments...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 2: Implement the tournaments renderer**

Create `prototype/assets/js/pages/tournaments.js`:

```js
import { route } from '../routes.js';
import { escapeHtml, formatDate, formatMoney } from '../ui.js';

export function render({ store, ui, components, root }) {
  const role = store.getRole();
  const params = new URLSearchParams(window.location.search);
  const tournaments = store.select.tournaments();
  const divisions = [...new Set(tournaments.map((tournament) => tournament.division))].sort();

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Tournament directory',
      title: 'Tournaments',
      description: 'Three seeded competitions cover registration, a live bracket, and a draft that can generate a bracket and schedule during the demo.',
      actions: role === 'organizer'
        ? `<a class="hf-btn hf-btn--primary" href="${route('tournamentCreate')}" data-testid="tournaments-create"><i class="bi bi-plus-lg"></i> New tournament</a>`
        : `<a class="hf-btn" href="${route('dashboard')}">Back to dashboard</a>`,
    })}

    <div class="hf-card hf-mb-3">
      <div class="hf-form-grid">
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="tournamentSearch">Search</label>
          <input class="hf-input" id="tournamentSearch" type="search"
                 placeholder="Name, venue, or division" value="${escapeHtml(params.get('q') || '')}"
                 data-testid="tournament-search">
        </div>
        <div class="hf-field">
          <label class="hf-label" for="tournamentStatus">Status</label>
          <select class="hf-select" id="tournamentStatus" data-testid="tournament-status-filter">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="registration-open">Registration open</option>
            <option value="upcoming">Upcoming</option>
            <option value="draft">Draft</option>
          </select>
        </div>
        <div class="hf-field">
          <label class="hf-label" for="tournamentDivision">Division</label>
          <select class="hf-select" id="tournamentDivision" data-testid="tournament-division-filter">
            <option value="">All divisions</option>
            ${divisions.map((division) => `<option value="${escapeHtml(division)}">${escapeHtml(division)}</option>`).join('')}
          </select>
        </div>
      </div>
    </div>

    <div id="tournamentList" data-testid="tournament-list"></div>
  `;

  const search = root.querySelector('#tournamentSearch');
  const status = root.querySelector('#tournamentStatus');
  const division = root.querySelector('#tournamentDivision');
  const list = root.querySelector('#tournamentList');
  status.value = params.get('status') || '';

  const card = (tournament) => {
    const kpis = store.select.kpis(tournament.id);
    const coachTeam = role === 'coach'
      ? store.select.coachTeams().find((team) => team.tournamentId === tournament.id)
      : null;
    const action = role === 'coach' && tournament.status === 'registration-open' && !coachTeam
      ? `<a class="hf-btn hf-btn--primary" href="${route('teamRegister', { tournament: tournament.id })}" data-testid="tournament-register-${tournament.id}">Register team</a>`
      : coachTeam
        ? `<a class="hf-btn" href="${route('myTeam', { team: coachTeam.id })}" data-testid="tournament-my-team-${tournament.id}">My team</a>`
        : `<a class="hf-btn" href="${route('tournamentShow', { id: tournament.id })}" data-testid="tournament-open-${tournament.id}">Open desk</a>`;
    return `
      <article class="hf-card hf-mb-2" data-testid="tournament-card-${tournament.id}">
        <div class="hf-between">
          <div>
            <span class="hf-flex hf-wrap hf-gap-1">
              ${ui.statusChip(tournament.status)}
              <span class="hf-chip hf-chip--slate">${escapeHtml(tournament.division)}</span>
              ${tournament.seedLocked ? '<span class="hf-chip hf-chip--gold">Seeds locked</span>' : ''}
            </span>
            <h2 class="hf-section-title hf-mt-1">${escapeHtml(tournament.name)}</h2>
            <p class="hf-small hf-muted hf-mb-0">
              ${escapeHtml(tournament.venue)} · ${escapeHtml(formatDate(tournament.startDate))} to ${escapeHtml(formatDate(tournament.endDate))}
            </p>
          </div>
          <div class="hf-text-right">
            <span class="hf-kpi-label">Teams</span>
            <strong class="hf-kpi-value" style="font-size: 32px">${kpis.approved}/${tournament.slots}</strong>
          </div>
        </div>
        <div class="hf-flex hf-wrap hf-gap-3 hf-mt-2 hf-small hf-muted">
          <span><i class="bi bi-geo-alt"></i> ${tournament.courts.length} courts</span>
          <span><i class="bi bi-cash"></i> ${escapeHtml(formatMoney(tournament.fee))} entry</span>
          <span><i class="bi bi-diagram-3"></i> ${kpis.matchesPlayed} played</span>
          <span><i class="bi bi-hourglass-split"></i> ${kpis.pending} pending</span>
        </div>
        <div class="hf-btn-row hf-mt-2">
          ${action}
          <a class="hf-btn" href="${route('bracket', { id: tournament.id })}">Bracket</a>
          <a class="hf-btn" href="${route('schedule', { id: tournament.id })}">Schedule</a>
        </div>
      </article>
    `;
  };

  const renderList = () => {
    const term = search.value.trim().toLowerCase();
    const statusValue = status.value;
    const divisionValue = division.value;
    const filtered = tournaments.filter((tournament) => {
      const haystack = `${tournament.name} ${tournament.venue} ${tournament.division}`.toLowerCase();
      return (
        (!term || haystack.includes(term)) &&
        (!statusValue || tournament.status === statusValue) &&
        (!divisionValue || tournament.division === divisionValue)
      );
    });
    list.innerHTML = filtered.length
      ? filtered.map(card).join('')
      : ui.emptyState({
          icon: 'search',
          title: 'No tournaments match',
          message: 'Clear the search or choose another status/division.',
          action: `<button type="button" class="hf-btn" data-clear-filters data-testid="tournament-clear-filters">Clear filters</button>`,
        });
    list.querySelector('[data-clear-filters]')?.addEventListener('click', () => {
      search.value = '';
      status.value = '';
      division.value = '';
      renderList();
    });
  };

  search.addEventListener('input', ui.debounce(renderList, 120));
  status.addEventListener('change', renderList);
  division.addEventListener('change', renderList);
  renderList();
}
```

- [ ] **Step 3: Verify filters and role actions**

Open `http://127.0.0.1:8001/tournaments/index.html`. Expected: three cards, search filters by name/venue/division, status filter matches, coach sees "Register team" on Pasig Youth Hoopfest but not on the active or draft cup, organizer sees "New tournament".

- [ ] **Step 4: Commit**

```powershell
git add prototype/tournaments/index.html prototype/assets/js/pages/tournaments.js
git commit -m "feat(prototype): rebuild tournaments directory"
```

## Task 12: Tournament show and live generation controls

**Files:**
- Rewrite: `prototype/tournaments/show.html`
- Create: `prototype/assets/js/pages/tournament-show.js`

**Interfaces:**
- Consumes: `route`, `store.select.bracket/kpis/teams/announcements/matches`, `actions.generateBracket/generateSchedule`, `store.planSchedule`, `ui.dialog/confirm/toast/setBusy`.
- Produces: the tournament desk used by the CDO bracket/schedule generation demo.

- [ ] **Step 1: Rewrite the tournament show HTML**

Replace `prototype/tournaments/show.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tournament — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="tournament-show" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading tournament...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 2: Implement the tournament renderer**

Create `prototype/assets/js/pages/tournament-show.js`:

```js
import { route } from '../routes.js';
import { escapeHtml, formatDate, formatMoney, formatTime } from '../ui.js';

export function render({ store, ui, components, root }) {
  const params = new URLSearchParams(window.location.search);
  const tournamentId = params.get('id') || 'tournament-1';
  let tournament = store.select.tournament(tournamentId);

  if (!tournament) {
    root.innerHTML = ui.emptyState({
      icon: 'trophy',
      title: 'Tournament not found',
      message: 'The tournament link is invalid or the demo has been reset.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}">Back to tournaments</a>`,
    });
    return;
  }

  function draw() {
    tournament = store.select.tournament(tournamentId);
    const role = store.getRole();
    const kpis = store.select.kpis(tournamentId);
    const allTeams = store.select.teams(tournamentId);
    const teams = role === 'fan' ? allTeams.filter((team) => team.status === 'approved') : allTeams;
    const bracket = store.select.bracket(tournamentId);
    const announcements = store
      .select.announcements(tournamentId)
      .filter((item) => role !== 'fan' || item.audience === 'all');
    const coachTeam = role === 'coach'
      ? store.select.coachTeams().find((team) => team.tournamentId === tournamentId)
      : null;
    const approved = teams.filter((team) => team.status === 'approved');
    const canGenerateBracket = role === 'organizer' && !bracket.hasBracket && approved.length >= 2;
    const canGenerateSchedule =
      role === 'organizer' &&
      bracket.hasBracket &&
      !kpis.matchesPlayed &&
      !store.select.matches(tournamentId).some((match) => match.courtId);

    const roleAction =
      role === 'coach'
        ? coachTeam
          ? `<a class="hf-btn" href="${route('myTeam', { team: coachTeam.id })}" data-testid="show-my-team">My team status</a>`
          : tournament.registrationOpen
            ? `<a class="hf-btn hf-btn--primary" href="${route('teamRegister', { tournament: tournamentId })}" data-testid="show-register">Register team</a>`
            : ''
        : '';

    root.innerHTML = `
      <section class="hf-hero hf-mb-3" data-testid="tournament-hero-${escapeHtml(tournament.id)}">
        <span class="hf-eyebrow" style="color: var(--hf-gold)">${escapeHtml(tournament.division)} · ${escapeHtml(tournament.status)}</span>
        <h1>${escapeHtml(tournament.name)}</h1>
        <p>${escapeHtml(tournament.description)}</p>
        <div class="hf-stat-strip">
          <div><span>Dates</span><strong>${escapeHtml(formatDate(tournament.startDate))} – ${escapeHtml(formatDate(tournament.endDate))}</strong></div>
          <div><span>Venue</span><strong>${escapeHtml(tournament.venue)}</strong></div>
          <div><span>Courts</span><strong>${tournament.courts.length}</strong></div>
          <div><span>Entry</span><strong>${escapeHtml(formatMoney(tournament.fee))}</strong></div>
        </div>
        <div class="hf-btn-row hf-mt-3">
          <a class="hf-btn hf-btn--primary" href="${route('bracket', { id: tournamentId })}">Bracket</a>
          <a class="hf-btn" href="${route('schedule', { id: tournamentId })}">Schedule</a>
          <a class="hf-btn" href="${route('standings', { id: tournamentId })}">Standings</a>
          ${roleAction}
          ${role === 'organizer' ? `<a class="hf-btn" href="${route('announcementCreate', { tournament: tournamentId })}" data-testid="show-create-announcement">Announce</a>` : ''}
        </div>
      </section>

      <div class="hf-grid hf-grid--4 hf-mb-3">
        ${
          role === 'fan'
            ? [
                components.kpiTile({ label: 'Teams', value: teams.length, hint: `${tournament.slots} slots`, tone: 'primary', testid: 'show-kpi-registered' }),
                components.kpiTile({ label: 'Matches played', value: kpis.matchesPlayed, hint: `${kpis.matchesScheduled} scheduled`, tone: 'success', testid: 'show-kpi-played' }),
                components.kpiTile({ label: 'Courts', value: kpis.courts, hint: 'Venue capacity', tone: 'gold', testid: 'show-kpi-courts' }),
                components.kpiTile({ label: 'Entry', value: formatMoney(tournament.fee), hint: 'Per team', tone: 'alert', testid: 'show-kpi-entry' }),
              ].join('')
            : [
                components.kpiTile({ label: 'Registered', value: kpis.registered, hint: `${tournament.slots} slots`, tone: 'primary', testid: 'show-kpi-registered' }),
                components.kpiTile({ label: 'Approved', value: kpis.approved, hint: `${kpis.pending} pending`, tone: 'success', testid: 'show-kpi-approved' }),
                components.kpiTile({ label: 'Matches played', value: kpis.matchesPlayed, hint: `${kpis.matchesScheduled} scheduled`, tone: 'gold', testid: 'show-kpi-played' }),
                components.kpiTile({ label: 'Collected', value: formatMoney(kpis.collected), hint: `${kpis.collectionRate}% of expected`, tone: 'alert', testid: 'show-kpi-money' }),
              ].join('')
        }
      </div>

      <div class="hf-split">
        <div>
          ${components.sectionHeader({ title: 'Team list', action: `<a class="hf-section-link" href="${route('teams')}">All teams</a>` })}
          <div class="hf-grid" data-testid="tournament-team-list">
            ${
              teams.length
                ? teams
                    .map((team) => {
                      const showMoney = role === 'organizer' || (role === 'coach' && coachTeam?.id === team.id);
                      return `
                        <a class="hf-card hf-card--flat" href="${route('teamShow', { id: team.id })}" data-testid="tournament-team-${escapeHtml(team.id)}">
                          <div class="hf-between">
                            <span class="hf-flex hf-items-center hf-gap-2">
                              ${components.shield(team)}
                              <span>
                                <strong>${escapeHtml(team.name)}</strong>
                                <span class="hf-small hf-muted" style="display:block">${escapeHtml(team.coach)} · ${escapeHtml(team.city)}</span>
                              </span>
                            </span>
                            <span class="hf-flex hf-wrap hf-gap-1">
                              ${ui.statusChip(team.status)}
                              ${showMoney ? ui.statusChip(team.payment.status) : ''}
                            </span>
                          </div>
                          ${role === 'organizer' && team.status === 'pending' ? `<div class="hf-small hf-muted hf-mt-1">Waiting for review · ${team.players.length} players</div>` : ''}
                        </a>
                      `;
                    })
                    .join('')
                : ui.emptyState({ icon: 'people', title: 'No teams yet', message: 'Coach registrations will appear here.' })
            }
          </div>

          ${components.sectionHeader({ title: 'Announcements', action: `<a class="hf-section-link" href="${route('announcements')}">All announcements</a>` })}
          <div class="hf-grid">
            ${announcements.length ? announcements.map((item) => components.announcementCard(item)).join('') : ui.emptyState({ icon: 'megaphone', title: 'No announcements', message: 'Publish a venue or schedule update for this tournament.' })}
          </div>
        </div>

        <aside>
          ${components.sectionHeader({ title: 'Bracket', action: `<a class="hf-section-link" href="${route('bracket', { id: tournamentId })}">Open</a>` })}
          <div class="hf-card hf-mb-3">
            ${
              bracket.hasBracket
                ? `<div class="hf-between"><span class="hf-chip hf-chip--gold">${bracket.rounds.length} rounds</span><strong>${kpis.matchesPlayed} played</strong></div>
                   <p class="hf-small hf-muted hf-mt-1">${bracket.champion ? `Champion: ${escapeHtml(bracket.champion.name)}` : 'Bracket is live. Winners advance automatically.'}</p>
                   <a class="hf-btn hf-btn--sm" href="${route('bracket', { id: tournamentId })}">Open bracket</a>`
                : `<p class="hf-small hf-muted">${approved.length} approved teams ready to seed.</p>
                   ${
                     canGenerateBracket
                       ? `<button type="button" class="hf-btn hf-btn--primary" data-generate-bracket data-testid="tournament-generate-bracket">Generate bracket</button>`
                       : `<button type="button" class="hf-btn" disabled data-testid="tournament-generate-bracket-disabled">Generate bracket</button>
                          <p class="hf-small hf-muted hf-mt-1">${role === 'organizer' ? 'At least two approved teams are required.' : 'Organizer access required.'}</p>`
                   }`
            }
          </div>

          ${components.sectionHeader({ title: 'Court schedule', action: `<a class="hf-section-link" href="${route('schedule', { id: tournamentId })}">Open</a>` })}
          <div class="hf-card hf-mb-3">
            ${
              !bracket.hasBracket
                ? '<p class="hf-small hf-muted hf-mb-0">Generate the bracket before scheduling courts.</p>'
                : canGenerateSchedule
                  ? `<p class="hf-small hf-muted">${tournament.courts.length} courts · ${tournament.slotMinutes}-minute slots · ${tournament.restMinutes}-minute rest.</p>
                     <button type="button" class="hf-btn hf-btn--primary" data-generate-schedule data-testid="tournament-generate-schedule">Preview schedule</button>`
                  : `<p class="hf-small hf-muted hf-mb-0">${kpis.matchesPlayed ? 'Results are recorded; the schedule is locked.' : 'Schedule already generated.'}</p>
                     <a class="hf-btn hf-btn--sm hf-mt-1" href="${route('schedule', { id: tournamentId })}">Open schedule</a>`
            }
          </div>

          <div class="hf-card">
            <span class="hf-kpi-label">Format</span>
            <h3 class="hf-section-title hf-mt-1">Single elimination</h3>
            <p class="hf-small hf-muted hf-mb-0">${tournament.slots}-team bracket. Round-robin is disabled in v1; the create wizard labels it “v2”.</p>
          </div>
        </aside>
      </div>
    `;

    attach();
  }

  function attach() {
    const bracketButton = root.querySelector('[data-generate-bracket]');
    if (bracketButton) {
      bracketButton.addEventListener('click', async () => {
        const ok = await ui.confirm({
          title: 'Generate bracket?',
          message: 'The approved teams will be seeded into the bracket and the seeds will lock.',
          confirmLabel: 'Generate bracket',
        });
        if (!ok) return;
        ui.setBusy(bracketButton, true, 'Seeding...');
        window.setTimeout(() => {
          try {
            const result = store.actions.generateBracket(tournamentId);
            ui.toast(`${result.matches} matches created with ${result.byes} byes.`, {
              tone: 'success',
              title: 'Bracket generated',
            });
            draw();
          } catch (error) {
            ui.setBusy(bracketButton, false);
            ui.toast(error.message, { tone: 'danger', title: 'Could not generate bracket' });
          }
        }, 450);
      });
    }

    const scheduleButton = root.querySelector('[data-generate-schedule]');
    if (scheduleButton) {
      scheduleButton.addEventListener('click', () => {
        let plan;
        try {
          plan = store.planSchedule(tournamentId);
        } catch (error) {
          ui.toast(error.message, { tone: 'danger', title: 'Could not plan schedule' });
          return;
        }
        const rows = plan.assignments
          .map((assignment) => {
            const match = store.select.match(assignment.matchId);
            const court = plan.courts.find((item) => item.id === assignment.courtId);
            const team1 = match.team1Id ? store.select.team(match.team1Id)?.shortName || 'TBD' : 'TBD';
            const team2 = match.team2Id ? store.select.team(match.team2Id)?.shortName || 'TBD' : 'TBD';
            return `<tr><td>${escapeHtml(match.roundLabel)}</td><td>${escapeHtml(team1)} vs ${escapeHtml(team2)}</td><td>${escapeHtml(court?.name || '')}</td><td>${escapeHtml(formatDate(assignment.scheduledAt))} · ${escapeHtml(formatTime(assignment.scheduledAt))}</td></tr>`;
          })
          .join('');
        ui.dialog({
          title: 'Schedule preview',
          size: 'wide',
          body: `
            <p class="hf-small hf-muted">${plan.assignments.length} matches across ${plan.courts.length} courts. No court double-bookings; every team has at least ${plan.restMinutes} minutes of rest.</p>
            <div class="hf-table-wrap"><table class="hf-table"><thead><tr><th>Round</th><th>Match</th><th>Court</th><th>Slot</th></tr></thead><tbody>${rows}</tbody></table></div>
          `,
          footer: `<button type="button" class="hf-btn" data-cancel-schedule data-testid="schedule-preview-cancel">Cancel</button><button type="button" class="hf-btn hf-btn--primary" data-publish-schedule data-testid="publish-schedule">Publish schedule</button>`,
          onMount(element, close) {
            element.querySelector('[data-cancel-schedule]').addEventListener('click', close);
            element.querySelector('[data-publish-schedule]').addEventListener('click', () => {
              try {
                store.actions.generateSchedule(tournamentId);
                close();
                ui.toast('Court schedule published.', { tone: 'success', title: 'Schedule ready' });
                draw();
              } catch (error) {
                ui.toast(error.message, { tone: 'danger', title: 'Could not publish schedule' });
              }
            });
          },
        });
      });
    }
  }

  draw();
}
```

- [ ] **Step 3: Check the module parses**

```powershell
cd prototype
node --check assets/js/pages/tournament-show.js
```

Expected: no output.

- [ ] **Step 4: Run the CDO generation demo**

Open `http://127.0.0.1:8001/tournaments/show.html?id=tournament-3`. Expected: draft hero, 8 approved teams, "Generate bracket" button. Generate the bracket, then preview and publish the schedule. Confirm the schedule dialog lists 7 matches and the schedule button changes to "Open schedule".

- [ ] **Step 5: Commit**

```powershell
git add prototype/tournaments/show.html prototype/assets/js/pages/tournament-show.js
git commit -m "feat(prototype): add tournament desk with live bracket and schedule generation"
```

## Task 13: Tournament creation wizard

**Files:**
- Create: `prototype/assets/js/guards.js`
- Create: `prototype/assets/js/options.js`
- Rewrite: `prototype/tournaments/create.html`
- Create: `prototype/assets/js/pages/tournament-create.js`

**Interfaces:**
- Consumes: `route`, `escapeHtml`, `formatDate`, `formatMoney`, `ui.setFieldError/clearFieldError/setBusy/toast`, `store.actions.createTournament`.
- Produces: `guardRole({ store, ui, root, roles, message }) -> boolean` from `guards.js`; `DIVISIONS`, `PAYMENT_METHODS`, `ANNOUNCEMENT_CATEGORIES`, `ANNOUNCEMENT_AUDIENCES` from `options.js`; a three-step creation wizard that publishes a registration-open tournament and redirects to its desk.

- [ ] **Step 1: Add the shared role guard and option lists**

Create `prototype/assets/js/guards.js`:

```js
import { route } from './routes.js';

const ROLE_LABELS = {
  organizer: 'organizer',
  scorekeeper: 'scorekeeper',
  coach: 'coach',
  fan: 'fan',
};

export function guardRole({ store, ui, root, roles, message = '' }) {
  const role = store.getRole();
  if (roles.includes(role)) return true;
  const detail =
    message || `The ${ROLE_LABELS[role] || role} demo role cannot open this page.`;
  root.innerHTML = ui.emptyState({
    icon: 'lock',
    title: 'Restricted area',
    message: detail,
    action: `<a class="hf-btn hf-btn--primary" href="${route('dashboard')}" data-testid="restricted-back">Back to dashboard</a>`,
  });
  ui.toast(detail, { tone: 'warning', title: 'Restricted area' });
  window.setTimeout(() => {
    window.location.href = route('dashboard');
  }, 1200);
  return false;
}
```

Create `prototype/assets/js/options.js`:

```js
export const DIVISIONS = [
  'Under 12 (U12)',
  'Under 14 (U14)',
  'Under 15 (U15)',
  'Under 17 (U17)',
  'Open',
];

export const PAYMENT_METHODS = ['GCash', 'Maya', 'Bank transfer', 'Cash'];

export const ANNOUNCEMENT_CATEGORIES = [
  { value: 'schedule', label: 'Schedule' },
  { value: 'venue', label: 'Venue' },
  { value: 'eligibility', label: 'Eligibility' },
  { value: 'general', label: 'General' },
];

export const ANNOUNCEMENT_AUDIENCES = [
  { value: 'all', label: 'Everyone' },
  { value: 'coaches', label: 'Coaches' },
  { value: 'organizers', label: 'Organizers' },
];
```

- [ ] **Step 2: Rewrite the creation HTML skeleton**

Replace `prototype/tournaments/create.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create tournament — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="tournament-create" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading tournament wizard...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 3: Implement the wizard**

Create `prototype/assets/js/pages/tournament-create.js`:

```js
import { route } from '../routes.js';
import { escapeHtml, formatDate, formatMoney } from '../ui.js';
import { guardRole } from '../guards.js';
import { DIVISIONS } from '../options.js';

const STEPS = ['Basics', 'Format & courts', 'Review'];

const STEP_FOR_FIELD = {
  name: 0,
  division: 0,
  venue: 0,
  startDate: 0,
  endDate: 0,
  description: 0,
  format: 1,
  slots: 1,
  courts: 1,
  slotMinutes: 1,
  restMinutes: 1,
  fee: 1,
};

export function render({ store, ui, components, root }) {
  if (!guardRole({ store, ui, root, roles: ['organizer'] })) return;

  let step = 0;
  let courtIndex = 2;

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Organizer desk · Setup',
      title: 'Create a tournament',
      description: 'Name the competition, choose the court plan, then publish a registration page in three steps.',
      actions: `<a class="hf-btn" href="${route('tournaments')}" data-testid="create-cancel"><i class="bi bi-arrow-left"></i> Tournaments</a>`,
    })}

    <ol class="hf-stepper" data-testid="create-stepper">
      ${STEPS.map(
        (label, index) => `
          <li class="hf-step ${index === 0 ? 'is-active' : ''}"
              data-step-indicator="${index}" data-testid="create-step-${index + 1}">
            <span>${index + 1}</span>${escapeHtml(label)}
          </li>
        `
      ).join('')}
    </ol>

    <form id="createForm" class="hf-card" novalidate data-testid="create-form">
      <section data-step-panel="0">
        <div class="hf-form-grid">
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="createName">Tournament name</label>
            <input class="hf-input" id="createName" name="name" value="HoopFlow Metro Invitational"
                   placeholder="HoopFlow Summer Cup" data-testid="create-name">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="createDivision">Division</label>
            <select class="hf-select" id="createDivision" name="division" data-testid="create-division">
              ${DIVISIONS.map((division) => `<option value="${escapeHtml(division)}">${escapeHtml(division)}</option>`).join('')}
            </select>
          </div>
          <div class="hf-field">
            <label class="hf-label" for="createVenue">Venue</label>
            <input class="hf-input" id="createVenue" name="venue" value="San Antonio Central Sports Complex"
                   placeholder="Ynares Sports Arena" data-testid="create-venue">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="createStart">Start date</label>
            <input class="hf-input" id="createStart" name="startDate" type="date" value="2026-08-01"
                   data-testid="create-start-date">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="createEnd">End date</label>
            <input class="hf-input" id="createEnd" name="endDate" type="date" value="2026-08-08"
                   data-testid="create-end-date">
          </div>
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="createDescription">Description</label>
            <textarea class="hf-textarea" id="createDescription" name="description" rows="3"
                      data-testid="create-description">Pre-season invitational for barangay youth teams, with accredited referees and a single-elimination bracket.</textarea>
          </div>
        </div>
      </section>

      <section data-step-panel="1" hidden>
        <div class="hf-form-grid">
          <div class="hf-field hf-field--full">
            <span class="hf-label">Format</span>
            <div class="hf-grid hf-grid--2">
              <label class="hf-card hf-card--flat hf-flex hf-items-center hf-gap-2">
                <input type="radio" name="format" value="single-elimination" checked data-testid="create-format-elimination">
                <span><strong>Single elimination</strong><span class="hf-small hf-muted" style="display:block">One loss ends the run. Winner advances automatically.</span></span>
              </label>
              <label class="hf-card hf-card--flat hf-flex hf-items-center hf-gap-2" aria-disabled="true">
                <input type="radio" name="format" value="round-robin" disabled data-testid="create-format-round-robin">
                <span><strong>Round robin <span class="hf-chip hf-chip--slate">v2</span></strong><span class="hf-small hf-muted" style="display:block">Scheduled for a later release. Not available in this demo.</span></span>
              </label>
            </div>
          </div>

          <div class="hf-field">
            <label class="hf-label" for="createSlots">Bracket slots</label>
            <select class="hf-select" id="createSlots" name="slots" data-testid="create-slots">
              <option value="8">8 teams</option>
              <option value="16" selected>16 teams</option>
            </select>
          </div>
          <div class="hf-field">
            <label class="hf-label" for="createFee">Entry fee (PHP)</label>
            <input class="hf-input" id="createFee" name="fee" type="number" min="0" step="100" value="1500"
                   data-testid="create-fee">
          </div>

          <div class="hf-field hf-field--full">
            <span class="hf-label">Courts</span>
            <div data-court-list></div>
            <button type="button" class="hf-btn hf-btn--sm hf-mt-2" data-add-court data-testid="create-add-court">
              <i class="bi bi-plus-lg"></i> Add court
            </button>
          </div>

          <div class="hf-field">
            <label class="hf-label" for="createSlotMinutes">Slot length (minutes)</label>
            <input class="hf-input" id="createSlotMinutes" name="slotMinutes" type="number" min="10" step="5" value="30"
                   data-testid="create-slot-minutes">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="createRestMinutes">Rest between games (minutes)</label>
            <input class="hf-input" id="createRestMinutes" name="restMinutes" type="number" min="0" step="5" value="15"
                   data-testid="create-rest-minutes">
          </div>
        </div>
      </section>

      <section data-step-panel="2" hidden>
        <div id="createSummary" data-testid="create-summary"></div>
        <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
          Publishing opens registration immediately. You can review teams, collect payments, then generate the bracket and court schedule from the tournament desk.
        </p>
      </section>

      <div class="hf-between hf-mt-4">
        <button type="button" class="hf-btn" data-back data-testid="create-back" hidden>
          <i class="bi bi-arrow-left"></i> Back
        </button>
        <span></span>
        <div class="hf-btn-row">
          <button type="button" class="hf-btn hf-btn--primary" data-next data-testid="create-next">
            Continue <i class="bi bi-arrow-right"></i>
          </button>
          <button type="submit" class="hf-btn hf-btn--primary" data-publish data-testid="create-publish" hidden>
            <i class="bi bi-broadcast"></i> Publish tournament
          </button>
        </div>
      </div>
    </form>
  `;

  const form = root.querySelector('#createForm');
  const back = root.querySelector('[data-back]');
  const next = root.querySelector('[data-next]');
  const publish = root.querySelector('[data-publish]');
  const courtList = root.querySelector('[data-court-list]');
  const summary = root.querySelector('#createSummary');

  const input = (name) => form.querySelector(`[name="${name}"]`);
  const courtInputs = () => [...form.querySelectorAll('[data-court-input]')];

  function addCourt(value = '') {
    const index = courtIndex++;
    courtList.insertAdjacentHTML(
      'beforeend',
      `
        <div class="hf-flex hf-items-center hf-gap-2 hf-mt-1" data-court-row>
          <input class="hf-input" name="court-${index}" data-court-input
                 value="${escapeHtml(value)}" placeholder="Court name"
                 data-testid="create-court-${index}">
          <button type="button" class="hf-icon-btn" data-remove-court
                  aria-label="Remove court" data-testid="create-remove-court-${index}">
            <i class="bi bi-trash3" aria-hidden="true"></i>
          </button>
        </div>
      `
    );
  }

  addCourt('Court 1');
  addCourt('Court 2');

  function showStep(index, { focus = true } = {}) {
    step = Math.max(0, Math.min(STEPS.length - 1, index));
    root.querySelectorAll('[data-step-panel]').forEach((panel) => {
      panel.hidden = Number(panel.dataset.stepPanel) !== step;
    });
    root.querySelectorAll('[data-step-indicator]').forEach((item, position) => {
      item.classList.toggle('is-active', position === step);
      item.classList.toggle('is-done', position < step);
      if (position === step) item.setAttribute('aria-current', 'step');
      else item.removeAttribute('aria-current');
    });
    back.hidden = step === 0;
    next.hidden = step === STEPS.length - 1;
    publish.hidden = step !== STEPS.length - 1;
    if (step === STEPS.length - 1) syncSummary();
    if (focus) {
      const target = root.querySelector(
        `[data-step-panel="${step}"] input, [data-step-panel="${step}"] select, [data-step-panel="${step}"] textarea`
      );
      target?.focus();
    }
  }

  function validateBasics() {
    ['name', 'division', 'venue', 'startDate', 'endDate'].forEach((name) =>
      ui.clearFieldError(input(name))
    );
    let ok = true;
    if (input('name').value.trim().length < 3) {
      ui.setFieldError(input('name'), 'Enter at least 3 characters.');
      ok = false;
    }
    if (!input('division').value) {
      ui.setFieldError(input('division'), 'Choose a division.');
      ok = false;
    }
    if (input('venue').value.trim().length < 3) {
      ui.setFieldError(input('venue'), 'Enter at least 3 characters.');
      ok = false;
    }
    if (!input('startDate').value) {
      ui.setFieldError(input('startDate'), 'Choose a start date.');
      ok = false;
    }
    if (!input('endDate').value) {
      ui.setFieldError(input('endDate'), 'Choose an end date.');
      ok = false;
    }
    if (
      input('startDate').value &&
      input('endDate').value &&
      input('endDate').value < input('startDate').value
    ) {
      ui.setFieldError(input('endDate'), 'End date must be on or after the start date.');
      ok = false;
    }
    return ok;
  }

  function validateFormat() {
    ['slots', 'slotMinutes', 'restMinutes', 'fee'].forEach((name) =>
      ui.clearFieldError(input(name))
    );
    courtInputs().forEach((court) => ui.clearFieldError(court));
    let ok = true;

    const filled = courtInputs().filter((court) => court.value.trim());
    if (!filled.length) {
      ui.setFieldError(courtInputs()[0], 'Add at least one court.');
      ok = false;
    }
    filled.forEach((court) => {
      if (court.value.trim().length < 2) {
        ui.setFieldError(court, 'Court name must be at least 2 characters.');
        ok = false;
      }
    });

    const slots = Number(input('slots').value);
    if (![8, 16].includes(slots)) {
      ui.setFieldError(input('slots'), 'Choose 8 or 16 slots.');
      ok = false;
    }
    const slotMinutes = Number(input('slotMinutes').value);
    if (!Number.isInteger(slotMinutes) || slotMinutes < 10) {
      ui.setFieldError(input('slotMinutes'), 'Use at least 10 minutes.');
      ok = false;
    }
    const restMinutes = Number(input('restMinutes').value);
    if (!Number.isInteger(restMinutes) || restMinutes < 0) {
      ui.setFieldError(input('restMinutes'), 'Rest must be zero or more minutes.');
      ok = false;
    }
    const fee = Number(input('fee').value);
    if (!Number.isFinite(fee) || fee < 0) {
      ui.setFieldError(input('fee'), 'Fee must be zero or more.');
      ok = false;
    }
    return ok;
  }

  function syncSummary() {
    const courts = courtInputs()
      .map((court) => court.value.trim())
      .filter(Boolean);
    const slots = Number(input('slots').value) || 16;
    const firstRound = slots / 2;
    summary.innerHTML = `
      <div class="hf-grid hf-grid--3 hf-mb-3">
        <div class="hf-card hf-card--flat">
          <span class="hf-kpi-label">Competition</span>
          <strong class="hf-section-title hf-mt-1">${escapeHtml(input('name').value || 'Untitled')}</strong>
          <p class="hf-small hf-muted hf-mb-0">${escapeHtml(input('division').value)} · ${escapeHtml(input('venue').value)}</p>
        </div>
        <div class="hf-card hf-card--flat">
          <span class="hf-kpi-label">Window</span>
          <strong class="hf-section-title hf-mt-1">${escapeHtml(formatDate(input('startDate').value))}</strong>
          <p class="hf-small hf-muted hf-mb-0">to ${escapeHtml(formatDate(input('endDate').value))}</p>
        </div>
        <div class="hf-card hf-card--flat">
          <span class="hf-kpi-label">Entry</span>
          <strong class="hf-kpi-value" style="font-size: 30px">${escapeHtml(formatMoney(input('fee').value))}</strong>
          <p class="hf-small hf-muted hf-mb-0">per team</p>
        </div>
      </div>
      <div class="hf-card hf-card--flat">
        <div class="hf-between">
          <span><strong>${slots}-team single elimination</strong><span class="hf-small hf-muted" style="display:block">${firstRound} first-round games, then quarterfinals, semifinals, and the final.</span></span>
          <span class="hf-chip hf-chip--gold">${courts.length} courts</span>
        </div>
        <div class="hf-flex hf-wrap hf-gap-2 hf-mt-2 hf-small hf-muted">
          <span><i class="bi bi-clock"></i> ${escapeHtml(input('slotMinutes').value)}-minute slots</span>
          <span><i class="bi bi-hourglass-split"></i> ${escapeHtml(input('restMinutes').value)}-minute rest</span>
          <span><i class="bi bi-geo-alt"></i> ${escapeHtml(courts.join(' · ') || 'No courts yet')}</span>
        </div>
      </div>
    `;
  }

  function handleError(error) {
    const targetStep = STEP_FOR_FIELD[error.field] ?? 0;
    showStep(targetStep, { focus: false });
    const target =
      error.field === 'courts' ? courtInputs()[0] : input(error.field || '');
    if (target) {
      ui.setFieldError(target, error.message);
      target.focus();
    }
    ui.toast(error.message, { tone: 'danger', title: 'Could not create tournament' });
  }

  back.addEventListener('click', () => showStep(step - 1));

  next.addEventListener('click', () => {
    if (step === 0 && !validateBasics()) return;
    if (step === 1 && !validateFormat()) return;
    showStep(step + 1);
  });

  root.querySelector('[data-add-court]').addEventListener('click', () => {
    addCourt('');
    const inputs = courtInputs();
    inputs[inputs.length - 1].focus();
  });

  courtList.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-court]');
    if (!button) return;
    if (courtList.querySelectorAll('[data-court-row]').length <= 1) {
      ui.toast('Keep at least one court for the schedule.', { tone: 'warning', title: 'Court required' });
      return;
    }
    button.closest('[data-court-row]').remove();
  });

  form.addEventListener('input', () => {
    if (step === 2) syncSummary();
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const basics = validateBasics();
    const format = validateFormat();
    if (!basics) {
      showStep(0);
      return;
    }
    if (!format) {
      showStep(1);
      return;
    }

    const payload = {
      name: input('name').value.trim(),
      division: input('division').value,
      venue: input('venue').value.trim(),
      startDate: input('startDate').value,
      endDate: input('endDate').value,
      description: input('description').value.trim(),
      format: form.querySelector('[name="format"]:checked')?.value || 'single-elimination',
      slots: Number(input('slots').value),
      courts: courtInputs()
        .map((court) => court.value.trim())
        .filter(Boolean),
      slotMinutes: Number(input('slotMinutes').value),
      restMinutes: Number(input('restMinutes').value),
      fee: Number(input('fee').value),
    };

    ui.setBusy(publish, true, 'Publishing...');
    try {
      const id = store.actions.createTournament(payload);
      ui.toast('Registration is open. Share the tournament desk with coaches.', {
        tone: 'success',
        title: 'Tournament created',
      });
      window.setTimeout(() => {
        window.location.href = route('tournamentShow', { id });
      }, 300);
    } catch (error) {
      ui.setBusy(publish, false);
      handleError(error);
    }
  });

  showStep(0, { focus: false });
}
```

- [ ] **Step 4: Check the wizard parses and works**

```powershell
cd prototype
node --check assets/js/pages/tournament-create.js
```

Expected: no output. Open `http://127.0.0.1:8001/tournaments/create.html` as organizer. Expected: prefilled basics, two court rows, a disabled round-robin option labelled v2. Continue to review, publish, and land on the new tournament desk with zero teams and a registration-open status.

- [ ] **Step 5: Commit**

```powershell
git add prototype/assets/js/guards.js prototype/assets/js/options.js prototype/tournaments/create.html prototype/assets/js/pages/tournament-create.js
git commit -m "feat(prototype): add three-step tournament creation wizard"
```

---

## Task 14: Submissions and payments

**Files:**
- Rewrite: `prototype/tournaments/submissions.html`
- Rewrite: `prototype/tournaments/payments.html`
- Create: `prototype/assets/js/pages/submissions.js`
- Create: `prototype/assets/js/pages/payments.js`

**Interfaces:**
- Consumes: `route`, `escapeHtml`, `formatDate`, `formatMoney`, `ui.statusChip/dialog/confirm/toast/setFieldError/clearFieldError/debounce`, `guardRole`, `PAYMENT_METHODS`, `store.actions.reviewTeam/verifyPayment`, `store.select.paymentSummary`.
- Produces: the organizer approval queue with roster detail and reject-reason flow, and the payments ledger with verification modal and live totals.

- [ ] **Step 1: Rewrite the submissions HTML**

Replace `prototype/tournaments/submissions.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submissions — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="submissions" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading submissions...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 2: Implement the submissions renderer**

Create `prototype/assets/js/pages/submissions.js`:

```js
import { route } from '../routes.js';
import { escapeHtml, formatDate } from '../ui.js';
import { guardRole } from '../guards.js';

export function render({ store, ui, components, root }) {
  if (
    !guardRole({
      store,
      ui,
      root,
      roles: ['organizer'],
      message: 'Only the organizer can review team submissions. Switch roles from the Demo menu.',
    })
  )
    return;

  const params = new URLSearchParams(window.location.search);
  let statusFilter = params.get('status') || 'pending';
  let searchTerm = params.get('q') || '';
  let tournamentFilter = params.get('tournament') || 'all';

  const tournaments = store.select.tournaments();

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Organizer desk · Eligibility',
      title: 'Submissions',
      description: 'Review rosters, consent, documents, and payment references before a team enters the bracket.',
      actions: `<a class="hf-btn" href="${route('payments')}" data-testid="submissions-open-payments"><i class="bi bi-credit-card"></i> Payments</a>`,
    })}
    <div id="submissionKpis" class="hf-grid hf-grid--4 hf-mb-3"></div>

    <div class="hf-card hf-mb-3">
      <div class="hf-form-grid">
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="submissionSearch">Search</label>
          <input class="hf-input" id="submissionSearch" type="search" placeholder="Team, coach, or city"
                 value="${escapeHtml(searchTerm)}" data-testid="submission-search">
        </div>
        <div class="hf-field">
          <label class="hf-label" for="submissionStatus">Status</label>
          <select class="hf-select" id="submissionStatus" data-testid="submission-status-filter">
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="all">All statuses</option>
          </select>
        </div>
        <div class="hf-field">
          <label class="hf-label" for="submissionTournament">Tournament</label>
          <select class="hf-select" id="submissionTournament" data-testid="submission-tournament-filter">
            <option value="all">All tournaments</option>
            ${tournaments.map((tournament) => `<option value="${escapeHtml(tournament.id)}">${escapeHtml(tournament.name)}</option>`).join('')}
          </select>
        </div>
      </div>
    </div>

    <div id="submissionList" data-testid="submission-list"></div>
  `;

  root.querySelector('#submissionStatus').value = statusFilter;
  root.querySelector('#submissionTournament').value = tournamentFilter;

  const search = root.querySelector('#submissionSearch');
  const status = root.querySelector('#submissionStatus');
  const tournamentSelect = root.querySelector('#submissionTournament');
  const list = root.querySelector('#submissionList');
  const kpis = root.querySelector('#submissionKpis');

  function allTeams() {
    return store.getState().teams;
  }

  function renderKpis() {
    const teams = allTeams();
    const pending = teams.filter((team) => team.status === 'pending').length;
    const approved = teams.filter((team) => team.status === 'approved').length;
    const rejected = teams.filter((team) => team.status === 'rejected').length;
    const open = tournaments.filter((tournament) => tournament.registrationOpen).length;
    kpis.innerHTML = [
      components.kpiTile({ label: 'Pending review', value: pending, hint: 'Across all tournaments', tone: 'gold', testid: 'submission-kpi-pending' }),
      components.kpiTile({ label: 'Approved', value: approved, hint: 'Eligible for seeding', tone: 'success', testid: 'submission-kpi-approved' }),
      components.kpiTile({ label: 'Rejected', value: rejected, hint: 'Archived from the bracket', tone: 'alert', testid: 'submission-kpi-rejected' }),
      components.kpiTile({ label: 'Open registration', value: open, hint: 'Tournaments accepting teams', tone: 'primary', testid: 'submission-kpi-open' }),
    ].join('');
  }

  function eligibilityChips(team) {
    const items = [];
    items.push(
      team.eligibility.documentsComplete
        ? ui.statusChip('verified', 'Documents')
        : ui.statusChip('missing', 'Documents')
    );
    items.push(
      team.eligibility.consentComplete
        ? ui.statusChip('verified', 'Consent')
        : ui.statusChip('missing', 'Consent')
    );
    return items.join(' ');
  }

  function teamRow(team) {
    const tournament = store.select.tournament(team.tournamentId);
    return `
      <article class="hf-card hf-card--flat" data-testid="submission-row-${escapeHtml(team.id)}">
        <div class="hf-between">
          <span class="hf-flex hf-items-center hf-gap-2">
            ${components.shield(team, 'lg')}
            <span>
              <strong>${escapeHtml(team.name)}</strong>
              <span class="hf-small hf-muted" style="display:block">
                ${escapeHtml(team.coach)} · ${escapeHtml(team.city)} · ${escapeHtml(tournament?.name || 'Tournament')}
              </span>
            </span>
          </span>
          <span class="hf-flex hf-wrap hf-gap-1">
            ${ui.statusChip(team.status)}
            ${ui.statusChip(team.payment.status)}
          </span>
        </div>
        <div class="hf-flex hf-wrap hf-gap-2 hf-mt-2 hf-small">
          <span class="hf-chip hf-chip--slate">${team.players.length} players</span>
          ${eligibilityChips(team)}
          <span class="hf-chip hf-chip--slate">${escapeHtml(tournament?.division || '')}</span>
          <span class="hf-chip hf-chip--slate">Submitted ${escapeHtml(formatDate(team.submittedAt))}</span>
        </div>
        ${team.rejectionReason ? `<p class="hf-small hf-muted hf-mt-1 hf-mb-0">Reason: ${escapeHtml(team.rejectionReason)}</p>` : ''}
        <div class="hf-btn-row hf-mt-2">
          <button type="button" class="hf-btn hf-btn--sm" data-review-team="${escapeHtml(team.id)}"
                  data-testid="submission-open-${escapeHtml(team.id)}">Review detail</button>
          ${team.status === 'pending' ? `
            <button type="button" class="hf-btn hf-btn--sm hf-btn--primary" data-approve-team="${escapeHtml(team.id)}"
                    data-testid="submission-approve-${escapeHtml(team.id)}">Approve</button>
            <button type="button" class="hf-btn hf-btn--sm hf-btn--danger" data-reject-team="${escapeHtml(team.id)}"
                    data-testid="submission-reject-${escapeHtml(team.id)}">Reject</button>
          ` : ''}
        </div>
      </article>
    `;
  }

  function renderList() {
    const term = search.value.trim().toLowerCase();
    const filtered = allTeams()
      .filter((team) => {
        const tournament = store.select.tournament(team.tournamentId);
        const haystack = `${team.name} ${team.coach} ${team.city} ${tournament?.name || ''}`.toLowerCase();
        return (
          (status.value === 'all' || team.status === status.value) &&
          (tournamentSelect.value === 'all' || team.tournamentId === tournamentSelect.value) &&
          (!term || haystack.includes(term))
        );
      })
      .sort((a, b) => String(b.submittedAt).localeCompare(String(a.submittedAt)));

    list.innerHTML = filtered.length
      ? `<div class="hf-grid">${filtered.map(teamRow).join('')}</div>`
      : ui.emptyState({
          icon: 'clipboard-check',
          title: 'No submissions match',
          message: 'Try another status, tournament, or search term.',
          action: `<button type="button" class="hf-btn" data-clear-submission-filters data-testid="submission-clear-filters">Clear filters</button>`,
        });

    list.querySelector('[data-clear-submission-filters]')?.addEventListener('click', () => {
      search.value = '';
      status.value = 'pending';
      tournamentSelect.value = 'all';
      renderList();
    });

    list.querySelectorAll('[data-review-team]').forEach((button) => {
      button.addEventListener('click', () => openDetail(button.dataset.reviewTeam));
    });
    list.querySelectorAll('[data-approve-team]').forEach((button) => {
      button.addEventListener('click', () => approve(button.dataset.approveTeam));
    });
    list.querySelectorAll('[data-reject-team]').forEach((button) => {
      button.addEventListener('click', () => openReject(button.dataset.rejectTeam));
    });
  }

  function rosterTable(team) {
    return `
      <div class="hf-table-wrap">
        <table class="hf-table">
          <thead>
            <tr><th scope="col">#</th><th scope="col">Player</th><th scope="col">Position</th><th scope="col">Age</th><th scope="col">Consent</th><th scope="col">Documents</th></tr>
          </thead>
          <tbody>
            ${team.players
              .map(
                (player) => `
                  <tr>
                    <td>${player.number}</td>
                    <td>${escapeHtml(player.name)}</td>
                    <td>${escapeHtml(player.position)}</td>
                    <td>${player.age}</td>
                    <td>${player.guardianConsent ? '<span class="hf-chip hf-chip--success">On file</span>' : '<span class="hf-chip hf-chip--danger">Missing</span>'}</td>
                    <td>${ui.statusChip(player.documentStatus)}</td>
                  </tr>
                `
              )
              .join('')}
          </tbody>
        </table>
      </div>
    `;
  }

  function openDetail(teamId) {
    const team = store.select.team(teamId);
    if (!team) return;
    const tournament = store.select.tournament(team.tournamentId);
    ui.dialog({
      title: team.name,
      size: 'wide',
      body: `
        <div class="hf-flex hf-items-center hf-gap-2 hf-mb-2">
          ${components.shield(team, 'lg')}
          <div>
            <strong>${escapeHtml(team.coach)} · ${escapeHtml(team.city)}</strong>
            <span class="hf-small hf-muted" style="display:block">${escapeHtml(tournament?.name || '')} · ${escapeHtml(tournament?.division || '')}</span>
          </div>
          <span class="hf-ml-auto hf-flex hf-gap-1">${ui.statusChip(team.status)}${ui.statusChip(team.payment.status)}</span>
        </div>

        <div class="hf-grid hf-grid--2 hf-mb-3">
          <div class="hf-card hf-card--flat">
            <span class="hf-kpi-label">Eligibility</span>
            <div class="hf-flex hf-wrap hf-gap-1 hf-mt-1">${eligibilityChips(team)}</div>
            <p class="hf-small hf-muted hf-mt-1 hf-mb-0">${team.eligibility.verifiedAt ? `Verified ${escapeHtml(formatDate(team.eligibility.verifiedAt))}` : 'Verification is recorded when you approve the team.'}</p>
          </div>
          <div class="hf-card hf-card--flat">
            <span class="hf-kpi-label">Payment reference</span>
            <p class="hf-mb-0"><strong>${escapeHtml(team.payment.method || 'No method')}</strong> · ${escapeHtml(team.payment.ref || 'No reference')}</p>
            <p class="hf-small hf-muted hf-mb-0">Amount ${escapeHtml(String(team.payment.amount || 0))} · ${escapeHtml(team.payment.status)}</p>
          </div>
        </div>

        <h3 class="hf-section-title">Roster (${team.players.length})</h3>
        ${rosterTable(team)}
        <p class="hf-small hf-muted hf-mt-2 hf-mb-0">Submitted ${escapeHtml(formatDate(team.submittedAt))}. Approval verifies the team's eligibility snapshot; the roster locks for the coach.</p>
      `,
      footer: `
        <button type="button" class="hf-btn" data-close-detail data-testid="submission-detail-close">Close</button>
        ${team.status === 'pending' ? `
          <button type="button" class="hf-btn hf-btn--danger" data-detail-reject data-testid="submission-detail-reject">Reject</button>
          <button type="button" class="hf-btn hf-btn--primary" data-detail-approve data-testid="submission-detail-approve">Approve team</button>
        ` : ''}
      `,
      onMount(element, close) {
        element.querySelector('[data-close-detail]').addEventListener('click', close);
        element.querySelector('[data-detail-approve]')?.addEventListener('click', () => {
          close();
          approve(teamId);
        });
        element.querySelector('[data-detail-reject]')?.addEventListener('click', () => {
          close();
          openReject(teamId);
        });
      },
    });
  }

  async function approve(teamId) {
    const team = store.select.team(teamId);
    if (!team) return;
    const ok = await ui.confirm({
      title: `Approve ${team.name}?`,
      message: 'The team becomes eligible for seeding and its roster locks for the coach.',
      confirmLabel: 'Approve team',
    });
    if (!ok) return;
    try {
      store.actions.reviewTeam(teamId, 'approve');
      ui.toast(`${team.name} is approved.`, { tone: 'success', title: 'Team approved' });
      renderKpis();
      renderList();
    } catch (error) {
      ui.toast(error.message, { tone: 'danger', title: 'Could not approve team' });
    }
  }

  function openReject(teamId) {
    const team = store.select.team(teamId);
    if (!team) return;
    ui.dialog({
      title: `Reject ${team.name}`,
      body: `
        <p class="hf-small hf-muted">Tell the coach what needs to change. The reason is shown on the coach's status page and written to the audit log.</p>
        <div class="hf-field">
          <label class="hf-label" for="rejectReason">Reason</label>
          <textarea class="hf-textarea" id="rejectReason" rows="4" placeholder="Missing PSA birth certificate for two players."
                    data-testid="submission-reject-reason"></textarea>
        </div>
      `,
      footer: `
        <button type="button" class="hf-btn" data-cancel-reject data-testid="submission-reject-cancel">Cancel</button>
        <button type="button" class="hf-btn hf-btn--danger" data-confirm-reject data-testid="submission-reject-confirm">Reject team</button>
      `,
      onMount(element, close) {
        const reason = element.querySelector('#rejectReason');
        element.querySelector('[data-cancel-reject]').addEventListener('click', close);
        element.querySelector('[data-confirm-reject]').addEventListener('click', () => {
          ui.clearFieldError(reason);
          if (reason.value.trim().length < 5) {
            ui.setFieldError(reason, 'Enter at least 5 characters.');
            reason.focus();
            return;
          }
          try {
            store.actions.reviewTeam(teamId, 'reject', reason.value.trim());
            close();
            ui.toast(`${team.name} was rejected with a reason.`, { tone: 'success', title: 'Team rejected' });
            renderKpis();
            renderList();
          } catch (error) {
            ui.setFieldError(reason, error.message);
            ui.toast(error.message, { tone: 'danger', title: 'Could not reject team' });
          }
        });
      },
    });
  }

  search.addEventListener('input', ui.debounce(renderList, 120));
  status.addEventListener('change', renderList);
  tournamentSelect.addEventListener('change', renderList);

  renderKpis();
  renderList();
}
```

- [ ] **Step 3: Rewrite the payments HTML**

Replace `prototype/tournaments/payments.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payments — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="payments" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading payments...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 4: Implement the payments renderer**

Create `prototype/assets/js/pages/payments.js`:

```js
import { route } from '../routes.js';
import { escapeHtml, formatDate, formatMoney } from '../ui.js';
import { guardRole } from '../guards.js';
import { PAYMENT_METHODS } from '../options.js';

export function render({ store, ui, components, root }) {
  if (!guardRole({ store, ui, root, roles: ['organizer'] })) return;

  const params = new URLSearchParams(window.location.search);
  let statusFilter = params.get('status') || 'pending';
  let searchTerm = params.get('q') || '';
  let tournamentFilter = params.get('tournament') || 'all';

  const tournaments = store.select.tournaments();

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Organizer desk · Finance',
      title: 'Payments',
      description: 'Verify entry fees, track the collection rate, and keep every reference in the audit trail.',
      actions: `<a class="hf-btn" href="${route('submissions')}" data-testid="payments-open-submissions"><i class="bi bi-clipboard-check"></i> Submissions</a>`,
    })}
    <div id="paymentKpis" class="hf-grid hf-grid--4 hf-mb-3"></div>

    <div class="hf-card hf-mb-3">
      <div class="hf-form-grid">
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="paymentSearch">Search</label>
          <input class="hf-input" id="paymentSearch" type="search" placeholder="Team, reference, or coach"
                 value="${escapeHtml(searchTerm)}" data-testid="payment-search">
        </div>
        <div class="hf-field">
          <label class="hf-label" for="paymentStatus">Status</label>
          <select class="hf-select" id="paymentStatus" data-testid="payment-status-filter">
            <option value="pending">Pending</option>
            <option value="partial">Partial</option>
            <option value="paid">Paid</option>
            <option value="unpaid">Pending or partial</option>
            <option value="all">All payments</option>
          </select>
        </div>
        <div class="hf-field">
          <label class="hf-label" for="paymentTournament">Tournament</label>
          <select class="hf-select" id="paymentTournament" data-testid="payment-tournament-filter">
            <option value="all">All tournaments</option>
            ${tournaments.map((tournament) => `<option value="${escapeHtml(tournament.id)}">${escapeHtml(tournament.name)}</option>`).join('')}
          </select>
        </div>
      </div>
    </div>

    <div id="paymentList" data-testid="payment-list"></div>
  `;

  root.querySelector('#paymentStatus').value = statusFilter;
  root.querySelector('#paymentTournament').value = tournamentFilter;

  const search = root.querySelector('#paymentSearch');
  const status = root.querySelector('#paymentStatus');
  const tournamentSelect = root.querySelector('#paymentTournament');
  const list = root.querySelector('#paymentList');
  const kpis = root.querySelector('#paymentKpis');

  function scopedTeams() {
    return store
      .getState()
      .teams.filter(
        (team) =>
          team.status !== 'rejected' &&
          (tournamentSelect.value === 'all' || team.tournamentId === tournamentSelect.value)
      );
  }

  function summaryFor(teams) {
    const billable = teams.filter((team) => team.status !== 'rejected');
    const expected = billable.reduce(
      (sum, team) => sum + (store.select.tournament(team.tournamentId)?.fee || 0),
      0
    );
    const collected = billable
      .filter((team) => team.payment.status === 'paid')
      .reduce((sum, team) => sum + (team.payment.amount || 0), 0);
    return {
      expected,
      collected,
      outstanding: Math.max(0, expected - collected),
      rate: expected ? Math.round((collected / expected) * 100) : 0,
      paidCount: billable.filter((team) => team.payment.status === 'paid').length,
      pendingCount: billable.filter((team) => team.payment.status === 'pending').length,
      partialCount: billable.filter((team) => team.payment.status === 'partial').length,
    };
  }

  function renderKpis() {
    const summary = summaryFor(scopedTeams());
    kpis.innerHTML = [
      components.kpiTile({ label: 'Collected', value: formatMoney(summary.collected), hint: `${summary.paidCount} teams paid`, tone: 'success', testid: 'payment-kpi-collected' }),
      components.kpiTile({ label: 'Outstanding', value: formatMoney(summary.outstanding), hint: `${summary.pendingCount} pending · ${summary.partialCount} partial`, tone: 'alert', testid: 'payment-kpi-outstanding' }),
      components.kpiTile({ label: 'Expected', value: formatMoney(summary.expected), hint: 'Entry fees for billable teams', tone: 'slate', testid: 'payment-kpi-expected' }),
      components.kpiTile({ label: 'Collection rate', value: `${summary.rate}%`, hint: 'Collected against expected', tone: 'primary', testid: 'payment-kpi-rate' }),
    ].join('');
  }

  function matchesStatus(team) {
    if (status.value === 'all') return true;
    if (status.value === 'unpaid') return team.payment.status !== 'paid';
    return team.payment.status === status.value;
  }

  function paymentRow(team) {
    const tournament = store.select.tournament(team.tournamentId);
    return `
      <article class="hf-card hf-card--flat" data-testid="payment-row-${escapeHtml(team.id)}">
        <div class="hf-between">
          <span class="hf-flex hf-items-center hf-gap-2">
            ${components.shield(team)}
            <span>
              <strong>${escapeHtml(team.name)}</strong>
              <span class="hf-small hf-muted" style="display:block">${escapeHtml(tournament?.name || '')} · ${escapeHtml(team.coach)}</span>
            </span>
          </span>
          ${ui.statusChip(team.payment.status)}
        </div>
        <div class="hf-flex hf-wrap hf-gap-3 hf-mt-2 hf-small">
          <span><span class="hf-kpi-label">Method</span><strong style="display:block">${escapeHtml(team.payment.method || '—')}</strong></span>
          <span><span class="hf-kpi-label">Reference</span><strong style="display:block">${escapeHtml(team.payment.ref || '—')}</strong></span>
          <span><span class="hf-kpi-label">Amount</span><strong style="display:block">${escapeHtml(formatMoney(team.payment.amount || tournament?.fee || 0))}</strong></span>
          <span><span class="hf-kpi-label">Verified</span><strong style="display:block">${team.payment.verifiedAt ? escapeHtml(formatDate(team.payment.verifiedAt)) : '—'}</strong></span>
        </div>
        <div class="hf-btn-row hf-mt-2">
          <button type="button" class="hf-btn hf-btn--sm ${team.payment.status === 'paid' ? '' : 'hf-btn--primary'}"
                  data-verify-payment="${escapeHtml(team.id)}"
                  data-testid="payment-verify-${escapeHtml(team.id)}">
            ${team.payment.status === 'paid' ? 'Review record' : 'Verify payment'}
          </button>
          <a class="hf-btn hf-btn--sm" href="${route('teamShow', { id: team.id })}" data-testid="payment-team-${escapeHtml(team.id)}">Team detail</a>
        </div>
      </article>
    `;
  }

  function renderList() {
    const term = search.value.trim().toLowerCase();
    const filtered = scopedTeams()
      .filter((team) => {
        const haystack = `${team.name} ${team.coach} ${team.payment.ref} ${team.payment.method}`.toLowerCase();
        return matchesStatus(team) && (!term || haystack.includes(term));
      })
      .sort((a, b) => {
        const rank = { pending: 0, partial: 1, paid: 2 };
        return (rank[a.payment.status] ?? 3) - (rank[b.payment.status] ?? 3) ||
          a.name.localeCompare(b.name);
      });

    list.innerHTML = filtered.length
      ? `<div class="hf-grid">${filtered.map(paymentRow).join('')}</div>`
      : ui.emptyState({
          icon: 'cash-stack',
          title: 'No payments match',
          message: 'Try another status, tournament, or search term.',
          action: `<button type="button" class="hf-btn" data-clear-payment-filters data-testid="payment-clear-filters">Clear filters</button>`,
        });

    list.querySelector('[data-clear-payment-filters]')?.addEventListener('click', () => {
      search.value = '';
      status.value = 'pending';
      tournamentSelect.value = 'all';
      renderKpis();
      renderList();
    });

    list.querySelectorAll('[data-verify-payment]').forEach((button) => {
      button.addEventListener('click', () => openVerify(button.dataset.verifyPayment));
    });
  }

  function openVerify(teamId) {
    const team = store.select.team(teamId);
    if (!team) return;
    const tournament = store.select.tournament(team.tournamentId);
    ui.dialog({
      title: `Verify payment · ${team.name}`,
      body: `
        <p class="hf-small hf-muted">Entry fee is ${escapeHtml(formatMoney(tournament?.fee || 0))}. A saved verification updates collected totals and writes an audit entry.</p>
        <div class="hf-form-grid">
          <div class="hf-field">
            <label class="hf-label" for="verifyMethod">Method</label>
            <select class="hf-select" id="verifyMethod" data-testid="payment-verify-method">
              ${PAYMENT_METHODS.map((method) => `<option value="${escapeHtml(method)}" ${method === (team.payment.method || 'GCash') ? 'selected' : ''}>${escapeHtml(method)}</option>`).join('')}
            </select>
          </div>
          <div class="hf-field">
            <label class="hf-label" for="verifyStatus">Status</label>
            <select class="hf-select" id="verifyStatus" data-testid="payment-verify-status">
              <option value="paid" ${team.payment.status !== 'partial' ? 'selected' : ''}>Paid in full</option>
              <option value="partial" ${team.payment.status === 'partial' ? 'selected' : ''}>Partial payment</option>
            </select>
          </div>
          <div class="hf-field">
            <label class="hf-label" for="verifyRef">Reference</label>
            <input class="hf-input" id="verifyRef" value="${escapeHtml(team.payment.ref || '')}"
                   placeholder="GCASH-9283746" data-testid="payment-verify-ref">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="verifyAmount">Amount (PHP)</label>
            <input class="hf-input" id="verifyAmount" type="number" min="1" step="100"
                   value="${team.payment.amount || tournament?.fee || 1500}" data-testid="payment-verify-amount">
          </div>
        </div>
      `,
      footer: `
        <button type="button" class="hf-btn" data-cancel-verify data-testid="payment-verify-cancel">Cancel</button>
        <button type="button" class="hf-btn hf-btn--primary" data-save-verify data-testid="payment-verify-save">Save verification</button>
      `,
      onMount(element, close) {
        const method = element.querySelector('#verifyMethod');
        const state = element.querySelector('#verifyStatus');
        const ref = element.querySelector('#verifyRef');
        const amount = element.querySelector('#verifyAmount');

        element.querySelector('[data-cancel-verify]').addEventListener('click', close);
        element.querySelector('[data-save-verify]').addEventListener('click', () => {
          [method, ref, amount].forEach((field) => ui.clearFieldError(field));
          let valid = true;
          if (!ref.value.trim()) {
            ui.setFieldError(ref, 'Enter the payment reference.');
            valid = false;
          }
          if (!Number.isFinite(Number(amount.value)) || Number(amount.value) <= 0) {
            ui.setFieldError(amount, 'Amount must be greater than zero.');
            valid = false;
          }
          if (!valid) return;

          try {
            store.actions.verifyPayment(teamId, {
              method: method.value,
              ref: ref.value.trim(),
              amount: Number(amount.value),
              status: state.value,
            });
            close();
            ui.toast(`${team.name} payment recorded.`, { tone: 'success', title: 'Payment verified' });
            renderKpis();
            renderList();
          } catch (error) {
            const map = {
              'payment.method': method,
              'payment.ref': ref,
              'payment.amount': amount,
            };
            const target = map[error.field];
            if (target) ui.setFieldError(target, error.message);
            ui.toast(error.message, { tone: 'danger', title: 'Could not verify payment' });
          }
        });
      },
    });
  }

  search.addEventListener('input', ui.debounce(renderList, 120));
  status.addEventListener('change', () => {
    renderKpis();
    renderList();
  });
  tournamentSelect.addEventListener('change', () => {
    renderKpis();
    renderList();
  });

  renderKpis();
  renderList();
}
```

- [ ] **Step 5: Verify the organizer queue and ledger**

Open `http://127.0.0.1:8001/tournaments/submissions.html?status=pending` as organizer. Expected: two pending teams from Pasig Youth Hoopfest (Bagong Ilog Bears and Kalawaan Kestrels), each with missing-document and consent chips. Open a detail dialog, approve one, and confirm the pending KPI drops from 2 to 1 without a reload. Then open `http://127.0.0.1:8001/tournaments/payments.html?status=pending`; expected: collected `₱19,500` of `₱22,500` for the active cup when the tournament filter is HoopFlow Summer Cup. Verify Maybunga Titans with a GCash reference and confirm collected rises to `₱21,000`.

- [ ] **Step 6: Commit**

```powershell
git add prototype/tournaments/submissions.html prototype/tournaments/payments.html prototype/assets/js/pages/submissions.js prototype/assets/js/pages/payments.js
git commit -m "feat(prototype): add organizer submissions and payments desks"
```

## Task 15: Teams directory, team detail, and coach registration

**Files:**
- Rewrite: `prototype/teams/index.html`
- Rewrite: `prototype/teams/show.html`
- Create: `prototype/teams/register.html`
- Create: `prototype/assets/js/pages/teams.js`
- Create: `prototype/assets/js/pages/team-show.js`
- Create: `prototype/assets/js/pages/team-register.js`

**Interfaces:**
- Consumes: `route`, `escapeHtml`, `formatDate`, `formatMoney`, `ui.*`, `guardRole`, `POSITIONS` from `seed.js`, `store.actions.registerTeam`, `store.select.teamRecord/teamMatches/nextMatch/coachTeams/bracket`.
- Produces: a searchable teams directory, a privacy-aware team profile, and `teams/register.html?tournament=…` with a four-step team → roster → eligibility/payment → review flow and a deliberate “Fill demo data” shortcut.

- [ ] **Step 1: Rewrite the teams index HTML**

Replace `prototype/teams/index.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Teams — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="teams" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading teams...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 2: Implement the teams directory**

Create `prototype/assets/js/pages/teams.js`:

```js
import { route } from '../routes.js';
import { escapeHtml } from '../ui.js';

export function render({ store, ui, components, root }) {
  const role = store.getRole();
  const params = new URLSearchParams(window.location.search);
  let statusFilter = params.get('status') || 'all';
  let divisionFilter = params.get('division') || 'all';
  let searchTerm = params.get('q') || '';

  const tournaments = store.select.tournaments();
  const divisions = [...new Set(tournaments.map((tournament) => tournament.division))].sort();
  const openTournament = tournaments.find((tournament) => tournament.registrationOpen);

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Team directory',
      title: 'Teams',
      description:
        role === 'fan'
          ? 'Public team cards show records, coaches, and cities. Payment and eligibility details stay private.'
          : 'Every registered team, scoped to its tournament, with record, roster size, and review state.',
      actions:
        role === 'organizer'
          ? `<a class="hf-btn hf-btn--primary" href="${route('submissions')}" data-testid="teams-review-queue"><i class="bi bi-clipboard-check"></i> Review queue</a>`
          : role === 'coach'
            ? openTournament
              ? `<a class="hf-btn hf-btn--primary" href="${route('teamRegister', { tournament: openTournament.id })}" data-testid="teams-register"><i class="bi bi-plus-lg"></i> Register team</a>`
              : ''
            : `<a class="hf-btn" href="${route('dashboard')}">Back to dashboard</a>`,
    })}

    <div id="teamKpis" class="hf-grid hf-grid--4 hf-mb-3"></div>

    <div class="hf-card hf-mb-3">
      <div class="hf-form-grid">
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="teamSearch">Search</label>
          <input class="hf-input" id="teamSearch" type="search" placeholder="Team, coach, or city"
                 value="${escapeHtml(searchTerm)}" data-testid="team-search">
        </div>
        <div class="hf-field">
          <label class="hf-label" for="teamStatus">Status</label>
          <select class="hf-select" id="teamStatus" data-testid="team-status-filter">
            <option value="all">All statuses</option>
            <option value="approved">Approved</option>
            <option value="pending">Pending</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
        <div class="hf-field">
          <label class="hf-label" for="teamDivision">Division</label>
          <select class="hf-select" id="teamDivision" data-testid="team-division-filter">
            <option value="all">All divisions</option>
            ${divisions.map((division) => `<option value="${escapeHtml(division)}">${escapeHtml(division)}</option>`).join('')}
          </select>
        </div>
      </div>
    </div>

    <div id="teamList" data-testid="team-list"></div>
  `;

  root.querySelector('#teamStatus').value = statusFilter;
  root.querySelector('#teamDivision').value = divisionFilter;

  const search = root.querySelector('#teamSearch');
  const status = root.querySelector('#teamStatus');
  const division = root.querySelector('#teamDivision');
  const list = root.querySelector('#teamList');
  const kpis = root.querySelector('#teamKpis');

  const coachTeams = role === 'coach' ? store.select.coachTeams() : [];
  const ownsTeam = (team) => coachTeams.some((item) => item.id === team.id);
  const canSeePrivate = (team) =>
    role === 'organizer' || (role === 'coach' && ownsTeam(team));

  function renderKpis() {
    const teams = store.getState().teams;
    kpis.innerHTML = [
      components.kpiTile({ label: 'Registered', value: teams.length, hint: `${tournaments.length} tournaments`, tone: 'primary', testid: 'team-kpi-registered' }),
      components.kpiTile({ label: 'Approved', value: teams.filter((team) => team.status === 'approved').length, hint: 'Eligible for seeding', tone: 'success', testid: 'team-kpi-approved' }),
      components.kpiTile({ label: 'Pending', value: teams.filter((team) => team.status === 'pending').length, hint: 'Waiting for organizer review', tone: 'gold', testid: 'team-kpi-pending' }),
      components.kpiTile({ label: 'Divisions', value: divisions.length, hint: 'U12 through open', tone: 'slate', testid: 'team-kpi-divisions' }),
    ].join('');
  }

  function teamCard(team) {
    const tournament = store.select.tournament(team.tournamentId);
    const record = store.select.teamRecord(team.id);
    const isOwn = ownsTeam(team);
    return `
      <article class="hf-card hf-card--flat" data-testid="team-card-${escapeHtml(team.id)}">
        <div class="hf-between">
          <span class="hf-flex hf-items-center hf-gap-2">
            ${components.shield(team, 'lg')}
            <span>
              <strong>${escapeHtml(team.name)}</strong>
              <span class="hf-small hf-muted" style="display:block">${escapeHtml(team.coach)} · ${escapeHtml(team.city)}</span>
            </span>
          </span>
          <span class="hf-flex hf-wrap hf-gap-1">
            ${ui.statusChip(team.status)}
            ${canSeePrivate(team) ? ui.statusChip(team.payment.status) : ''}
          </span>
        </div>
        <div class="hf-flex hf-wrap hf-gap-3 hf-mt-2 hf-small">
          <span><span class="hf-kpi-label">Tournament</span><strong style="display:block">${escapeHtml(tournament?.name || '')}</strong></span>
          <span><span class="hf-kpi-label">Division</span><strong style="display:block">${escapeHtml(tournament?.division || '')}</strong></span>
          <span><span class="hf-kpi-label">Record</span><strong style="display:block">${record.wins}-${record.losses}</strong></span>
          <span><span class="hf-kpi-label">Roster</span><strong style="display:block">${team.players.length}</strong></span>
        </div>
        ${canSeePrivate(team) ? `<div class="hf-flex hf-wrap hf-gap-1 hf-mt-2">${
          team.eligibility.documentsComplete ? ui.statusChip('verified', 'Documents') : ui.statusChip('missing', 'Documents')
        }${team.eligibility.consentComplete ? ui.statusChip('verified', 'Consent') : ui.statusChip('missing', 'Consent')}</div>` : ''}
        <div class="hf-btn-row hf-mt-2">
          <a class="hf-btn hf-btn--sm" href="${route('teamShow', { id: team.id })}" data-testid="team-open-${escapeHtml(team.id)}">Team profile</a>
          ${isOwn ? `<a class="hf-btn hf-btn--sm hf-btn--primary" href="${route('myTeam', { team: team.id })}" data-testid="team-manage-${escapeHtml(team.id)}">Manage roster</a>` : ''}
        </div>
      </article>
    `;
  }

  function renderList() {
    const term = search.value.trim().toLowerCase();
    const filtered = store
      .getState()
      .teams.filter((team) => {
        const tournament = store.select.tournament(team.tournamentId);
        const haystack = `${team.name} ${team.coach} ${team.city} ${tournament?.name || ''}`.toLowerCase();
        return (
          (status.value === 'all' || team.status === status.value) &&
          (division.value === 'all' || tournament?.division === division.value) &&
          (!term || haystack.includes(term))
        );
      })
      .sort((a, b) => {
        const rank = { pending: 0, approved: 1, rejected: 2 };
        return (rank[a.status] ?? 3) - (rank[b.status] ?? 3) || a.name.localeCompare(b.name);
      });

    list.innerHTML = filtered.length
      ? `<div class="hf-grid hf-grid--2">${filtered.map(teamCard).join('')}</div>`
      : ui.emptyState({
          icon: 'people',
          title: 'No teams match',
          message: 'Try another status, division, or search term.',
          action: `<button type="button" class="hf-btn" data-clear-team-filters data-testid="team-clear-filters">Clear filters</button>`,
        });

    list.querySelector('[data-clear-team-filters]')?.addEventListener('click', () => {
      search.value = '';
      status.value = 'all';
      division.value = 'all';
      renderList();
    });
  }

  search.addEventListener('input', ui.debounce(renderList, 120));
  status.addEventListener('change', renderList);
  division.addEventListener('change', renderList);

  renderKpis();
  renderList();
}
```

- [ ] **Step 3: Rewrite the team detail HTML**

Replace `prototype/teams/show.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Team — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="team-show" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading team...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 4: Implement the team detail renderer**

Create `prototype/assets/js/pages/team-show.js`:

```js
import { route } from '../routes.js';
import { escapeHtml, formatDate, formatMoney, formatTime } from '../ui.js';

export function render({ store, ui, components, root }) {
  const params = new URLSearchParams(window.location.search);
  const teamId = params.get('id') || '';
  const team = store.select.team(teamId);

  if (!team) {
    root.innerHTML = ui.emptyState({
      icon: 'people',
      title: 'Team not found',
      message: 'The team link is invalid or the demo has been reset.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('teams')}">Back to teams</a>`,
    });
    return;
  }

  const role = store.getRole();
  const tournament = store.select.tournament(team.tournamentId);
  const isOwner = role === 'coach' && store.select.coachTeams().some((item) => item.id === team.id);
  const canSeePrivate = role === 'organizer' || isOwner;
  const record = store.select.teamRecord(team.id);
  const matches = store.select.teamMatches(team.id);
  const played = matches.filter((match) => match.status === 'final' || match.status === 'forfeit');
  const next = store.select.nextMatch(team.id);
  const bracket = store.select.bracket(team.tournamentId);
  const bracketMatch = bracket.rounds
    .flatMap((round) => round.matches)
    .find((match) => match.team1Id === team.id || match.team2Id === team.id);

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: `${tournament?.division || 'Division'} · ${tournament?.name || 'Tournament'}`,
      title: team.name,
      description: `${team.coach} · ${team.city}`,
      actions: `
        <a class="hf-btn" href="${route('teams')}" data-testid="team-back"><i class="bi bi-arrow-left"></i> Teams</a>
        ${isOwner ? `<a class="hf-btn hf-btn--primary" href="${route('myTeam', { team: team.id })}" data-testid="team-manage-roster"><i class="bi bi-person-lines-fill"></i> Manage roster</a>` : ''}
      `,
    })}

    <section class="hf-hero hf-mb-3" data-testid="team-hero-${escapeHtml(team.id)}">
      <div class="hf-flex hf-items-center hf-gap-3">
        ${components.shield(team, 'lg')}
        <div>
          <span class="hf-flex hf-wrap hf-gap-1">
            ${ui.statusChip(team.status)}
            ${canSeePrivate ? ui.statusChip(team.payment.status) : ''}
            ${team.seed ? `<span class="hf-chip hf-chip--slate">Seed ${team.seed}</span>` : ''}
          </span>
          <h1>${escapeHtml(team.name)}</h1>
          <p>${escapeHtml(team.coach)} · ${escapeHtml(team.city)} · ${escapeHtml(tournament?.venue || '')}</p>
        </div>
      </div>
      <div class="hf-stat-strip">
        <div><span>Record</span><strong>${record.wins}-${record.losses}</strong></div>
        <div><span>Points for</span><strong>${record.pointsFor}</strong></div>
        <div><span>Point diff</span><strong>${record.diff > 0 ? '+' : ''}${record.diff}</strong></div>
        <div><span>Roster</span><strong>${team.players.length}</strong></div>
      </div>
    </section>

    <div class="hf-split">
      <div>
        ${components.sectionHeader({ title: `Roster (${team.players.length})` })}
        <div class="hf-card hf-mb-3" data-testid="team-roster">
          ${
            team.players.length
              ? `<div class="hf-table-wrap">
                   <table class="hf-table">
                     <thead>
                       <tr>
                         <th scope="col">#</th>
                         <th scope="col">Player</th>
                         <th scope="col">Position</th>
                         <th scope="col">Age</th>
                         ${canSeePrivate ? '<th scope="col">Consent</th><th scope="col">Documents</th>' : ''}
                       </tr>
                     </thead>
                     <tbody>
                       ${team.players
                         .map(
                           (player) => `
                             <tr>
                               <td>${player.number}</td>
                               <td><strong>${escapeHtml(player.name)}</strong></td>
                               <td>${escapeHtml(player.position)}</td>
                               <td>${player.age}</td>
                               ${canSeePrivate ? `<td>${player.guardianConsent ? '<span class="hf-chip hf-chip--success">On file</span>' : '<span class="hf-chip hf-chip--danger">Missing</span>'}</td><td>${ui.statusChip(player.documentStatus)}</td>` : ''}
                             </tr>
                           `
                         )
                         .join('')}
                     </tbody>
                   </table>
                 </div>`
              : ui.emptyState({ icon: 'person-plus', title: 'No players yet', message: 'The coach has not added a roster.' })
          }
        </div>

        ${components.sectionHeader({ title: 'Results', action: `<a class="hf-section-link" href="${route('matches', { id: team.tournamentId })}">All matchups</a>` })}
        <div class="hf-grid hf-grid--2 hf-mb-3">
          ${played.length
            ? played.map((match) => components.matchCard(match, { compact: true, href: route('gameStats', { match: match.id }) })).join('')
            : ui.emptyState({ icon: 'flag', title: 'No results yet', message: 'Final scores will appear here.' })}
        </div>
      </div>

      <aside>
        <div class="hf-card hf-mb-3">
          <span class="hf-kpi-label">Next game</span>
          ${
            next
              ? `<h3 class="hf-section-title hf-mt-1">${escapeHtml(next.roundLabel)}</h3>
                 <p class="hf-small hf-muted hf-mb-1">${escapeHtml(formatDate(next.scheduledAt))} · ${escapeHtml(formatTime(next.scheduledAt))}</p>
                 <a class="hf-btn hf-btn--sm" href="${route('gameStats', { match: next.id })}" data-testid="team-next-game">Game details</a>`
              : `<p class="hf-small hf-muted hf-mb-0">No scheduled game. The bracket or schedule may still be in progress.</p>`
          }
        </div>

        <div class="hf-card hf-mb-3">
          <span class="hf-kpi-label">Bracket position</span>
          ${
            bracketMatch
              ? `<h3 class="hf-section-title hf-mt-1">${escapeHtml(bracketMatch.roundLabel)} · Slot ${bracketMatch.bracketSlot}</h3>
                 <p class="hf-small hf-muted hf-mb-1">${ui.statusChip(bracketMatch.status)}</p>
                 <a class="hf-btn hf-btn--sm" href="${route('bracket', { id: team.tournamentId, match: bracketMatch.id })}" data-testid="team-bracket-link">Open bracket</a>`
              : `<p class="hf-small hf-muted hf-mb-0">The bracket has not been generated yet.</p>`
          }
        </div>

        ${
          canSeePrivate
            ? `<div class="hf-card hf-mb-3" data-testid="team-private">
                 <span class="hf-kpi-label">Registration</span>
                 <h3 class="hf-section-title hf-mt-1">${escapeHtml(team.status)}</h3>
                 <p class="hf-small hf-muted">${escapeHtml(team.rejectionReason || 'Documents, consent, and payment are tracked by the organizer.')}</p>
                 <div class="hf-flex hf-wrap hf-gap-1 hf-mb-2">
                   ${team.eligibility.documentsComplete ? ui.statusChip('verified', 'Documents') : ui.statusChip('missing', 'Documents')}
                   ${team.eligibility.consentComplete ? ui.statusChip('verified', 'Consent') : ui.statusChip('missing', 'Consent')}
                 </div>
                 <span class="hf-kpi-label">Payment</span>
                 <p class="hf-small hf-mb-0">${escapeHtml(team.payment.method || 'No method')} · ${escapeHtml(team.payment.ref || 'No reference')} · ${escapeHtml(formatMoney(team.payment.amount || 0))}</p>
               </div>`
            : `<div class="hf-card hf-mb-3">
                 <span class="hf-kpi-label">Public profile</span>
                 <p class="hf-small hf-muted hf-mb-0">Payment and eligibility details are visible only to the organizer and the team's coach.</p>
               </div>`
        }

        <div class="hf-card">
          <span class="hf-kpi-label">Tournament</span>
          <h3 class="hf-section-title hf-mt-1">${escapeHtml(tournament?.name || '')}</h3>
          <p class="hf-small hf-muted hf-mb-1">${escapeHtml(tournament?.division || '')} · ${escapeHtml(tournament?.venue || '')}</p>
          <a class="hf-btn hf-btn--sm" href="${route('tournamentShow', { id: team.tournamentId })}" data-testid="team-tournament-link">Tournament desk</a>
        </div>
      </aside>
    </div>
  `;
}
```

- [ ] **Step 5: Create the coach registration HTML**

Create `prototype/teams/register.html`:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register team — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="team-register" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading registration...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 6: Implement the coach registration wizard**

Create `prototype/assets/js/pages/team-register.js`:

```js
import { route } from '../routes.js';
import { escapeHtml, formatDate, formatMoney } from '../ui.js';
import { guardRole } from '../guards.js';
import { POSITIONS } from '../seed.js';

const STEPS = ['Team', 'Roster', 'Eligibility & payment', 'Review'];
const COLORS = [
  { value: '#ea580c', label: 'Orange' },
  { value: '#2563eb', label: 'Blue' },
  { value: '#059669', label: 'Green' },
  { value: '#7c3aed', label: 'Violet' },
  { value: '#dc2626', label: 'Red' },
  { value: '#475569', label: 'Slate' },
];

const DEMO_PLAYERS = [
  ['Jayson Ramos', 4, 14], ['Kobe Cruz', 7, 15], ['Carl Gomez', 10, 14],
  ['Kai Fajardo', 11, 15], ['June Mar Diaz', 13, 14], ['Gabe Santos', 21, 15],
  ['CJ Reyes', 24, 14], ['Dwight Bautista', 30, 15],
];

export function render({ store, ui, components, root }) {
  if (!guardRole({ store, ui, root, roles: ['coach'] })) return;

  const params = new URLSearchParams(window.location.search);
  const state = store.getState();
  const tournamentId =
    params.get('tournament') ||
    state.tournaments.find((tournament) => tournament.registrationOpen)?.id ||
    '';
  const tournament = store.select.tournament(tournamentId);

  if (!tournament) {
    root.innerHTML = ui.emptyState({
      icon: 'trophy',
      title: 'Tournament not found',
      message: 'Choose an open tournament from the directory.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}">Browse tournaments</a>`,
    });
    return;
  }

  if (!tournament.registrationOpen || tournament.status !== 'registration-open') {
    root.innerHTML = ui.emptyState({
      icon: 'lock',
      title: 'Registration is closed',
      message: `${tournament.name} is not accepting new teams.`,
      action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}">Find an open tournament</a>`,
    });
    return;
  }

  const existing = store.select.coachTeams().find((team) => team.tournamentId === tournamentId);
  if (existing) {
    root.innerHTML = ui.emptyState({
      icon: 'check2-circle',
      title: 'Team already registered',
      message: `${existing.name} is already in ${tournament.name} with status “${existing.status}”.`,
      action: `<a class="hf-btn hf-btn--primary" href="${route('myTeam', { team: existing.id })}">Open my team</a>`,
    });
    return;
  }

  const user = store.getCurrentUser();
  const defaultCoach = user.name.replace(/^Coach\s+/i, '');
  const divisionLimit = Number((tournament.division.match(/(\d+)/) || [])[1] || 99);

  let step = 0;
  const roster = Array.from({ length: 8 }, () => ({
    name: '',
    number: '',
    position: POSITIONS[0],
    age: '',
    guardianConsent: false,
    documentStatus: 'pending',
  }));

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: `${tournament.division} · Registration open`,
      title: 'Register a team',
      description: `${tournament.name} · ${tournament.venue} · ${formatDate(tournament.startDate)} to ${formatDate(tournament.endDate)} · ${formatMoney(tournament.fee)} entry.`,
      actions: `<a class="hf-btn" href="${route('tournamentShow', { id: tournamentId })}" data-testid="register-cancel"><i class="bi bi-arrow-left"></i> Tournament</a>`,
    })}

    <ol class="hf-stepper" data-testid="register-stepper">
      ${STEPS.map(
        (label, index) => `
          <li class="hf-step ${index === 0 ? 'is-active' : ''}"
              data-step-indicator="${index}" data-testid="register-step-${index + 1}">
            <span>${index + 1}</span>${escapeHtml(label)}
          </li>
        `
      ).join('')}
    </ol>

    <form id="registerForm" class="hf-card" novalidate data-testid="register-form">
      <section data-step-panel="0">
        <div class="hf-between hf-mb-2">
          <p class="hf-small hf-muted hf-mb-0">Coach account: ${escapeHtml(user.name)} · ${escapeHtml(user.organization || '')}</p>
          <button type="button" class="hf-btn hf-btn--sm" data-fill-demo data-testid="register-fill-demo">
            <i class="bi bi-magic"></i> Fill demo data
          </button>
        </div>
        <div class="hf-form-grid">
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="registerTeamName">Team name</label>
            <input class="hf-input" id="registerTeamName" name="name" placeholder="San Antonio Vipers U17"
                   data-testid="register-team-name">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="registerShortName">Short name (2–3 letters)</label>
            <input class="hf-input" id="registerShortName" name="shortName" maxlength="3" placeholder="VPR"
                   data-testid="register-short-name">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="registerCity">City</label>
            <input class="hf-input" id="registerCity" name="city" placeholder="Pasig City"
                   data-testid="register-city">
          </div>
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="registerCoach">Coach</label>
            <input class="hf-input" id="registerCoach" name="coach" value="${escapeHtml(defaultCoach)}"
                   data-testid="register-coach">
          </div>
          <div class="hf-field hf-field--full">
            <span class="hf-label">Team color</span>
            <div class="hf-flex hf-wrap hf-gap-2">
              ${COLORS.map(
                (color, index) => `
                  <label class="hf-check">
                    <input type="radio" name="avatarColor" value="${color.value}" ${index === 0 ? 'checked' : ''}
                           data-testid="register-color-${index}">
                    <span class="hf-shield" style="--team-color: ${color.value}" aria-hidden="true"></span>
                    ${escapeHtml(color.label)}
                  </label>
                `
              ).join('')}
            </div>
          </div>
        </div>
      </section>

      <section data-step-panel="1" hidden>
        <div class="hf-between hf-mb-2">
          <div>
            <p class="hf-small hf-muted hf-mb-0">A roster needs 8 to 12 players. Jersey numbers must be unique and every player needs guardian consent.</p>
            <p class="hf-small hf-muted hf-mb-0">Age limit for ${escapeHtml(tournament.division)}: 8 to ${divisionLimit}.</p>
          </div>
          <button type="button" class="hf-btn hf-btn--sm" data-add-player data-testid="register-add-player">
            <i class="bi bi-plus-lg"></i> Add player
          </button>
        </div>
        <div id="rosterList" data-testid="register-roster"></div>
      </section>

      <section data-step-panel="2" hidden>
        <div class="hf-grid hf-grid--2">
          <div class="hf-card hf-card--flat">
            <span class="hf-eyebrow">Eligibility checklist</span>
            <ul class="hf-small hf-muted hf-mt-1">
              <li>8–12 players on the roster</li>
              <li>Unique jersey numbers from 1 to 99</li>
              <li>Guardian consent for every player</li>
              <li>PSA birth certificate reviewed by the organizer</li>
            </ul>
          </div>
          <div class="hf-card hf-card--flat">
            <span class="hf-eyebrow">Entry fee</span>
            <strong class="hf-kpi-value" style="font-size: 34px">${escapeHtml(formatMoney(tournament.fee))}</strong>
            <p class="hf-small hf-muted hf-mb-0">Submit a reference now; the organizer verifies the payment before seeding.</p>
          </div>
        </div>
        <div class="hf-form-grid hf-mt-3">
          <div class="hf-field">
            <label class="hf-label" for="registerMethod">Payment method</label>
            <select class="hf-select" id="registerMethod" data-testid="register-payment-method">
              <option value="GCash">GCash</option>
              <option value="Maya">Maya</option>
              <option value="Bank transfer">Bank transfer</option>
              <option value="Cash">Cash</option>
            </select>
          </div>
          <div class="hf-field">
            <label class="hf-label" for="registerRef">Reference</label>
            <input class="hf-input" id="registerRef" placeholder="GCASH-9283746" data-testid="register-payment-ref">
          </div>
        </div>
      </section>

      <section data-step-panel="3" hidden>
        <div id="registerSummary" data-testid="register-summary"></div>
      </section>

      <div class="hf-between hf-mt-4">
        <button type="button" class="hf-btn" data-back data-testid="register-back" hidden>
          <i class="bi bi-arrow-left"></i> Back
        </button>
        <span></span>
        <div class="hf-btn-row">
          <button type="button" class="hf-btn hf-btn--primary" data-next data-testid="register-next">
            Continue <i class="bi bi-arrow-right"></i>
          </button>
          <button type="submit" class="hf-btn hf-btn--primary" data-submit data-testid="register-submit" hidden>
            <i class="bi bi-send"></i> Submit registration
          </button>
        </div>
      </div>
    </form>
  `;

  const form = root.querySelector('#registerForm');
  const back = root.querySelector('[data-back]');
  const next = root.querySelector('[data-next]');
  const submit = root.querySelector('[data-submit]');
  const rosterList = root.querySelector('#rosterList');
  const summary = root.querySelector('#registerSummary');
  const input = (name) => form.querySelector(`[name="${name}"]`);

  function renderRoster() {
    rosterList.innerHTML = roster
      .map(
        (player, index) => `
          <div class="hf-card hf-card--flat hf-mb-2" data-player-row="${index}" data-testid="register-player-${index}">
            <div class="hf-between hf-mb-1">
              <strong class="hf-small">Player ${index + 1}</strong>
              <button type="button" class="hf-btn hf-btn--sm" data-remove-player="${index}"
                      ${roster.length <= 8 ? 'disabled' : ''} data-testid="register-remove-player-${index}">
                Remove
              </button>
            </div>
            <div class="hf-form-grid">
              <div class="hf-field hf-field--full">
                <label class="hf-label" for="playerName${index}">Player name</label>
                <input class="hf-input" id="playerName${index}" data-player-name value="${escapeHtml(player.name)}"
                       placeholder="Full name" data-testid="register-player-name-${index}">
              </div>
              <div class="hf-field">
                <label class="hf-label" for="playerNumber${index}">Jersey number</label>
                <input class="hf-input" id="playerNumber${index}" data-player-number type="number" min="1" max="99"
                       value="${escapeHtml(String(player.number))}" data-testid="register-player-number-${index}">
              </div>
              <div class="hf-field">
                <label class="hf-label" for="playerPosition${index}">Position</label>
                <select class="hf-select" id="playerPosition${index}" data-player-position
                        data-testid="register-player-position-${index}">
                  ${POSITIONS.map((position) => `<option value="${escapeHtml(position)}" ${position === player.position ? 'selected' : ''}>${escapeHtml(position)}</option>`).join('')}
                </select>
              </div>
              <div class="hf-field">
                <label class="hf-label" for="playerAge${index}">Age</label>
                <input class="hf-input" id="playerAge${index}" data-player-age type="number" min="8" max="${divisionLimit}"
                       value="${escapeHtml(String(player.age))}" data-testid="register-player-age-${index}">
              </div>
              <div class="hf-field">
                <label class="hf-label" for="playerDocument${index}">Documents</label>
                <select class="hf-select" id="playerDocument${index}" data-player-document
                        data-testid="register-player-document-${index}">
                  <option value="verified" ${player.documentStatus === 'verified' ? 'selected' : ''}>Verified</option>
                  <option value="pending" ${player.documentStatus === 'pending' ? 'selected' : ''}>Pending review</option>
                </select>
              </div>
              <div class="hf-field hf-field--full">
                <label class="hf-check">
                  <input type="checkbox" data-player-consent ${player.guardianConsent ? 'checked' : ''}
                         data-testid="register-player-consent-${index}">
                  Guardian consent on file
                </label>
              </div>
            </div>
          </div>
        `
      )
      .join('');
  }

  function syncRoster() {
    rosterList.querySelectorAll('[data-player-row]').forEach((row) => {
      const index = Number(row.dataset.playerRow);
      if (!roster[index]) return;
      roster[index].name = row.querySelector('[data-player-name]').value;
      roster[index].number = row.querySelector('[data-player-number]').value;
      roster[index].position = row.querySelector('[data-player-position]').value;
      roster[index].age = row.querySelector('[data-player-age]').value;
      roster[index].guardianConsent = row.querySelector('[data-player-consent]').checked;
      roster[index].documentStatus = row.querySelector('[data-player-document]').value;
    });
  }

  function showStep(index, { focus = true } = {}) {
    step = Math.max(0, Math.min(STEPS.length - 1, index));
    root.querySelectorAll('[data-step-panel]').forEach((panel) => {
      panel.hidden = Number(panel.dataset.stepPanel) !== step;
    });
    root.querySelectorAll('[data-step-indicator]').forEach((item, position) => {
      item.classList.toggle('is-active', position === step);
      item.classList.toggle('is-done', position < step);
      if (position === step) item.setAttribute('aria-current', 'step');
      else item.removeAttribute('aria-current');
    });
    back.hidden = step === 0;
    next.hidden = step === STEPS.length - 1;
    submit.hidden = step !== STEPS.length - 1;
    if (step === STEPS.length - 1) syncSummary();
    if (focus) {
      root.querySelector(`[data-step-panel="${step}"] input, [data-step-panel="${step}"] select`)?.focus();
    }
  }

  function validateTeam() {
    ['name', 'shortName', 'city', 'coach'].forEach((name) => ui.clearFieldError(input(name)));
    let ok = true;
    if (input('name').value.trim().length < 3) {
      ui.setFieldError(input('name'), 'Enter at least 3 characters.');
      ok = false;
    }
    const shortName = input('shortName').value.trim();
    if (shortName.length < 2) {
      ui.setFieldError(input('shortName'), 'Use 2 or 3 letters.');
      ok = false;
    }
    if (input('city').value.trim().length < 2) {
      ui.setFieldError(input('city'), 'Enter a city.');
      ok = false;
    }
    if (input('coach').value.trim().length < 3) {
      ui.setFieldError(input('coach'), 'Enter the coach name.');
      ok = false;
    }
    return ok;
  }

  function validateRoster() {
    syncRoster();
    let ok = true;
    let firstInvalid = null;
    const numbers = new Set();

    roster.forEach((player, index) => {
      const row = rosterList.querySelector(`[data-player-row="${index}"]`);
      const nameInput = row.querySelector('[data-player-name]');
      const numberInput = row.querySelector('[data-player-number]');
      const ageInput = row.querySelector('[data-player-age]');
      const consentInput = row.querySelector('[data-player-consent]');
      [nameInput, numberInput, ageInput, consentInput].forEach((field) => ui.clearFieldError(field));

      if (player.name.trim().length < 2) {
        ui.setFieldError(nameInput, 'Enter the player name.');
        ok = false;
        firstInvalid ||= nameInput;
      }
      const number = Number(player.number);
      if (!Number.isInteger(number) || number < 1 || number > 99) {
        ui.setFieldError(numberInput, 'Use 1–99.');
        ok = false;
        firstInvalid ||= numberInput;
      } else if (numbers.has(number)) {
        ui.setFieldError(numberInput, `Jersey ${number} is already taken.`);
        ok = false;
        firstInvalid ||= numberInput;
      } else {
        numbers.add(number);
      }
      const age = Number(player.age);
      if (!Number.isInteger(age) || age < 8 || age > divisionLimit) {
        ui.setFieldError(ageInput, `Age must be 8–${divisionLimit}.`);
        ok = false;
        firstInvalid ||= ageInput;
      }
      if (!player.guardianConsent) {
        ui.setFieldError(consentInput, 'Guardian consent is required.');
        ok = false;
        firstInvalid ||= consentInput;
      }
    });

    if (roster.length < 8 || roster.length > 12) {
      ui.toast('A roster needs 8 to 12 players.', { tone: 'warning', title: 'Roster size' });
      ok = false;
    }
    if (!ok) {
      showStep(1, { focus: false });
      firstInvalid?.focus();
    }
    return ok;
  }

  function validatePayment() {
    const ref = input('registerRef');
    ui.clearFieldError(ref);
    if (!/^[A-Za-z0-9-]{6,24}$/.test(ref.value.trim())) {
      ui.setFieldError(ref, 'Use 6–24 letters, numbers, or dashes.');
      ref.focus();
      return false;
    }
    return true;
  }

  function syncSummary() {
    syncRoster();
    const players = roster.filter((player) => player.name.trim());
    summary.innerHTML = `
      <div class="hf-grid hf-grid--3 hf-mb-3">
        <div class="hf-card hf-card--flat">
          <span class="hf-kpi-label">Team</span>
          <strong class="hf-section-title hf-mt-1">${escapeHtml(input('name').value || 'Untitled')}</strong>
          <p class="hf-small hf-muted hf-mb-0">${escapeHtml(input('city').value)} · ${escapeHtml(input('coach').value)}</p>
        </div>
        <div class="hf-card hf-card--flat">
          <span class="hf-kpi-label">Roster</span>
          <strong class="hf-kpi-value" style="font-size: 34px">${players.length}</strong>
          <p class="hf-small hf-muted hf-mb-0">players · ${roster.filter((player) => player.guardianConsent).length} with consent</p>
        </div>
        <div class="hf-card hf-card--flat">
          <span class="hf-kpi-label">Payment</span>
          <strong class="hf-section-title hf-mt-1">${escapeHtml(input('registerMethod').value)}</strong>
          <p class="hf-small hf-muted hf-mb-0">${escapeHtml(input('registerRef').value || 'No reference')} · ${escapeHtml(formatMoney(tournament.fee))}</p>
        </div>
      </div>
      <div class="hf-card hf-card--flat">
        <div class="hf-table-wrap">
          <table class="hf-table">
            <thead><tr><th scope="col">#</th><th scope="col">Player</th><th scope="col">Position</th><th scope="col">Age</th><th scope="col">Documents</th></tr></thead>
            <tbody>
              ${players.map((player) => `
                <tr>
                  <td>${escapeHtml(String(player.number))}</td>
                  <td><strong>${escapeHtml(player.name)}</strong></td>
                  <td>${escapeHtml(player.position)}</td>
                  <td>${escapeHtml(String(player.age))}</td>
                  <td>${ui.statusChip(player.documentStatus)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
        <p class="hf-small hf-muted hf-mt-2 hf-mb-0">Submitting creates a pending team. The organizer reviews eligibility before seeding.</p>
      </div>
    `;
  }

  function handleError(error) {
    const field = error.field || '';
    if (field.startsWith('players')) {
      showStep(1, { focus: false });
      const match = field.match(/^players\.(\d+)(?:\.(.+))?/);
      const row = rosterList.querySelector(`[data-player-row="${match ? match[1] : 0}"]`);
      const suffixMap = {
        name: 'name',
        number: 'number',
        position: 'position',
        age: 'age',
        guardianConsent: 'consent',
        documentStatus: 'document',
      };
      const suffix = suffixMap[match?.[2]] || 'name';
      const target =
        row?.querySelector(`[data-player-${suffix}]`) ||
        row?.querySelector('[data-player-name]');
      if (target) {
        ui.setFieldError(target, error.message);
        target.focus();
      }
    } else if (field.startsWith('payment')) {
      showStep(2);
      const target = field === 'payment.method' ? input('registerMethod') : input('registerRef');
      ui.setFieldError(target, error.message);
    } else {
      showStep(0);
      const target = input(field);
      if (target) {
        ui.setFieldError(target, error.message);
        target.focus();
      }
    }
    ui.toast(error.message, { tone: 'danger', title: 'Could not submit registration' });
  }

  root.querySelector('[data-add-player]').addEventListener('click', () => {
    syncRoster();
    if (roster.length >= 12) {
      ui.toast('A roster can have at most 12 players.', { tone: 'warning', title: 'Roster full' });
      return;
    }
    roster.push({
      name: '',
      number: '',
      position: POSITIONS[0],
      age: '',
      guardianConsent: false,
      documentStatus: 'pending',
    });
    renderRoster();
    rosterList.lastElementChild?.querySelector('[data-player-name]')?.focus();
  });

  rosterList.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-player]');
    if (!button) return;
    syncRoster();
    if (roster.length <= 8) {
      ui.toast('Keep at least 8 players on the roster.', { tone: 'warning', title: 'Roster minimum' });
      return;
    }
    roster.splice(Number(button.dataset.removePlayer), 1);
    renderRoster();
  });

  root.querySelector('[data-fill-demo]').addEventListener('click', () => {
    input('name').value = 'San Antonio Vipers U17';
    input('shortName').value = 'V17';
    input('city').value = 'Pasig City';
    input('coach').value = defaultCoach;
    roster.splice(
      0,
      roster.length,
      ...DEMO_PLAYERS.map(([name, number, age], index) => ({
        name,
        number,
        position: POSITIONS[index % POSITIONS.length],
        age,
        guardianConsent: true,
        documentStatus: 'verified',
      }))
    );
    renderRoster();
    input('registerRef').value = 'GCASH-9090909';
    ui.toast('Demo team, roster, and payment reference filled in. Review before submitting.', {
      tone: 'info',
      title: 'Demo data loaded',
    });
  });

  back.addEventListener('click', () => showStep(step - 1));

  next.addEventListener('click', () => {
    if (step === 0 && !validateTeam()) return;
    if (step === 1 && !validateRoster()) return;
    if (step === 2 && !validatePayment()) return;
    showStep(step + 1);
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const teamOk = validateTeam();
    const rosterOk = validateRoster();
    const paymentOk = validatePayment();
    if (!teamOk) {
      showStep(0);
      return;
    }
    if (!rosterOk) return;
    if (!paymentOk) {
      showStep(2);
      return;
    }

    syncRoster();
    const payload = {
      name: input('name').value.trim(),
      shortName: input('shortName').value.trim().toUpperCase(),
      city: input('city').value.trim(),
      coach: input('coach').value.trim(),
      avatarColor: form.querySelector('[name="avatarColor"]:checked')?.value || '#ea580c',
      players: roster.map((player) => ({
        name: player.name.trim(),
        number: Number(player.number),
        position: player.position,
        age: Number(player.age),
        guardianConsent: Boolean(player.guardianConsent),
        documentStatus: player.documentStatus,
      })),
      payment: {
        method: input('registerMethod').value,
        ref: input('registerRef').value.trim(),
      },
    };

    ui.setBusy(submit, true, 'Submitting...');
    try {
      const teamId = store.actions.registerTeam(tournamentId, payload);
      ui.toast('Registration submitted. The organizer will review the roster and payment.', {
        tone: 'success',
        title: 'Team submitted',
      });
      window.setTimeout(() => {
        window.location.href = route('myTeam', { team: teamId });
      }, 350);
    } catch (error) {
      ui.setBusy(submit, false);
      handleError(error);
    }
  });

  renderRoster();
  showStep(0, { focus: false });
}
```

- [ ] **Step 7: Verify the three team surfaces**

Open `http://127.0.0.1:8001/teams/index.html`. Expected: 32 team cards, filters work, and the coach sees “Manage roster” only for San Antonio Vipers. Open `http://127.0.0.1:8001/teams/show.html?id=team-1` as organizer: full roster and payment visible. Switch to fan and reload: payment and consent/documents disappear.

Switch to coach, open `http://127.0.0.1:8001/teams/register.html?tournament=tournament-2`, click “Fill demo data”, continue through review, and submit. Expected: redirect to My Team with status pending, `GCASH-9090909`, and 8 verified players.

- [ ] **Step 8: Run the module checks and commit**

```powershell
cd prototype
node --check assets/js/pages/teams.js
node --check assets/js/pages/team-show.js
node --check assets/js/pages/team-register.js
```

Expected: no output.

```powershell
git add prototype/teams/index.html prototype/teams/show.html prototype/teams/register.html prototype/assets/js/pages/teams.js prototype/assets/js/pages/team-show.js prototype/assets/js/pages/team-register.js
git commit -m "feat(prototype): add teams directory, team profile, and coach registration"
```

## Task 16: My Team roster and payment flow

**Files:**
- Rewrite: `prototype/teams/my-team.html`
- Create: `prototype/assets/js/pages/my-team.js`

**Interfaces:**
- Consumes: `route`, `escapeHtml`, `formatDate`, `formatTime`, `formatMoney`, `guardRole`, `POSITIONS`, `store.actions.addPlayer/updatePlayer/removePlayer/submitPayment`, `store.select.coachTeams/teamRecord/nextMatch`.
- Produces: the coach’s editable roster, consent/document visibility, registration timeline, payment reference update, and roster lock after verification.

- [ ] **Step 1: Rewrite the My Team HTML**

Replace `prototype/teams/my-team.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My team — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="my-team" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading my team...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 2: Implement the My Team renderer**

Create `prototype/assets/js/pages/my-team.js`:

```js
import { route } from '../routes.js';
import { escapeHtml, formatDate, formatTime, formatMoney } from '../ui.js';
import { guardRole } from '../guards.js';
import { PAYMENT_METHODS } from '../options.js';
import { POSITIONS } from '../seed.js';

export function render({ store, ui, components, root }) {
  if (!guardRole({ store, ui, root, roles: ['coach'] })) return;

  const params = new URLSearchParams(window.location.search);
  let teams = store.select.coachTeams();

  if (!teams.length) {
    root.innerHTML = ui.emptyState({
      icon: 'person-plus',
      title: 'No team attached',
      message: 'Register a team for an open tournament to start the coach journey.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}" data-testid="my-team-browse">Browse tournaments</a>`,
    });
    return;
  }

  let teamId = params.get('team') || teams[0].id;
  if (!teams.some((team) => team.id === teamId)) teamId = teams[0].id;

  function draw() {
    teams = store.select.coachTeams();
    const team = store.select.team(teamId);
    if (!team || !teams.some((item) => item.id === team.id)) {
      root.innerHTML = ui.emptyState({
        icon: 'people',
        title: 'Team not found',
        message: 'Choose one of your registered teams.',
        action: `<a class="hf-btn hf-btn--primary" href="${route('myTeam')}">Open my team</a>`,
      });
      return;
    }

    const tournament = store.select.tournament(team.tournamentId);
    const record = store.select.teamRecord(team.id);
    const next = store.select.nextMatch(team.id);
    const locked = Boolean(team.eligibility.verifiedAt);
    const consentCount = team.players.filter((player) => player.guardianConsent).length;
    const docsCount = team.players.filter((player) => player.documentStatus === 'verified').length;

    const timeline = [
      { title: 'Registration submitted', detail: formatDate(team.submittedAt), done: true },
      {
        title: 'Organizer review',
        detail: team.status === 'pending' ? 'Waiting for review' : `Status: ${team.status}`,
        done: team.status !== 'pending',
      },
      {
        title: 'Payment',
        detail: `${team.payment.status} · ${team.payment.method || 'No method'}${team.payment.ref ? ` · ${team.payment.ref}` : ''}`,
        done: team.payment.status === 'paid',
      },
      {
        title: 'Roster verified',
        detail: team.eligibility.verifiedAt ? `Verified ${formatDate(team.eligibility.verifiedAt)}` : 'Locks after organizer verification',
        done: Boolean(team.eligibility.verifiedAt),
      },
      {
        title: 'Seeding',
        detail: team.seed ? `Seed ${team.seed}` : 'Assigned when the bracket is generated',
        done: Boolean(team.seed),
      },
    ];

    root.innerHTML = `
      ${components.pageHeader({
        eyebrow: `${tournament?.name || 'Tournament'} · ${tournament?.division || ''}`,
        title: team.name,
        description: `${team.coach} · ${team.city} · ${tournament?.venue || ''}`,
        actions: `
          <a class="hf-btn" href="${route('teamShow', { id: team.id })}" data-testid="my-team-public">Public profile</a>
          <a class="hf-btn" href="${route('tournaments')}" data-testid="my-team-register-another"><i class="bi bi-plus-lg"></i> Register another team</a>
        `,
        meta: `
          <span><i class="bi bi-shield-check"></i>${ui.statusChip(team.status)}</span>
          <span><i class="bi bi-cash-coin"></i>${ui.statusChip(team.payment.status)}</span>
          <span><i class="bi bi-people"></i>${team.players.length} players</span>
        `,
      })}

      ${
        teams.length > 1
          ? `<div class="hf-card hf-card--flat hf-mb-3">
               <label class="hf-label" for="myTeamSelect">Switch team</label>
               <select class="hf-select" id="myTeamSelect" data-testid="my-team-select">
                 ${teams.map((item) => `<option value="${escapeHtml(item.id)}" ${item.id === team.id ? 'selected' : ''}>${escapeHtml(item.name)} · ${escapeHtml(item.status)}</option>`).join('')}
               </select>
             </div>`
          : ''
      }

      ${
        team.status === 'rejected'
          ? `<div class="hf-card hf-card--flat hf-mb-3" style="border-color: var(--hf-alert)">
               <span class="hf-chip hf-chip--danger">Rejected</span>
               <h3 class="hf-section-title hf-mt-1">Organizer feedback</h3>
               <p class="hf-small hf-muted hf-mb-0">${escapeHtml(team.rejectionReason || 'Contact the tournament committee for details.')}</p>
             </div>`
          : ''
      }

      <div class="hf-grid hf-grid--4 hf-mb-3">
        ${components.kpiTile({ label: 'Record', value: `${record.wins}-${record.losses}`, hint: 'Wins and losses', tone: 'primary', testid: 'my-team-record' })}
        ${components.kpiTile({ label: 'Roster', value: team.players.length, hint: `${consentCount} consents · ${docsCount} verified`, tone: 'success', testid: 'my-team-roster-kpi' })}
        ${components.kpiTile({ label: 'Seed', value: team.seed ?? 'TBD', hint: team.seed ? 'Locked for the bracket' : 'Assigned after approval', tone: 'gold', testid: 'my-team-seed' })}
        ${components.kpiTile({ label: 'Entry fee', value: formatMoney(tournament?.fee || 0), hint: team.payment.status === 'paid' ? 'Paid' : 'Payment outstanding', tone: 'alert', testid: 'my-team-fee' })}
      </div>

      <div class="hf-split">
        <div>
          ${components.sectionHeader({
            title: `Roster (${team.players.length})`,
            action: locked
              ? '<span class="hf-chip hf-chip--slate"><i class="bi bi-lock"></i> Locked after verification</span>'
              : `<button type="button" class="hf-btn hf-btn--sm hf-btn--primary" data-add-player data-testid="my-team-add-player" ${team.players.length >= 12 ? 'disabled' : ''}><i class="bi bi-plus-lg"></i> Add player</button>`,
          })}
          <div class="hf-card hf-mb-3" data-testid="my-team-roster">
            ${team.players.length
              ? `<div class="hf-table-wrap">
                   <table class="hf-table">
                     <thead>
                       <tr>
                         <th scope="col">#</th>
                         <th scope="col">Player</th>
                         <th scope="col">Position</th>
                         <th scope="col">Age</th>
                         <th scope="col">Consent</th>
                         <th scope="col">Documents</th>
                         ${locked ? '' : '<th scope="col">Actions</th>'}
                       </tr>
                     </thead>
                     <tbody>
                       ${team.players.map((player) => `
                         <tr data-testid="my-team-player-${escapeHtml(player.id)}">
                           <td>${player.number}</td>
                           <td><strong>${escapeHtml(player.name)}</strong></td>
                           <td>${escapeHtml(player.position)}</td>
                           <td>${player.age}</td>
                           <td>${player.guardianConsent ? '<span class="hf-chip hf-chip--success">On file</span>' : '<span class="hf-chip hf-chip--danger">Missing</span>'}</td>
                           <td>${ui.statusChip(player.documentStatus)}</td>
                           ${locked ? '' : `<td>
                             <div class="hf-flex hf-gap-1">
                               <button type="button" class="hf-icon-btn" data-edit-player="${escapeHtml(player.id)}"
                                       aria-label="Edit ${escapeHtml(player.name)}" data-testid="my-team-edit-${escapeHtml(player.id)}">
                                 <i class="bi bi-pencil-square"></i>
                               </button>
                               <button type="button" class="hf-icon-btn" data-remove-player="${escapeHtml(player.id)}"
                                       aria-label="Remove ${escapeHtml(player.name)}" data-testid="my-team-remove-${escapeHtml(player.id)}">
                                 <i class="bi bi-trash3"></i>
                               </button>
                             </div>
                           </td>`}
                         </tr>
                       `).join('')}
                     </tbody>
                   </table>
                 </div>`
              : ui.emptyState({ icon: 'person-plus', title: 'No players yet', message: 'Add eight to twelve players to complete the roster.' })}
          </div>

          ${components.sectionHeader({ title: 'Registration timeline' })}
          <div class="hf-card hf-mb-3">
            <ol class="hf-timeline" data-testid="my-team-timeline">
              ${timeline.map((item) => `
                <li class="hf-timeline-item">
                  <div class="hf-timeline-meta">
                    <span>${item.done ? 'Complete' : 'Pending'}</span>
                    ${item.done ? '<span class="hf-chip hf-chip--success">Done</span>' : '<span class="hf-chip hf-chip--slate">Waiting</span>'}
                  </div>
                  <h3>${escapeHtml(item.title)}</h3>
                  <p>${escapeHtml(item.detail)}</p>
                </li>
              `).join('')}
            </ol>
          </div>
        </div>

        <aside>
          <div class="hf-card hf-mb-3">
            <span class="hf-kpi-label">Eligibility</span>
            <h3 class="hf-section-title hf-mt-1">${locked ? 'Roster locked' : 'Roster editable'}</h3>
            <div class="hf-flex hf-wrap hf-gap-1 hf-mt-1">
              ${consentCount === team.players.length ? ui.statusChip('verified', 'Consent complete') : ui.statusChip('missing', `${team.players.length - consentCount} consent missing`)}
              ${docsCount === team.players.length ? ui.statusChip('verified', 'Documents complete') : ui.statusChip('missing', `${team.players.length - docsCount} documents pending`)}
            </div>
            <p class="hf-small hf-muted hf-mt-2 hf-mb-0">${locked ? 'The organizer verified this roster. Contact the committee to make a change.' : 'Add missing consent and documents before the organizer review.'}</p>
          </div>

          <div class="hf-card hf-mb-3" data-testid="my-team-payment">
            <div class="hf-between">
              <span class="hf-kpi-label">Payment</span>
              ${ui.statusChip(team.payment.status)}
            </div>
            <h3 class="hf-section-title hf-mt-1">${escapeHtml(team.payment.method || 'No method')}</h3>
            <p class="hf-small hf-muted hf-mb-1">${escapeHtml(team.payment.ref || 'No reference')} · ${escapeHtml(formatMoney(team.payment.amount || tournament?.fee || 0))}</p>
            ${team.payment.verifiedAt ? `<p class="hf-small hf-muted hf-mb-0">Verified ${escapeHtml(formatDate(team.payment.verifiedAt))}</p>` : ''}
            ${team.payment.status !== 'paid' ? `<button type="button" class="hf-btn hf-btn--sm hf-btn--primary hf-mt-2" data-update-payment data-testid="my-team-update-payment">Update payment reference</button>` : ''}
          </div>

          <div class="hf-card hf-mb-3">
            <span class="hf-kpi-label">Next game</span>
            ${next
              ? `<h3 class="hf-section-title hf-mt-1">${escapeHtml(next.roundLabel)}</h3>
                 <p class="hf-small hf-muted hf-mb-1">${escapeHtml(formatDate(next.scheduledAt))} · ${escapeHtml(formatTime(next.scheduledAt))}</p>
                 <p class="hf-small hf-muted hf-mb-1">${escapeHtml(tournament?.courts.find((court) => court.id === next.courtId)?.name || 'Court TBD')}</p>
                 <a class="hf-btn hf-btn--sm" href="${route('gameStats', { match: next.id })}" data-testid="my-team-next-game">Game details</a>`
              : `<p class="hf-small hf-muted hf-mb-0">No game scheduled yet.</p>`}
          </div>

          <div class="hf-card">
            <span class="hf-kpi-label">Tournament</span>
            <h3 class="hf-section-title hf-mt-1">${escapeHtml(tournament?.name || '')}</h3>
            <p class="hf-small hf-muted hf-mb-1">${escapeHtml(tournament?.division || '')} · ${escapeHtml(tournament?.venue || '')}</p>
            <a class="hf-btn hf-btn--sm" href="${route('tournamentShow', { id: team.tournamentId })}" data-testid="my-team-tournament">Tournament desk</a>
          </div>
        </aside>
      </div>
    `;

    attach(team, locked);
  }

  function attach(team, locked) {
    root.querySelector('#myTeamSelect')?.addEventListener('change', (event) => {
      window.location.href = route('myTeam', { team: event.target.value });
    });

    root.querySelector('[data-add-player]')?.addEventListener('click', () => openPlayerDialog(team));

    root.querySelectorAll('[data-edit-player]').forEach((button) => {
      button.addEventListener('click', () => {
        const player = team.players.find((item) => item.id === button.dataset.editPlayer);
        if (player) openPlayerDialog(team, player);
      });
    });

    root.querySelectorAll('[data-remove-player]').forEach((button) => {
      button.addEventListener('click', async () => {
        const player = team.players.find((item) => item.id === button.dataset.removePlayer);
        if (!player) return;
        const ok = await ui.confirm({
          title: `Remove ${player.name}?`,
          message: 'The player is removed from the roster immediately. You can add them again before verification.',
          confirmLabel: 'Remove player',
          tone: 'danger',
        });
        if (!ok) return;
        try {
          store.actions.removePlayer(team.id, player.id);
          ui.toast(`${player.name} removed from the roster.`, { tone: 'success', title: 'Roster updated' });
          draw();
        } catch (error) {
          ui.toast(error.message, { tone: 'danger', title: 'Could not remove player' });
        }
      });
    });

    root.querySelector('[data-update-payment]')?.addEventListener('click', () => openPaymentDialog(team));
  }

  function openPlayerDialog(team, player = null) {
    const editing = Boolean(player);
    const draft = player
      ? { ...player }
      : { name: '', number: '', position: POSITIONS[0], age: '', guardianConsent: false, documentStatus: 'pending' };

    ui.dialog({
      title: editing ? `Edit ${player.name}` : 'Add player',
      body: `
        <div class="hf-form-grid">
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="myPlayerName">Player name</label>
            <input class="hf-input" id="myPlayerName" value="${escapeHtml(draft.name)}" data-testid="my-player-name">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="myPlayerNumber">Jersey number</label>
            <input class="hf-input" id="myPlayerNumber" type="number" min="1" max="99" value="${escapeHtml(String(draft.number))}" data-testid="my-player-number">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="myPlayerPosition">Position</label>
            <select class="hf-select" id="myPlayerPosition" data-testid="my-player-position">
              ${POSITIONS.map((position) => `<option value="${escapeHtml(position)}" ${position === draft.position ? 'selected' : ''}>${escapeHtml(position)}</option>`).join('')}
            </select>
          </div>
          <div class="hf-field">
            <label class="hf-label" for="myPlayerAge">Age</label>
            <input class="hf-input" id="myPlayerAge" type="number" min="8" max="99" value="${escapeHtml(String(draft.age))}" data-testid="my-player-age">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="myPlayerDocument">Documents</label>
            <select class="hf-select" id="myPlayerDocument" data-testid="my-player-document">
              <option value="verified" ${draft.documentStatus === 'verified' ? 'selected' : ''}>Verified</option>
              <option value="pending" ${draft.documentStatus === 'pending' ? 'selected' : ''}>Pending review</option>
              <option value="missing" ${draft.documentStatus === 'missing' ? 'selected' : ''}>Missing</option>
            </select>
          </div>
          <div class="hf-field hf-field--full">
            <label class="hf-check">
              <input type="checkbox" id="myPlayerConsent" ${draft.guardianConsent ? 'checked' : ''} data-testid="my-player-consent">
              Guardian consent on file
            </label>
          </div>
        </div>
      `,
      footer: `
        <button type="button" class="hf-btn" data-cancel-player data-testid="my-player-cancel">Cancel</button>
        <button type="button" class="hf-btn hf-btn--primary" data-save-player data-testid="my-player-save">${editing ? 'Save player' : 'Add player'}</button>
      `,
      onMount(element, close) {
        const fields = {
          name: element.querySelector('#myPlayerName'),
          number: element.querySelector('#myPlayerNumber'),
          position: element.querySelector('#myPlayerPosition'),
          age: element.querySelector('#myPlayerAge'),
          documentStatus: element.querySelector('#myPlayerDocument'),
          guardianConsent: element.querySelector('#myPlayerConsent'),
        };
        element.querySelector('[data-cancel-player]').addEventListener('click', close);
        element.querySelector('[data-save-player]').addEventListener('click', () => {
          Object.values(fields).forEach((field) => ui.clearFieldError(field));
          const payload = {
            name: fields.name.value.trim(),
            number: Number(fields.number.value),
            position: fields.position.value,
            age: Number(fields.age.value),
            guardianConsent: fields.guardianConsent.checked,
            documentStatus: fields.documentStatus.value,
          };
          try {
            if (editing) store.actions.updatePlayer(team.id, player.id, payload);
            else store.actions.addPlayer(team.id, payload);
            close();
            ui.toast(editing ? `${payload.name} updated.` : `${payload.name} added to the roster.`, {
              tone: 'success',
              title: 'Roster updated',
            });
            draw();
          } catch (error) {
            const target = fields[error.field];
            if (target) ui.setFieldError(target, error.message);
            ui.toast(error.message, { tone: 'danger', title: 'Could not save player' });
          }
        });
      },
    });
  }

  function openPaymentDialog(team) {
    const tournament = store.select.tournament(team.tournamentId);
    ui.dialog({
      title: 'Update payment reference',
      body: `
        <p class="hf-small hf-muted">Entry fee is ${escapeHtml(formatMoney(tournament?.fee || 0))}. The organizer verifies the reference before seeding.</p>
        <div class="hf-form-grid">
          <div class="hf-field">
            <label class="hf-label" for="myPaymentMethod">Method</label>
            <select class="hf-select" id="myPaymentMethod" data-testid="my-payment-method">
              ${PAYMENT_METHODS.map((method) => `<option value="${escapeHtml(method)}" ${method === (team.payment.method || 'GCash') ? 'selected' : ''}>${escapeHtml(method)}</option>`).join('')}
            </select>
          </div>
          <div class="hf-field">
            <label class="hf-label" for="myPaymentRef">Reference</label>
            <input class="hf-input" id="myPaymentRef" value="${escapeHtml(team.payment.ref || '')}" placeholder="GCASH-9283746" data-testid="my-payment-ref">
          </div>
        </div>
      `,
      footer: `
        <button type="button" class="hf-btn" data-cancel-payment data-testid="my-payment-cancel">Cancel</button>
        <button type="button" class="hf-btn hf-btn--primary" data-save-payment data-testid="my-payment-save">Submit reference</button>
      `,
      onMount(element, close) {
        const method = element.querySelector('#myPaymentMethod');
        const ref = element.querySelector('#myPaymentRef');
        element.querySelector('[data-cancel-payment]').addEventListener('click', close);
        element.querySelector('[data-save-payment]').addEventListener('click', () => {
          ui.clearFieldError(ref);
          if (!/^[A-Za-z0-9-]{6,24}$/.test(ref.value.trim())) {
            ui.setFieldError(ref, 'Use 6–24 letters, numbers, or dashes.');
            ref.focus();
            return;
          }
          try {
            store.actions.submitPayment(team.id, { method: method.value, ref: ref.value.trim() });
            close();
            ui.toast('Payment reference submitted for verification.', {
              tone: 'success',
              title: 'Reference saved',
            });
            draw();
          } catch (error) {
            const target = error.field === 'method' ? method : ref;
            ui.setFieldError(target, error.message);
            ui.toast(error.message, { tone: 'danger', title: 'Could not save reference' });
          }
        });
      },
    });
  }

  draw();
}
```

- [ ] **Step 3: Verify the roster and payment flows**

Open `http://127.0.0.1:8001/teams/my-team.html` as coach. Expected: San Antonio Vipers with a locked roster, paid payment, seed 1, and a next game. Click the team selector or register a new team first.

After registering for Pasig Youth Hoopfest, open My Team. Expected: roster editable, “Update payment reference” visible, timeline shows organizer review pending. Add a ninth player; confirm the roster count updates without a reload. Remove a player; confirm a dialog appears. Edit the payment reference and confirm the status returns to pending.

- [ ] **Step 4: Check the module parses and commit**

```powershell
cd prototype
node --check assets/js/pages/my-team.js
```

Expected: no output.

```powershell
git add prototype/teams/my-team.html prototype/assets/js/pages/my-team.js
git commit -m "feat(prototype): add coach roster and payment management"
```

## Task 17: Bracket tree, mobile pager, and score console

**Files:**
- Create: `prototype/assets/js/score-dialogs.js`
- Rewrite: `prototype/brackets/view.html`
- Create: `prototype/assets/js/pages/bracket.js`

**Interfaces:**
- Consumes: `route`, `escapeHtml`, `formatDate`, `formatTime`, `ui.dialog/confirm/toast/setBusy`, `components.bracketMatch/scoreboard/kpiTile/pageHeader/sectionHeader`, `store.select.bracket/match/team/tournament`, `store.actions.generateBracket`.
- Produces: `openScoreConsole({ store, ui, matchId, onDone })` and `openCorrectionDialog({ store, ui, matchId, onDone })` from `score-dialogs.js`; a desktop bracket tree, a mobile round pager, match dialog, score finalization, and correction flow that advances the bracket and updates standings.

- [ ] **Step 1: Create the shared score dialogs**

Create `prototype/assets/js/score-dialogs.js`:

```js
import { escapeHtml } from './ui.js';

function teamPanel(team, side) {
  return `
    <div class="hf-card hf-card--flat" data-team-panel="${side}">
      <span class="hf-flex hf-items-center hf-gap-2">
        <span class="hf-shield" style="--team-color: ${escapeHtml(team.avatarColor)}" aria-hidden="true">${escapeHtml(team.shortName)}</span>
        <strong>${escapeHtml(team.name)}</strong>
      </span>
      <div class="hf-score" data-score-display="${side}" style="font-size: clamp(48px, 9vw, 78px); line-height: 1">0</div>
      <label class="hf-label" for="scoreInput${side}">Final score</label>
      <input class="hf-input hf-mb-2" id="scoreInput${side}" type="number" min="0" max="199"
             inputmode="numeric" value="0" data-score-input="${side}"
             data-testid="score-input-${side}">
      <div class="hf-btn-row">
        ${[1, 2, 3]
          .map(
            (points) => `
              <button type="button" class="hf-btn" data-add-score data-team="${side}" data-points="${points}"
                      data-testid="score-add-${side}-${points}">+${points}</button>
            `
          )
          .join('')}
      </div>
    </div>
  `;
}

export function openScoreConsole({ store, ui, matchId, onDone }) {
  const match = store.select.match(matchId);
  if (!match) {
    ui.toast('That match no longer exists.', { tone: 'danger', title: 'Match not found' });
    return;
  }
  const team1 = store.select.team(match.team1Id);
  const team2 = store.select.team(match.team2Id);
  if (!team1 || !team2) {
    ui.toast('Both teams must be known before entering a score.', {
      tone: 'warning',
      title: 'Teams pending',
    });
    return;
  }

  let score1 = 0;
  let score2 = 0;
  let forfeitTeam = 0;
  const history = [];
  let errorElement = null;

  ui.dialog({
    title: `${team1.shortName} vs ${team2.shortName}`,
    size: 'wide',
    body: `
      <p class="hf-small hf-muted">${escapeHtml(match.roundLabel)} · Slot ${match.bracketSlot}. Use the score buttons or a forfeit, then finalize. The winner advances immediately.</p>
      <div class="hf-grid hf-grid--2">
        ${teamPanel(team1, 1)}
        ${teamPanel(team2, 2)}
      </div>
      <div class="hf-card hf-card--flat hf-mt-2">
        <label class="hf-label" for="scoreForfeit">Forfeit</label>
        <select class="hf-select" id="scoreForfeit" data-testid="score-forfeit">
          <option value="0">No forfeit</option>
          <option value="1">${escapeHtml(team1.name)} forfeits · ${escapeHtml(team2.shortName)} wins 20–0</option>
          <option value="2">${escapeHtml(team2.name)} forfeits · ${escapeHtml(team1.shortName)} wins 20–0</option>
        </select>
        <p class="hf-small hf-muted hf-mt-1 hf-mb-0" data-forfeit-note hidden>
          FIBA forfeit score is 20–0. Score buttons are disabled while a forfeit is selected.
        </p>
        <p class="hf-field-error hf-mt-1" data-score-error hidden></p>
      </div>
    `,
    footer: `
      <button type="button" class="hf-btn" data-undo-score data-testid="score-undo" disabled>Undo</button>
      <button type="button" class="hf-btn" data-cancel-score data-testid="score-cancel">Cancel</button>
      <button type="button" class="hf-btn hf-btn--primary" data-finalize-score data-testid="score-finalize">
        Finalize score
      </button>
    `,
    onMount(element, close) {
      const forfeit = element.querySelector('#scoreForfeit');
      const undo = element.querySelector('[data-undo-score]');
      const finalize = element.querySelector('[data-finalize-score]');
      const note = element.querySelector('[data-forfeit-note]');
      const inputs = [...element.querySelectorAll('[data-score-input]')];
      errorElement = element.querySelector('[data-score-error]');

      function setError(message) {
        errorElement.hidden = !message;
        errorElement.textContent = message || '';
      }

      function paint() {
        const shown1 = forfeitTeam === 1 ? 0 : forfeitTeam === 2 ? 20 : score1;
        const shown2 = forfeitTeam === 2 ? 0 : forfeitTeam === 1 ? 20 : score2;
        element.querySelector('[data-score-display="1"]').textContent = String(shown1);
        element.querySelector('[data-score-display="2"]').textContent = String(shown2);
        element.querySelector('[data-score-input="1"]').value = String(shown1);
        element.querySelector('[data-score-input="2"]').value = String(shown2);
        element.querySelectorAll('[data-add-score]').forEach((button) => {
          button.disabled = Boolean(forfeitTeam);
        });
        inputs.forEach((input) => {
          input.disabled = Boolean(forfeitTeam);
        });
        undo.disabled = history.length === 0 || Boolean(forfeitTeam);
        note.hidden = !forfeitTeam;
      }

      element.querySelector('[data-cancel-score]').addEventListener('click', close);

      element.querySelectorAll('[data-add-score]').forEach((button) => {
        button.addEventListener('click', () => {
          if (forfeitTeam) return;
          history.push({ score1, score2 });
          const points = Number(button.dataset.points);
          if (button.dataset.team === '1') score1 += points;
          else score2 += points;
          setError('');
          paint();
        });
      });

      inputs.forEach((input) => {
        input.addEventListener('input', () => {
          if (forfeitTeam) return;
          const value = Number(input.value);
          if (!Number.isFinite(value) || value < 0) return;
          const next = Math.min(199, Math.floor(value));
          if (input.dataset.scoreInput === '1') score1 = next;
          else score2 = next;
          element.querySelector(`[data-score-display="${input.dataset.scoreInput}"]`).textContent = String(next);
          setError('');
        });

        input.addEventListener('change', () => {
          const side = input.dataset.scoreInput;
          input.value = String(side === '1' ? score1 : score2);
        });
      });

      undo.addEventListener('click', () => {
        const previous = history.pop();
        if (!previous) return;
        score1 = previous.score1;
        score2 = previous.score2;
        paint();
      });

      forfeit.addEventListener('change', () => {
        forfeitTeam = Number(forfeit.value) || 0;
        setError('');
        paint();
      });

      finalize.addEventListener('click', async () => {
        setError('');
        if (forfeitTeam) {
          const winner = forfeitTeam === 1 ? team2 : team1;
          const loser = forfeitTeam === 1 ? team1 : team2;
          const ok = await ui.confirm({
            title: 'Record forfeit?',
            message: `${loser.name} forfeits. ${winner.name} wins 20–0.`,
            confirmLabel: 'Record forfeit',
            tone: 'danger',
          });
          if (!ok) return;
          try {
            store.actions.saveScore(match.id, 0, 0, { forfeitTeam });
            close();
            ui.toast(`${winner.shortName} advances on a 20–0 forfeit.`, {
              tone: 'success',
              title: 'Forfeit recorded',
            });
            onDone?.();
          } catch (error) {
            setError(error.message);
          }
          return;
        }

        if (score1 === score2) {
          setError('Elimination games cannot end in a tie.');
          return;
        }

        const ok = await ui.confirm({
          title: 'Finalize score?',
          message: `${team1.shortName} ${score1}–${score2} ${team2.shortName}. The winner advances and standings update immediately.`,
          confirmLabel: 'Finalize score',
        });
        if (!ok) return;

        try {
          store.actions.saveScore(match.id, score1, score2);
          close();
          ui.toast(
            `${score1 > score2 ? team1.shortName : team2.shortName} advances to the next round.`,
            { tone: 'success', title: 'Final score recorded' }
          );
          onDone?.();
        } catch (error) {
          setError(error.message);
        }
      });

      paint();
    },
  });
}

export function openCorrectionDialog({ store, ui, matchId, onDone }) {
  const match = store.select.match(matchId);
  if (!match) {
    ui.toast('That match no longer exists.', { tone: 'danger', title: 'Match not found' });
    return;
  }
  if (match.status !== 'final' && match.status !== 'forfeit') {
    ui.toast('Only a recorded result can be corrected.', {
      tone: 'warning',
      title: 'Not final yet',
    });
    return;
  }
  const team1 = store.select.team(match.team1Id);
  const team2 = store.select.team(match.team2Id);

  ui.dialog({
    title: `Correct score · ${team1.shortName} vs ${team2.shortName}`,
    size: 'wide',
    body: `
      <p class="hf-small hf-muted">Current result: ${match.score1}–${match.score2}${match.correction ? ` (corrected from ${match.correction.previousScore1}–${match.correction.previousScore2})` : ''}. A correction requires a reason and rewrites the downstream bracket if the winner changes.</p>
      <div class="hf-form-grid">
        <div class="hf-field">
          <label class="hf-label" for="correctionScore1">${escapeHtml(team1.name)}</label>
          <input class="hf-input" id="correctionScore1" type="number" min="0" value="${match.score1}"
                 data-testid="correction-score-1">
        </div>
        <div class="hf-field">
          <label class="hf-label" for="correctionScore2">${escapeHtml(team2.name)}</label>
          <input class="hf-input" id="correctionScore2" type="number" min="0" value="${match.score2}"
                 data-testid="correction-score-2">
        </div>
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="correctionReason">Reason</label>
          <textarea class="hf-textarea" id="correctionReason" rows="3"
                    placeholder="Shot-clock correction after referee review."
                    data-testid="correction-reason"></textarea>
        </div>
      </div>
      <p class="hf-field-error hf-mt-1" data-correction-error hidden></p>
    `,
    footer: `
      <button type="button" class="hf-btn" data-cancel-correction data-testid="correction-cancel">Cancel</button>
      <button type="button" class="hf-btn hf-btn--danger" data-save-correction data-testid="correction-save">
        Save correction
      </button>
    `,
    onMount(element, close) {
      const score1Input = element.querySelector('#correctionScore1');
      const score2Input = element.querySelector('#correctionScore2');
      const reasonInput = element.querySelector('#correctionReason');
      const errorElement = element.querySelector('[data-correction-error]');

      function setError(message, target = null) {
        errorElement.hidden = !message;
        errorElement.textContent = message || '';
        if (target) {
          ui.setFieldError(target, message);
          target.focus();
        }
      }

      element.querySelector('[data-cancel-correction]').addEventListener('click', close);
      element.querySelector('[data-save-correction]').addEventListener('click', async () => {
        [score1Input, score2Input, reasonInput].forEach((field) => ui.clearFieldError(field));
        errorElement.hidden = true;

        const score1 = Number(score1Input.value);
        const score2 = Number(score2Input.value);
        if (!Number.isInteger(score1) || !Number.isInteger(score2) || score1 < 0 || score2 < 0) {
          setError('Scores must be zero or positive whole numbers.', score1Input);
          return;
        }
        if (score1 === score2) {
          setError('Elimination games cannot end in a tie.', score2Input);
          return;
        }
        if (reasonInput.value.trim().length < 5) {
          setError('Enter at least 5 characters for the correction reason.', reasonInput);
          return;
        }

        const ok = await ui.confirm({
          title: 'Apply correction?',
          message: `Change ${team1.shortName} ${match.score1}–${match.score2} ${team2.shortName} to ${score1}–${score2}. The audit log keeps the previous values.`,
          confirmLabel: 'Apply correction',
          tone: 'danger',
        });
        if (!ok) return;

        try {
          store.actions.correctScore(match.id, score1, score2, reasonInput.value.trim());
          close();
          ui.toast('Correction saved and the bracket path was recalculated.', {
            tone: 'success',
            title: 'Score corrected',
          });
          onDone?.();
        } catch (error) {
          setError(error.message);
        }
      });
    },
  });
}
```

- [ ] **Step 2: Rewrite the bracket HTML**

Replace `prototype/brackets/view.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bracket — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="bracket" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading bracket...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 3: Implement the bracket renderer**

Create `prototype/assets/js/pages/bracket.js`:

```js
import { route } from '../routes.js';
import { escapeHtml, formatDate, formatTime } from '../ui.js';
import { openCorrectionDialog, openScoreConsole } from '../score-dialogs.js';

export function render({ store, ui, components, root }) {
  const params = new URLSearchParams(window.location.search);
  const tournamentId = params.get('id') || 'tournament-1';
  const requestedMatch = params.get('match') || '';
  let mobileRound = 0;
  let openedFromQuery = false;

  function draw() {
    const tournament = store.select.tournament(tournamentId);
    if (!tournament) {
      root.innerHTML = ui.emptyState({
        icon: 'trophy',
        title: 'Tournament not found',
        message: 'The bracket link is invalid or the demo has been reset.',
        action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}">Back to tournaments</a>`,
      });
      return;
    }

    const role = store.getRole();
    const bracket = store.select.bracket(tournamentId);
    const approved = store.select.teams(tournamentId, { status: 'approved' });
    const kpis = store.select.kpis(tournamentId);
    const canGenerate = role === 'organizer' && approved.length >= 2;
    const canScore = role === 'organizer' || role === 'scorekeeper';

    const header = components.pageHeader({
      eyebrow: `${tournament.division} · ${tournament.status}`,
      title: bracket.hasBracket ? 'Bracket' : 'Build the bracket',
      description: bracket.hasBracket
        ? `${tournament.name} · ${bracket.rounds.length} rounds · winners advance automatically.`
        : `${tournament.name} · ${approved.length} approved teams ready to seed into a ${tournament.slots}-slot single-elimination bracket.`,
      actions: `
        <a class="hf-btn" href="${route('tournamentShow', { id: tournamentId })}" data-testid="bracket-tournament"><i class="bi bi-arrow-left"></i> Tournament</a>
        <a class="hf-btn" href="${route('schedule', { id: tournamentId })}" data-testid="bracket-schedule"><i class="bi bi-calendar3"></i> Schedule</a>
        <a class="hf-btn" href="${route('standings', { id: tournamentId })}" data-testid="bracket-standings"><i class="bi bi-bar-chart"></i> Standings</a>
      `,
    });

    if (!bracket.hasBracket) {
      root.innerHTML = `
        ${header}
        <div class="hf-grid hf-grid--3 hf-mb-3">
          ${components.kpiTile({ label: 'Approved teams', value: approved.length, hint: `${tournament.slots} bracket slots`, tone: 'primary', testid: 'bracket-kpi-approved' })}
          ${components.kpiTile({ label: 'First-round games', value: tournament.slots / 2, hint: 'Byes fill empty slots', tone: 'gold', testid: 'bracket-kpi-games' })}
          ${components.kpiTile({ label: 'Courts', value: tournament.courts.length, hint: tournament.venue, tone: 'slate', testid: 'bracket-kpi-courts' })}
        </div>
        <div class="hf-card" data-testid="bracket-empty">
          <h2 class="hf-section-title">Seed preview</h2>
          <p class="hf-small hf-muted">Teams are seeded by their registration seed, then the bracket pairs 1–16, 8–9, 5–12, 4–13, and so on. Empty slots become byes.</p>
          <div class="hf-grid hf-grid--2 hf-mt-2">
            ${approved.length
              ? approved
                  .slice()
                  .sort((a, b) => (a.seed ?? 999) - (b.seed ?? 999))
                  .map(
                    (team) => `
                      <div class="hf-flex hf-items-center hf-gap-2 hf-card hf-card--flat">
                        ${components.shield(team)}
                        <span><strong>${escapeHtml(team.name)}</strong><span class="hf-small hf-muted" style="display:block">${escapeHtml(team.city)} · seed ${team.seed ?? 'TBD'}</span></span>
                      </div>
                    `
                  )
                  .join('')
              : ui.emptyState({ icon: 'people', title: 'No approved teams', message: 'Approve at least two teams before generating the bracket.' })}
          </div>
          <div class="hf-btn-row hf-mt-3">
            ${
              canGenerate
                ? `<button type="button" class="hf-btn hf-btn--primary" data-generate-bracket data-testid="bracket-generate">Generate bracket</button>`
                : `<button type="button" class="hf-btn" disabled data-testid="bracket-generate-disabled">Generate bracket</button>
                   <p class="hf-small hf-muted hf-mb-0">${role === 'organizer' ? 'At least two approved teams are required.' : 'Organizer access required.'}</p>`
            }
          </div>
        </div>
      `;
      attachGeneration();
      return;
    }

    const rounds = bracket.rounds;
    mobileRound = Math.min(mobileRound, rounds.length - 1);
    const champion = bracket.champion;

    root.innerHTML = `
      ${header}

      ${
        champion
          ? `<section class="hf-bracket-champion hf-mb-3" data-testid="bracket-champion">
               <span class="hf-eyebrow" style="color: var(--hf-gold)">Champion</span>
               <div class="hf-flex hf-items-center hf-gap-3 hf-mt-1">
                 ${components.shield(champion, 'lg')}
                 <div>
                   <h1>${escapeHtml(champion.name)}</h1>
                   <p class="hf-mb-0">${escapeHtml(champion.coach)} · ${escapeHtml(champion.city)}</p>
                 </div>
               </div>
             </section>`
          : ''
      }

      <div class="hf-grid hf-grid--4 hf-mb-3">
        ${components.kpiTile({ label: 'Rounds', value: rounds.length, hint: `${tournament.slots}-team bracket`, tone: 'primary', testid: 'bracket-kpi-rounds' })}
        ${components.kpiTile({ label: 'Played', value: kpis.matchesPlayed, hint: `${kpis.matchesScheduled} scheduled`, tone: 'success', testid: 'bracket-kpi-played' })}
        ${components.kpiTile({ label: 'Champion', value: champion ? champion.shortName : 'TBD', hint: champion ? champion.name : 'Final not played', tone: 'gold', testid: 'bracket-kpi-champion' })}
        ${components.kpiTile({ label: 'Corrections', value: store.select.audit({ entityType: 'match' }).filter((entry) => entry.action === 'score.correct').length, hint: 'Audited score changes', tone: 'alert', testid: 'bracket-kpi-corrections' })}
      </div>

      <div class="hf-flex hf-wrap hf-gap-2 hf-mb-2 hf-small hf-muted">
        <span class="hf-chip hf-chip--slate"><i class="bi bi-clock"></i> Scheduled</span>
        <span class="hf-chip hf-chip--success"><i class="bi bi-flag-fill"></i> Final</span>
        <span class="hf-chip hf-chip--danger"><i class="bi bi-shield-x"></i> Forfeit</span>
        <span>${canScore ? 'Select a match to enter or correct a score.' : 'Public bracket view. Scores are read-only for this role.'}</span>
      </div>

      <div class="hf-bracket" data-testid="bracket-tree">
        ${rounds
          .map(
            (round) => `
              <div class="hf-bracket-round">
                <div class="hf-bracket-round-title">${escapeHtml(round.label)} <span class="hf-small hf-muted">${round.matches.length}</span></div>
                ${round.matches.map((match) => components.bracketMatch(match)).join('')}
              </div>
            `
          )
          .join('')}
      </div>

      <div class="hf-bracket-mobile" data-testid="bracket-mobile">
        <div class="hf-round-pager" data-round-pager>
          ${rounds
            .map(
              (round, index) => `
                <button type="button" class="${index === mobileRound ? 'is-active' : ''}" data-mobile-round="${index}"
                        data-testid="bracket-round-tab-${index}">${escapeHtml(round.label)}</button>
              `
            )
            .join('')}
        </div>
        <div class="hf-round-progress"><span data-mobile-progress style="width: ${((mobileRound + 1) / rounds.length) * 100}%"></span></div>
        <div id="mobileRoundBody" data-testid="bracket-mobile-body"></div>
        <div class="hf-between hf-mt-2">
          <button type="button" class="hf-btn hf-btn--sm" data-mobile-prev data-testid="bracket-prev-round" ${mobileRound === 0 ? 'disabled' : ''}>
            <i class="bi bi-arrow-left"></i> Back
          </button>
          <span class="hf-small hf-muted" data-mobile-count>${mobileRound + 1} of ${rounds.length}</span>
          <button type="button" class="hf-btn hf-btn--sm" data-mobile-next data-testid="bracket-next-round" ${mobileRound === rounds.length - 1 ? 'disabled' : ''}>
            Next <i class="bi bi-arrow-right"></i>
          </button>
        </div>
      </div>

      <div class="hf-card hf-card--flat hf-mt-3">
        <p class="hf-small hf-muted hf-mb-0">Bracket progress is computed from match results. A correction that changes a winner rewrites the next-round slot, team record, standings, and audit trail in one action.</p>
      </div>
    `;

    attachBracket(rounds);
    if (requestedMatch && !openedFromQuery) {
      openedFromQuery = true;
      openMatch(requestedMatch);
    }
  }

  function paintMobileRound(rounds) {
    const body = root.querySelector('#mobileRoundBody');
    if (!body) return;
    body.innerHTML = rounds[mobileRound].matches
      .map((match) => components.bracketMatch(match))
      .join('');
    root.querySelectorAll('[data-mobile-round]').forEach((button, index) => {
      button.classList.toggle('is-active', index === mobileRound);
    });
    const progress = root.querySelector('[data-mobile-progress]');
    if (progress) progress.style.width = `${((mobileRound + 1) / rounds.length) * 100}%`;
    const prev = root.querySelector('[data-mobile-prev]');
    const next = root.querySelector('[data-mobile-next]');
    if (prev) prev.disabled = mobileRound === 0;
    if (next) next.disabled = mobileRound === rounds.length - 1;
    const label = root.querySelector('[data-mobile-count]');
    if (label) label.textContent = `${mobileRound + 1} of ${rounds.length}`;
    attachMatchButtons(body);
  }

  function attachGeneration() {
    const button = root.querySelector('[data-generate-bracket]');
    if (!button) return;
    button.addEventListener('click', async () => {
      const ok = await ui.confirm({
        title: 'Generate bracket?',
        message: 'Approved teams are seeded, byes fill the empty slots, and the seeds lock.',
        confirmLabel: 'Generate bracket',
      });
      if (!ok) return;
      ui.setBusy(button, true, 'Seeding...');
      window.setTimeout(() => {
        try {
          const result = store.actions.generateBracket(tournamentId);
          ui.toast(`${result.matches} matches created with ${result.byes} byes.`, {
            tone: 'success',
            title: 'Bracket generated',
          });
          draw();
        } catch (error) {
          ui.setBusy(button, false);
          ui.toast(error.message, { tone: 'danger', title: 'Could not generate bracket' });
        }
      }, 450);
    });
  }

  function attachMatchButtons(scope) {
    scope.querySelectorAll('[data-match-id]').forEach((element) => {
      element.addEventListener('click', () => openMatch(element.dataset.matchId));
      element.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          openMatch(element.dataset.matchId);
        }
      });
    });
  }

  function attachBracket(rounds) {
    attachMatchButtons(root.querySelector('[data-testid="bracket-tree"]'));
    paintMobileRound(rounds);

    root.querySelectorAll('[data-mobile-round]').forEach((button) => {
      button.addEventListener('click', () => {
        mobileRound = Number(button.dataset.mobileRound);
        paintMobileRound(rounds);
      });
    });
    root.querySelector('[data-mobile-prev]').addEventListener('click', () => {
      if (mobileRound > 0) {
        mobileRound -= 1;
        paintMobileRound(rounds);
      }
    });
    root.querySelector('[data-mobile-next]').addEventListener('click', () => {
      if (mobileRound < rounds.length - 1) {
        mobileRound += 1;
        paintMobileRound(rounds);
      }
    });
  }

  function openMatch(matchId) {
    const match = store.select.match(matchId);
    if (!match) return;
    const team1 = match.team1Id ? store.select.team(match.team1Id) : null;
    const team2 = match.team2Id ? store.select.team(match.team2Id) : null;
    const tournament = store.select.tournament(match.tournamentId);
    const court = tournament?.courts.find((item) => item.id === match.courtId);
    const role = store.getRole();
    const canScore =
      (role === 'organizer' || role === 'scorekeeper') &&
      match.status === 'scheduled' &&
      match.team1Id &&
      match.team2Id &&
      !match.isBye;
    const canCorrect =
      (role === 'organizer' || role === 'scorekeeper') &&
      (match.status === 'final' || match.status === 'forfeit');

    ui.dialog({
      title: `${match.roundLabel} · Slot ${match.bracketSlot}`,
      size: 'wide',
      body: `
        ${match.isBye ? '<p class="hf-small hf-muted">This is a bye. The seeded team advances automatically.</p>' : ''}
        <div class="hf-card hf-card--flat" data-testid="bracket-match-detail">
          <div class="hf-between hf-match-team ${match.winnerId === match.team1Id ? 'is-winner' : ''}">
            <span class="hf-flex hf-items-center hf-gap-2">
              ${team1 ? components.shield(team1) : ''}
              <strong>${escapeHtml(team1?.name || 'TBD')}</strong>
            </span>
            <span class="hf-score">${escapeHtml(String(match.score1 ?? '–'))}</span>
          </div>
          <div class="hf-between hf-match-team ${match.winnerId === match.team2Id ? 'is-winner' : ''}">
            <span class="hf-flex hf-items-center hf-gap-2">
              ${team2 ? components.shield(team2) : ''}
              <strong>${escapeHtml(team2?.name || 'TBD')}</strong>
            </span>
            <span class="hf-score">${escapeHtml(String(match.score2 ?? '–'))}</span>
          </div>
          <div class="hf-stat-strip hf-mt-2">
            <div><span>Status</span><strong>${escapeHtml(match.status)}</strong></div>
            <div><span>Court</span><strong>${escapeHtml(court?.name || 'TBD')}</strong></div>
            <div><span>Date</span><strong>${escapeHtml(match.scheduledAt ? formatDate(match.scheduledAt) : 'TBD')}</strong></div>
            <div><span>Tip-off</span><strong>${escapeHtml(match.scheduledAt ? formatTime(match.scheduledAt) : 'TBD')}</strong></div>
          </div>
        </div>
        ${
          match.correction
            ? `<div class="hf-card hf-card--flat hf-mt-2">
                 <span class="hf-chip hf-chip--danger">Corrected</span>
                 <p class="hf-small hf-muted hf-mt-1 hf-mb-0">${escapeHtml(match.correction.reason)} · previous ${match.correction.previousScore1}–${match.correction.previousScore2}</p>
               </div>`
            : ''
        }
      `,
      footer: `
        <button type="button" class="hf-btn" data-close-match data-testid="bracket-match-close">Close</button>
        <a class="hf-btn" href="${route('gameStats', { match: match.id })}" data-testid="bracket-match-stats">Game stats</a>
        ${canScore ? `<button type="button" class="hf-btn hf-btn--primary" data-open-score data-testid="bracket-enter-score">Enter final score</button>` : ''}
        ${canCorrect ? `<button type="button" class="hf-btn hf-btn--danger" data-open-correction data-testid="bracket-correct-score">Correct score</button>` : ''}
      `,
      onMount(element, close) {
        element.querySelector('[data-close-match]').addEventListener('click', close);
        element.querySelector('[data-open-score]')?.addEventListener('click', () => {
          close();
          openScoreConsole({ store, ui, matchId: match.id, onDone: draw });
        });
        element.querySelector('[data-open-correction]')?.addEventListener('click', () => {
          close();
          openCorrectionDialog({ store, ui, matchId: match.id, onDone: draw });
        });
      },
    });
  }

  draw();
}
```

- [ ] **Step 4: Check the modules parse**

```powershell
cd prototype
node --check assets/js/score-dialogs.js
node --check assets/js/pages/bracket.js
```

Expected: no output.

- [ ] **Step 5: Run the quarterfinal scoring demo**

Open `http://127.0.0.1:8001/brackets/view.html?match=qf-3` as organizer. Expected: the match dialog opens with Kapitolyo Knights vs Kalawaan Comets, both teams known, and “Enter final score”. Type `71` and `65` in the two final-score inputs, finalize, and confirm the Knights appear in semifinal slot 1. Open Standings and confirm the Knights show a win and the Comets a loss. Then open `qf-1`, correct `78–70` to `79–70` with a reason and confirm the audit log entry.

At 390px wide, confirm the desktop tree is replaced by the round pager and that Back/Next move between Round of 16, Quarterfinals, Semifinals, and Championship Final.

- [ ] **Step 6: Commit**

```powershell
git add prototype/assets/js/score-dialogs.js prototype/brackets/view.html prototype/assets/js/pages/bracket.js
git commit -m "feat(prototype): add bracket tree, mobile pager, and score console"
```

## Task 18: Schedule and matchups

**Files:**
- Rewrite: `prototype/schedule/index.html`
- Rewrite: `prototype/schedule/matches.html`
- Create: `prototype/assets/js/pages/schedule.js`
- Create: `prototype/assets/js/pages/matches.js`

**Interfaces:**
- Consumes: `route`, `escapeHtml`, `formatDay`, `formatDate`, `formatTime`, `ui.dialog/confirm/toast/setBusy`, `components.matchCard/kpiTile/pageHeader/sectionHeader`, `store.select.bracket/matches/kpis`, `store.planSchedule`, `store.actions.generateSchedule`.
- Produces: a month grid on desktop and a week strip on mobile, wired court filters, day selection, a schedule preview with court/rest validation, and a filterable matchup list grouped by day.

- [ ] **Step 1: Rewrite the schedule HTML**

Replace `prototype/schedule/index.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Schedule — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="schedule" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading schedule...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 2: Implement the schedule renderer**

Create `prototype/assets/js/pages/schedule.js`:

```js
import { route } from '../routes.js';
import { escapeHtml, formatDate, formatDay, formatTime } from '../ui.js';

function keyOf(value) {
  return value ? String(value).slice(0, 10) : '';
}

function parseKey(key) {
  return new Date(`${key}T00:00:00Z`);
}

function isoKey(date) {
  return date.toISOString().slice(0, 10);
}

function addDays(key, days) {
  const date = parseKey(key);
  date.setUTCDate(date.getUTCDate() + days);
  return isoKey(date);
}

function startOfMonth(key) {
  return `${key.slice(0, 7)}-01`;
}

function monthLabel(key) {
  return new Intl.DateTimeFormat('en-PH', {
    timeZone: 'UTC',
    month: 'long',
    year: 'numeric',
  }).format(parseKey(startOfMonth(key)));
}

function weekdayShort(key) {
  return new Intl.DateTimeFormat('en-PH', { timeZone: 'UTC', weekday: 'short' }).format(
    parseKey(key)
  );
}

function dayNumber(key) {
  return parseKey(key).getUTCDate();
}

function monthCells(anchor) {
  const first = parseKey(startOfMonth(anchor));
  const start = addDays(isoKey(first), -first.getUTCDay());
  return Array.from({ length: 42 }, (_, index) => addDays(start, index));
}

export function render({ store, ui, components, root }) {
  const params = new URLSearchParams(window.location.search);
  const tournamentId = params.get('id') || 'tournament-1';
  const tournament = store.select.tournament(tournamentId);

  if (!tournament) {
    root.innerHTML = ui.emptyState({
      icon: 'calendar3',
      title: 'Tournament not found',
      message: 'The schedule link is invalid or the demo has been reset.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}">Back to tournaments</a>`,
    });
    return;
  }

  const allMatches = store.select.matches(tournamentId);
  const scheduled = allMatches.filter((match) => match.scheduledAt && match.courtId);
  const matchDays = [...new Set(scheduled.map((match) => keyOf(match.scheduledAt)))].sort();
  let selected = params.get('date') || matchDays[0] || tournament.startDate;
  let month = startOfMonth(selected);
  const courtFilter = new Set(tournament.courts.map((court) => court.id));

  function visibleMatches() {
    return scheduled.filter((match) => courtFilter.has(match.courtId));
  }

  function matchesOn(key) {
    return visibleMatches().filter((match) => keyOf(match.scheduledAt) === key);
  }

  function draw() {
    const role = store.getRole();
    const bracket = store.select.bracket(tournamentId);
    const kpis = store.select.kpis(tournamentId);
    const unscheduled = allMatches.filter((match) => !match.scheduledAt && !match.isBye);
    const canGenerate =
      role === 'organizer' &&
      bracket.hasBracket &&
      !kpis.matchesPlayed &&
      !allMatches.some((match) => match.courtId);
    const locked = bracket.hasBracket && kpis.matchesPlayed > 0;

    root.innerHTML = `
      ${components.pageHeader({
        eyebrow: `${tournament.division} · ${tournament.venue}`,
        title: 'Court schedule',
        description: `${formatDate(tournament.startDate)} to ${formatDate(tournament.endDate)} · ${tournament.courts.length} courts · ${tournament.slotMinutes}-minute slots with ${tournament.restMinutes} minutes of rest.`,
        actions: `
          <a class="hf-btn" href="${route('tournamentShow', { id: tournamentId })}" data-testid="schedule-tournament"><i class="bi bi-arrow-left"></i> Tournament</a>
          <a class="hf-btn" href="${route('bracket', { id: tournamentId })}" data-testid="schedule-bracket"><i class="bi bi-diagram-3"></i> Bracket</a>
          <a class="hf-btn" href="${route('matches', { id: tournamentId })}" data-testid="schedule-matches"><i class="bi bi-list-ul"></i> Matchups</a>
        `,
      })}

      <div class="hf-grid hf-grid--4 hf-mb-3">
        ${components.kpiTile({ label: 'Scheduled', value: scheduled.length, hint: `of ${allMatches.length} bracket games`, tone: 'primary', testid: 'schedule-kpi-scheduled' })}
        ${components.kpiTile({ label: 'Matchdays', value: matchDays.length, hint: 'Days with at least one game', tone: 'gold', testid: 'schedule-kpi-days' })}
        ${components.kpiTile({ label: 'Courts', value: tournament.courts.length, hint: tournament.venue, tone: 'slate', testid: 'schedule-kpi-courts' })}
        ${components.kpiTile({ label: 'Results', value: kpis.matchesPlayed, hint: 'Final and forfeit', tone: 'success', testid: 'schedule-kpi-results' })}
      </div>

      <div class="hf-split">
        <div>
          <div class="hf-card hf-mb-3">
            <div class="hf-between hf-mb-2">
              <button type="button" class="hf-btn hf-btn--sm" data-month-prev data-testid="schedule-month-prev">
                <i class="bi bi-chevron-left"></i> Prev
              </button>
              <strong class="hf-section-title hf-mb-0" data-calendar-label data-testid="schedule-month-label">${escapeHtml(monthLabel(month))}</strong>
              <button type="button" class="hf-btn hf-btn--sm" data-month-next data-testid="schedule-month-next">
                Next <i class="bi bi-chevron-right"></i>
              </button>
            </div>
            <div class="hf-calendar" data-calendar data-testid="schedule-calendar"></div>
            <div class="hf-week-strip" data-week-strip data-testid="schedule-week"></div>
            <div class="hf-between hf-mt-2">
              <button type="button" class="hf-btn hf-btn--sm" data-week-prev data-testid="schedule-week-prev">
                <i class="bi bi-arrow-left"></i> Week
              </button>
              <button type="button" class="hf-btn hf-btn--sm" data-first-day data-testid="schedule-first-day">First matchday</button>
              <button type="button" class="hf-btn hf-btn--sm" data-week-next data-testid="schedule-week-next">
                Week <i class="bi bi-arrow-right"></i>
              </button>
            </div>
          </div>

          <div class="hf-card hf-mb-3">
            <div class="hf-between hf-mb-2">
              <span class="hf-label hf-mb-0">Court filters</span>
              <button type="button" class="hf-section-link" data-all-courts data-testid="schedule-all-courts">All courts</button>
            </div>
            <div class="hf-filter-row" data-court-filters>
              ${tournament.courts
                .map(
                  (court) => `
                    <button type="button" class="hf-chip hf-chip--${courtFilter.has(court.id) ? 'primary' : 'slate'}"
                            data-court-toggle="${escapeHtml(court.id)}" aria-pressed="${courtFilter.has(court.id)}"
                            data-testid="schedule-court-${escapeHtml(court.id)}">
                      <i class="bi bi-geo-alt"></i> ${escapeHtml(court.name)}
                    </button>
                  `
                )
                .join('')}
            </div>
          </div>

          <div id="scheduleList" data-testid="schedule-list"></div>
        </div>

        <aside>
          <div class="hf-card hf-mb-3">
            <span class="hf-kpi-label">Selected day</span>
            <h3 class="hf-section-title hf-mt-1">${escapeHtml(formatDay(selected))}</h3>
            <p class="hf-small hf-muted hf-mb-2" data-day-summary data-testid="schedule-day-summary"></p>
            <a class="hf-btn hf-btn--sm" href="${route('matches', { id: tournamentId, date: selected })}" data-testid="schedule-open-day">Open day list</a>
          </div>

          <div class="hf-card hf-mb-3">
            <span class="hf-kpi-label">Schedule generation</span>
            ${
              !bracket.hasBracket
                ? `<p class="hf-small hf-muted hf-mt-1 hf-mb-0">Generate the bracket before assigning courts.</p>`
                : canGenerate
                  ? `<p class="hf-small hf-muted hf-mt-1">The planner checks court double-bookings and team rest before publishing.</p>
                     <button type="button" class="hf-btn hf-btn--primary" data-generate-schedule data-testid="schedule-generate">Preview schedule</button>`
                  : `<p class="hf-small hf-muted hf-mt-1 hf-mb-0">${locked ? 'Results exist, so the schedule is locked.' : 'The court schedule is already published.'}</p>
                     <a class="hf-btn hf-btn--sm hf-mt-1" href="${route('matches', { id: tournamentId })}" data-testid="schedule-view-all">View all matchups</a>`
            }
          </div>

          <div class="hf-card">
            <span class="hf-kpi-label">Unscheduled</span>
            <h3 class="hf-section-title hf-mt-1">${unscheduled.length} games</h3>
            <p class="hf-small hf-muted hf-mb-0">${unscheduled.length ? 'These bracket games still need a court and time.' : 'Every non-bye game has a court and time.'}</p>
          </div>
        </aside>
      </div>
    `;

    renderCalendar();
    renderWeek();
    renderList();
    const summary = root.querySelector('[data-day-summary]');
    if (summary) {
      const dayMatches = matchesOn(selected);
      const courts = new Set(dayMatches.map((match) => match.courtId)).size;
      summary.textContent = `${dayMatches.length} games · ${courts} courts · first tip-off ${dayMatches.length ? formatTime(dayMatches[0].scheduledAt) : 'TBD'}`;
    }
  }

  function renderCalendar() {
    const calendar = root.querySelector('[data-calendar]');
    if (!calendar) return;
    calendar.innerHTML = `
      ${['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => `<div class="hf-calendar-head">${day}</div>`).join('')}
      ${monthCells(month)
        .map((key) => {
          const count = matchesOn(key).length;
          const outside = key.slice(0, 7) !== month.slice(0, 7);
          return `
            <button type="button" class="hf-calendar-cell ${count ? 'has-events' : ''} ${key === selected ? 'is-selected' : ''}"
                    data-day="${key}" data-testid="schedule-day-${key}"
                    style="${outside ? 'opacity: .45' : ''}">
              <span>${dayNumber(key)}</span>
              ${count ? `<span class="hf-calendar-count">${count} games</span>` : ''}
            </button>
          `;
        })
        .join('')}
    `;
  }

  function renderWeek() {
    const strip = root.querySelector('[data-week-strip]');
    if (!strip) return;
    const start = addDays(selected, -3);
    strip.innerHTML = Array.from({ length: 7 }, (_, index) => addDays(start, index))
      .map((key) => {
        const count = matchesOn(key).length;
        return `
          <button type="button" class="${key === selected ? 'is-selected' : ''}" data-day="${key}"
                  data-testid="schedule-week-${key}">
            <small>${weekdayShort(key)}</small>
            <strong>${dayNumber(key)}</strong>
            ${count ? `<span class="hf-calendar-count">${count}</span>` : ''}
          </button>
        `;
      })
      .join('');
  }

  function renderList() {
    const list = root.querySelector('#scheduleList');
    if (!list) return;
    const dayMatches = matchesOn(selected).sort((a, b) =>
      String(a.scheduledAt).localeCompare(String(b.scheduledAt))
    );

    list.innerHTML = `
      ${components.sectionHeader({
        title: dayMatches.length ? `${formatDay(selected)}` : 'No games on this day',
        action: `<span class="hf-chip hf-chip--slate">${dayMatches.length} games</span>`,
      })}
      ${
        dayMatches.length
          ? `<div class="hf-grid hf-grid--2" data-testid="schedule-day-list">${dayMatches
              .map((match) =>
                components.matchCard(match, {
                  href: route('gameStats', { match: match.id }),
                  testid: `schedule-match-${match.id}`,
                })
              )
              .join('')}</div>`
          : ui.emptyState({
              icon: 'calendar3',
              title: 'No games scheduled',
              message: 'Choose another day, re-enable a court filter, or publish a schedule from the tournament desk.',
            })
      }
    `;
  }

  function openPreview(button) {
    let plan;
    try {
      plan = store.planSchedule(tournamentId);
    } catch (error) {
      ui.toast(error.message, { tone: 'danger', title: 'Could not plan schedule' });
      return;
    }

    const rows = plan.assignments
      .map((assignment) => {
        const match = store.select.match(assignment.matchId);
        const court = plan.courts.find((item) => item.id === assignment.courtId);
        const team1 = match.team1Id ? store.select.team(match.team1Id)?.shortName || 'TBD' : 'TBD';
        const team2 = match.team2Id ? store.select.team(match.team2Id)?.shortName || 'TBD' : 'TBD';
        return `
          <tr>
            <td>${escapeHtml(match.roundLabel)}</td>
            <td>${escapeHtml(team1)} vs ${escapeHtml(team2)}</td>
            <td>${escapeHtml(court?.name || '')}</td>
            <td>${escapeHtml(formatDate(assignment.scheduledAt))} · ${escapeHtml(formatTime(assignment.scheduledAt))}</td>
          </tr>
        `;
      })
      .join('');

    ui.dialog({
      title: 'Schedule preview',
      size: 'wide',
      body: `
        <p class="hf-small hf-muted">${plan.assignments.length} games across ${plan.courts.length} courts. No court double-bookings; every team keeps at least ${plan.restMinutes} minutes of rest.</p>
        <div class="hf-table-wrap">
          <table class="hf-table">
            <thead><tr><th scope="col">Round</th><th scope="col">Match</th><th scope="col">Court</th><th scope="col">Slot</th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      `,
      footer: `
        <button type="button" class="hf-btn" data-cancel-schedule data-testid="schedule-preview-cancel">Cancel</button>
        <button type="button" class="hf-btn hf-btn--primary" data-publish-schedule data-testid="schedule-preview-publish">Publish schedule</button>
      `,
      onMount(element, close) {
        element.querySelector('[data-cancel-schedule]').addEventListener('click', close);
        element.querySelector('[data-publish-schedule]').addEventListener('click', () => {
          ui.setBusy(button, true, 'Publishing...');
          try {
            const count = store.actions.generateSchedule(tournamentId);
            close();
            const first = plan.assignments[0]?.scheduledAt;
            if (first) {
              selected = keyOf(first);
              month = startOfMonth(selected);
            }
            ui.toast(`${count} games scheduled across ${plan.courts.length} courts.`, {
              tone: 'success',
              title: 'Schedule published',
            });
            draw();
          } catch (error) {
            ui.setBusy(button, false);
            ui.toast(error.message, { tone: 'danger', title: 'Could not publish schedule' });
          }
        });
      },
    });
  }

  root.addEventListener('click', (event) => {
    const dayButton = event.target.closest('[data-day]');
    if (dayButton) {
      selected = dayButton.dataset.day;
      month = startOfMonth(selected);
      draw();
      return;
    }

    if (event.target.closest('[data-month-prev]')) {
      month = startOfMonth(addDays(month, -1));
      selected = month;
      draw();
      return;
    }
    if (event.target.closest('[data-month-next]')) {
      month = startOfMonth(addDays(startOfMonth(month), 32));
      selected = month;
      draw();
      return;
    }
    if (event.target.closest('[data-week-prev]')) {
      selected = addDays(selected, -7);
      month = startOfMonth(selected);
      draw();
      return;
    }
    if (event.target.closest('[data-week-next]')) {
      selected = addDays(selected, 7);
      month = startOfMonth(selected);
      draw();
      return;
    }
    if (event.target.closest('[data-first-day]')) {
      selected = matchDays[0] || tournament.startDate;
      month = startOfMonth(selected);
      draw();
      return;
    }

    const courtButton = event.target.closest('[data-court-toggle]');
    if (courtButton) {
      const id = courtButton.dataset.courtToggle;
      if (courtFilter.has(id)) courtFilter.delete(id);
      else courtFilter.add(id);
      draw();
      return;
    }
    if (event.target.closest('[data-all-courts]')) {
      tournament.courts.forEach((court) => courtFilter.add(court.id));
      draw();
      return;
    }

    const generate = event.target.closest('[data-generate-schedule]');
    if (generate) {
      openPreview(generate);
    }
  });

  draw();
}
```

- [ ] **Step 3: Rewrite the matchups HTML**

Replace `prototype/schedule/matches.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Matchups — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="matches" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading matchups...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 4: Implement the matchups renderer**

Create `prototype/assets/js/pages/matches.js`:

```js
import { route } from '../routes.js';
import { escapeHtml, formatDay } from '../ui.js';

function keyOf(value) {
  return value ? String(value).slice(0, 10) : '';
}

export function render({ store, ui, components, root }) {
  const params = new URLSearchParams(window.location.search);
  const tournamentId = params.get('id') || 'tournament-1';
  const tournament = store.select.tournament(tournamentId);

  if (!tournament) {
    root.innerHTML = ui.emptyState({
      icon: 'list-ul',
      title: 'Tournament not found',
      message: 'The matchup link is invalid or the demo has been reset.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}">Back to tournaments</a>`,
    });
    return;
  }

  let searchTerm = params.get('q') || '';
  let dateFilter = params.get('date') || '';
  let courtFilter = params.get('court') || 'all';
  let statusFilter = params.get('status') || 'all';

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: `${tournament.division} · ${tournament.name}`,
      title: 'Matchups',
      description: 'Every bracket game grouped by matchday, with court, status, and a link to the official box score.',
      actions: `
        <a class="hf-btn" href="${route('schedule', { id: tournamentId })}" data-testid="matches-schedule"><i class="bi bi-calendar3"></i> Schedule</a>
        <a class="hf-btn" href="${route('bracket', { id: tournamentId })}" data-testid="matches-bracket"><i class="bi bi-diagram-3"></i> Bracket</a>
      `,
    })}

    <div class="hf-card hf-mb-3">
      <div class="hf-form-grid">
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="matchSearch">Search</label>
          <input class="hf-input" id="matchSearch" type="search" placeholder="Team or round"
                 value="${escapeHtml(searchTerm)}" data-testid="match-search">
        </div>
        <div class="hf-field">
          <label class="hf-label" for="matchDate">Day</label>
          <input class="hf-input" id="matchDate" type="date" value="${escapeHtml(dateFilter)}" data-testid="match-date-filter">
        </div>
        <div class="hf-field">
          <label class="hf-label" for="matchCourt">Court</label>
          <select class="hf-select" id="matchCourt" data-testid="match-court-filter">
            <option value="all">All courts</option>
            ${tournament.courts.map((court) => `<option value="${escapeHtml(court.id)}">${escapeHtml(court.name)}</option>`).join('')}
          </select>
        </div>
        <div class="hf-field">
          <label class="hf-label" for="matchStatus">Status</label>
          <select class="hf-select" id="matchStatus" data-testid="match-status-filter">
            <option value="all">All statuses</option>
            <option value="scheduled">Scheduled</option>
            <option value="final">Final</option>
            <option value="forfeit">Forfeit</option>
          </select>
        </div>
      </div>
    </div>

    <div id="matchList" data-testid="match-list"></div>
  `;

  root.querySelector('#matchCourt').value = courtFilter;
  root.querySelector('#matchStatus').value = statusFilter;

  const search = root.querySelector('#matchSearch');
  const date = root.querySelector('#matchDate');
  const court = root.querySelector('#matchCourt');
  const status = root.querySelector('#matchStatus');
  const list = root.querySelector('#matchList');

  function renderList() {
    const term = search.value.trim().toLowerCase();
    const filtered = store
      .select.matches(tournamentId)
      .filter((match) => {
        const team1 = match.team1Id ? store.select.team(match.team1Id) : null;
        const team2 = match.team2Id ? store.select.team(match.team2Id) : null;
        const haystack = `${team1?.name || ''} ${team2?.name || ''} ${match.roundLabel}`.toLowerCase();
        return (
          (court.value === 'all' || match.courtId === court.value) &&
          (status.value === 'all' || match.status === status.value) &&
          (!date.value || keyOf(match.scheduledAt) === date.value) &&
          (!term || haystack.includes(term))
        );
      })
      .sort((a, b) => String(a.scheduledAt || '9999').localeCompare(String(b.scheduledAt || '9999')));

    if (!filtered.length) {
      list.innerHTML = ui.emptyState({
        icon: 'search',
        title: 'No matchups match',
        message: 'Clear the day, court, or status filter and try again.',
        action: `<button type="button" class="hf-btn" data-clear-match-filters data-testid="match-clear-filters">Clear filters</button>`,
      });
      list.querySelector('[data-clear-match-filters]')?.addEventListener('click', () => {
        search.value = '';
        date.value = '';
        court.value = 'all';
        status.value = 'all';
        renderList();
      });
      return;
    }

    const groups = new Map();
    filtered.forEach((match) => {
      const key = keyOf(match.scheduledAt) || 'unscheduled';
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push(match);
    });

    list.innerHTML = [...groups.entries()]
      .map(([key, matches]) => `
        <section class="hf-mb-3" data-testid="match-day-${escapeHtml(key)}">
          <div class="hf-section-head">
            <h2 class="hf-section-title">${key === 'unscheduled' ? 'Unscheduled' : escapeHtml(formatDay(key))}</h2>
            <span class="hf-chip hf-chip--slate">${matches.length} games</span>
          </div>
          <div class="hf-grid hf-grid--2">
            ${matches
              .map((match) =>
                components.matchCard(match, {
                  href: route('gameStats', { match: match.id }),
                  testid: `match-card-${match.id}`,
                })
              )
              .join('')}
          </div>
        </section>
      `)
      .join('');
  }

  search.addEventListener('input', ui.debounce(renderList, 120));
  date.addEventListener('change', renderList);
  court.addEventListener('change', renderList);
  status.addEventListener('change', renderList);

  renderList();
}
```

- [ ] **Step 5: Verify the schedule and matchups**

Open `http://127.0.0.1:8001/schedule/index.html` as organizer. Expected: May 16 selected by default, court chips all active, and four Round of 16 cards. Toggle Court 1 off; the day count and list drop to the games on Courts 2–4. Click May 18 and confirm the two quarterfinals appear. Open a final card and confirm the box score page loads.

Open `http://127.0.0.1:8001/schedule/matches.html?id=tournament-3`; expected: an empty matchup state because the CDO tournament has no bracket. Generate the CDO bracket and schedule from the tournament desk, then reload; expected: seven games grouped across three matchdays.

- [ ] **Step 6: Check the modules parse and commit**

```powershell
cd prototype
node --check assets/js/pages/schedule.js
node --check assets/js/pages/matches.js
```

Expected: no output.

```powershell
git add prototype/schedule/index.html prototype/schedule/matches.html prototype/assets/js/pages/schedule.js prototype/assets/js/pages/matches.js
git commit -m "feat(prototype): add schedule calendar, court filters, and matchup list"
```

## Task 19: Standings search and game box scores

**Files:**
- Modify: `prototype/assets/js/components.js`
- Create: `prototype/assets/js/stats.js`
- Create: `prototype/tests/stats.test.mjs`
- Rewrite: `prototype/standings/table.html`
- Rewrite: `prototype/games/stats.html`
- Create: `prototype/assets/js/pages/standings.js`
- Create: `prototype/assets/js/pages/game-stats.js`

**Interfaces:**
- Consumes: `route`, `escapeHtml`, `formatDate`, `formatTime`, `ui.debounce/toast`, `components.pageHeader/kpiTile/sectionHeader/standingsTable/scoreboard/statusChip`, `store.select.tournament/tournaments/standings/kpis/match/team`, `store.can`, `openScoreConsole/openCorrectionDialog`.
- Produces: `boxScoreRows(match, team)`, `sortBoxScore(rows, key, direction)`, and `boxScoreTotals(rows)` from `stats.js`; a standings page with search, qualification filters, and a tournament switcher; a game-stats page with team filters, player search, sortable box scores, score entry, and correction history.

- [ ] **Step 1: Add the standings query option**

In `prototype/assets/js/components.js`, replace the whole `standingsTable` function with:

```js
  function standingsTable(tournamentId, { limit = null, query = null } = {}) {
    const allRows = store.select.standings(tournamentId);
    if (!allRows.length) {
      return ui.emptyState({
        icon: 'bar-chart',
        title: 'No standings yet',
        message: 'Standings appear as soon as approved teams have recorded results.',
      });
    }

    const term = String(query?.search || '').trim().toLowerCase();
    const filter = query?.filter || 'all';
    const rows = allRows.filter((row) => {
      const haystack = `${row.team.name} ${row.team.shortName} ${row.team.city}`.toLowerCase();
      const matchesTerm = !term || haystack.includes(term);
      const matchesFilter =
        filter === 'all' ||
        (filter === 'qualified' && row.qualified) ||
        (filter === 'wins' && row.wins > 0) ||
        (filter === 'winless' && row.wins === 0);
      return matchesTerm && matchesFilter;
    });

    if (!rows.length) {
      return ui.emptyState({
        icon: 'search',
        title: 'No teams match',
        message: 'Clear the search or choose another standings filter.',
      });
    }

    const visible = limit ? rows.slice(0, limit) : rows;
    const lastQualified = !query && !limit ? visible.filter((row) => row.qualified).length : 0;
    const table = `
      <div class="hf-table-wrap">
        <table class="hf-table hf-table--standings">
          <thead>
            <tr>
              <th scope="col">#</th>
              <th scope="col">Team</th>
              <th scope="col" class="is-num">W</th>
              <th scope="col" class="is-num">L</th>
              <th scope="col" class="is-num">PF</th>
              <th scope="col" class="is-num">PA</th>
              <th scope="col" class="is-num">+/-</th>
              <th scope="col" class="is-num">Seed</th>
            </tr>
          </thead>
          <tbody>
            ${visible
              .map(
                (row, index) => `
                  <tr class="${row.qualified ? 'is-qualified' : ''}" data-testid="standings-row-${escapeHtml(row.teamId)}">
                    <td class="is-num">${row.rank}</td>
                    <td>
                      <a class="hf-flex hf-items-center hf-gap-1" href="../teams/show.html?id=${encodeURIComponent(row.teamId)}">
                        ${shield(row.team)}
                        <strong>${escapeHtml(row.team.name)}</strong>
                      </a>
                    </td>
                    <td class="is-num">${row.wins}</td>
                    <td class="is-num">${row.losses}</td>
                    <td class="is-num">${row.pointsFor}</td>
                    <td class="is-num">${row.pointsAgainst}</td>
                    <td class="is-num">${row.diff > 0 ? '+' : ''}${row.diff}</td>
                    <td class="is-num">${row.seed ?? '–'}</td>
                  </tr>
                  ${lastQualified === index + 1 ? '<tr class="hf-qual-line"><td colspan="8"></td></tr>' : ''}
                `
              )
              .join('')}
          </tbody>
        </table>
      </div>
    `;
    const cards = `
      <div class="hf-standings-cards">
        ${visible
          .map(
            (row) => `
              <a class="hf-card hf-card--flat" href="../teams/show.html?id=${encodeURIComponent(row.teamId)}" data-testid="standings-card-${escapeHtml(row.teamId)}">
                <div class="hf-between">
                  <span class="hf-flex hf-items-center hf-gap-1">
                    <span class="hf-kpi-label">#${row.rank}</span>
                    ${shield(row.team)}
                    <strong>${escapeHtml(row.team.name)}</strong>
                  </span>
                  ${row.qualified ? '<span class="hf-chip hf-chip--gold">Playoff cut</span>' : ''}
                </div>
                <div class="hf-flex hf-gap-3 hf-mt-1 hf-small">
                  <span><strong>${row.wins}</strong> W</span>
                  <span><strong>${row.losses}</strong> L</span>
                  <span><strong>${row.diff > 0 ? '+' : ''}${row.diff}</strong> DIFF</span>
                </div>
              </a>
            `
          )
          .join('')}
      </div>
    `;
    return table + cards;
  }
```

- [ ] **Step 2: Write the failing box-score tests**

Create `prototype/tests/stats.test.mjs`:

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { SEED } from '../assets/js/seed.js';
import { boxScoreRows, boxScoreTotals, sortBoxScore } from '../assets/js/stats.js';

const match = SEED.matches.find((item) => item.id === 'qf-1');
const team = SEED.teams.find((item) => item.id === match.team1Id);

test('boxScoreRows joins box score entries to the roster', () => {
  const rows = boxScoreRows(match, team);
  assert.equal(rows.length, team.players.length);
  assert.equal(rows[0].playerId, team.players[0].id);
  assert.equal(rows[0].name, team.players[0].name);
  assert.equal(rows[0].number, team.players[0].number);
  assert.equal(rows.reduce((sum, row) => sum + row.points, 0), 78);
});

test('boxScoreRows falls back when a player left the roster', () => {
  const stale = {
    ...match,
    boxScore: {
      team1: [{ playerId: 'player-missing', points: 4, assists: 1, rebounds: 2 }],
      team2: [],
    },
  };
  const rows = boxScoreRows(stale, team);
  assert.equal(rows.length, 1);
  assert.equal(rows[0].name, 'Unknown player');
  assert.equal(rows[0].number, null);
});

test('sortBoxScore sorts by points by default and supports ascending', () => {
  const rows = [
    { name: 'Bautista', number: 12, points: 4, assists: 2, rebounds: 1 },
    { name: 'Aquino', number: 7, points: 9, assists: 1, rebounds: 5 },
    { name: 'Cruz', number: 3, points: 4, assists: 6, rebounds: 4 },
  ];
  assert.deepEqual(sortBoxScore(rows).map((row) => row.name), ['Aquino', 'Bautista', 'Cruz']);
  assert.deepEqual(sortBoxScore(rows, 'number', 'asc').map((row) => row.name), ['Cruz', 'Aquino', 'Bautista']);
  assert.deepEqual(sortBoxScore(rows, 'rebounds', 'desc').map((row) => row.name), ['Aquino', 'Cruz', 'Bautista']);
});

test('boxScoreTotals sums the visible rows', () => {
  const rows = boxScoreRows(match, team);
  const totals = boxScoreTotals(rows);
  assert.equal(totals.points, 78);
  assert.equal(totals.assists, rows.reduce((sum, row) => sum + row.assists, 0));
  assert.equal(totals.rebounds, rows.reduce((sum, row) => sum + row.rebounds, 0));
});
```

- [ ] **Step 3: Run the tests to confirm they fail**

```powershell
cd prototype
node --test tests/stats.test.mjs
```

Expected: FAIL because `../assets/js/stats.js` does not exist.

- [ ] **Step 4: Implement the box-score helpers**

Create `prototype/assets/js/stats.js`:

```js
const SORTABLE = new Set(['number', 'name', 'points', 'assists', 'rebounds']);

export function boxScoreRows(match, team) {
  if (!match || !team) return [];
  const side = match.team1Id === team.id ? 'team1' : match.team2Id === team.id ? 'team2' : null;
  if (!side) return [];
  const entries = match.boxScore?.[side] || [];
  const roster = new Map((team.players || []).map((player) => [player.id, player]));
  return entries.map((entry) => {
    const player = roster.get(entry.playerId);
    return {
      playerId: entry.playerId,
      name: player?.name || 'Unknown player',
      number: player?.number ?? null,
      position: player?.position || '',
      points: Number(entry.points) || 0,
      assists: Number(entry.assists) || 0,
      rebounds: Number(entry.rebounds) || 0,
    };
  });
}

export function sortBoxScore(rows, key = 'points', direction = 'desc') {
  const column = SORTABLE.has(key) ? key : 'points';
  const factor = direction === 'asc' ? 1 : -1;
  return [...rows].sort((a, b) => {
    if (column === 'name') return a.name.localeCompare(b.name) * factor;
    const first = Number(a[column] ?? 0);
    const second = Number(b[column] ?? 0);
    return (first - second) * factor || a.name.localeCompare(b.name);
  });
}

export function boxScoreTotals(rows) {
  return rows.reduce(
    (totals, row) => ({
      points: totals.points + row.points,
      assists: totals.assists + row.assists,
      rebounds: totals.rebounds + row.rebounds,
    }),
    { points: 0, assists: 0, rebounds: 0 }
  );
}
```

- [ ] **Step 5: Run the box-score tests**

```powershell
cd prototype
node --test tests/stats.test.mjs
```

Expected: all 4 tests PASS.

- [ ] **Step 6: Rewrite the standings HTML**

Replace `prototype/standings/table.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Standings — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="standings" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading standings...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 7: Implement the standings renderer**

Create `prototype/assets/js/pages/standings.js`:

```js
import { route } from '../routes.js';
import { escapeHtml } from '../ui.js';

const FILTERS = [
  { value: 'all', label: 'All teams' },
  { value: 'qualified', label: 'Playoff cut' },
  { value: 'wins', label: 'With a win' },
  { value: 'winless', label: 'Winless' },
];

export function render({ store, ui, components, root }) {
  const params = new URLSearchParams(window.location.search);
  const tournamentId = params.get('id') || 'tournament-1';
  const tournament = store.select.tournament(tournamentId);

  if (!tournament) {
    root.innerHTML = ui.emptyState({
      icon: 'bar-chart',
      title: 'Tournament not found',
      message: 'The standings link is invalid or the demo has been reset.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}">Back to tournaments</a>`,
    });
    return;
  }

  let filter = FILTERS.some((item) => item.value === params.get('filter'))
    ? params.get('filter')
    : 'all';
  let searchTerm = params.get('q') || '';
  const cut = Math.ceil(tournament.slots / 2);

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: `${tournament.division} · ${tournament.name}`,
      title: 'Standings',
      description: 'Wins, losses, point differential, and the playoff cut — computed from every recorded result.',
      actions: `
        <label class="hf-sr-only" for="standingsTournament">Tournament</label>
        <select class="hf-select" id="standingsTournament" data-testid="standings-tournament" style="width: auto; min-width: 210px">
          ${store
            .select.tournaments()
            .map(
              (item) =>
                `<option value="${escapeHtml(item.id)}" ${item.id === tournamentId ? 'selected' : ''}>${escapeHtml(item.name)}</option>`
            )
            .join('')}
        </select>
        <a class="hf-btn" href="${route('bracket', { id: tournamentId })}" data-testid="standings-bracket"><i class="bi bi-diagram-3"></i> Bracket</a>
        <a class="hf-btn" href="${route('matches', { id: tournamentId })}" data-testid="standings-matches"><i class="bi bi-list-ul"></i> Matchups</a>
      `,
    })}

    <div class="hf-grid hf-grid--4 hf-mb-3" id="standingsKpis" data-testid="standings-kpis"></div>

    <div class="hf-card hf-mb-3">
      <div class="hf-field">
        <label class="hf-label" for="standingsSearch">Search teams</label>
        <input class="hf-input" id="standingsSearch" type="search" placeholder="Team, short name, or city"
               value="${escapeHtml(searchTerm)}" data-testid="standings-search">
      </div>
      <div class="hf-filter-row hf-mt-2" data-standings-filters data-testid="standings-filters">
        ${FILTERS.map(
          (item) => `
            <button type="button" class="hf-chip hf-chip--slate" data-standings-filter="${item.value}"
                    aria-pressed="false" data-testid="standings-filter-${item.value}">
              ${escapeHtml(item.label)}
            </button>
          `
        ).join('')}
      </div>
    </div>

    <div id="standingsBody" data-testid="standings-body"></div>

    <div class="hf-card hf-card--flat hf-mt-3">
      <p class="hf-small hf-muted hf-mb-0">
        <strong>Tiebreakers:</strong> wins, then point differential, then points for, then registration seed.
        The top ${cut} teams sit above the gold qualification line.
      </p>
    </div>
  `;

  const search = root.querySelector('#standingsSearch');

  function paintKpis() {
    const rows = store.select.standings(tournamentId);
    const kpis = store.select.kpis(tournamentId);
    const leader = rows[0] || null;
    const bestDiff = rows.reduce(
      (best, row) => (!best || row.diff > best.diff ? row : best),
      null
    );
    root.querySelector('#standingsKpis').innerHTML = [
      components.kpiTile({
        label: 'Leader',
        value: leader?.team.shortName || 'TBD',
        hint: leader ? `${leader.wins}-${leader.losses} · ${leader.team.name}` : 'No results yet',
        tone: 'gold',
        testid: 'standings-kpi-leader',
      }),
      components.kpiTile({
        label: 'Playoff cut',
        value: `Top ${cut}`,
        hint: `${rows.filter((row) => row.qualified).length} teams above the line`,
        tone: 'primary',
        testid: 'standings-kpi-cut',
      }),
      components.kpiTile({
        label: 'Best diff',
        value: bestDiff ? `${bestDiff.diff > 0 ? '+' : ''}${bestDiff.diff}` : '0',
        hint: bestDiff?.team.shortName || 'No games recorded',
        tone: 'success',
        testid: 'standings-kpi-diff',
      }),
      components.kpiTile({
        label: 'Results',
        value: kpis.matchesPlayed,
        hint: 'Final and forfeit games',
        tone: 'slate',
        testid: 'standings-kpi-results',
      }),
    ].join('');
  }

  function paintFilters() {
    root.querySelectorAll('[data-standings-filter]').forEach((button) => {
      const active = button.dataset.standingsFilter === filter;
      button.classList.toggle('hf-chip--primary', active);
      button.classList.toggle('hf-chip--slate', !active);
      button.setAttribute('aria-pressed', String(active));
    });
  }

  function paintTable() {
    root.querySelector('#standingsBody').innerHTML = components.standingsTable(tournamentId, {
      query: { search: searchTerm, filter },
    });
    paintFilters();
  }

  root.querySelector('#standingsTournament').addEventListener('change', (event) => {
    window.location.href = route('standings', { id: event.target.value });
  });

  root.querySelectorAll('[data-standings-filter]').forEach((button) => {
    button.addEventListener('click', () => {
      filter = button.dataset.standingsFilter;
      paintTable();
    });
  });

  search.addEventListener(
    'input',
    ui.debounce(() => {
      searchTerm = search.value;
      paintTable();
    }, 140)
  );

  paintKpis();
  paintTable();
}
```

- [ ] **Step 8: Rewrite the game-stats HTML**

Replace `prototype/games/stats.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Game stats — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="game-stats" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading box score...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 9: Implement the game-stats renderer**

Create `prototype/assets/js/pages/game-stats.js`:

```js
import { route } from '../routes.js';
import { escapeHtml, formatDate, formatTime } from '../ui.js';
import { boxScoreRows, boxScoreTotals, sortBoxScore } from '../stats.js';
import { openCorrectionDialog, openScoreConsole } from '../score-dialogs.js';

const COLUMNS = [
  { key: 'number', label: '#', numeric: true, sortable: false },
  { key: 'name', label: 'Player', numeric: false, sortable: false },
  { key: 'points', label: 'PTS', numeric: true, sortable: true },
  { key: 'assists', label: 'AST', numeric: true, sortable: true },
  { key: 'rebounds', label: 'REB', numeric: true, sortable: true },
];

export function render({ store, ui, components, root }) {
  const params = new URLSearchParams(window.location.search);
  const matchId = params.get('match') || '';
  const match = matchId ? store.select.match(matchId) : null;

  if (!match) {
    root.innerHTML = ui.emptyState({
      icon: 'graph-up',
      title: 'Game not found',
      message: 'Open a game from the bracket, schedule, or matchup list.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('matches')}">Browse matchups</a>`,
    });
    return;
  }

  let side = 'all';
  let searchTerm = '';
  let sortKey = 'points';
  let sortDir = 'desc';

  function draw() {
    const current = store.select.match(matchId);
    if (!current) {
      root.innerHTML = ui.emptyState({
        icon: 'graph-up',
        title: 'Game not found',
        message: 'This game disappeared when the demo was reset.',
        action: `<a class="hf-btn hf-btn--primary" href="${route('matches')}">Browse matchups</a>`,
      });
      return;
    }

    const tournament = store.select.tournament(current.tournamentId);
    const team1 = current.team1Id ? store.select.team(current.team1Id) : null;
    const team2 = current.team2Id ? store.select.team(current.team2Id) : null;
    const role = store.getRole();
    const canScore =
      store.can('score.save') &&
      current.status === 'scheduled' &&
      Boolean(team1) &&
      Boolean(team2);
    const canCorrect =
      store.can('score.correct') &&
      (current.status === 'final' || current.status === 'forfeit');
    const court = tournament?.courts.find((item) => item.id === current.courtId);
    const hasTeams = Boolean(team1) && Boolean(team2);
    const hasStats = [team1, team2].some(
      (team) => team && boxScoreRows(current, team).length > 0
    );

    root.innerHTML = `
      ${components.pageHeader({
        eyebrow: `${tournament?.division || 'Tournament'} · ${tournament?.name || 'HoopFlow'}`,
        title: 'Game stats',
        description: `${current.roundLabel} · ${court?.name || 'Court TBD'} · ${current.scheduledAt ? `${formatDate(current.scheduledAt)} at ${formatTime(current.scheduledAt)}` : 'Tip-off TBD'}.`,
        actions: `
          <a class="hf-btn" href="${route('bracket', { id: current.tournamentId, match: current.id })}" data-testid="stats-bracket"><i class="bi bi-diagram-3"></i> Bracket</a>
          <a class="hf-btn" href="${route('schedule', { id: current.tournamentId })}" data-testid="stats-schedule"><i class="bi bi-calendar3"></i> Schedule</a>
          <a class="hf-btn" href="${route('matches', { id: current.tournamentId })}" data-testid="stats-matches"><i class="bi bi-list-ul"></i> Matchups</a>
        `,
      })}

      ${components.scoreboard(current)}

      ${
        current.correction
          ? `<div class="hf-card hf-card--flat hf-mb-3" data-testid="stats-correction">
               <div class="hf-between hf-wrap">
                 <span class="hf-chip hf-chip--gold"><i class="bi bi-arrow-repeat"></i> Corrected score</span>
                 <span class="hf-small hf-muted">${escapeHtml(formatDate(current.correction.correctedAt))} · ${escapeHtml(formatTime(current.correction.correctedAt))}</span>
               </div>
               <p class="hf-small hf-mt-1 hf-mb-0">
                 <strong>${current.correction.previousScore1}–${current.correction.previousScore2}</strong>
                 →
                 <strong>${current.score1}–${current.score2}</strong>
                 · ${escapeHtml(current.correction.reason)}
               </p>
             </div>`
          : ''
      }

      <div class="hf-card hf-card--flat hf-mb-3" data-testid="stats-controls">
        <div class="hf-between hf-wrap">
          <div>
            <span class="hf-kpi-label">Official result</span>
            <p class="hf-small hf-muted hf-mb-0">
              ${
                current.status === 'scheduled'
                  ? hasTeams
                    ? 'Both teams are set. Enter the final score to advance the winner and generate the box score.'
                    : 'Waiting for both semifinal results before this game can be played.'
                  : `Recorded as ${current.status}. Corrections require a reason and are written to the audit trail.`
              }
            </p>
          </div>
          <div class="hf-btn-row">
            ${canScore ? `<button type="button" class="hf-btn hf-btn--primary" data-open-score data-testid="stats-enter-score"><i class="bi bi-pencil-square"></i> Enter final score</button>` : ''}
            ${canCorrect ? `<button type="button" class="hf-btn hf-btn--danger" data-open-correction data-testid="stats-correct-score"><i class="bi bi-arrow-repeat"></i> Correct score</button>` : ''}
            ${!canScore && !canCorrect ? `<span class="hf-chip hf-chip--slate"><i class="bi bi-eye"></i> Read-only ${escapeHtml(role)} view</span>` : ''}
          </div>
        </div>
      </div>

      <section class="hf-card hf-mb-3" data-testid="stats-box-score">
        <div class="hf-between hf-wrap hf-mb-2">
          <div>
            <span class="hf-eyebrow">Official box score</span>
            <h2 class="hf-section-title hf-mb-0">Player stats</h2>
          </div>
          <div class="hf-filter-row" data-side-filters data-testid="stats-side-filters">
            <button type="button" class="hf-chip hf-chip--primary" data-side="all" aria-pressed="true" data-testid="stats-side-all">Both teams</button>
            ${team1 ? `<button type="button" class="hf-chip hf-chip--slate" data-side="team1" aria-pressed="false" data-testid="stats-side-team1">${escapeHtml(team1.shortName)}</button>` : ''}
            ${team2 ? `<button type="button" class="hf-chip hf-chip--slate" data-side="team2" aria-pressed="false" data-testid="stats-side-team2">${escapeHtml(team2.shortName)}</button>` : ''}
          </div>
        </div>

        <div class="hf-field hf-mb-2">
          <label class="hf-label" for="statsSearch">Search player</label>
          <input class="hf-input" id="statsSearch" type="search" placeholder="Player name"
                 value="${escapeHtml(searchTerm)}" data-testid="stats-player-search">
        </div>

        <div id="statsBody" data-testid="stats-body"></div>

        ${
          !hasStats && current.status !== 'scheduled'
            ? `<p class="hf-small hf-muted hf-mt-2 hf-mb-0">${hasTeams ? 'No player stats were recorded for this game. The team score is still official.' : 'Player stats appear once both teams are decided and a result is recorded.'}</p>`
            : ''
        }
      </section>
    `;

    const search = root.querySelector('#statsSearch');
    search.addEventListener(
      'input',
      ui.debounce(() => {
        searchTerm = search.value;
        paintStats();
      }, 120)
    );

    root.querySelectorAll('[data-side]').forEach((button) => {
      button.addEventListener('click', () => {
        side = button.dataset.side;
        paintStats();
      });
    });

    paintStats();
  }

  function sortMark(key) {
    if (sortKey !== key) return '<i class="bi bi-arrow-down-up hf-muted" aria-hidden="true"></i>';
    return `<i class="bi bi-caret-${sortDir === 'asc' ? 'up' : 'down'}-fill" aria-hidden="true"></i>`;
  }

  function tableFor(team, sideKey) {
    const current = store.select.match(matchId);
    const term = searchTerm.trim().toLowerCase();
    const rows = sortBoxScore(
      boxScoreRows(current, team).filter((row) => !term || row.name.toLowerCase().includes(term)),
      sortKey,
      sortDir
    );
    if (!rows.length) {
      return `
        <section class="hf-mb-3" data-testid="stats-team-${escapeHtml(team.id)}">
          <h3 class="hf-section-title">${escapeHtml(team.name)}</h3>
          ${ui.emptyState({
            icon: 'person-x',
            title: term ? 'No players match' : 'No player stats',
            message: term
              ? 'Clear the player search to see the full rotation.'
              : 'This team does not have a recorded box score for this game.',
          })}
        </section>
      `;
    }
    const totals = boxScoreTotals(rows);
    return `
      <section class="hf-mb-3" data-testid="stats-team-${escapeHtml(team.id)}">
        <div class="hf-between hf-mb-1">
          <span class="hf-flex hf-items-center hf-gap-2">
            ${components.shield(team)}
            <h3 class="hf-section-title hf-mb-0">${escapeHtml(team.name)}</h3>
          </span>
          <span class="hf-chip hf-chip--slate">${rows.length} players</span>
        </div>
        <div class="hf-table-wrap">
          <table class="hf-table">
            <thead>
              <tr>
                ${COLUMNS.map((column) => {
                  if (!column.sortable) {
                    return `<th scope="col" class="${column.numeric ? 'is-num' : ''}">${escapeHtml(column.label)}</th>`;
                  }
                  return `
                    <th scope="col" class="is-num">
                      <button type="button" class="hf-section-link" data-sort="${column.key}"
                              data-testid="stats-sort-${sideKey}-${column.key}">
                        ${escapeHtml(column.label)} ${sortMark(column.key)}
                      </button>
                    </th>
                  `;
                }).join('')}
              </tr>
            </thead>
            <tbody>
              ${rows
                .map(
                  (row) => `
                    <tr data-testid="stats-row-${sideKey}-${escapeHtml(row.playerId)}">
                      <td class="is-num">${row.number ?? '–'}</td>
                      <td>
                        <strong>${escapeHtml(row.name)}</strong>
                        <span class="hf-small hf-muted" style="display: block">${escapeHtml(row.position || 'Player')}</span>
                      </td>
                      <td class="is-num">${row.points}</td>
                      <td class="is-num">${row.assists}</td>
                      <td class="is-num">${row.rebounds}</td>
                    </tr>
                  `
                )
                .join('')}
            </tbody>
            <tfoot>
              <tr>
                <td colspan="2"><strong>Team totals</strong></td>
                <td class="is-num"><strong>${totals.points}</strong></td>
                <td class="is-num"><strong>${totals.assists}</strong></td>
                <td class="is-num"><strong>${totals.rebounds}</strong></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>
    `;
  }

  function paintStats() {
    const current = store.select.match(matchId);
    if (!current) return;
    const team1 = current.team1Id ? store.select.team(current.team1Id) : null;
    const team2 = current.team2Id ? store.select.team(current.team2Id) : null;

    root.querySelectorAll('[data-side]').forEach((button) => {
      const active = button.dataset.side === side;
      button.classList.toggle('hf-chip--primary', active);
      button.classList.toggle('hf-chip--slate', !active);
      button.setAttribute('aria-pressed', String(active));
    });

    const body = root.querySelector('#statsBody');
    if (!body) return;

    if (!team1 || !team2) {
      body.innerHTML = ui.emptyState({
        icon: 'hourglass-split',
        title: 'Teams still pending',
        message: 'The box score unlocks when both teams are known.',
      });
      return;
    }

    if (side === 'team1') {
      body.innerHTML = tableFor(team1, 'team1');
      attachSortButtons();
      return;
    }
    if (side === 'team2') {
      body.innerHTML = tableFor(team2, 'team2');
      attachSortButtons();
      return;
    }
    body.innerHTML = tableFor(team1, 'team1') + tableFor(team2, 'team2');
    attachSortButtons();
  }

  function attachSortButtons() {
    root.querySelectorAll('[data-sort]').forEach((button) => {
      button.addEventListener('click', () => {
        const nextKey = button.dataset.sort;
        if (sortKey === nextKey) {
          sortDir = sortDir === 'desc' ? 'asc' : 'desc';
        } else {
          sortKey = nextKey;
          sortDir = 'desc';
        }
        paintStats();
      });
    });
  }

  root.addEventListener('click', (event) => {
    if (event.target.closest('[data-open-score]')) {
      openScoreConsole({ store, ui, matchId, onDone: draw });
      return;
    }
    if (event.target.closest('[data-open-correction]')) {
      openCorrectionDialog({ store, ui, matchId, onDone: draw });
    }
  });

  draw();
}
```

- [ ] **Step 10: Verify standings and game stats in the browser**

Open `http://127.0.0.1:8001/standings/table.html` as organizer. Expected: Ugong Blue Hawks first at 2–0, Mandaluyong Tigers second at 1–0 with a +20 differential, San Antonio Vipers third at 1–0, and Kapitolyo Knights fourth at 1–0; a gold qualification line after the top eight; and four KPI tiles. Search “knights”; only the Knights card/row remains. Choose “Playoff cut”; the table keeps only qualified teams and the ranks stay stable. Clear the search; the full table returns.

Open `http://127.0.0.1:8001/games/stats.html?match=qf-1`. Expected: the scoreboard shows Vipers 78–70 Warriors, a gold “Corrected score” banner with 74–70 → 78–70, and two box-score tables with team totals of 78 and 70. Sort by REB and confirm both tables reorder. Search “ramos”; only matching players remain. As organizer, “Correct score” opens the correction dialog; cancel it and confirm the page is unchanged.

Open `http://127.0.0.1:8001/games/stats.html?match=qf-3` as scorekeeper. Expected: “Enter final score” opens the console; enter 70–66 and finalize; the scoreboard updates, Knights advance, and the box score appears. Switch to fan and reload: no score controls are shown.

- [ ] **Step 11: Check the modules parse, run the suite, and commit**

```powershell
cd prototype
node --check assets/js/stats.js
node --check assets/js/pages/standings.js
node --check assets/js/pages/game-stats.js
node --test tests
```

Expected: no syntax output and every test PASS.

```powershell
git add prototype/assets/js/components.js prototype/assets/js/stats.js prototype/tests/stats.test.mjs prototype/standings/table.html prototype/games/stats.html prototype/assets/js/pages/standings.js prototype/assets/js/pages/game-stats.js
git commit -m "feat(prototype): add standings filters and official box scores"
```

## Task 20: Announcements feed and composer

**Files:**
- Rewrite: `prototype/announcements/index.html`
- Rewrite: `prototype/announcements/create.html`
- Create: `prototype/assets/js/pages/announcements.js`
- Create: `prototype/assets/js/pages/announcement-create.js`
- Modify: `prototype/assets/js/components.js`
- Modify: `prototype/assets/css/hoopflow.css`

**Interfaces:**
- Consumes: `route`, `escapeHtml`, `ui.debounce/toast/setFieldError/clearFieldError/setBusy`, `components.pageHeader/sectionHeader/kpiTile/announcementCard`, `store.select.tournaments/tournament/announcements`, `store.actions.publishAnnouncement/markAnnouncementsSeen`, `AUDIENCE_BY_ROLE` from `store.js`, `ANNOUNCEMENT_CATEGORIES/ANNOUNCEMENT_AUDIENCES` from `options.js`, `guardRole`.
- Produces: a role-filtered announcement feed with search, tournament and category filters, a pinned section, an organizer composer with validation and a live preview, and a cleaner announcement card used by the dashboard and tournament desk.

- [ ] **Step 1: Rewrite the announcements index HTML**

Replace `prototype/announcements/index.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Announcements — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="announcements" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading announcements...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 2: Implement the announcements feed**

Create `prototype/assets/js/pages/announcements.js`:

```js
import { route } from '../routes.js';
import { escapeHtml } from '../ui.js';
import { ANNOUNCEMENT_CATEGORIES } from '../options.js';
import { AUDIENCE_BY_ROLE } from '../store.js';

export function render({ store, ui, components, root }) {
  const params = new URLSearchParams(window.location.search);
  const role = store.getRole();
  const tournaments = store.select.tournaments();
  const allowedAudiences = AUDIENCE_BY_ROLE[role] || ['all'];
  let tournamentFilter = params.get('id') || 'all';
  let categoryFilter = params.get('category') || 'all';

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Matchday desk · Bulletin',
      title: 'Announcements',
      description:
        role === 'fan'
          ? 'Official venue, schedule, and eligibility notices from the tournament desk.'
          : 'Venue, schedule, and eligibility bulletins, filtered to what this role can see.',
      actions:
        role === 'organizer'
          ? `<a class="hf-btn hf-btn--primary" href="${route('announcementCreate', tournamentFilter === 'all' ? {} : { id: tournamentFilter })}" data-testid="announcement-new"><i class="bi bi-megaphone"></i> New announcement</a>`
          : '',
    })}

    <div id="announcementKpis" class="hf-grid hf-grid--4 hf-mb-3"></div>

    <div class="hf-card hf-mb-3">
      <div class="hf-form-grid">
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="announcementSearch">Search</label>
          <input class="hf-input" id="announcementSearch" type="search"
                 placeholder="Headline, message, or author"
                 value="${escapeHtml(params.get('q') || '')}" data-testid="announcement-search">
        </div>
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="announcementTournament">Tournament</label>
          <select class="hf-select" id="announcementTournament" data-testid="announcement-tournament-filter">
            <option value="all">All tournaments</option>
            ${tournaments.map((tournament) => `<option value="${escapeHtml(tournament.id)}">${escapeHtml(tournament.name)}</option>`).join('')}
          </select>
        </div>
      </div>
      <div class="hf-flex hf-wrap hf-items-center hf-gap-2 hf-mt-2">
        <span class="hf-label">Category</span>
        <div class="hf-filter-row hf-mb-0" data-category-filters>
          ${[{ value: 'all', label: 'All' }, ...ANNOUNCEMENT_CATEGORIES]
            .map(
              (option) => `
                <button type="button" class="hf-filter ${categoryFilter === option.value ? 'is-active' : ''}"
                        data-category="${escapeHtml(option.value)}" aria-pressed="${categoryFilter === option.value}"
                        data-testid="announcement-category-${escapeHtml(option.value)}">
                  ${escapeHtml(option.label)}
                </button>
              `
            )
            .join('')}
        </div>
      </div>
    </div>

    <div id="announcementFeed" data-testid="announcement-feed"></div>
  `;

  const search = root.querySelector('#announcementSearch');
  const tournamentSelect = root.querySelector('#announcementTournament');
  const feed = root.querySelector('#announcementFeed');
  const kpis = root.querySelector('#announcementKpis');

  tournamentSelect.value = tournamentFilter;

  function visibleItems() {
    const term = search.value.trim().toLowerCase();
    return store
      .select.announcements(tournamentFilter === 'all' ? undefined : tournamentFilter)
      .filter((item) => allowedAudiences.includes(item.audience))
      .filter((item) => categoryFilter === 'all' || item.category === categoryFilter)
      .filter((item) => {
        if (!term) return true;
        return `${item.title} ${item.body} ${item.author}`.toLowerCase().includes(term);
      });
  }

  function renderKpis() {
    const items = visibleItems();
    const pinned = items.filter((item) => item.pinned).length;
    const tournamentCount = new Set(items.map((item) => item.tournamentId)).size;
    const categoryCount = new Set(items.map((item) => item.category)).size;
    kpis.innerHTML = [
      components.kpiTile({ label: 'Visible notices', value: items.length, hint: 'For this role and filter', tone: 'primary', testid: 'announcement-kpi-visible' }),
      components.kpiTile({ label: 'Pinned', value: pinned, hint: 'Lead the feed', tone: 'gold', testid: 'announcement-kpi-pinned' }),
      components.kpiTile({ label: 'Tournaments', value: tournamentCount, hint: 'With visible notices', tone: 'slate', testid: 'announcement-kpi-tournaments' }),
      components.kpiTile({ label: 'Categories', value: categoryCount, hint: 'Schedule, venue, eligibility', tone: 'success', testid: 'announcement-kpi-categories' }),
    ].join('');
  }

  function renderFeed() {
    renderKpis();
    const items = visibleItems();
    if (!items.length) {
      feed.innerHTML = ui.emptyState({
        icon: 'megaphone',
        title: 'No announcements match',
        message: 'Try another tournament, category, or search term.',
        action:
          role === 'organizer'
            ? `<a class="hf-btn hf-btn--primary" href="${route('announcementCreate', tournamentFilter === 'all' ? {} : { id: tournamentFilter })}" data-testid="announcement-empty-create">Write an announcement</a>`
            : `<button type="button" class="hf-btn" data-clear-announcement-filters data-testid="announcement-clear-filters">Clear filters</button>`,
      });
    } else {
      const pinned = items.filter((item) => item.pinned);
      const rest = items.filter((item) => !item.pinned);
      feed.innerHTML = `
        ${
          pinned.length
            ? `${components.sectionHeader({ title: 'Pinned' })}
               <div class="hf-grid hf-grid--2 hf-mb-3">${pinned.map((item) => components.announcementCard(item)).join('')}</div>`
            : ''
        }
        ${
          rest.length
            ? `${components.sectionHeader({ title: pinned.length ? 'Latest updates' : 'All announcements' })}
               <div class="hf-grid hf-grid--2">${rest.map((item) => components.announcementCard(item)).join('')}</div>`
            : ''
        }
      `;
    }

    feed.querySelector('[data-clear-announcement-filters]')?.addEventListener('click', () => {
      search.value = '';
      tournamentFilter = 'all';
      categoryFilter = 'all';
      tournamentSelect.value = 'all';
      paintCategories();
      renderFeed();
    });
  }

  function paintCategories() {
    root.querySelectorAll('[data-category]').forEach((button) => {
      const active = button.dataset.category === categoryFilter;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', String(active));
    });
  }

  function markSeen() {
    const state = store.getState();
    const latest = state.announcements.reduce(
      (max, item) => Math.max(max, new Date(item.createdAt).getTime()),
      0
    );
    const seen = new Date(state.meta?.lastSeenAnnouncementsAt || 0).getTime();
    if (latest > seen) {
      try {
        store.actions.markAnnouncementsSeen();
      } catch {
        // Clearing the notification dot is non-blocking.
      }
    }
  }

  search.addEventListener('input', ui.debounce(renderFeed, 120));
  tournamentSelect.addEventListener('change', () => {
    tournamentFilter = tournamentSelect.value;
    renderFeed();
  });
  root.querySelectorAll('[data-category]').forEach((button) => {
    button.addEventListener('click', () => {
      categoryFilter = button.dataset.category;
      paintCategories();
      renderFeed();
    });
  });

  paintCategories();
  renderFeed();
  markSeen();
}
```

- [ ] **Step 3: Rewrite the composer HTML**

Replace `prototype/announcements/create.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>New announcement — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="announcement-create" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading composer...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 4: Implement the composer**

Create `prototype/assets/js/pages/announcement-create.js`:

```js
import { route } from '../routes.js';
import { escapeHtml } from '../ui.js';
import { guardRole } from '../guards.js';
import { ANNOUNCEMENT_AUDIENCES, ANNOUNCEMENT_CATEGORIES } from '../options.js';

function labelOf(options, value) {
  return options.find((option) => option.value === value)?.label || value;
}

export function render({ store, ui, components, root }) {
  if (!guardRole({ store, ui, root, roles: ['organizer'] })) return;

  const params = new URLSearchParams(window.location.search);
  const tournaments = store.select.tournaments();
  if (!tournaments.length) {
    root.innerHTML = ui.emptyState({
      icon: 'megaphone',
      title: 'No tournaments yet',
      message: 'Create a tournament before publishing a bulletin.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('tournamentCreate')}">Create tournament</a>`,
    });
    return;
  }

  const defaultTournament =
    params.get('id') ||
    tournaments.find((tournament) => tournament.status === 'active')?.id ||
    tournaments[0].id;
  const user = store.getCurrentUser();
  const author = user.organization || user.name;

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Matchday desk · Bulletin',
      title: 'New announcement',
      description: 'Publish a venue, schedule, or eligibility notice to the right audience.',
      actions: `<a class="hf-btn" href="${route('announcements')}" data-testid="announcement-composer-back"><i class="bi bi-arrow-left"></i> Announcements</a>`,
    })}

    <div class="hf-split">
      <form id="announcementForm" novalidate>
        <div class="hf-card hf-mb-3">
          <div class="hf-form-grid">
            <div class="hf-field hf-field--full">
              <label class="hf-label" for="announcementTournament">Tournament</label>
              <select class="hf-select" id="announcementTournament" data-testid="announcement-tournament">
                ${tournaments
                  .map(
                    (tournament) => `
                      <option value="${escapeHtml(tournament.id)}" ${tournament.id === defaultTournament ? 'selected' : ''}>
                        ${escapeHtml(tournament.name)} · ${escapeHtml(tournament.division)}
                      </option>
                    `
                  )
                  .join('')}
              </select>
            </div>
            <div class="hf-field hf-field--full">
              <label class="hf-label" for="announcementTitle">Headline</label>
              <input class="hf-input" id="announcementTitle" name="title" maxlength="90"
                     placeholder="Championship week moves to Court 1" data-testid="announcement-title">
              <span class="hf-help">Keep it short enough for the top of the feed.</span>
            </div>
            <div class="hf-field hf-field--full">
              <label class="hf-label" for="announcementBody">Message</label>
              <textarea class="hf-textarea" id="announcementBody" name="body" maxlength="320" rows="5"
                        placeholder="Gate opens 45 minutes before tip-off. All semifinals and finals are on Court 1."
                        data-testid="announcement-body"></textarea>
              <span class="hf-help">Up to 320 characters. Coaches and fans see plain text.</span>
            </div>
            <div class="hf-field">
              <label class="hf-label" for="announcementCategory">Category</label>
              <select class="hf-select" id="announcementCategory" data-testid="announcement-category">
                ${ANNOUNCEMENT_CATEGORIES.map(
                  (option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`
                ).join('')}
              </select>
            </div>
            <div class="hf-field">
              <label class="hf-label" for="announcementAudience">Audience</label>
              <select class="hf-select" id="announcementAudience" data-testid="announcement-audience">
                ${ANNOUNCEMENT_AUDIENCES.map(
                  (option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`
                ).join('')}
              </select>
            </div>
            <label class="hf-check hf-field--full">
              <input type="checkbox" id="announcementPinned" data-testid="announcement-pinned">
              <span>
                <strong>Pin to the top</strong>
                <span class="hf-help" style="display:block">Pinned notices lead the feed and the dashboard.</span>
              </span>
            </label>
          </div>
        </div>

        <div class="hf-btn-row">
          <a class="hf-btn" href="${route('announcements')}" data-testid="announcement-cancel">Cancel</a>
          <button type="submit" class="hf-btn hf-btn--primary" data-testid="announcement-publish">
            <i class="bi bi-send-fill"></i> Publish announcement
          </button>
        </div>
      </form>

      <aside>
        <div class="hf-card hf-mb-3">
          <span class="hf-kpi-label">Preview</span>
          <article class="hf-card hf-card--flat hf-mt-1" data-preview data-testid="announcement-preview"></article>
        </div>
        <div class="hf-card">
          <span class="hf-kpi-label">Before publishing</span>
          <ul class="hf-small hf-muted hf-mt-1 hf-mb-0">
            <li>Choose the tournament the notice belongs to.</li>
            <li>Coaches see “Coaches” notices; fans only see “Everyone”.</li>
            <li>Pinned notices lead the feed and the dashboard.</li>
          </ul>
        </div>
      </aside>
    </div>
  `;

  const form = root.querySelector('#announcementForm');
  const tournamentSelect = root.querySelector('#announcementTournament');
  const title = root.querySelector('#announcementTitle');
  const body = root.querySelector('#announcementBody');
  const categorySelect = root.querySelector('#announcementCategory');
  const audienceSelect = root.querySelector('#announcementAudience');
  const pinned = root.querySelector('#announcementPinned');
  const preview = root.querySelector('[data-preview]');
  const publish = root.querySelector('[data-testid="announcement-publish"]');

  function paintPreview() {
    const selected = tournaments.find((tournament) => tournament.id === tournamentSelect.value);
    preview.classList.toggle('hf-announcement--pinned', pinned.checked);
    preview.innerHTML = `
      <div class="hf-between">
        <span class="hf-chip hf-chip--slate">${escapeHtml(labelOf(ANNOUNCEMENT_CATEGORIES, categorySelect.value))}</span>
        ${pinned.checked ? '<span class="hf-chip hf-chip--gold"><i class="bi bi-pin-angle-fill" aria-hidden="true"></i>Pinned</span>' : ''}
      </div>
      <h3 class="hf-section-title hf-mt-1">${escapeHtml(title.value.trim() || 'Your headline')}</h3>
      <p class="hf-small hf-muted hf-mb-0">${escapeHtml(body.value.trim() || 'The message coaches and fans will see.')}</p>
      <div class="hf-match-head hf-mt-2">
        <span>${escapeHtml(author)}</span>
        <span>Just now</span>
      </div>
      <div class="hf-match-head">
        <span>Audience: ${escapeHtml(labelOf(ANNOUNCEMENT_AUDIENCES, audienceSelect.value))}</span>
        <span>${escapeHtml(selected?.name || 'No tournament')}</span>
      </div>
    `;
  }

  function validate() {
    let valid = true;
    if (!tournamentSelect.value) {
      ui.setFieldError(tournamentSelect, 'Choose a tournament.');
      valid = false;
    } else {
      ui.clearFieldError(tournamentSelect);
    }
    if (title.value.trim().length < 4) {
      ui.setFieldError(title, 'Use at least 4 characters.');
      valid = false;
    } else {
      ui.clearFieldError(title);
    }
    if (body.value.trim().length < 10) {
      ui.setFieldError(body, 'Write at least 10 characters.');
      valid = false;
    } else {
      ui.clearFieldError(body);
    }
    return valid;
  }

  form.addEventListener('input', (event) => {
    if (event.target.matches('input, textarea, select')) ui.clearFieldError(event.target);
    paintPreview();
  });
  form.addEventListener('change', paintPreview);

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!validate()) return;

    const payload = {
      tournamentId: tournamentSelect.value,
      title: title.value.trim(),
      body: body.value.trim(),
      category: categorySelect.value,
      audience: audienceSelect.value,
      pinned: pinned.checked,
    };

    ui.setBusy(publish, true, 'Publishing...');
    window.setTimeout(() => {
      try {
        store.actions.publishAnnouncement(payload);
        ui.toast('The bulletin is live for its audience.', {
          tone: 'success',
          title: 'Announcement published',
        });
        window.setTimeout(() => {
          window.location.href = route('announcements', { id: payload.tournamentId });
        }, 300);
      } catch (error) {
        ui.setBusy(publish, false);
        const fields = {
          tournamentId: tournamentSelect,
          title,
          body,
          category: categorySelect,
          audience: audienceSelect,
        };
        if (error.field && fields[error.field]) {
          ui.setFieldError(fields[error.field], error.message);
          fields[error.field].focus();
        }
        ui.toast(error.message, { tone: 'danger', title: 'Could not publish' });
      }
    }, 350);
  });

  paintPreview();
}
```

- [ ] **Step 5: Improve the shared announcement card**

In `prototype/assets/js/components.js`, add this import after the `ui.js` import:

```js
import { ANNOUNCEMENT_AUDIENCES, ANNOUNCEMENT_CATEGORIES } from './options.js';
```

Replace the whole `announcementCard` function with:

```js
  function announcementCard(item, { compact = false } = {}) {
    const tournament = store.select.tournament(item.tournamentId);
    const category =
      ANNOUNCEMENT_CATEGORIES.find((option) => option.value === item.category)?.label ||
      item.category;
    const audience =
      ANNOUNCEMENT_AUDIENCES.find((option) => option.value === item.audience)?.label ||
      item.audience;
    return `
      <article class="hf-card hf-card--flat ${item.pinned ? 'hf-announcement--pinned' : ''}"
               data-testid="announcement-${escapeHtml(item.id)}">
        <div class="hf-between">
          <span class="hf-chip hf-chip--slate">${escapeHtml(category)}</span>
          ${item.pinned ? '<span class="hf-chip hf-chip--gold"><i class="bi bi-pin-angle-fill" aria-hidden="true"></i>Pinned</span>' : ''}
        </div>
        <h3 class="hf-section-title hf-mt-1">${escapeHtml(item.title)}</h3>
        <p class="hf-muted hf-small hf-mb-0">${escapeHtml(item.body)}</p>
        <div class="hf-match-head hf-mt-2">
          <span>${escapeHtml(item.author)}</span>
          <span>${escapeHtml(formatDate(item.createdAt))}</span>
        </div>
        ${
          compact
            ? ''
            : `<div class="hf-match-head">
                 <span>Audience: ${escapeHtml(audience)}</span>
                 <span>${escapeHtml(tournament?.name || item.tournamentId)}</span>
               </div>`
        }
      </article>
    `;
  }
```

- [ ] **Step 6: Add the announcement surface styles**

Append to `prototype/assets/css/hoopflow.css`:

```css
/* Announcements */
.hf-announcement--pinned {
  border-left: 4px solid var(--hf-gold);
  background: color-mix(in srgb, var(--hf-gold) 6%, var(--hf-surface));
}
```

- [ ] **Step 7: Verify the feed and composer in the browser**

Open `http://127.0.0.1:8001/announcements/index.html` as organizer. Expected: four notices, the pinned Court 1 notice first under “Pinned”, four KPI tiles, and category chips. Filter to Venue: two notices remain. Search “roster”: one notice remains (the eligibility notice). Clear the filters.

Click “New announcement”, fill a headline and message, choose Schedule, Everyone, and Pin, then publish. Expected: redirect to the feed for the selected tournament, five notices, and the new item first under “Pinned”.

Switch to coach: the feed still shows the eligibility notice addressed to coaches. Switch to fan: the coaches-only notice disappears. At 390px, the category chips wrap, cards stack, and the composer preview sits below the form.

- [ ] **Step 8: Check the modules parse and commit**

```powershell
cd prototype
node --check assets/js/pages/announcements.js
node --check assets/js/pages/announcement-create.js
node --check assets/js/components.js
```

Expected: no output.

```powershell
git add prototype/announcements prototype/assets/js/pages/announcements.js prototype/assets/js/pages/announcement-create.js prototype/assets/js/components.js prototype/assets/css/hoopflow.css
git commit -m "feat(prototype): add announcement feed and composer"
```

## Task 21: Profile, theme control, demo reset, and audit timeline

**Files:**
- Rewrite: `prototype/profile/index.html`
- Create: `prototype/profile/audit.html`
- Create: `prototype/assets/js/pages/profile.js`
- Create: `prototype/assets/js/pages/audit.js`
- Modify: `prototype/assets/js/app.js`
- Modify: `prototype/assets/js/shell.js`
- Modify: `prototype/assets/css/hoopflow.css`

**Interfaces:**
- Consumes: `route`, `escapeHtml`, `formatDate`, `formatDateTime`, `initials`, `guardRole`, `store.getCurrentUser/getRole/getState/select.team/select.tournament/select.audit`, `store.actions.reset`, `ui.confirm/toast/emptyState`, `theme.get/set`.
- Produces: `render({ store, ui, components, root, theme })` for profile and audit; `app.js` passes the live theme object to every page; `shell.js` re-syncs its theme buttons when a page calls `theme.set()`.

- [ ] **Step 1: Rewrite the profile HTML**

Replace `prototype/profile/index.html` with:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile - HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="profile" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading profile...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 2: Implement the profile renderer**

Create `prototype/assets/js/pages/profile.js`:

```js
import { route } from '../routes.js';
import { escapeHtml, formatDate, initials } from '../ui.js';

const ROLE_COPY = {
  organizer:
    'Full tournament operations: approvals, payments, brackets, schedules, scores, announcements, and audit.',
  scorekeeper:
    'Score entry, score corrections, and official box scores. Approvals, payments, and roster edits are blocked.',
  coach:
    'Register a team, manage its roster and eligibility, submit a payment reference, and follow its status.',
  fan:
    'Read-only public pass: bracket, schedule, results, standings, and announcements. No private payment or eligibility data.',
};

const ROLE_LINKS = {
  organizer: [
    ['dashboard', 'Dashboard'],
    ['submissions', 'Review queue'],
    ['payments', 'Payments'],
    ['audit', 'Audit log'],
  ],
  scorekeeper: [
    ['bracket', 'Score queue'],
    ['gameStats', 'Game stats'],
  ],
  coach: [
    ['myTeam', 'My team'],
    ['tournaments', 'Tournaments'],
  ],
  fan: [
    ['dashboard', 'Courtside pass'],
    ['bracket', 'Bracket'],
    ['schedule', 'Schedule'],
  ],
};

export function render({ store, ui, components, root, theme }) {
  const role = store.getRole();
  const user = store.getCurrentUser();
  const state = store.getState();
  const team = user.teamId ? store.select.team(user.teamId) : null;
  const tournament = team ? store.select.tournament(team.tournamentId) : null;
  const links = ROLE_LINKS[role] || [];

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Account',
      title: 'Profile',
      description:
        'Your demo identity, role scope, appearance, and the controls that keep a live run recoverable.',
      actions:
        role === 'organizer'
          ? `<a class="hf-btn" href="${route('audit')}" data-testid="profile-open-audit"><i class="bi bi-clock-history"></i> Audit log</a>`
          : '',
    })}

    <section class="hf-card hf-profile-head hf-mb-3" data-testid="profile-identity">
      <span class="hf-avatar hf-avatar--xl">${escapeHtml(initials(user.name))}</span>
      <div class="hf-profile-meta">
        <span class="hf-flex hf-wrap hf-gap-1">
          <span class="hf-chip hf-chip--primary" style="text-transform: capitalize">${escapeHtml(role)}</span>
          <span class="hf-chip hf-chip--slate">${escapeHtml(state.seedVersion)}</span>
        </span>
        <h2>${escapeHtml(user.name)}</h2>
        <p>${escapeHtml(user.email)}</p>
        <p class="hf-small hf-muted hf-mb-0">${escapeHtml(user.organization || 'No organization on file')}</p>
      </div>
    </section>

    <div class="hf-grid hf-grid--2 hf-mb-3">
      <section class="hf-card" data-testid="profile-role-card">
        <span class="hf-kpi-label">Role context</span>
        <h2 class="hf-section-title hf-mt-1" style="text-transform: capitalize">${escapeHtml(role)}</h2>
        <p class="hf-small hf-muted">${escapeHtml(ROLE_COPY[role] || '')}</p>
        <div class="hf-btn-row hf-mt-2">
          ${links
            .map(
              ([key, label], index) => `
                <a class="hf-btn hf-btn--sm ${index === 0 ? 'hf-btn--primary' : ''}"
                   href="${route(key)}" data-testid="profile-link-${key}">${escapeHtml(label)}</a>
              `
            )
            .join('')}
        </div>
      </section>

      <section class="hf-card" data-testid="profile-appearance">
        <span class="hf-kpi-label">Appearance</span>
        <h2 class="hf-section-title hf-mt-1">Theme</h2>
        <p class="hf-small hf-muted">The light paper theme is the demo default. The floodlit navy theme is easier to read in a dark room.</p>
        <div class="hf-btn-row" role="group" aria-label="Color theme">
          <button type="button" class="hf-btn" data-profile-theme="light"
                  aria-pressed="${theme.get() === 'light'}" data-testid="profile-theme-light">
            <i class="bi bi-sun" aria-hidden="true"></i> Light
          </button>
          <button type="button" class="hf-btn" data-profile-theme="dark"
                  aria-pressed="${theme.get() === 'dark'}" data-testid="profile-theme-dark">
            <i class="bi bi-moon-stars" aria-hidden="true"></i> Dark
          </button>
        </div>
      </section>
    </div>

    <div class="hf-grid hf-grid--2">
      ${
        team
          ? `<section class="hf-card" data-testid="profile-team">
               <span class="hf-kpi-label">Team context</span>
               <h2 class="hf-section-title hf-mt-1">${escapeHtml(team.name)}</h2>
               <p class="hf-small hf-muted">${escapeHtml(tournament?.name || '')} · ${escapeHtml(tournament?.division || '')}</p>
               <p class="hf-small hf-muted">Status ${escapeHtml(team.status)} · submitted ${escapeHtml(formatDate(team.submittedAt))}</p>
               <a class="hf-btn hf-btn--sm" href="${route('myTeam', { team: team.id })}" data-testid="profile-open-team">Open My Team</a>
             </section>`
          : ''
      }

      <section class="hf-card" data-testid="profile-demo-data">
        <span class="hf-kpi-label">Demo data</span>
        <div class="hf-setting">
          <span><strong>Seed version</strong><span>Deterministic demo fixture</span></span>
          <span class="hf-mono">${escapeHtml(state.seedVersion)}</span>
        </div>
        <div class="hf-setting">
          <span><strong>Schema</strong><span>Normalized store version</span></span>
          <span class="hf-mono">v${state.schemaVersion}</span>
        </div>
        <div class="hf-setting">
          <span><strong>Storage</strong><span>Browser localStorage only</span></span>
          <span class="hf-chip hf-chip--slate">Local</span>
        </div>
        <p class="hf-small hf-muted">Reset restores the seeded tournaments, teams, matches, payments, and audit trail. Use it between live runs.</p>
        <button type="button" class="hf-btn hf-btn--danger" data-profile-reset data-testid="profile-reset">
          <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Reset demo
        </button>
      </section>
    </div>
  `;

  const themeButtons = [...root.querySelectorAll('[data-profile-theme]')];
  const syncThemeButtons = () => {
    themeButtons.forEach((button) => {
      button.setAttribute('aria-pressed', String(button.dataset.profileTheme === theme.get()));
    });
  };

  themeButtons.forEach((button) => {
    button.addEventListener('click', () => {
      theme.set(button.dataset.profileTheme);
      syncThemeButtons();
    });
  });
  syncThemeButtons();

  root.querySelector('[data-profile-reset]').addEventListener('click', async () => {
    const ok = await ui.confirm({
      title: 'Reset demo?',
      message: 'This restores the exact seeded tournaments, teams, matches, and audit trail.',
      confirmLabel: 'Reset demo',
      tone: 'danger',
    });
    if (!ok) return;
    store.actions.reset();
    ui.toast('Demo reset to the seeded matchday.', { tone: 'success', title: 'Reset complete' });
    window.setTimeout(() => {
      window.location.href = route('dashboard');
    }, 250);
  });
}
```

- [ ] **Step 3: Create the audit HTML**

Create `prototype/profile/audit.html`:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit log - HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="audit" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading audit log...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 4: Implement the audit renderer**

Create `prototype/assets/js/pages/audit.js`:

```js
import { route } from '../routes.js';
import { escapeHtml, formatDateTime } from '../ui.js';
import { guardRole } from '../guards.js';

const ACTION_LABELS = {
  'tournament.create': 'Tournament created',
  'team.register': 'Team registered',
  'team.approve': 'Team approved',
  'team.reject': 'Team rejected',
  'team.roster.add': 'Player added',
  'team.roster.update': 'Player updated',
  'team.roster.remove': 'Player removed',
  'payment.submit': 'Payment reference submitted',
  'payment.verify': 'Payment verified',
  'bracket.generate': 'Bracket generated',
  'schedule.generate': 'Schedule generated',
  'score.save': 'Score saved',
  'score.correct': 'Score corrected',
  'announcement.publish': 'Announcement published',
};

const ENTITY_LABELS = {
  tournament: 'Tournament',
  team: 'Team',
  match: 'Match',
  announcement: 'Announcement',
};

function humanize(key) {
  return String(key)
    .replace(/([A-Z])/g, ' $1')
    .replaceAll('_', ' ')
    .replace(/^./, (character) => character.toUpperCase());
}

export function render({ store, ui, components, root }) {
  if (!guardRole({ store, ui, root, roles: ['organizer'] })) return;

  const state = store.getState();
  const params = new URLSearchParams(window.location.search);
  const entries = store
    .select.audit()
    .sort((a, b) => String(b.at).localeCompare(String(a.at)));
  const users = state.users;
  const entities = [...new Set(entries.map((entry) => entry.entityType))].sort();
  const actors = [...new Set(entries.map((entry) => entry.actorId))]
    .map((id) => users.find((user) => user.id === id) || { id, name: id })
    .sort((a, b) => a.name.localeCompare(b.name));

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Operations',
      title: 'Audit log',
      description:
        'A reverse-chronological record of every approval, payment, bracket, schedule, score, and announcement. Corrections keep their previous values.',
      actions: `<a class="hf-btn" href="${route('profile')}" data-testid="audit-back-profile"><i class="bi bi-person"></i> Profile</a>`,
    })}

    <div class="hf-grid hf-grid--4 hf-mb-3">
      ${components.kpiTile({ label: 'Entries', value: entries.length, hint: 'Seed plus this session', tone: 'primary', testid: 'audit-kpi-total' })}
      ${components.kpiTile({ label: 'Corrections', value: entries.filter((entry) => entry.action === 'score.correct').length, hint: 'Old values retained', tone: 'alert', testid: 'audit-kpi-corrections' })}
      ${components.kpiTile({ label: 'Approvals', value: entries.filter((entry) => ['team.approve', 'team.reject'].includes(entry.action)).length, hint: 'Team decisions', tone: 'gold', testid: 'audit-kpi-approvals' })}
      ${components.kpiTile({ label: 'Payments', value: entries.filter((entry) => entry.action === 'payment.verify').length, hint: 'Verified references', tone: 'success', testid: 'audit-kpi-payments' })}
    </div>

    <section class="hf-card hf-mb-3" aria-label="Audit filters">
      <div class="hf-form-grid">
        <div class="hf-field">
          <label class="hf-label" for="auditEntity">Entity</label>
          <select class="hf-select" id="auditEntity" data-testid="audit-entity-filter">
            <option value="all">All entities</option>
            ${entities
              .map(
                (entity) =>
                  `<option value="${escapeHtml(entity)}">${escapeHtml(ENTITY_LABELS[entity] || humanize(entity))}</option>`
              )
              .join('')}
          </select>
        </div>
        <div class="hf-field">
          <label class="hf-label" for="auditActor">Actor</label>
          <select class="hf-select" id="auditActor" data-testid="audit-actor-filter">
            <option value="all">All actors</option>
            ${actors
              .map(
                (actor) =>
                  `<option value="${escapeHtml(actor.id)}">${escapeHtml(actor.name)}</option>`
              )
              .join('')}
          </select>
        </div>
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="auditSearch">Search</label>
          <input class="hf-input" id="auditSearch" type="search"
                 placeholder="Summary, action, actor, or entity id"
                 data-testid="audit-search">
        </div>
      </div>
      <div class="hf-between hf-mt-2">
        <span class="hf-small hf-muted" id="auditCount" data-testid="audit-count"></span>
        <button type="button" class="hf-btn hf-btn--sm" data-audit-clear data-testid="audit-clear">Clear filters</button>
      </div>
    </section>

    <div id="auditList" data-testid="audit-list"></div>
  `;

  const entity = root.querySelector('#auditEntity');
  const actor = root.querySelector('#auditActor');
  const search = root.querySelector('#auditSearch');
  const count = root.querySelector('#auditCount');
  const list = root.querySelector('#auditList');

  entity.value = params.get('entity') || 'all';
  actor.value = params.get('actor') || 'all';
  search.value = params.get('q') || '';

  const userById = (id) => users.find((user) => user.id === id) || { name: id };

  function entityLink(entry) {
    if (entry.entityType === 'team') {
      const team = store.select.team(entry.entityId);
      return team
        ? `<a class="hf-section-link" href="${route('teamShow', { id: team.id })}" data-testid="audit-team-link-${escapeHtml(entry.id)}">${escapeHtml(team.name)}</a>`
        : `<span class="hf-mono">${escapeHtml(entry.entityId)}</span>`;
    }
    if (entry.entityType === 'match') {
      const match = store.select.match(entry.entityId);
      if (!match) return `<span class="hf-mono">${escapeHtml(entry.entityId)}</span>`;
      const team1 = store.select.team(match.team1Id);
      const team2 = store.select.team(match.team2Id);
      const label = `${team1?.shortName || 'TBD'} vs ${team2?.shortName || 'TBD'}`;
      return `<a class="hf-section-link" href="${route('gameStats', { match: match.id })}" data-testid="audit-match-link-${escapeHtml(entry.id)}">${escapeHtml(label)}</a>`;
    }
    if (entry.entityType === 'tournament') {
      const tournament = store.select.tournament(entry.entityId);
      return tournament
        ? `<a class="hf-section-link" href="${route('tournamentShow', { id: tournament.id })}" data-testid="audit-tournament-link-${escapeHtml(entry.id)}">${escapeHtml(tournament.name)}</a>`
        : `<span class="hf-mono">${escapeHtml(entry.entityId)}</span>`;
    }
    if (entry.entityType === 'announcement') {
      const announcement = state.announcements.find((item) => item.id === entry.entityId);
      return announcement
        ? `<a class="hf-section-link" href="${route('announcements', { id: announcement.tournamentId })}" data-testid="audit-announcement-link-${escapeHtml(entry.id)}">${escapeHtml(announcement.title)}</a>`
        : `<span class="hf-mono">${escapeHtml(entry.entityId)}</span>`;
    }
    return `<span class="hf-mono">${escapeHtml(entry.entityId)}</span>`;
  }

  function changeChips(entry) {
    if (!entry.changes) return '';
    if (entry.action === 'score.correct') {
      const changes = entry.changes;
      return `<span class="hf-change">${escapeHtml(String(changes.previousScore1))}–${escapeHtml(String(changes.previousScore2))} → ${escapeHtml(String(changes.score1))}–${escapeHtml(String(changes.score2))}</span>`;
    }
    return Object.entries(entry.changes)
      .map(([key, value]) => {
        const formatted = typeof value === 'object' ? JSON.stringify(value) : String(value);
        return `<span class="hf-change">${escapeHtml(humanize(key))}: ${escapeHtml(formatted)}</span>`;
      })
      .join('');
  }

  function auditEntry(entry) {
    const author = userById(entry.actorId);
    return `
      <article class="hf-timeline-item" data-testid="audit-entry-${escapeHtml(entry.id)}">
        <div class="hf-timeline-meta">
          <span><i class="bi bi-clock" aria-hidden="true"></i> ${escapeHtml(formatDateTime(entry.at))}</span>
          <span>${escapeHtml(author.name)}</span>
          <span class="hf-chip hf-chip--slate" style="text-transform: capitalize">${escapeHtml(entry.actorRole)}</span>
        </div>
        <h3>${escapeHtml(ACTION_LABELS[entry.action] || humanize(entry.action))}</h3>
        <p>${escapeHtml(entry.summary)}</p>
        <div class="hf-flex hf-wrap hf-gap-1 hf-mt-1">
          ${entityLink(entry)}
          ${changeChips(entry)}
        </div>
      </article>
    `;
  }

  function renderList() {
    const term = search.value.trim().toLowerCase();
    const filtered = entries.filter((entry) => {
      const author = userById(entry.actorId);
      const haystack = `${entry.summary} ${entry.action} ${entry.entityId} ${author.name} ${entry.actorRole}`.toLowerCase();
      return (
        (entity.value === 'all' || entry.entityType === entity.value) &&
        (actor.value === 'all' || entry.actorId === actor.value) &&
        (!term || haystack.includes(term))
      );
    });

    count.textContent = `${filtered.length} of ${entries.length} entries`;
    list.innerHTML = filtered.length
      ? `<div class="hf-timeline">${filtered.map(auditEntry).join('')}</div>`
      : ui.emptyState({
          icon: 'clock-history',
          title: 'No audit entries match',
          message: 'Try another entity, actor, or search term.',
          action: `<button type="button" class="hf-btn" data-audit-clear-inline data-testid="audit-clear-inline">Clear filters</button>`,
        });

    list.querySelector('[data-audit-clear-inline]')?.addEventListener('click', clearFilters);
  }

  function clearFilters() {
    entity.value = 'all';
    actor.value = 'all';
    search.value = '';
    renderList();
  }

  entity.addEventListener('change', renderList);
  actor.addEventListener('change', renderList);
  search.addEventListener('input', ui.debounce(renderList, 120));
  root.querySelector('[data-audit-clear]').addEventListener('click', clearFilters);

  renderList();
}
```

- [ ] **Step 5: Pass the theme into pages and sync the shell**

In `prototype/assets/js/app.js`, replace the page bootstrap block:

```js
const page = document.body.dataset.page;
const root = document.getElementById('main');
if (page && root) {
  import(`./pages/${page}.js`)
    .then((module) => module.render({ store, ui, components, root }))
    .catch((error) => {
```

with:

```js
const page = document.body.dataset.page;
const root = document.getElementById('main');
if (page && root) {
  import(`./pages/${page}.js`)
    .then((module) => module.render({ store, ui, components, root, theme }))
    .catch((error) => {
```

In `prototype/assets/js/shell.js`, immediately before the final `syncTheme();`, add:

```js
  document.addEventListener('hf:theme-changed', syncTheme);
```

- [ ] **Step 6: Add the profile styles**

Append to `prototype/assets/css/hoopflow.css`:

```css
/* Profile */
.hf-profile-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 18px;
}

.hf-avatar--xl {
  width: 68px;
  height: 68px;
  font-size: 24px;
  flex-shrink: 0;
}

.hf-profile-meta {
  min-width: 0;
}

.hf-profile-meta h2 {
  margin: 6px 0 2px;
  font-family: var(--hf-font-display);
  font-size: 30px;
  font-weight: 800;
  letter-spacing: 0.01em;
  line-height: 1;
  text-transform: uppercase;
}

.hf-profile-meta p {
  margin: 0 0 2px;
  color: var(--hf-muted);
  font-size: 13px;
  overflow-wrap: anywhere;
}

.hf-setting {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 0;
  border-top: 1px solid var(--hf-border);
}

.hf-setting:first-of-type {
  border-top: 0;
}

.hf-setting > span:first-child {
  display: grid;
  gap: 1px;
  min-width: 0;
}

.hf-setting strong {
  font-size: 13px;
}

.hf-setting > span:first-child > span {
  color: var(--hf-muted);
  font-size: 11px;
}

@media (max-width: 575.98px) {
  .hf-profile-head {
    align-items: flex-start;
  }

  .hf-profile-meta h2 {
    font-size: 24px;
  }
}
```

- [ ] **Step 7: Verify profile, theme, reset, and audit**

Open `http://127.0.0.1:8001/profile/index.html` as organizer. Expected: Jeward Mahinay, organizer chip, HoopFlow Tournament Operations, four role links, light/dark theme buttons, seed `2026-10-30`, schema `v2`, and a reset button.

Choose Dark. Expected: the page switches immediately, the topbar moon becomes a sun, and the Demo menu's Dark button becomes pressed. Choose Light. Expected: the reverse happens without a reload.

Open `http://127.0.0.1:8001/profile/audit.html`. Expected: 8 seeded entries, four KPI tiles, entity and actor filters. Filter Entity to Match. Expected: the correction entry remains with `74–70 → 78–70` and a match link. Filter Actor to Coach Marcus Rivera. Expected: the seeding registration entry remains. Clear filters. Expected: all 8 entries return.

Open the Teams directory, approve a pending team, verify a payment, and record a score. Return to the audit log. Expected: the new entries are first, newest first, with the actor and role on each. Click the team, match, and tournament links and confirm they land on the right record.

Open the profile as coach. Expected: the coach card shows San Antonio Vipers, its tournament, status, and an Open My Team link. Open as scorekeeper and fan and confirm the role copy and links change.

Click Reset demo on the profile, confirm, and wait for the dashboard. Expected: pending teams return to 2, uncollected returns to ₱3,000, and the audit log is back to 8 seeded entries.

- [ ] **Step 8: Check the modules parse and commit**

```powershell
cd prototype
node --check assets/js/app.js
node --check assets/js/shell.js
node --check assets/js/pages/profile.js
node --check assets/js/pages/audit.js
```

Expected: no output.

```powershell
git add prototype/profile prototype/assets/js/pages/profile.js prototype/assets/js/pages/audit.js prototype/assets/js/app.js prototype/assets/js/shell.js prototype/assets/css/hoopflow.css
git commit -m "feat(prototype): add profile, theme control, and audit timeline"
```

## Task 22: Public/fan restrictions and mobile pass

**Files:**
- Modify: `prototype/assets/js/pages/teams.js`
- Modify: `prototype/assets/js/pages/team-show.js`
- Modify: `prototype/assets/js/shell.js`
- Modify: `prototype/assets/css/hoopflow.css`

**Interfaces:**
- Consumes: `store.getRole/select.team`, `ui.emptyState`, `formatTime`, the `.hf-demo-menu` markup from Task 9.
- Produces: an approved-only public team directory and team profile, a demo menu that closes on outside click and Escape, and mobile rules that keep the 390px pass free of horizontal overflow and sub-44px controls.

- [ ] **Step 1: Scope the team directory for fans**

In `prototype/assets/js/pages/teams.js`, change the status initialization:

```js
  const role = store.getRole();
  const params = new URLSearchParams(window.location.search);
  let statusFilter = role === 'fan' ? 'approved' : params.get('status') || 'all';
```

Replace the Status filter field in the template with a role-aware block:

```html
        ${
          role === 'fan'
            ? ''
            : `<div class="hf-field">
                 <label class="hf-label" for="teamStatus">Status</label>
                 <select class="hf-select" id="teamStatus" data-testid="team-status-filter">
                   <option value="all">All statuses</option>
                   <option value="approved">Approved</option>
                   <option value="pending">Pending</option>
                   <option value="rejected">Rejected</option>
                 </select>
               </div>`
        }
```

Move the element lookups above the value assignment and make the status element optional:

```js
  const search = root.querySelector('#teamSearch');
  const status = root.querySelector('#teamStatus');
  const division = root.querySelector('#teamDivision');
  if (status) status.value = statusFilter;
  division.value = divisionFilter;
```

Replace `renderKpis` with a scoped version:

```js
  function visibleTeams() {
    const teams = store.getState().teams;
    return role === 'fan'
      ? teams.filter((team) => team.status === 'approved')
      : teams;
  }

  function renderKpis() {
    const teams = visibleTeams();
    kpis.innerHTML = role === 'fan'
      ? [
          components.kpiTile({ label: 'Public teams', value: teams.length, hint: 'Approved and visible', tone: 'primary', testid: 'team-kpi-teams' }),
          components.kpiTile({ label: 'Divisions', value: divisions.length, hint: 'Age groups', tone: 'slate', testid: 'team-kpi-divisions' }),
          components.kpiTile({ label: 'Cities', value: new Set(teams.map((team) => team.city)).size, hint: 'Clubs represented', tone: 'gold', testid: 'team-kpi-cities' }),
          components.kpiTile({ label: 'Tournaments', value: tournaments.length, hint: 'Seeded venues', tone: 'success', testid: 'team-kpi-tournaments' }),
        ].join('')
      : [
          components.kpiTile({ label: 'Registered', value: teams.length, hint: `${tournaments.length} tournaments`, tone: 'primary', testid: 'team-kpi-registered' }),
          components.kpiTile({ label: 'Approved', value: teams.filter((team) => team.status === 'approved').length, hint: 'Eligible for seeding', tone: 'success', testid: 'team-kpi-approved' }),
          components.kpiTile({ label: 'Pending', value: teams.filter((team) => team.status === 'pending').length, hint: 'Waiting for organizer review', tone: 'gold', testid: 'team-kpi-pending' }),
          components.kpiTile({ label: 'Divisions', value: divisions.length, hint: 'U12 through open', tone: 'slate', testid: 'team-kpi-divisions' }),
        ].join('');
  }
```

Change `renderList` to filter the scoped list and tolerate a missing status select:

```js
    const statusValue = status ? status.value : 'approved';
    const filtered = visibleTeams()
      .filter((team) => {
        const tournament = store.select.tournament(team.tournamentId);
        const haystack = `${team.name} ${team.coach} ${team.city} ${tournament?.name || ''}`.toLowerCase();
        return (
          (statusValue === 'all' || team.status === statusValue) &&
          (division.value === 'all' || tournament?.division === division.value) &&
          (!term || haystack.includes(term))
        );
      })
```

Guard the clear and change handlers:

```js
    list.querySelector('[data-clear-team-filters]')?.addEventListener('click', () => {
      search.value = '';
      if (status) status.value = 'all';
      division.value = 'all';
      renderList();
    });
```

```js
  status?.addEventListener('change', renderList);
```

- [ ] **Step 2: Hide pending teams and minor ages from the public profile**

In `prototype/assets/js/pages/team-show.js`, update the UI import:

```js
import { escapeHtml, formatDate, formatMoney, formatTime } from '../ui.js';
```

Insert the public guard after `const role = store.getRole();`:

```js
  if (role === 'fan' && team.status !== 'approved') {
    root.innerHTML = ui.emptyState({
      icon: 'shield-lock',
      title: 'Team not available',
      message: 'Pending and rejected registrations are visible only to the organizer and the team coach.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('teams')}">Back to public teams</a>`,
    });
    return;
  }
```

Replace the hand-rolled next-game time with the shared formatter:

```html
                 <p class="hf-small hf-muted hf-mb-1">${escapeHtml(formatDate(next.scheduledAt))} · ${escapeHtml(formatTime(next.scheduledAt))}</p>
```

Hide the age column from fans:

```html
                          <th scope="col">Player</th>
                          <th scope="col">Position</th>
                          ${role === 'fan' ? '' : '<th scope="col">Age</th>'}
```

```html
                                <td>${escapeHtml(player.position)}</td>
                                ${role === 'fan' ? '' : `<td>${player.age}</td>`}
```

- [ ] **Step 3: Close the demo menu on outside click and Escape**

In `prototype/assets/js/shell.js`, confirm both demo summaries already carry the accessible name from Task 9:

```js
        <summary aria-label="Demo controls"><i class="bi bi-sliders2" aria-hidden="true"></i><span>Demo</span></summary>
```

Immediately before `const refreshBadges = () => {`, add:

```js
  const demoMenus = [...root.querySelectorAll('.hf-demo-menu details')];
  const closeDemoMenus = () => demoMenus.forEach((details) => details.removeAttribute('open'));

  document.addEventListener('pointerdown', (event) => {
    if (!demoMenus.some((details) => details.contains(event.target))) closeDemoMenus();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeDemoMenus();
  });
```

- [ ] **Step 4: Add the mobile hardening rules**

Append to `prototype/assets/css/hoopflow.css`:

```css
/* Public and mobile hardening */
.hf-demo-roles button,
.hf-demo-row .hf-btn,
.hf-filter {
  min-height: 44px;
}

.hf-main,
.hf-grid > *,
.hf-split > * {
  min-width: 0;
}

.hf-change {
  overflow-wrap: anywhere;
}

@media (max-width: 991.98px) {
  .hf-main {
    padding-bottom: calc(82px + env(safe-area-inset-bottom));
    overflow-x: clip;
  }

  .hf-topbar-context {
    min-width: 0;
  }

  .hf-topbar-title {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .hf-demo-panel {
    max-height: calc(100dvh - 150px);
    overflow-y: auto;
  }
}

@media (max-width: 575.98px) {
  .hf-page-title {
    overflow-wrap: anywhere;
  }

  .hf-page-actions {
    flex-direction: column;
  }

  .hf-page-actions .hf-btn {
    width: 100%;
  }

  .hf-between {
    flex-wrap: wrap;
  }

  .hf-stat-strip {
    gap: 8px 16px;
  }

  .hf-stat-strip div {
    min-width: 62px;
  }

  .hf-card {
    padding: 14px;
  }

  .hf-timeline {
    padding-left: 12px;
  }

  .hf-timeline-item {
    padding-left: 14px;
  }
}
```

- [ ] **Step 5: Verify the fan pass at 1366 and 390**

Sign in as fan and open `http://127.0.0.1:8001/teams/index.html` at 1366×768. Expected: 29 approved teams, no Status filter, no pending/rejected chips, and KPI labels Public teams, Divisions, Cities, Tournaments. Search for “Napico”. Expected: no match, because the rejected team is not public.

Open `http://127.0.0.1:8001/teams/show.html?id=team-16`. Expected: “Team not available” and a Back to public teams button. Open `?id=team-1`. Expected: the Vipers profile has no Consent, Documents, payment, or age column and no Manage roster action.

Open the Demo menu. Click outside it. Expected: it closes. Open it again, press Escape. Expected: it closes. Confirm every role button, theme button, and filter chip is at least 44px tall.

Set the viewport to 390×844 and open the dashboard, bracket, schedule, standings, announcements, teams, and the Vipers profile. Expected: no horizontal page scroll (`document.documentElement.scrollWidth <= window.innerWidth`), the bottom navigation does not cover the last card, tables scroll inside their own wrappers, and the demo panel stays inside the viewport. No console errors.

Switch back to organizer and open Teams. Expected: 32 teams, the Status filter returns, pending and rejected teams appear, and the Vipers profile shows the private payment and eligibility cards.

- [ ] **Step 6: Check the modules parse and commit**

```powershell
cd prototype
node --check assets/js/pages/teams.js
node --check assets/js/pages/team-show.js
node --check assets/js/shell.js
```

Expected: no output.

```powershell
git add prototype/assets/js/pages/teams.js prototype/assets/js/pages/team-show.js prototype/assets/js/shell.js prototype/assets/css/hoopflow.css
git commit -m "fix(prototype): harden public views and mobile layout"
```

## Task 23: Remove legacy prototype code and rewrite the README

**Files:**
- Delete: `prototype/assets/js/mock-data.js`
- Delete: `prototype/assets/js/navigation.js`
- Delete: `prototype/assets/js/role-switcher.js`
- Rewrite: `prototype/README.md`

**Interfaces:**
- Consumes: the shared shell, store, page modules, and seed data from Tasks 2–22.
- Produces: a single documented prototype entry point, no legacy globals or floating role pill, and a README that matches the demo script, routes, roles, and offline setup.

- [ ] **Step 1: Prove the legacy files are unused**

```powershell
Get-ChildItem prototype -Recurse -Include *.html,*.js,*.css,*.md -File |
  Select-String -Pattern 'mock-data|navigation\.js|role-switcher|data-role-required|hoopflow_role|hfTopbarContainer|app-container'
```

Expected: no output. If a page still references one of these, replace it with the shared `#hfShell` / `#main` structure from Task 9 before continuing.

- [ ] **Step 2: Delete the legacy modules**

```powershell
Remove-Item prototype/assets/js/mock-data.js, prototype/assets/js/navigation.js, prototype/assets/js/role-switcher.js
```

Expected: the three files are gone and every remaining script import resolves under `prototype/assets/js/`.

- [ ] **Step 3: Rewrite the README**

Replace `prototype/README.md` with:

```markdown
# HoopFlow courtside demo

A build-free, click-through prototype of the HoopFlow tournament operations product. This is a demo surface, not the Laravel application in [`../src`](../src).

The prototype runs offline, keeps all state in one browser, and covers the full ten-step organizer, scorekeeper, coach, and fan story.

## Quick start

```powershell
# from the repository root
php -S 127.0.0.1:8001 -t prototype
```

Open `http://127.0.0.1:8001`. The root route redirects to the splash screen.

Do not open the HTML files with `file://`; the ES modules and local assets need an HTTP origin.

## Demo accounts

| Role | Email | Scope |
|---|---|---|
| Organizer | `organizer@hoopflow.ph` | Everything: approvals, payments, tournaments, brackets, schedules, scores, announcements, audit |
| Scorekeeper | `scorekeeper@hoopflow.ph` | Score entry, corrections, and game stats |
| Coach | `coach.marcus@hoopflow.ph` | San Antonio Vipers, roster, eligibility, payment reference |
| Fan | `fan.juan@hoopflow.ph` | Read-only public bracket, schedule, results, standings, announcements |

The login page has quick-fill buttons for all four roles. Passwords are not checked in this prototype.

## Ten-step demo

| # | Action | Expected result |
|---|---|---|
| 1 | Sign in as organizer | Dashboard shows 3 tournaments, 2 pending teams, ₱3,000 uncollected, and 15 approved teams in the active cup |
| 2 | Submissions → approve Bagong Ilog Bears (`team-open-7`) | Pending teams drops to 1; the team becomes approved and an audit entry is written |
| 3 | Payments → verify Maybunga Titans (`team-6`) with GCash | Collected rises from ₱19,500 to ₱21,000; outstanding drops to ₱1,500 |
| 4 | Bracket → open `qf-3` → enter 71–65 for Kapitolyo Knights | Knights advance into semifinal slot 1; the bracket, standings, and audit update together |
| 5 | Schedule → pick a court and day → open a box score | Filters drive the match list; the final opens the official box score |
| 6 | Switch to coach → register for Pasig Youth Hoopfest → Fill demo data → submit | San Antonio Vipers U17 is created as pending with 8 verified players and `GCASH-9090909` |
| 7 | Switch to organizer → approve San Antonio Vipers U17 | The coach's registration status becomes approved; the queue count falls |
| 8 | Switch to scorekeeper → open the bracket → open a score console → visit Submissions | Score entry is available; the restricted route redirects to the dashboard with a toast |
| 9 | Switch to fan → resize to 390×844 | Public dashboard, bracket pager, week strip, standings cards, and announcements; no private payment or eligibility data |
| 10 | Profile → Audit log → Reset demo | New audit entries appear newest first; reset restores 8 seeded entries, 2 pending teams, ₱19,500 collected |

Bracket and schedule generation are demonstrated separately on **CDO Grassroots Classic** (`tournament-3`): generate the 8-team bracket, then generate the schedule. The active cup is never disturbed.

## Roles and permissions

| Role | Allowed | Blocked |
|---|---|---|
| Organizer | All actions | — |
| Scorekeeper | `score.save`, `score.correct`, `stats.view` | Approvals, payments, tournament setup, roster edits, announcements |
| Coach | `team.register`, `team.edit`, `payment.submit`, `team.view.own` | Score entry, approvals, other teams' private data |
| Fan | `public.view` | Every write action and every private detail |

Restricted routes use `guardRole` and redirect to the dashboard with an explanation. Permissions are enforced in the store, not only by hiding buttons.

## Demo controls

The **Demo** menu in the press-box rail or topbar contains:

- Role switch for organizer, scorekeeper, coach, and fan.
- Light and dark theme.
- Seed version.
- Reset demo.

The profile page repeats the theme and reset controls and links to the audit log for organizers. Reset requires confirmation and restores the exact seeded state.

Theme is stored in `localStorage.hoopflow_theme`. The store is `localStorage.hoopflow_db`.

## Seeded data

Three Philippine grassroots tournaments:

| Tournament | ID | Division | Status | Story |
|---|---|---|---|---|
| HoopFlow Summer Cup | `tournament-1` | U15 | Active | 16 registered, 15 approved, 1 rejected before seeding; 4 courts; one bye, one forfeit, one corrected score; R16 complete, two quarterfinals final, two scheduled |
| Pasig Youth Hoopfest | `tournament-2` | U17 | Registration open | 8 of 16 slots claimed: 6 approved and 2 pending; no bracket; used for live registration and review |
| CDO Grassroots Classic | `tournament-3` | U14 | Draft/upcoming | 8 approved teams, 2 courts, no bracket or schedule; used for live generation |

The coach owns San Antonio Vipers (`team-1`) in the active cup and has not registered for the open cup, so the registration journey can be shown live.

## Routes

| Surface | Route |
|---|---|
| Splash / login / register | `auth/splash.html`, `auth/login.html`, `auth/register.html` |
| Dashboard | `dashboard/index.html` |
| Tournaments | `tournaments/index.html`, `show.html?id=…`, `create.html` |
| Submissions / payments | `tournaments/submissions.html`, `tournaments/payments.html` |
| Teams | `teams/index.html`, `show.html?id=…` |
| Coach registration / My Team | `teams/register.html?tournament=…`, `teams/my-team.html?team=…` |
| Bracket | `brackets/view.html?id=…&match=…` |
| Schedule / matchups | `schedule/index.html?id=…`, `schedule/matches.html?id=…` |
| Standings | `standings/table.html?id=…` |
| Game stats | `games/stats.html?match=…` |
| Announcements | `announcements/index.html`, `announcements/create.html?id=…` |
| Profile / audit | `profile/index.html`, `profile/audit.html` |

Query conventions: `id` scopes a tournament page, `match` opens a game, and `team` / `tournament` scope people flows.

## Architecture

```text
prototype/
  assets/css/hoopflow.css       Design tokens, components, themes, responsive rules
  assets/js/app.js              Boots the store, UI, shell, theme, and page module
  assets/js/seed.js             Deterministic schema-v2 demo data
  assets/js/store.js            Versioned localStorage store, permissions, actions, selectors, audit
  assets/js/ui.js               Formatting, toasts, dialogs, focus, field errors
  assets/js/components.js       Shared cards, KPI tiles, headers, bracket matches, standings
  assets/js/shell.js            Press-box rail, topbar, mobile tabs, demo menu
  assets/js/routes.js           Route map and query builder
  assets/js/guards.js           Role guard with redirect and toast
  assets/js/options.js          Divisions, payment methods, announcement options
  assets/js/stats.js            Box-score row, sort, and total helpers
  assets/js/score-dialogs.js    Shared score console and correction dialog
  assets/js/pages/              One renderer per data-page
  assets/vendor/                Vendored Bootstrap, Bootstrap Icons, and fonts
  tests/                        Node test runner suites
```

Pages never touch `localStorage` directly. They render from store selectors and call named actions. A score change updates the match, the next-round slot, standings, team records, recent results, and audit through one action.

## Tests

```powershell
cd prototype
node --test tests
```

The suite covers the seed fixture, store schema, permissions, every action, bracket propagation, schedule conflicts, scoring, corrections, and the ten-step demo sequence.

## Offline demo

Bootstrap CSS/JS, Bootstrap Icons, and the Barlow Condensed + Inter font files are vendored under `prototype/assets/vendor/`. No page loads a CDN asset.

If the vendor files are missing, rebuild them from the repository root:

```powershell
./tools/vendor-prototype-assets.ps1
```

## Prototype-only shortcuts

These are deliberate and must not be carried into `src/`:

- Passwords are not checked. Quick-fill sets the demo role and redirects.
- All data is browser localStorage. There is no server, database, authentication, file upload, or real payment.
- The profile is read-only; name and organization editing are out of scope.
- The score console records final scores. It does not track a live clock, fouls, timeouts, or play-by-play.
- Single elimination only. The round-robin option is disabled with a v2 note.
- Fan is a read-only public view. Pending and rejected registrations, payments, consent, and documents stay private.
- Tabs do not live-sync. A storage event reloads the page so a second tab cannot silently drift.
- Generated IDs include timestamps. Reset between rehearsal runs.
- Supported demo sizes are 1366×768 and ~390×844.

## Troubleshooting

- **Blank page:** serve over HTTP and check the browser console. ES modules do not load from `file://`.
- **Stale demo state:** Demo → Reset, or remove `localStorage.hoopflow_db`.
- **Wrong role or theme:** reset the store, or clear `hoopflow_theme` and reload.
- **PHP unavailable:** any static server works, for example `python -m http.server 8001 --directory prototype`.
- **Offline check:** load the app once over localhost, switch the network off, and walk the ten steps. There should be no failed requests.

## Design source

- Spec: [`../docs/superpowers/specs/2026-10-30-prototype-ui-interactivity-design.md`](../docs/superpowers/specs/2026-10-30-prototype-ui-interactivity-design.md)
- Plan: [`../docs/superpowers/plans/2026-10-30-prototype-ui-interactivity.md`](../docs/superpowers/plans/2026-10-30-prototype-ui-interactivity.md)
```

- [ ] **Step 4: Run the final reference sweeps**

```powershell
Get-ChildItem prototype -Recurse -Include *.html,*.js,*.css,*.md -File |
  Select-String -Pattern 'mock-data|navigation\.js|role-switcher|data-role-required|hoopflow_role|hfTopbarContainer|app-container'
Get-ChildItem prototype -Recurse -Include *.html,*.css -File |
  Select-String -Pattern 'cdn\.|jsdelivr|fonts\.googleapis|unpkg'
Get-ChildItem prototype/assets/js -Recurse -Include *.js -File |
  Select-String -Pattern '(?<!\.)\b(alert|confirm|prompt)\('
```

Expected: no output from all three commands.

- [ ] **Step 5: Commit the cleanup**

```powershell
git add prototype/README.md
git add -u prototype/assets/js/mock-data.js prototype/assets/js/navigation.js prototype/assets/js/role-switcher.js
git commit -m "chore(prototype): remove legacy scripts and document the demo"
```

## Task 24: Ten-step rehearsal and release verification

**Files:**
- Create: `prototype/tests/demo-script.test.mjs`
- Create: `docs/superpowers/verification/2026-10-30-prototype-demo.md`

**Interfaces:**
- Consumes: the complete prototype from Tasks 1–23, the store actions/selectors, and the browser at 1366×768 and 390×844.
- Produces: an automated store-level run of the ten-step script, a browser rehearsal record, offline and console evidence, Lighthouse accessibility scores, and a reset/persistence check.

- [ ] **Step 1: Add the ten-step integration test**

Create `prototype/tests/demo-script.test.mjs`:

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { createStore } from '../assets/js/store.js';
import { memoryStorage } from './helpers.mjs';

function registration() {
  return {
    name: 'San Antonio Vipers U17',
    shortName: 'V17',
    city: 'Pasig City',
    coach: 'Marcus Rivera',
    avatarColor: '#ea580c',
    players: Array.from({ length: 8 }, (_, index) => ({
      name: `Demo Player ${index + 1}`,
      number: index + 1,
      position: 'Point Guard (PG)',
      age: 15,
      guardianConsent: true,
      documentStatus: 'verified',
    })),
    payment: { method: 'GCash', ref: 'GCASH-9090909' },
  };
}

test('the ten-step demo script stays consistent end to end', () => {
  const store = createStore({ storage: memoryStorage() });

  // 1. Organizer dashboard baseline.
  const kpis = store.select.kpis('tournament-1');
  assert.equal(kpis.approved, 15);
  assert.equal(store.getState().teams.filter((team) => team.status === 'pending').length, 2);
  const beforePayments = store.select.paymentSummary('tournament-1');
  assert.equal(beforePayments.collected, 19500);
  assert.equal(beforePayments.outstanding, 3000);
  assert.equal(beforePayments.pendingCount, 2);

  // 2. Approve a pending open-cup team.
  store.actions.reviewTeam('team-open-7', 'approve');
  assert.equal(store.select.team('team-open-7').status, 'approved');
  assert.equal(store.getState().teams.filter((team) => team.status === 'pending').length, 1);

  // 3. Verify a GCash payment in the active cup.
  store.actions.verifyPayment('team-6', {
    method: 'GCash',
    ref: 'GCASH-7788991',
    amount: 1500,
  });
  const afterPayments = store.select.paymentSummary('tournament-1');
  assert.equal(afterPayments.collected, 21000);
  assert.equal(afterPayments.outstanding, 1500);
  assert.equal(afterPayments.pendingCount, 1);

  // 4. Record qf-3; the winner advances and standings update.
  const scored = store.actions.saveScore('qf-3', 71, 65);
  assert.equal(scored.status, 'final');
  assert.equal(scored.winnerId, 'team-2');
  assert.equal(store.select.match('sf-2').team1Id, 'team-2');
  const knights = store.select.standings('tournament-1').find((row) => row.teamId === 'team-2');
  assert.equal(knights.wins, 2);
  assert.equal(knights.losses, 0);

  // 6. Coach registers for the open cup.
  store.actions.setRole('coach');
  const newTeamId = store.actions.registerTeam('tournament-2', registration());
  assert.equal(store.select.team(newTeamId).status, 'pending');
  assert.equal(store.select.team(newTeamId).players.length, 8);
  assert.equal(store.select.team(newTeamId).payment.ref, 'GCASH-9090909');

  // 7. Organizer approves the new registration.
  store.actions.setRole('organizer');
  store.actions.reviewTeam(newTeamId, 'approve');
  assert.equal(store.select.team(newTeamId).status, 'approved');

  // 8. Scorekeeper can score but cannot approve.
  store.actions.setRole('scorekeeper');
  assert.equal(store.can('score.save'), true);
  assert.equal(store.can('team.approve'), false);
  assert.throws(
    () => store.actions.reviewTeam(newTeamId, 'approve'),
    (error) => error.code === 'FORBIDDEN'
  );

  // 9. Fan is read-only public.
  store.actions.setRole('fan');
  assert.equal(store.can('public.view'), true);
  assert.equal(store.can('score.save'), false);
  assert.equal(store.can('team.approve'), false);

  // 10. The run writes five audit entries; reset restores the seed exactly.
  assert.equal(store.getState().audit.length, 13);
  store.actions.reset();
  assert.equal(store.getRole(), 'organizer');
  assert.equal(store.getState().audit.length, 8);
  assert.equal(store.select.team('team-open-7').status, 'pending');
  assert.equal(store.select.match('qf-3').status, 'scheduled');
  assert.equal(store.select.paymentSummary('tournament-1').collected, 19500);
  assert.equal(store.getState().teams.filter((team) => team.status === 'pending').length, 2);
});
```

- [ ] **Step 2: Run the full automated suite**

```powershell
cd prototype
node --test tests
```

Expected: every seed, store, action, bracket, schedule, scoring, stats, and demo-script test passes with zero failures.

- [ ] **Step 3: Rehearse the ten steps at 1366×768 from a fresh store**

Start the server and open `http://127.0.0.1:8001/auth/login.html` in a 1366×768 browser. Use Demo → Reset before the first run so the fixture is exact. Walk the script with no page reloads except where noted:

1. **Organizer login.** Choose the organizer quick-fill. Expect `kpi-tournaments` = 3, `kpi-pending` = 2, `kpi-uncollected` = ₱3,000, `kpi-approved` = 15. Open Tournaments and confirm the three seeded cards.
2. **Approve a team.** Open Submissions, search `Bears`, open Bagong Ilog Bears, and approve. Expect the row to become approved, the pending KPI to fall to 1 on the dashboard, and an audit entry to exist.
3. **Verify a payment.** Open Payments, filter Pending, open Maybunga Titans, choose GCash, enter `GCASH-7788991` and ₱1,500, and save. Expect collected ₱21,000, outstanding ₱1,500, and pending count 1.
4. **Score a quarterfinal.** Open Bracket, open `qf-3` (Knights vs Comets), choose Enter final score, type 71 and 65 in the two score inputs, finalize, and confirm. Expect the Knights in semifinal slot 1, the match to show Final, the standings to show the Knights at 2–0, and the bracket KPI corrections count unchanged.
5. **Filter the schedule.** Open Schedule, choose Court 1, then a day with games. Expect the match list to narrow, the day summary to match the visible games, and the box-score link to open `games/stats.html?match=qf-1`. Confirm the scoreboard reads Vipers 78–70 Warriors and the correction banner reads 74–70 → 78–70.
6. **Coach registration.** Switch to coach from Demo. Open Tournaments, choose Pasig Youth Hoopfest, click Register team, then Fill demo data. Confirm San Antonio Vipers U17, 8 players, verified documents, consent, GCash, and `GCASH-9090909`. Continue to Review and submit. Expect My Team with status pending and the four-step timeline.
7. **Approve the coach.** Switch to organizer. Open Submissions, find San Antonio Vipers U17, approve it. Expect the team status approved and the coach's My Team page to show approved after a reload.
8. **Scorekeeper denial.** Switch to scorekeeper. Open Bracket and confirm Enter final score is available on `qf-4`; open the console, confirm the score inputs and +1/+2/+3 buttons are present, then cancel. Navigate to `tournaments/submissions.html`. Expect a redirect to the dashboard and a toast explaining that only the organizer can review team submissions.
9. **Fan mobile pass.** Switch to fan, set the viewport to 390×844, and open the dashboard, bracket, schedule, standings, announcements, teams, and a team profile. Expect the bracket round pager, week strip, standings cards, no private payment or eligibility data, and no horizontal page scroll. Confirm the rejected team `team-16` shows “Team not available” if opened directly.
10. **Audit and reset.** Open Profile → Audit log. Expect 13 entries, newest first, including the approval, payment, score, registration, and second approval. Filter Entity to Match and confirm the correction shows 74–70 → 78–70. Return to Profile, reset, confirm, and land on the dashboard. Expect pending 2, uncollected ₱3,000, `qf-3` scheduled, and 8 audit entries.

- [ ] **Step 4: Verify persistence, refresh, and reset**

After step 4, reload the bracket. Expected: qf-3 remains final and the Knights remain in semifinal slot 1. After step 7, open My Team for San Antonio Vipers U17 (use the team switcher or the `?team=` link) and reload. Expected: the new team remains approved. After step 10, reload the dashboard. Expected: the reset state persists. Open a second tab, change the role in one tab, and confirm the other tab reloads through the `storage` event rather than showing stale data.

- [ ] **Step 5: Check console, network, and offline behavior**

Visit every route in the README at 1366×768 with the browser console open. Expected: no errors, no failed requests, and no request to a non-localhost origin. Then enable Offline in DevTools Network, reload the dashboard, bracket, schedule, standings, announcements, and login. Expected: all render from the vendored assets with no broken icons or fonts.

```powershell
Get-ChildItem prototype -Recurse -Include *.html,*.css -File | Select-String -Pattern 'https?://'
```

Expected: no output.

- [ ] **Step 6: Run Lighthouse accessibility on the five named screens**

Serve the prototype, then run Lighthouse against the dashboard, bracket, schedule, standings, and login. Use Chrome DevTools → Lighthouse if `npx` is unavailable.

```powershell
$pages = @(
  @{ name = 'dashboard'; path = 'dashboard/index.html' },
  @{ name = 'bracket'; path = 'brackets/view.html' },
  @{ name = 'schedule'; path = 'schedule/index.html' },
  @{ name = 'standings'; path = 'standings/table.html' },
  @{ name = 'login'; path = 'auth/login.html' }
)
foreach ($page in $pages) {
  npx --yes lighthouse "http://127.0.0.1:8001/$($page.path)" `
    --only-categories=accessibility --chrome-flags="--headless=new" `
    --output=json --output-path="$env:TEMP/hoopflow-lighthouse-$($page.name).json" --quiet
  $score = (Get-Content "$env:TEMP/hoopflow-lighthouse-$($page.name).json" | ConvertFrom-Json).categories.accessibility.score
  "$($page.name): $([math]::Round($score * 100))"
}
```

Expected: every score is 95 or higher. Investigate and fix any contrast, name, role, or focus failure before recording the result.

- [ ] **Step 7: Write the verification record**

Create `docs/superpowers/verification/2026-10-30-prototype-demo.md` with these sections, filled with the observed evidence:

```markdown
# HoopFlow prototype verification - 2026-10-30

## Environment
- OS, browser, PHP version, viewport sizes, seed version.

## Automated checks
- `node --test tests` result.
- Syntax checks for every page module, store, ui, components, shell, routes, guards, options, stats, and score dialogs.
- Legacy, CDN, and native-dialog sweeps with their output.

## Ten-step rehearsal
- A table with Step, Action, Expected, Actual, Pass/Fail.
- Note the qf-3 score, the new team ID/name, the payment totals after step 3, and the audit count after step 10.

## Responsive check
- 1366×768 and 390×844 results for dashboard, bracket, schedule, standings, announcements, teams, profile, and audit.
- Horizontal scroll, bottom-nav clearance, demo-menu containment, and touch-target results.

## Persistence and reset
- Mid-demo refresh results.
- Second-tab storage behavior.
- Exact reset state: pending teams, collected total, qf-3 status, audit length.

## Console and network
- Route list checked, error count, failed request count, external request count.
- Offline reload result.

## Accessibility
- Lighthouse scores for dashboard, bracket, schedule, standings, login.
- Any manual focus, keyboard, or dialog findings.

## Deviations and follow-ups
- Anything that differs from the spec, with a reason and an owner.
```

- [ ] **Step 8: Run the final static gates and commit**

```powershell
cd prototype
Get-ChildItem assets/js -Recurse -Filter *.js | ForEach-Object { node --check $_.FullName }
node --test tests
```

Expected: no syntax output and every test passes.

```powershell
Get-ChildItem prototype -Recurse -Include *.html,*.js,*.css,*.md -File |
  Select-String -Pattern 'mock-data|navigation\.js|role-switcher|data-role-required|hoopflow_role'
Get-ChildItem prototype -Recurse -Include *.html,*.css -File |
  Select-String -Pattern 'cdn\.|jsdelivr|fonts\.googleapis|unpkg'
Get-ChildItem prototype/assets/js -Recurse -Include *.js -File |
  Select-String -Pattern '(?<!\.)\b(alert|confirm|prompt)\('
```

Expected: no output from all three commands.

```powershell
git add prototype/tests/demo-script.test.mjs docs/superpowers/verification/2026-10-30-prototype-demo.md
git commit -m "test(prototype): verify the ten-step demo and record release checks"
```
