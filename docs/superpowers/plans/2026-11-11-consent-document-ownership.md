# Consent & Document Ownership Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move consent and document control from the coach to the organizer after registration, and replace the numeric age input with a validated birthdate on both coach screens.

**Architecture:** Add a tri-state consent status to `players`, make `eligibility_documents` the document source of truth, and route every status write through `EligibilityService`. Coaches write only player identity fields. Organizers edit consent/document status inline in Submissions, with audit logging and an approval warning.

**Tech Stack:** Laravel 13, PHP 8.4, Livewire 4, Blade, Alpine 3, Bootstrap 5.3, Pest, PostgreSQL/SQLite.

## Global Constraints

- Laravel 13, PHP 8.4+, Blade, Livewire 4, Alpine 3.
- Bootstrap 5.3 compiled from SCSS by Vite — no Tailwind, no Bootstrap JavaScript, no SPA framework.
- PostgreSQL 17 in production; SQLite in-memory is used by the test suite.
- All consent, document, and eligibility status writes must go through `App\Services\EligibilityService`.
- The coach must never write `consent_status`, `consent_at`, or `eligibility_documents.status`.
- The organizer account is the app's admin. Do not add a new role or change route permissions.
- Before claiming work is done, run `composer quality` from `src/` and report the actual output.

---

## File Map

| File | Responsibility |
|---|---|
| `database/migrations/2026_11_11_000001_add_consent_status_to_players_table.php` | Add consent columns, backfill, make `file_url` nullable, dedupe and uniquely index documents |
| `app/Models/Player.php` | Fillable fields, casts, and recorder relation |
| `database/factories/PlayerFactory.php` | Default pending consent state |
| `database/seeders/PrototypeSeeder.php` | Keep seeded consent/document states consistent |
| `app/Services/EligibilityService.php` | Single writer for consent, document, and eligibility state |
| `app/Rules/EligibleBirthdate.php` | Division age validation at tournament start |
| `app/Livewire/Coach/TeamRegister.php` | Birthdate-only coach registration, pending initialization |
| `app/Livewire/Coach/MyTeam.php` | Birthdate-only player editing, document reset on birthdate change |
| `app/Livewire/Organizer/SubmissionsIndex.php` | Inline status editing, audit, approval warning |
| `resources/views/livewire/coach/team-register.blade.php` | Registration form and review table |
| `resources/views/livewire/coach/my-team.blade.php` | Player dialog and read-only status chips |
| `resources/views/livewire/organizer/submissions-index.blade.php` | Inline selects and approval warning UI |
| `tests/Feature/Database/ConsentSchemaTest.php` | Schema defaults and unique document constraint |
| `tests/Unit/Services/EligibilityServiceTest.php` | Service transitions |
| `tests/Unit/Rules/EligibleBirthdateTest.php` | Birthdate rule |
| `tests/Feature/Pages/TeamRegisterTest.php` | Coach registration |
| `tests/Feature/Pages/MyTeamTest.php` | Coach player editing |
| `tests/Feature/Pages/TeamsTest.php` | Existing My Team add/update tests |
| `tests/Feature/Pages/SubmissionsPaymentsTest.php` | Organizer status editing and approval |
| `tests/Feature/Pages/AuditTrailTest.php` | Existing registration/approval payloads |

---

### Task 1: Schema, model, factory, and seeder

**Files:**
- Create: `src/database/migrations/2026_11_11_000001_add_consent_status_to_players_table.php`
- Modify: `src/app/Models/Player.php`
- Modify: `src/database/factories/PlayerFactory.php`
- Modify: `src/database/seeders/PrototypeSeeder.php`
- Test: `src/tests/Feature/Database/ConsentSchemaTest.php`

**Interfaces:**
- Produces: `players.consent_status` (`pending` / `verified` / `missing`), `players.consent_recorded_by`, `players.consent_recorded_at`; nullable `eligibility_documents.file_url`; unique `(player_id, type)`.

- [ ] **Step 1: Write the failing schema test**

Create `src/tests/Feature/Database/ConsentSchemaTest.php`:

```php
<?php

use App\Models\EligibilityDocument;
use App\Models\Player;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults new players to pending consent', function () {
    $player = Player::factory()->create();

    expect($player->fresh()->consent_status)->toBe('pending')
        ->and($player->fresh()->consent_at)->toBeNull();
});

it('allows a pending eligibility document without a file url', function () {
    $document = EligibilityDocument::factory()->create([
        'player_id' => Player::factory()->create()->id,
        'type' => 'birth_certificate',
        'file_url' => null,
        'status' => 'pending',
    ]);

    expect($document->fresh()->file_url)->toBeNull();
});

it('allows only one document per player and type', function () {
    $player = Player::factory()->create();

    EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
    ]);

    expect(fn () => EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
    ]))->toThrow(QueryException::class);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run from `src/`:

```powershell
php artisan test tests/Feature/Database/ConsentSchemaTest.php
```

Expected: FAIL with an error that `consent_status` or the nullable `file_url` does not exist.

- [ ] **Step 3: Create the migration**

Create `src/database/migrations/2026_11_11_000001_add_consent_status_to_players_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('consent_status')->default('pending');
            $table->foreignId('consent_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('consent_recorded_at')->nullable();
        });

        DB::table('players')->whereNotNull('consent_at')->update([
            'consent_status' => 'verified',
            'consent_recorded_at' => DB::raw('consent_at'),
        ]);

        Schema::table('eligibility_documents', function (Blueprint $table) {
            $table->string('file_url')->nullable()->change();
        });

        $duplicates = DB::table('eligibility_documents')
            ->select('player_id', 'type', DB::raw('MAX(id) as keep_id'))
            ->groupBy('player_id', 'type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('eligibility_documents')
                ->where('player_id', $duplicate->player_id)
                ->where('type', $duplicate->type)
                ->where('id', '<>', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('eligibility_documents', function (Blueprint $table) {
            $table->unique(['player_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('eligibility_documents', function (Blueprint $table) {
            $table->dropUnique(['player_id', 'type']);
        });

        DB::table('eligibility_documents')->whereNull('file_url')->update([
            'file_url' => 'pending-review',
        ]);

        Schema::table('eligibility_documents', function (Blueprint $table) {
            $table->string('file_url')->nullable(false)->change();
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consent_recorded_by');
            $table->dropColumn(['consent_status', 'consent_recorded_at']);
        });
    }
};
```

- [ ] **Step 4: Update the Player model**

In `src/app/Models/Player.php`, replace the `#[Fillable(...)]` attribute with:

```php
#[Fillable(['team_id', 'first_name', 'last_name', 'birthdate', 'jersey_number', 'position', 'photo_url', 'guardian_name', 'guardian_phone', 'consent_at', 'consent_status', 'consent_recorded_by', 'consent_recorded_at', 'consent_version', 'eligibility_status', 'verified_by', 'verified_at'])]
```

Add `consent_recorded_at` to the casts:

```php
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'consent_at' => 'datetime',
            'consent_recorded_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
```

Add the recorder relation after `verifiedBy()`:

```php
    /**
     * @return BelongsTo<User, $this>
     */
    public function consentRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consent_recorded_by');
    }
```

- [ ] **Step 5: Update the player factory**

In `src/database/factories/PlayerFactory.php`, replace the consent/eligibility block with:

```php
            'consent_at' => null,
            'consent_status' => 'pending',
            'consent_recorded_by' => null,
            'consent_recorded_at' => null,
            'consent_version' => null,
            'eligibility_status' => 'pending',
            'verified_by' => null,
            'verified_at' => null,
```

- [ ] **Step 6: Update the prototype seeder**

In `src/database/seeders/PrototypeSeeder.php`, update each player creation block.

Active teams block (around lines 299–311):

```php
                        'eligibility_status' => $p['missing'] ? 'pending' : 'verified',
                        'consent_at' => $p['missing'] ? null : now(),
                        'consent_status' => $p['missing'] ? 'missing' : 'verified',
                        'consent_recorded_by' => $p['missing'] ? null : $this->organizer->id,
                        'consent_recorded_at' => $p['missing'] ? null : now(),
                        'guardian_name' => null,
                        'guardian_phone' => null,
```

and its document row:

```php
                    ['file_url' => 'https://example.com/doc/'.$player->id, 'status' => $p['missing'] ? 'missing' : 'approved', 'reviewed_by' => $p['missing'] ? null : $this->organizer->id, 'reviewed_at' => $p['missing'] ? null : now(), 'notes' => null]
```

Rejected team block (around lines 356–368):

```php
                        'eligibility_status' => 'pending',
                        'consent_at' => null,
                        'consent_status' => 'pending',
                        'consent_recorded_by' => null,
                        'consent_recorded_at' => null,
                        'guardian_name' => null,
                        'guardian_phone' => null,
```

Open teams block (around lines 424–436):

```php
                        'eligibility_status' => $pending || $p['missing'] ? 'pending' : 'verified',
                        'consent_at' => $pending || $p['missing'] ? null : now(),
                        'consent_status' => $pending ? 'pending' : ($p['missing'] ? 'missing' : 'verified'),
                        'consent_recorded_by' => $pending ? null : $this->organizer->id,
                        'consent_recorded_at' => $pending ? null : now(),
                        'guardian_name' => null,
                        'guardian_phone' => null,
```

and its document row:

```php
                    ['file_url' => 'https://example.com/doc/'.$player->id, 'status' => $pending ? 'pending' : ($p['missing'] ? 'missing' : 'approved'), 'reviewed_by' => $pending || $p['missing'] ? null : $this->organizer->id, 'reviewed_at' => $pending || $p['missing'] ? null : now(), 'notes' => null]
```

CDO teams block (around lines 497–509):

```php
                        'eligibility_status' => 'verified',
                        'consent_at' => now(),
                        'consent_status' => 'verified',
                        'consent_recorded_by' => $this->organizer->id,
                        'consent_recorded_at' => now(),
                        'guardian_name' => null,
                        'guardian_phone' => null,
```

- [ ] **Step 7: Run the schema test**

Run from `src/`:

```powershell
php artisan test tests/Feature/Database/ConsentSchemaTest.php
```

Expected: PASS (3 tests).

- [ ] **Step 8: Commit**

```powershell
git add src/database/migrations/2026_11_11_000001_add_consent_status_to_players_table.php src/app/Models/Player.php src/database/factories/PlayerFactory.php src/database/seeders/PrototypeSeeder.php src/tests/Feature/Database/ConsentSchemaTest.php
git commit -m "feat(db): add tri-state consent and document constraints"
```

---

### Task 2: EligibilityService becomes the single status writer

**Files:**
- Modify: `src/app/Services/EligibilityService.php`
- Test: `src/tests/Unit/Services/EligibilityServiceTest.php`

**Interfaces:**
- Consumes: `players.consent_status`, `players.consent_recorded_by`, `players.consent_recorded_at`, `eligibility_documents.status`.
- Produces:
  - `initializePlayer(Player $player): void`
  - `recordConsent(Player $player, string $status, ?User $actor = null): void`
  - `reviewDocument(Player $player, string $status, ?User $actor = null): void`
  - `resetDocumentReview(Player $player): void`
  - `recalculate(Player $player, ?User $actor = null): void`
  - `status(Team $team): array{documentsComplete: bool, consentComplete: bool, verifiedAt: mixed}`
  - `documentStatusForPlayer(Player $player): string`

- [ ] **Step 1: Replace the old service tests**

Replace lines 12–147 of `src/tests/Unit/Services/EligibilityServiceTest.php` with these tests. Keep the two `validatePlayer` tests at the end.

```php
it('initializes a player with pending consent and a pending birth certificate', function () {
    $team = Team::factory()->create();
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'consent_status' => 'verified',
        'consent_at' => now(),
    ]);

    (new EligibilityService)->initializePlayer($player);

    $player->refresh();

    expect($player->consent_status)->toBe('pending')
        ->and($player->consent_at)->toBeNull()
        ->and($player->eligibility_status)->toBe('pending')
        ->and($player->eligibilityDocuments()
            ->where('type', 'birth_certificate')
            ->where('status', 'pending')
            ->count())->toBe(1);
});

it('records verified consent with actor metadata', function () {
    $organizer = User::factory()->create();
    $player = Player::factory()->create([
        'consent_status' => 'pending',
        'consent_at' => null,
    ]);

    (new EligibilityService)->recordConsent($player, 'verified', $organizer);

    $player->refresh();

    expect($player->consent_status)->toBe('verified')
        ->and($player->consent_at)->not->toBeNull()
        ->and((int) $player->consent_recorded_by)->toBe((int) $organizer->id)
        ->and($player->consent_recorded_at)->not->toBeNull();
});

it('records missing consent and clears the consent timestamp', function () {
    $organizer = User::factory()->create();
    $player = Player::factory()->create([
        'consent_status' => 'verified',
        'consent_at' => now(),
    ]);

    (new EligibilityService)->recordConsent($player, 'missing', $organizer);

    expect($player->fresh()->consent_status)->toBe('missing')
        ->and($player->fresh()->consent_at)->toBeNull()
        ->and($player->fresh()->eligibility_status)->toBe('missing');
});

it('reviews a document as approved and records the reviewer', function () {
    $organizer = User::factory()->create();
    $player = Player::factory()->create([
        'consent_status' => 'verified',
        'consent_at' => now(),
    ]);

    (new EligibilityService)->reviewDocument($player, 'approved', $organizer);

    $document = $player->eligibilityDocuments()
        ->where('type', 'birth_certificate')
        ->firstOrFail();

    expect($document->status)->toBe('approved')
        ->and((int) $document->reviewed_by)->toBe((int) $organizer->id)
        ->and($document->reviewed_at)->not->toBeNull()
        ->and($player->fresh()->eligibility_status)->toBe('verified');
});

it('resets document review to pending and clears reviewer metadata', function () {
    $organizer = User::factory()->create();
    $player = Player::factory()->create([
        'consent_status' => 'verified',
        'consent_at' => now(),
    ]);
    $document = EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
        'status' => 'approved',
        'reviewed_by' => $organizer->id,
        'reviewed_at' => now(),
    ]);

    (new EligibilityService)->resetDocumentReview($player);

    $document->refresh();

    expect($document->status)->toBe('pending')
        ->and($document->reviewed_by)->toBeNull()
        ->and($document->reviewed_at)->toBeNull()
        ->and($player->fresh()->eligibility_status)->toBe('pending');
});

it('recalculates verified only when consent and the document are both approved', function () {
    $player = Player::factory()->create([
        'consent_status' => 'verified',
        'consent_at' => now(),
    ]);
    EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
        'status' => 'approved',
    ]);

    (new EligibilityService)->recalculate($player);

    expect($player->fresh()->eligibility_status)->toBe('verified')
        ->and($player->fresh()->verified_at)->not->toBeNull();
});

it('recalculates pending when consent is still pending', function () {
    $player = Player::factory()->create([
        'consent_status' => 'pending',
        'consent_at' => null,
    ]);
    EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
        'status' => 'approved',
    ]);

    (new EligibilityService)->recalculate($player);

    expect($player->fresh()->eligibility_status)->toBe('pending')
        ->and($player->fresh()->verified_at)->toBeNull();
});

it('recalculates missing when the document is missing', function () {
    $player = Player::factory()->create([
        'consent_status' => 'verified',
        'consent_at' => now(),
    ]);
    EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
        'status' => 'missing',
    ]);

    (new EligibilityService)->recalculate($player);

    expect($player->fresh()->eligibility_status)->toBe('missing');
});

it('status reads consent and document state from the new columns', function () {
    $team = Team::factory()->create();
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'consent_status' => 'verified',
        'consent_at' => now(),
        'eligibility_status' => 'verified',
        'verified_at' => now(),
    ]);
    EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
        'status' => 'approved',
    ]);

    $result = (new EligibilityService)->status($team);

    expect($result['documentsComplete'])->toBeTrue()
        ->and($result['consentComplete'])->toBeTrue()
        ->and($result['verifiedAt'])->not->toBeNull();
});

it('treats a player without a document row as pending', function () {
    $player = Player::factory()->create([
        'consent_status' => 'verified',
        'consent_at' => now(),
        'eligibility_status' => 'verified',
    ]);

    expect((new EligibilityService)->documentStatusForPlayer($player))->toBe('pending');
});
```

Add `use App\Models\User;` to the imports.

- [ ] **Step 2: Run the tests to verify they fail**

Run from `src/`:

```powershell
php artisan test tests/Unit/Services/EligibilityServiceTest.php
```

Expected: FAIL because `initializePlayer`, `recordConsent`, `reviewDocument`, and `resetDocumentReview` do not exist.

- [ ] **Step 3: Replace the service status methods**

In `src/app/Services/EligibilityService.php`, replace everything from `public function refresh(Team $team): void` through `private function documentsCompleteFor(Player $player): bool` with the following. Keep `POSITIONS`, `DOCUMENT_TYPE`, `COACH_DECLARED_FILE_URL`, `validatePlayer`, and `resolveNumbers` unchanged.

```php
    public function initializePlayer(Player $player): void
    {
        $player->update([
            'consent_status' => 'pending',
            'consent_at' => null,
            'consent_recorded_by' => null,
            'consent_recorded_at' => null,
        ]);

        $document = $this->documentFor($player);

        if ($document === null) {
            $document = new EligibilityDocument;
            $document->player_id = $player->id;
            $document->type = self::DOCUMENT_TYPE;
        }

        $document->file_url = null;
        $document->status = 'pending';
        $document->reviewed_by = null;
        $document->reviewed_at = null;
        $document->save();

        $this->recalculate($player);
    }

    public function recordConsent(Player $player, string $status, ?User $actor = null): void
    {
        $this->assertStatus($status, ['pending', 'verified', 'missing']);

        $player->update([
            'consent_status' => $status,
            'consent_at' => $status === 'verified' ? now() : null,
            'consent_recorded_by' => $actor?->id,
            'consent_recorded_at' => now(),
        ]);

        $this->recalculate($player, $actor);
    }

    public function reviewDocument(Player $player, string $status, ?User $actor = null): void
    {
        $this->assertStatus($status, ['pending', 'approved', 'missing']);

        $document = $this->documentFor($player);

        if ($document === null) {
            $document = new EligibilityDocument;
            $document->player_id = $player->id;
            $document->type = self::DOCUMENT_TYPE;
            $document->file_url = null;
        }

        $document->status = $status;
        $document->reviewed_by = $status === 'approved' ? $actor?->id : null;
        $document->reviewed_at = $status === 'approved' ? now() : null;
        $document->save();

        $this->recalculate($player, $actor);
    }

    public function resetDocumentReview(Player $player): void
    {
        $this->reviewDocument($player, 'pending');
    }

    public function recalculate(Player $player, ?User $actor = null): void
    {
        $document = $this->documentFor($player);

        $status = match (true) {
            $player->consent_status === 'verified' && $document?->status === 'approved' => 'verified',
            $player->consent_status === 'missing' || $document?->status === 'missing' => 'missing',
            default => 'pending',
        };

        $player->eligibility_status = $status;

        if ($status === 'verified') {
            if ($player->verified_at === null) {
                $player->verified_at = $document?->reviewed_at ?? now();
            }

            if ($player->verified_by === null) {
                $player->verified_by = $document?->reviewed_by ?? $actor?->id;
            }
        } else {
            $player->verified_by = null;
            $player->verified_at = null;
        }

        $player->save();
    }

    public function refresh(Team $team): void
    {
        $team->load('players.eligibilityDocuments');

        foreach ($team->players as $player) {
            $this->recalculate($player);
        }
    }

    public function documentStatusForPlayer(Player $player): string
    {
        $document = $this->documentFor($player);

        return match ($document?->status) {
            'approved' => 'verified',
            'missing' => 'missing',
            default => 'pending',
        };
    }

    /**
     * @return array{documentsComplete: bool, consentComplete: bool, verifiedAt: mixed}
     */
    public function status(Team $team): array
    {
        $team->load('players.eligibilityDocuments');

        $players = $team->players;

        $documentsComplete = $players->isNotEmpty()
            && $players->every(fn (Player $player): bool => $this->documentsCompleteFor($player));

        $consentComplete = $players->every(
            fn (Player $player): bool => $player->consent_status === 'verified'
        );

        $verifiedAt = null;

        if ($players->isNotEmpty() && $documentsComplete && $consentComplete) {
            $verifiedAt = $players->max('verified_at') ?? now();
        }

        return [
            'documentsComplete' => $documentsComplete,
            'consentComplete' => $consentComplete,
            'verifiedAt' => $verifiedAt,
        ];
    }

    private function documentFor(Player $player): ?EligibilityDocument
    {
        return $player->eligibilityDocuments()
            ->where('type', self::DOCUMENT_TYPE)
            ->first();
    }

    private function documentsCompleteFor(Player $player): bool
    {
        $document = $player->eligibilityDocuments
            ->firstWhere('type', self::DOCUMENT_TYPE);

        return $document instanceof EligibilityDocument && $document->status === 'approved';
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function assertStatus(string $status, array $allowed): void
    {
        if (! in_array($status, $allowed, true)) {
            throw new InvalidArgumentException("Unsupported eligibility status [{$status}].");
        }
    }
```

Add the imports at the top:

```php
use App\Models\User;
use InvalidArgumentException;
```

Remove the old `syncDocumentStatus()` method completely.

- [ ] **Step 4: Run the service tests**

Run from `src/`:

```powershell
php artisan test tests/Unit/Services/EligibilityServiceTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```powershell
git add src/app/Services/EligibilityService.php src/tests/Unit/Services/EligibilityServiceTest.php
git commit -m "feat(eligibility): centralize consent and document transitions"
```

---

### Task 3: EligibleBirthdate validation rule

**Files:**
- Create: `src/app/Rules/EligibleBirthdate.php`
- Test: `src/tests/Unit/Rules/EligibleBirthdateTest.php`

**Interfaces:**
- Produces: `new EligibleBirthdate(?Division $division = null, ?Tournament $tournament = null)` usable in a Laravel validation rule array.

- [ ] **Step 1: Write the failing rule test**

Create `src/tests/Unit/Rules/EligibleBirthdateTest.php`:

```php
<?php

use App\Models\Division;
use App\Models\Tournament;
use App\Rules\EligibleBirthdate;
use Illuminate\Support\Facades\Validator;

function birthdateErrors(string $birthdate, Division $division, Tournament $tournament): array
{
    return Validator::make(
        ['birthdate' => $birthdate],
        ['birthdate' => [new EligibleBirthdate($division, $tournament)]],
    )->errors()->all();
}

it('accepts a player whose age at tournament start is inside the range', function () {
    $tournament = Tournament::factory()->create(['start_date' => '2026-06-01']);
    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Under 15 (U15)',
        'age_min' => 13,
        'age_max' => 15,
    ]);

    expect(birthdateErrors('2012-01-01', $division, $tournament))->toBeEmpty();
});

it('rejects a player who is too old at tournament start', function () {
    $tournament = Tournament::factory()->create(['start_date' => '2026-06-01']);
    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Under 15 (U15)',
        'age_min' => 13,
        'age_max' => 15,
    ]);

    expect(birthdateErrors('2008-01-01', $division, $tournament))
        ->toContain('Player must be 13–15 for Under 15 (U15) at tournament start.');
});

it('rejects a player who is too young at tournament start', function () {
    $tournament = Tournament::factory()->create(['start_date' => '2026-06-01']);
    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Under 15 (U15)',
        'age_min' => 13,
        'age_max' => 15,
    ]);

    expect(birthdateErrors('2014-01-01', $division, $tournament))
        ->toContain('Player must be 13–15 for Under 15 (U15) at tournament start.');
});

it('rejects a future birthdate', function () {
    $tournament = Tournament::factory()->create(['start_date' => '2026-06-01']);
    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Under 15 (U15)',
        'age_min' => 13,
        'age_max' => 15,
    ]);

    expect(birthdateErrors(now()->addYear()->format('Y-m-d'), $division, $tournament))
        ->toContain('Birthdate cannot be in the future.');
});

it('rejects an invalid date string', function () {
    $tournament = Tournament::factory()->create(['start_date' => '2026-06-01']);
    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Under 15 (U15)',
        'age_min' => 13,
        'age_max' => 15,
    ]);

    expect(birthdateErrors('not-a-date', $division, $tournament))
        ->toContain('Enter a valid birthdate.');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run from `src/`:

```powershell
php artisan test tests/Unit/Rules/EligibleBirthdateTest.php
```

Expected: FAIL because `App\Rules\EligibleBirthdate` does not exist.

- [ ] **Step 3: Create the rule**

Create `src/app/Rules/EligibleBirthdate.php`:

```php
<?php

namespace App\Rules;

use App\Models\Division;
use App\Models\Tournament;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;

class EligibleBirthdate implements ValidationRule
{
    public function __construct(
        private readonly ?Division $division = null,
        private readonly ?Tournament $tournament = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Enter the player birthdate.');

            return;
        }

        $birthdate = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($birthdate === false || $birthdate->format('Y-m-d') !== $value) {
            $fail('Enter a valid birthdate.');

            return;
        }

        $today = CarbonImmutable::today();

        if ($birthdate > $today) {
            $fail('Birthdate cannot be in the future.');

            return;
        }

        $reference = $this->tournament?->start_date !== null
            ? CarbonImmutable::parse($this->tournament->start_date)
            : $today;

        $age = (int) $birthdate->diff($reference)->y;
        $min = $this->division?->age_min ?? 8;
        $max = $this->division?->age_max ?? 99;

        if ($age < $min || $age > $max) {
            $divisionName = $this->division?->name;

            $fail($divisionName !== null
                ? "Player must be {$min}–{$max} for {$divisionName} at tournament start."
                : "Player must be {$min}–{$max} at tournament start.");
        }
    }
}
```

- [ ] **Step 4: Run the rule tests**

Run from `src/`:

```powershell
php artisan test tests/Unit/Rules/EligibleBirthdateTest.php
```

Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```powershell
git add src/app/Rules/EligibleBirthdate.php src/tests/Unit/Rules/EligibleBirthdateTest.php
git commit -m "feat(validation): add division birthdate rule"
```

---

### Task 4: Coach registration uses birthdate and creates pending statuses

**Files:**
- Modify: `src/app/Livewire/Coach/TeamRegister.php`
- Modify: `src/resources/views/livewire/coach/team-register.blade.php`
- Modify: `src/tests/Feature/Pages/TeamRegisterTest.php`
- Modify: `src/tests/Feature/Pages/AuditTrailTest.php`

**Interfaces:**
- Consumes: `EligibleBirthdate`, `EligibilityService::initializePlayer()`.
- Produces: registration player payload `{name, number, position, birthdate}` with no consent/document keys.

- [ ] **Step 1: Update the registration tests**

In `src/tests/Feature/Pages/TeamRegisterTest.php`, replace every player payload with this shape:

```php
        $players[] = [
            'name' => 'Test Player '.$number,
            'number' => (string) $number,
            'position' => 'Point Guard (PG)',
            'birthdate' => '2012-01-01',
        ];
```

Replace the second test (`persists coach document statuses for organizer review`) with:

```php
it('creates pending consent and pending documents for organizer review', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();

    $players = [];
    for ($number = 1; $number <= 8; $number++) {
        $players[] = [
            'name' => 'Document Player '.$number,
            'number' => (string) $number,
            'position' => 'Point Guard (PG)',
            'birthdate' => '2012-01-01',
        ];
    }

    Livewire::actingAs($coach)
        ->test(TeamRegister::class, ['tournament_id' => $tournament->id])
        ->set('name', 'Documentville Titans')
        ->set('shortName', 'DVT')
        ->set('city', 'Documentville')
        ->set('coach', 'Coach Documents')
        ->set('avatarColor', '#123456')
        ->set('players', $players)
        ->set('paymentMethod', 'GCash')
        ->set('paymentRef', 'DOCPAY123')
        ->call('save')
        ->assertHasNoErrors();

    $team = Team::where('name', 'Documentville Titans')->firstOrFail();

    expect($team->players()->count())->toBe(8)
        ->and($team->players()->where('consent_status', 'pending')->count())->toBe(8)
        ->and($team->players()
            ->whereHas('eligibilityDocuments', fn ($query) => $query
                ->where('type', 'birth_certificate')
                ->where('status', 'pending'))
            ->count())->toBe(8)
        ->and(app(EligibilityService::class)->status($team)['documentsComplete'])->toBeFalse();
});

it('rejects a birthdate outside the division age range at tournament start', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();

    $players = [];
    for ($number = 1; $number <= 8; $number++) {
        $players[] = [
            'name' => 'Too Old Player '.$number,
            'number' => (string) $number,
            'position' => 'Point Guard (PG)',
            'birthdate' => '2000-01-01',
        ];
    }

    Livewire::actingAs($coach)
        ->test(TeamRegister::class, ['tournament_id' => $tournament->id])
        ->set('name', 'Too Old Titans')
        ->set('shortName', 'TOT')
        ->set('city', 'Testville')
        ->set('coach', 'Coach Test')
        ->set('avatarColor', '#123456')
        ->set('players', $players)
        ->set('paymentMethod', 'GCash')
        ->set('paymentRef', 'TOOOLD123')
        ->call('save')
        ->assertHasErrors(['players.0.birthdate']);

    expect(Team::where('name', 'Too Old Titans')->exists())->toBeFalse();
});
```

In the first test, also add:

```php
    expect($team->players()->first()->birthdate->format('Y-m-d'))->toBe('2012-01-01');
```

In `src/tests/Feature/Pages/AuditTrailTest.php`, replace the player payload at lines 101–108 with:

```php
        $players[] = [
            'name' => 'Audit Player '.$number,
            'number' => (string) $number,
            'position' => 'Point Guard (PG)',
            'birthdate' => '2012-01-01',
        ];
```

- [ ] **Step 2: Run the tests to verify they fail**

Run from `src/`:

```powershell
php artisan test tests/Feature/Pages/TeamRegisterTest.php tests/Feature/Pages/AuditTrailTest.php
```

Expected: FAIL because the component still validates `players.*.age`, `guardianConsent`, and `documentStatus`.

- [ ] **Step 3: Update the TeamRegister component**

In `src/app/Livewire/Coach/TeamRegister.php`:

Add imports:

```php
use App\Rules\EligibleBirthdate;
use Carbon\Carbon;
```

Replace the class docblock with:

```php
/**
 * @property-read Tournament|null $tournament
 * @property-read Team|null $existingTeam
 * @property-read Division|null $division
 * @property-read int $divisionAgeMin
 * @property-read int $divisionAgeMax
 * @property-read float $entryFee
 */
```

Replace `blankPlayer()` with:

```php
    /**
     * @return array<string, mixed>
     */
    protected function blankPlayer(): array
    {
        return [
            'name' => '',
            'number' => '',
            'position' => self::POSITIONS[0],
            'birthdate' => '',
        ];
    }
```

Replace the `divisionLimit()` computed with:

```php
    #[Computed]
    public function division(): ?Division
    {
        return $this->tournament?->divisions()->first();
    }

    #[Computed]
    public function divisionAgeMin(): int
    {
        return $this->division?->age_min ?? 8;
    }

    #[Computed]
    public function divisionAgeMax(): int
    {
        return $this->division?->age_max ?? 99;
    }
```

In `fillDemo()`, replace the player mapping with:

```php
        $this->players = array_map(function (array $player, int $index): array {
            return [
                'name' => $player[0],
                'number' => (string) $player[1],
                'position' => self::POSITIONS[$index % count(self::POSITIONS)],
                'birthdate' => $this->demoBirthdate($player[2]),
            ];
        }, self::DEMO_PLAYERS, array_keys(self::DEMO_PLAYERS));
```

Add this method near `fillDemo()`:

```php
    protected function demoBirthdate(int $age): string
    {
        $reference = $this->tournament?->start_date ?? now();

        return $reference->copy()->subYears($age)->format('Y-m-d');
    }

    public function ageAtTournamentStart(?string $birthdate): int|string
    {
        if ($birthdate === null || $birthdate === '') {
            return '—';
        }

        $reference = $this->tournament?->start_date ?? now();

        return (int) Carbon::parse($birthdate)->diffInYears($reference);
    }
```

In `save()`, replace the entire `foreach ($this->players as $player)` body with:

```php
            foreach ($this->players as $player) {
                $name = trim((string) $player['name']);
                $parts = preg_split('/\s+/', $name, 2) ?: [];
                $firstName = $parts[0] ?? $name;
                $lastName = $parts[1] ?? '';

                $createdPlayer = Player::create([
                    'team_id' => $team->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'birthdate' => (string) $player['birthdate'],
                    'jersey_number' => (int) $player['number'],
                    'position' => (string) $player['position'],
                ]);

                $eligibility->initializePlayer($createdPlayer);
            }
```

Replace `rosterRules()` with:

```php
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rosterRules(): array
    {
        return [
            'players' => ['required', 'array', 'min:8', 'max:12'],
            'players.*.name' => ['required', 'string', 'min:2', 'max:255'],
            'players.*.number' => ['required', 'integer', 'min:1', 'max:99'],
            'players.*.position' => ['required', 'string', 'in:'.implode(',', self::POSITIONS)],
            'players.*.birthdate' => ['required', new EligibleBirthdate($this->division, $this->tournament)],
        ];
    }
```

In `validationMessages()`, remove the age and guardian-consent lines and add:

```php
            'players.*.birthdate.required' => 'Enter the player birthdate.',
```

- [ ] **Step 4: Update the registration view**

In `src/resources/views/livewire/coach/team-register.blade.php`, replace the step 1 helper copy with:

```blade
                        <p class="hf-small hf-muted hf-mb-0">A roster needs 8 to 12 players. Jersey numbers must be unique. Birthdate must fall within {{ $this->division?->name ?? 'the division' }} limits ({{ $this->divisionAgeMin }}–{{ $this->divisionAgeMax }}) at tournament start.</p>
                        <p class="hf-small hf-muted hf-mb-0">Guardian consent and documents are reviewed by the organizer after submission.</p>
```

Replace the age, documents, and consent fields (lines 171–191) with:

```blade
                                <div class="hf-field">
                                    <label class="hf-label" for="playerBirthdate{{ $index }}">Birthdate</label>
                                    <input class="hf-input" id="playerBirthdate{{ $index }}" type="date" max="{{ now()->format('Y-m-d') }}" wire:model="players.{{ $index }}.birthdate"
                                           @error('players.'.$index.'.birthdate') aria-invalid="true" @enderror data-testid="register-player-birthdate-{{ $index }}">
                                    @error('players.'.$index.'.birthdate') <p class="hf-field-error">{{ $message }}</p> @enderror
                                </div>
```

Replace the eligibility checklist bullets with:

```blade
                            <li>8–12 players on the roster</li>
                            <li>Unique jersey numbers from 1 to 99</li>
                            <li>Birthdates inside the division age range</li>
                            <li>Organizer reviews guardian consent and PSA birth certificates after submission</li>
```

Replace the review step's `$consentCount` calculation with:

```php
                    $filledPlayers = collect($players)->filter(fn ($player) => trim((string) ($player['name'] ?? '')) !== '');
```

Replace the roster card hint with:

```blade
                        <p class="hf-small hf-muted hf-mb-0">players · consent and documents pending organizer review</p>
```

Replace the review table columns and rows with:

```blade
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Player</th>
                                    <th scope="col">Position</th>
                                    <th scope="col">Age at start</th>
                                    <th scope="col">Eligibility</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($filledPlayers as $player)
                                    <tr>
                                        <td>{{ $player['number'] }}</td>
                                        <td><strong>{{ $player['name'] }}</strong></td>
                                        <td>{{ $player['position'] }}</td>
                                        <td>{{ $this->ageAtTournamentStart($player['birthdate'] ?? null) }}</td>
                                        <td><x-status-chip status="pending" label="Pending organizer review" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
```

Update the closing note to:

```blade
                    <p class="hf-small hf-muted hf-mt-2 hf-mb-0">Submitting creates a pending team. The organizer records guardian consent and verifies documents in Submissions.</p>
```

- [ ] **Step 5: Run the coach registration tests**

Run from `src/`:

```powershell
php artisan test tests/Feature/Pages/TeamRegisterTest.php tests/Feature/Pages/AuditTrailTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```powershell
git add src/app/Livewire/Coach/TeamRegister.php src/resources/views/livewire/coach/team-register.blade.php src/tests/Feature/Pages/TeamRegisterTest.php src/tests/Feature/Pages/AuditTrailTest.php
git commit -m "feat(register): use birthdate and pending eligibility"
```

---

### Task 5: My Team edits player fields only and resets docs on birthdate change

**Files:**
- Modify: `src/app/Livewire/Coach/MyTeam.php`
- Modify: `src/resources/views/livewire/coach/my-team.blade.php`
- Modify: `src/tests/Feature/Pages/MyTeamTest.php`
- Modify: `src/tests/Feature/Pages/TeamsTest.php`

**Interfaces:**
- Consumes: `EligibilityService::initializePlayer()`, `resetDocumentReview()`, `EligibleBirthdate`.
- Produces: `playerBirthdate` component property; no `playerConsent` or `playerDocumentStatus`.

- [ ] **Step 1: Replace the MyTeam tests**

Replace `src/tests/Feature/Pages/MyTeamTest.php` with:

```php
<?php

use App\Livewire\Coach\MyTeam;
use App\Models\EligibilityDocument;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('updates player fields without touching consent or document status', function () {
    $coach = User::factory()->create();
    $team = Team::factory()->create(['coach_user_id' => $coach->id]);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'birthdate' => '2012-01-01',
        'consent_status' => 'verified',
        'consent_at' => now(),
        'eligibility_status' => 'verified',
        'verified_at' => now(),
    ]);
    $document = EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
        'status' => 'approved',
    ]);

    Livewire::actingAs($coach)
        ->test(MyTeam::class)
        ->call('openPlayerDialog', $player->id)
        ->set('playerName', 'Updated Player')
        ->set('playerNumber', '9')
        ->set('playerPosition', MyTeam::POSITIONS[0])
        ->set('playerBirthdate', '2012-01-01')
        ->call('savePlayer')
        ->assertHasNoErrors();

    expect($player->fresh()->consent_status)->toBe('verified')
        ->and($document->fresh()->status)->toBe('approved');
});

it('resets document review when the coach changes the birthdate', function () {
    $coach = User::factory()->create();
    $team = Team::factory()->create(['coach_user_id' => $coach->id]);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'birthdate' => '2012-01-01',
        'consent_status' => 'verified',
        'consent_at' => now(),
        'eligibility_status' => 'verified',
        'verified_at' => now(),
    ]);
    $document = EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
        'status' => 'approved',
        'reviewed_by' => $coach->id,
        'reviewed_at' => now(),
    ]);

    Livewire::actingAs($coach)
        ->test(MyTeam::class)
        ->call('openPlayerDialog', $player->id)
        ->set('playerBirthdate', '2013-01-01')
        ->call('savePlayer')
        ->assertHasNoErrors();

    expect($document->fresh()->status)->toBe('pending')
        ->and($document->fresh()->reviewed_by)->toBeNull()
        ->and($player->fresh()->eligibility_status)->toBe('pending')
        ->and($player->fresh()->consent_status)->toBe('verified');
});

it('rejects a birthdate outside the allowed range', function () {
    $coach = User::factory()->create();
    $team = Team::factory()->create(['coach_user_id' => $coach->id]);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'birthdate' => '2012-01-01',
    ]);

    Livewire::actingAs($coach)
        ->test(MyTeam::class)
        ->call('openPlayerDialog', $player->id)
        ->set('playerBirthdate', '2020-01-01')
        ->call('savePlayer')
        ->assertHasErrors(['playerBirthdate']);
});

it('does not expose consent or document editing in the player dialog', function () {
    $coach = User::factory()->create();
    $team = Team::factory()->create(['coach_user_id' => $coach->id]);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'birthdate' => '2012-01-01',
    ]);

    Livewire::actingAs($coach)
        ->test(MyTeam::class)
        ->call('openPlayerDialog', $player->id)
        ->assertSeeHtml('data-testid="my-player-birthdate"')
        ->assertDontSeeHtml('data-testid="my-player-consent"')
        ->assertDontSeeHtml('data-testid="my-player-document"');
});
```

- [ ] **Step 2: Update the existing TeamsTest add-player payload**

In `src/tests/Feature/Pages/TeamsTest.php`, replace:

```php
        ->set('playerAge', '14')
        ->set('playerConsent', true)
        ->set('playerDocumentStatus', 'pending')
```

with:

```php
        ->set('playerBirthdate', '2012-01-01')
```

- [ ] **Step 3: Run the tests to verify they fail**

Run from `src/`:

```powershell
php artisan test tests/Feature/Pages/MyTeamTest.php tests/Feature/Pages/TeamsTest.php
```

Expected: FAIL because the component still exposes `playerAge`, `playerConsent`, and `playerDocumentStatus`.

- [ ] **Step 4: Update the MyTeam component**

In `src/app/Livewire/Coach/MyTeam.php`:

Add imports:

```php
use App\Rules\EligibleBirthdate;
```

Remove the `DOCUMENT_STATUSES` constant.

Replace the three player dialog properties with:

```php
    public string $playerBirthdate = '';
```

In `openPlayerDialog()`, replace the age/consent/document assignments with:

```php
            $this->playerBirthdate = $player->birthdate?->format('Y-m-d') ?? '';
```

and in the new-player branch:

```php
            $this->playerBirthdate = '';
```

In `savePlayer()`, replace the variable setup and transaction with:

```php
        $name = trim($this->playerName);
        $parts = preg_split('/\s+/', $name, 2) ?: [];
        $firstName = $parts[0] ?? $name;
        $lastName = $parts[1] ?? '';
        $birthdate = $this->playerBirthdate;
        $number = (int) $this->playerNumber;
        $birthdateChanged = $player instanceof Player
            && $player->birthdate?->format('Y-m-d') !== $birthdate;

        DB::transaction(function () use ($team, $player, $firstName, $lastName, $name, $number, $birthdate, $birthdateChanged): void {
            $eligibility = app(EligibilityService::class);

            $attributes = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birthdate' => $birthdate,
                'jersey_number' => $number,
                'position' => $this->playerPosition,
            ];

            if ($player instanceof Player) {
                $before = [
                    'name' => trim($player->first_name.' '.$player->last_name),
                    'number' => $player->jersey_number,
                    'birthdate' => $player->birthdate?->format('Y-m-d'),
                ];

                $player->update($attributes);

                if ($birthdateChanged) {
                    $eligibility->resetDocumentReview($player);
                }

                $this->writeAudit($team, 'team.roster.update', $before, [
                    'name' => $name,
                    'number' => $number,
                    'birthdate' => $birthdate,
                ]);

                return;
            }

            $createdPlayer = $team->players()->create($attributes);
            $eligibility->initializePlayer($createdPlayer);

            $this->writeAudit($team, 'team.roster.add', null, [
                'name' => $name,
                'number' => $number,
                'birthdate' => $birthdate,
            ]);
        });
```

Replace `playerRules()` with:

```php
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function playerRules(): array
    {
        $team = $this->team;
        $unique = Rule::unique('players', 'jersey_number')
            ->where(fn ($query) => $query->where('team_id', $team?->id));

        if ($this->editingPlayerId !== null) {
            $unique->ignore($this->editingPlayerId);
        }

        return [
            'playerName' => ['required', 'string', 'min:2', 'max:255'],
            'playerNumber' => ['required', 'integer', 'min:1', 'max:99', $unique],
            'playerPosition' => ['required', 'string', 'in:'.implode(',', self::POSITIONS)],
            'playerBirthdate' => ['required', new EligibleBirthdate($this->registration?->division, $this->tournament)],
        ];
    }
```

Replace `playerMessages()` with:

```php
    /**
     * @return array<string, string>
     */
    protected function playerMessages(): array
    {
        return [
            'playerName.required' => 'Enter the player name.',
            'playerName.min' => 'Enter the player name.',
            'playerNumber.required' => 'Use 1–99.',
            'playerNumber.min' => 'Use 1–99.',
            'playerNumber.max' => 'Use 1–99.',
            'playerNumber.unique' => 'That jersey number is already taken.',
            'playerBirthdate.required' => 'Enter the player birthdate.',
        ];
    }
```

In the `team()` computed query, change `'players' => fn ($query) => ...` to eager load documents:

```php
                'players' => fn ($query) => $query
                    ->with('eligibilityDocuments')
                    ->orderBy('jersey_number')
                    ->orderBy('id'),
```

- [ ] **Step 5: Update the My Team view**

In `src/resources/views/livewire/coach/my-team.blade.php`:

Replace the roster KPI hint with:

```blade
                :hint="$consentCount.' consents · '.$docsCount.' documents'"
```

Replace the `$consentCount` and `$docsCount` calculations at the top with:

```php
        $consentCount = $players->filter(fn ($player) => $player->consent_status === 'verified')->count();
        $docsCount = $players->filter(fn ($player) => $player->eligibilityDocuments
            ->contains(fn ($document) => $document->type === \App\Services\EligibilityService::DOCUMENT_TYPE
                && $document->status === 'approved'))->count();
```

Replace the consent and documents cells with:

```blade
                                            <td><x-status-chip :status="$player->consent_status" /></td>
                                            <td><x-status-chip :status="app(\App\Services\EligibilityService::class)->documentStatusForPlayer($player)" /></td>
```

Replace the eligibility card copy with:

```blade
                        {{ $locked ? 'The organizer verified this roster. Contact the committee to make a change.' : 'The organizer records consent and verifies documents in Submissions. Keep player details accurate.' }}
```

Replace the age field in the player dialog with:

```blade
                            <div class="hf-field">
                                <label class="hf-label" for="myPlayerBirthdate">Birthdate</label>
                                <input
                                    class="hf-input"
                                    id="myPlayerBirthdate"
                                    type="date"
                                    max="{{ now()->format('Y-m-d') }}"
                                    wire:model="playerBirthdate"
                                    @error('playerBirthdate') aria-invalid="true" @enderror
                                    data-testid="my-player-birthdate"
                                >
                                @error('playerBirthdate') <p class="hf-field-error">{{ $message }}</p> @enderror
                            </div>
```

Remove the Documents select and the Guardian consent checkbox blocks entirely.

- [ ] **Step 6: Run the My Team tests**

Run from `src/`:

```powershell
php artisan test tests/Feature/Pages/MyTeamTest.php tests/Feature/Pages/TeamsTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```powershell
git add src/app/Livewire/Coach/MyTeam.php src/resources/views/livewire/coach/my-team.blade.php src/tests/Feature/Pages/MyTeamTest.php src/tests/Feature/Pages/TeamsTest.php
git commit -m "feat(my-team): edit player fields only and reset docs on birthdate change"
```

---

### Task 6: Organizer inline consent/document editing

**Files:**
- Modify: `src/app/Livewire/Organizer/SubmissionsIndex.php`
- Modify: `src/resources/views/livewire/organizer/submissions-index.blade.php`
- Modify: `src/tests/Feature/Pages/SubmissionsPaymentsTest.php`

**Interfaces:**
- Consumes: `EligibilityService::recordConsent()`, `reviewDocument()`, `AuditLogger`.
- Produces:
  - `updateConsent(int $playerId, string $status): void`
  - `updateDocument(int $playerId, string $status): void`
  - `approvalIssues(int $registrationId): array`

- [ ] **Step 1: Add failing organizer tests**

In `src/tests/Feature/Pages/SubmissionsPaymentsTest.php`, replace the test `labels coach-declared verified documents as verified in submission detail` with:

```php
it('updates player consent from submissions and audits it', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $registration = Registration::where('status', 'submitted')->firstOrFail();
    $player = $registration->team->players()->firstOrFail();
    $player->update(['consent_status' => 'pending', 'consent_at' => null]);

    Livewire::actingAs($organizer)
        ->test(SubmissionsIndex::class)
        ->call('openReview', $registration->id)
        ->call('updateConsent', $player->id, 'verified')
        ->assertHasNoErrors();

    expect($player->fresh()->consent_status)->toBe('verified')
        ->and($player->fresh()->consent_at)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'player.consent.update',
        'entity_type' => 'player',
        'entity_id' => $player->id,
    ]);
});

it('updates player document status from submissions and audits it', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $registration = Registration::where('status', 'submitted')->firstOrFail();
    $player = $registration->team->players()->firstOrFail();
    $player->update(['consent_status' => 'verified', 'consent_at' => now()]);

    Livewire::actingAs($organizer)
        ->test(SubmissionsIndex::class)
        ->call('openReview', $registration->id)
        ->call('updateDocument', $player->id, 'approved')
        ->assertHasNoErrors();

    $document = $player->eligibilityDocuments()
        ->where('type', 'birth_certificate')
        ->firstOrFail();

    expect($document->status)->toBe('approved')
        ->and((int) $document->reviewed_by)->toBe((int) $organizer->id)
        ->and($player->fresh()->eligibility_status)->toBe('verified');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'player.document.review',
        'entity_type' => 'player',
        'entity_id' => $player->id,
    ]);
});

it('renders inline status controls in the submission detail', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $registration = Registration::where('status', 'submitted')->firstOrFail();
    $player = $registration->team->players()->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(SubmissionsIndex::class)
        ->call('openReview', $registration->id)
        ->assertSeeHtml('data-testid="submission-consent-'.$player->id.'"')
        ->assertSeeHtml('data-testid="submission-document-'.$player->id.'"');
});
```

Update the existing `approve moves a submission to approved` test to include the override:

```php
        ->call('approve', $registration->id)
        ->set('approveAnyway', true)
        ->call('confirmApprove')
```

Update `records an audit entry when a submission is approved` in `AuditTrailTest.php` the same way.

- [ ] **Step 2: Run the tests to verify they fail**

Run from `src/`:

```powershell
php artisan test tests/Feature/Pages/SubmissionsPaymentsTest.php tests/Feature/Pages/AuditTrailTest.php
```

Expected: FAIL because `updateConsent`, `updateDocument`, and `approveAnyway` do not exist.

- [ ] **Step 3: Update the SubmissionsIndex component**

In `src/app/Livewire/Organizer/SubmissionsIndex.php`, add imports:

```php
use App\Models\EligibilityDocument;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Services\EligibilityService;
```

Add the property:

```php
    public bool $approveAnyway = false;
```

Replace `approve()`, `confirmApprove()`, and `cancelApprove()` with:

```php
    public function approve(int $registrationId): void
    {
        $this->approvingId = $registrationId;
        $this->approveAnyway = false;
        $this->reviewingId = null;
        $this->rejectingId = null;
        $this->resetValidation();
    }

    public function confirmApprove(): void
    {
        if ($this->approvingId === null) {
            return;
        }

        if ($this->approvalIssues($this->approvingId) !== [] && ! $this->approveAnyway) {
            $this->addError('approveAnyway', 'Confirm the override before approving an incomplete team.');

            return;
        }

        $this->doApprove($this->approvingId);
        $this->approvingId = null;
        $this->approveAnyway = false;
        $this->resetValidation();
    }

    public function cancelApprove(): void
    {
        $this->approvingId = null;
        $this->approveAnyway = false;
        $this->resetValidation();
    }
```

Add these methods before `doApprove()`:

```php
    public function updateConsent(int $playerId, string $status): void
    {
        if (! in_array($status, ['pending', 'verified', 'missing'], true)) {
            return;
        }

        $registration = $this->reviewingRegistration();

        if (! $registration instanceof Registration
            || ! in_array($registration->status, ['submitted', 'approved'], true)) {
            return;
        }

        $player = $registration->team?->players->firstWhere('id', $playerId);

        if (! $player instanceof Player) {
            return;
        }

        $actor = auth()->user();
        $actor = $actor instanceof User ? $actor : null;
        $before = ['consent_status' => $player->consent_status];

        app(EligibilityService::class)->recordConsent($player, $status, $actor);
        app(AuditLogger::class)->log(
            'player.consent.update',
            'player',
            $player->id,
            $before,
            ['consent_status' => $status],
            $registration->team?->organization_id,
            $actor?->id,
        );

        unset($this->registrations);
        $this->dispatch('hf-toast', title: 'Consent updated', message: 'The player consent status is now '.$status.'.', tone: 'success');
    }

    public function updateDocument(int $playerId, string $status): void
    {
        if (! in_array($status, ['pending', 'approved', 'missing'], true)) {
            return;
        }

        $registration = $this->reviewingRegistration();

        if (! $registration instanceof Registration
            || ! in_array($registration->status, ['submitted', 'approved'], true)) {
            return;
        }

        $player = $registration->team?->players->firstWhere('id', $playerId);

        if (! $player instanceof Player) {
            return;
        }

        $actor = auth()->user();
        $actor = $actor instanceof User ? $actor : null;
        $document = $player->eligibilityDocuments
            ->firstWhere('type', EligibilityService::DOCUMENT_TYPE);
        $before = ['status' => $document?->status ?? 'pending'];

        app(EligibilityService::class)->reviewDocument($player, $status, $actor);
        app(AuditLogger::class)->log(
            'player.document.review',
            'player',
            $player->id,
            $before,
            ['status' => $status],
            $registration->team?->organization_id,
            $actor?->id,
        );

        unset($this->registrations);
        $this->dispatch('hf-toast', title: 'Document updated', message: 'The document status is now '.$status.'.', tone: 'success');
    }

    /**
     * @return array<int, array{name: string, consent: string, document: string}>
     */
    public function approvalIssues(int $registrationId): array
    {
        $registration = Registration::query()
            ->with(['team.players.eligibilityDocuments'])
            ->find($registrationId);

        $team = $registration?->team;

        if (! $team instanceof Team) {
            return [];
        }

        return $team->players
            ->filter(function (Player $player): bool {
                $document = $player->eligibilityDocuments
                    ->firstWhere('type', EligibilityService::DOCUMENT_TYPE);

                return $player->consent_status !== 'verified'
                    || ! $document instanceof EligibilityDocument
                    || $document->status !== 'approved';
            })
            ->map(function (Player $player): array {
                $document = $player->eligibilityDocuments
                    ->firstWhere('type', EligibilityService::DOCUMENT_TYPE);

                return [
                    'name' => trim($player->first_name.' '.$player->last_name),
                    'consent' => $player->consent_status,
                    'document' => $document?->status ?? 'pending',
                ];
            })
            ->values()
            ->all();
    }

    protected function reviewingRegistration(): ?Registration
    {
        if ($this->reviewingId === null) {
            return null;
        }

        return Registration::query()
            ->with(['team.players.eligibilityDocuments'])
            ->find($this->reviewingId);
    }
```

In `doApprove()`, add `'roster_locked_at' => now(),` to the update array and add `unset($this->registrations, $this->kpis);` after the audit log.

In `doReject()`, add `unset($this->registrations, $this->kpis);` after the audit log.

Replace `findRegistration()` with:

```php
    public function findRegistration(int|string|null $id): ?Registration
    {
        if ($id === null || ! is_numeric($id)) {
            return null;
        }

        return Registration::query()
            ->with(['team.players.eligibilityDocuments', 'tournament', 'division', 'payment'])
            ->find((int) $id);
    }
```

- [ ] **Step 4: Update the Submissions view**

In `src/resources/views/livewire/organizer/submissions-index.blade.php`, replace the review roster table body (lines 197–207) with:

```blade
                                @foreach ($reviewPlayers as $player)
                                    @php
                                        $document = $player->eligibilityDocuments
                                            ->firstWhere('type', \App\Services\EligibilityService::DOCUMENT_TYPE);
                                        $documentStatus = $document?->status ?? 'pending';
                                        $editable = in_array($review->status, ['submitted', 'approved'], true);
                                    @endphp
                                    <tr wire:key="review-player-{{ $player->id }}">
                                        <td>{{ $player->jersey_number }}</td>
                                        <td>{{ $player->first_name }} {{ $player->last_name }}</td>
                                        <td>{{ $player->position }}</td>
                                        <td>{{ $player->birthdate?->age ?? '—' }}</td>
                                        <td>
                                            @if ($editable)
                                                <select class="hf-select" wire:change="updateConsent({{ $player->id }}, $event.target.value)" data-testid="submission-consent-{{ $player->id }}">
                                                    <option value="pending" @selected($player->consent_status === 'pending')>Pending</option>
                                                    <option value="verified" @selected($player->consent_status === 'verified')>Verified</option>
                                                    <option value="missing" @selected($player->consent_status === 'missing')>Missing</option>
                                                </select>
                                            @else
                                                @include('components.status-chip', ['status' => $player->consent_status])
                                            @endif
                                        </td>
                                        <td>
                                            @if ($editable)
                                                <select class="hf-select" wire:change="updateDocument({{ $player->id }}, $event.target.value)" data-testid="submission-document-{{ $player->id }}">
                                                    <option value="pending" @selected($documentStatus === 'pending')>Pending</option>
                                                    <option value="approved" @selected($documentStatus === 'approved')>Verified</option>
                                                    <option value="missing" @selected($documentStatus === 'missing')>Missing</option>
                                                </select>
                                            @else
                                                @include('components.status-chip', ['status' => $documentStatus === 'approved' ? 'verified' : $documentStatus])
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
```

- [ ] **Step 5: Run the organizer tests**

Run from `src/`:

```powershell
php artisan test tests/Feature/Pages/SubmissionsPaymentsTest.php tests/Feature/Pages/AuditTrailTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```powershell
git add src/app/Livewire/Organizer/SubmissionsIndex.php src/resources/views/livewire/organizer/submissions-index.blade.php src/tests/Feature/Pages/SubmissionsPaymentsTest.php src/tests/Feature/Pages/AuditTrailTest.php
git commit -m "feat(submissions): let organizers edit consent and document status"
```

---

### Task 7: Approval warning and explicit roster lock

**Files:**
- Modify: `src/app/Livewire/Organizer/SubmissionsIndex.php`
- Modify: `src/resources/views/livewire/organizer/submissions-index.blade.php`
- Modify: `src/tests/Feature/Pages/SubmissionsPaymentsTest.php`

**Interfaces:**
- Consumes: `approvalIssues()`, `approveAnyway`.
- Produces: `roster_locked_at` set on approval; incomplete approvals require the override.

- [ ] **Step 1: Add failing approval tests**

Append to `src/tests/Feature/Pages/SubmissionsPaymentsTest.php`:

```php
it('requires an override before approving incomplete eligibility', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $registration = Registration::where('status', 'submitted')->firstOrFail();
    $registration->team->players()->update([
        'consent_status' => 'pending',
        'consent_at' => null,
    ]);

    Livewire::actingAs($organizer)
        ->test(SubmissionsIndex::class)
        ->call('approve', $registration->id)
        ->call('confirmApprove')
        ->assertHasErrors(['approveAnyway']);

    expect($registration->fresh()->status)->toBe('submitted');
});

it('approves incomplete eligibility after the override and locks the roster', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $registration = Registration::where('status', 'submitted')->firstOrFail();
    $registration->team->players()->update([
        'consent_status' => 'pending',
        'consent_at' => null,
    ]);

    Livewire::actingAs($organizer)
        ->test(SubmissionsIndex::class)
        ->call('approve', $registration->id)
        ->set('approveAnyway', true)
        ->call('confirmApprove')
        ->assertHasNoErrors();

    expect($registration->fresh()->status)->toBe('approved')
        ->and($registration->fresh()->roster_locked_at)->not->toBeNull();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run from `src/`:

```powershell
php artisan test tests/Feature/Pages/SubmissionsPaymentsTest.php --filter="override"
```

Expected: FAIL because the override guard is not enforced in the view/component.

- [ ] **Step 3: Update the approve confirmation dialog**

In `src/resources/views/livewire/organizer/submissions-index.blade.php`, in the `$approvingId` dialog, add this before the dialog body:

```blade
            @php $approveIssues = $this->approvalIssues($approveReg->id); @endphp
```

Replace the dialog body with:

```blade
                <div class="hf-dialog__body">
                    <p class="hf-small hf-muted">The team becomes eligible for seeding and its roster locks for the coach.</p>
                    @if ($approveIssues !== [])
                        <div class="hf-card hf-card--flat hf-mt-2" style="border-color: var(--hf-alert)">
                            <strong class="hf-small">Incomplete eligibility</strong>
                            <ul class="hf-small hf-muted hf-mt-1">
                                @foreach ($approveIssues as $issue)
                                    <li>{{ $issue['name'] }} — consent {{ $issue['consent'] }}, documents {{ $issue['document'] }}</li>
                                @endforeach
                            </ul>
                            <label class="hf-check">
                                <input type="checkbox" wire:model="approveAnyway" data-testid="submission-approve-anyway">
                                Approve anyway
                            </label>
                            @error('approveAnyway') <p class="hf-field-error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
```

Replace the confirm button with:

```blade
                    <button type="button" class="hf-btn hf-btn--primary" wire:click="confirmApprove" data-testid="submission-approve-confirm" @disabled($approveIssues !== [] && ! $approveAnyway)>Approve team</button>
```

- [ ] **Step 4: Run the approval tests**

Run from `src/`:

```powershell
php artisan test tests/Feature/Pages/SubmissionsPaymentsTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```powershell
git add src/app/Livewire/Organizer/SubmissionsIndex.php src/resources/views/livewire/organizer/submissions-index.blade.php src/tests/Feature/Pages/SubmissionsPaymentsTest.php
git commit -m "feat(submissions): warn before approving incomplete eligibility"
```

---

### Task 8: Full quality gate and local migration

**Files:**
- No new files expected; fix any files flagged by Pint, Larastan, or Pest.

- [ ] **Step 1: Run the local PostgreSQL migration**

Run from `src/`:

```powershell
php artisan migrate
```

Expected: the new migration runs without errors.

- [ ] **Step 2: Format the code**

Run from `src/`:

```powershell
composer format
```

Expected: Pint rewrites any style issues.

- [ ] **Step 3: Run the full quality gate**

Run from `src/`:

```powershell
composer quality
```

Expected: Pint passes, Larastan level 6 passes, and the full Pest suite passes.

- [ ] **Step 4: Fix any failures and re-run**

If Pint, Larastan, or Pest reports issues, fix them in the affected file and rerun:

```powershell
composer quality
```

Do not claim completion until the command exits successfully. Report the actual output.

- [ ] **Step 5: Commit any final fixes**

```powershell
git add -A
git commit -m "chore: pass quality gate for consent ownership"
```

---

## Self-Review Notes

- **Spec coverage:** schema (Task 1), service transitions (Task 2), birthdate rule (Task 3), coach registration (Task 4), coach editing (Task 5), organizer inline editing and audit (Task 6), approval warning and roster lock (Task 7), quality gate (Task 8).
- **No coach writes:** Tasks 4 and 5 remove every coach-facing consent/document field and only call `initializePlayer` / `resetDocumentReview`.
- **Status naming:** consent uses `pending|verified|missing`; documents use `pending|approved|missing`; the UI labels document `approved` as **Verified**.
- **Seeder consistency:** Task 1 updates all four seeded player creation blocks, so fresh seeds match the migration backfill.
