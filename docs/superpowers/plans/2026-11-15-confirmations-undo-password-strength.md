# Confirmations, Undo, and Password Strength — Implementation Plan

- **Date:** 2026-11-15
- **Spec:** `docs/superpowers/specs/2026-11-15-confirmations-undo-password-strength-design.md`
- **Working directory:** repository root. All paths below are relative to the repository root unless they start with `src/`.
- **Stack:** Laravel 13, PHP 8.4+, Livewire 4, Blade, Alpine 3, Bootstrap 5.3 SCSS. No Tailwind, no Bootstrap JavaScript, no Vue, no React, no new packages.

> **For the executing engineer:** follow the tasks in order. Each task is TDD: write the failing test, run it, implement, run it again, then commit. Do not stage `.obsidian/graph.json`, `.kilo/`, `.env`, or build output that is not part of the task.

---

## Preflight

### Task 1: Confirm the baseline and create a branch

**Files:** none.

**Step 1:** Confirm the working tree and the two known unrelated changes.

```powershell
git status --short
```

Expected: `M .obsidian/graph.json` and an untracked `.kilo/` may appear. Leave both alone.

**Step 2:** Create the feature branch from the current commit (`195d9e3` or later).

```powershell
git switch -c feat/confirmations-undo-password-strength
```

**Step 3:** Confirm the current gates before changing anything.

```powershell
cd src
composer quality
npm run build
node --test tests/js
cd ..
```

Report the actual output. If a gate is already red, record the exact failing test before proceeding; do not "fix" unrelated failures inside this branch.

**Step 4:** Commit only the branch state if the repository requires an empty marker commit. Otherwise continue.

```powershell
git commit --allow-empty -m "chore: start confirmations and undo work"
```

---

## Phase 1 — Undo core

### Task 2: Add the undo result, exception, and payload reader

**Files:**
- Create: `src/app/Services/Undo/UndoResult.php`
- Create: `src/app/Services/Undo/UndoException.php`
- Create: `src/app/Services/Undo/UndoPayload.php`
- Test: `src/tests/Feature/Undo/UndoTokenTest.php` (created in Task 3; this task adds no test of its own because the types are exercised there).

**Step 1:** Create `src/app/Services/Undo/UndoException.php`.

```php
<?php

namespace App\Services\Undo;

use RuntimeException;

class UndoException extends RuntimeException
{
}
```

**Step 2:** Create `src/app/Services/Undo/UndoResult.php`.

```php
<?php

namespace App\Services\Undo;

final readonly class UndoResult
{
    public function __construct(
        public bool $ok,
        public string $title,
        public string $message,
        public string $tone = 'success',
        public ?string $returnTo = null,
    ) {
    }

    public static function success(string $title, string $message, ?string $returnTo = null): self
    {
        return new self(true, $title, $message, 'success', $returnTo);
    }

    public static function warning(string $title, string $message, ?string $returnTo = null): self
    {
        return new self(false, $title, $message, 'warning', $returnTo);
    }

    /**
     * @return array{title: string, message: string, tone: string}
     */
    public function toToast(): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'tone' => $this->tone,
        ];
    }
}
```

**Step 3:** Create `src/app/Services/Undo/UndoPayload.php`.

```php
<?php

namespace App\Services\Undo;

final class UndoPayload
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function int(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new UndoException('That undo is no longer available.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function nullableInt(array $payload, string $key): ?int
    {
        if (! array_key_exists($key, $payload)) {
            throw new UndoException('That undo is no longer available.');
        }

        if ($payload[$key] === null) {
            return null;
        }

        return self::int($payload, $key);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value)) {
            throw new UndoException('That undo is no longer available.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function nullableString(array $payload, string $key): ?string
    {
        if (! array_key_exists($key, $payload)) {
            throw new UndoException('That undo is no longer available.');
        }

        if ($payload[$key] === null) {
            return null;
        }

        return self::string($payload, $key);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function bool(array $payload, string $key): bool
    {
        $value = $payload[$key] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 0 || $value === 1) {
            return (bool) $value;
        }

        throw new UndoException('That undo is no longer available.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function array(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;

        if (! is_array($value)) {
            throw new UndoException('That undo is no longer available.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public static function nullableArray(array $payload, string $key): ?array
    {
        if (! array_key_exists($key, $payload)) {
            throw new UndoException('That undo is no longer available.');
        }

        if ($payload[$key] === null) {
            return null;
        }

        return self::array($payload, $key);
    }
}
```

**Step 4:** Run the existing tests to prove nothing broke.

```powershell
cd src
php artisan test --compact tests/Feature/Undo
cd ..
```

Expected: the directory does not exist yet, so PHPUnit reports no tests. That is fine for this step.

**Step 5:** Commit.

```powershell
git add src/app/Services/Undo/UndoResult.php src/app/Services/Undo/UndoException.php src/app/Services/Undo/UndoPayload.php
git commit -m "feat(undo): add result, exception, and payload helpers"
```

---

### Task 3: Add the session-backed undo service

**Files:**
- Create: `src/app/Services/Undo/UndoService.php`
- Test: `src/tests/Feature/Undo/UndoTokenTest.php`

**Step 1:** Write the failing test at `src/tests/Feature/Undo/UndoTokenTest.php`.

```php
<?php

use App\Models\User;
use App\Services\Undo\UndoRegistry;
use App\Services\Undo\UndoService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('issues a forty-character token bound to the actor', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $service = app(UndoService::class);

    $token = $service->remember(
        $organizer,
        'tournament.archive',
        ['id' => 1],
        'Tournament archived',
        'Done.',
    );

    $entries = session('hf_undo');

    expect(strlen($token))->toBe(40)
        ->and($entries)->toBeArray()
        ->and($entries)->toHaveKey($token)
        ->and($entries[$token]['user_id'])->toBe($organizer->id)
        ->and($entries[$token]['action'])->toBe('tournament.archive');
});

it('consumes a token the first time it is executed', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $service = app(UndoService::class);
    $token = $service->remember($organizer, 'unknown.action', [], 'Unknown', 'Unknown.');

    $service->execute($token, $organizer);

    expect(session('hf_undo'))->not->toHaveKey($token);

    $second = $service->execute($token, $organizer);

    expect($second->ok)->toBeFalse()
        ->and($second->message)->toBe('That undo is no longer available.');
});

it('expires a token after ten minutes', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $service = app(UndoService::class);
    $token = $service->remember($organizer, 'unknown.action', [], 'Unknown', 'Unknown.');

    $this->travel(11)->minutes();

    $result = $service->execute($token, $organizer);

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toBe('That undo expired. The change is permanent.');
});

it('refuses a token that belongs to another user', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $service = app(UndoService::class);
    $token = $service->remember($organizer, 'unknown.action', [], 'Unknown', 'Unknown.');

    $result = $service->execute($token, $coach);

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toBe('That undo is no longer available.');
});

it('keeps only the newest ten tokens', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $service = app(UndoService::class);
    $tokens = [];

    for ($index = 0; $index < 12; $index++) {
        $tokens[] = $service->remember(
            $organizer,
            'unknown.action',
            ['index' => $index],
            'Unknown',
            'Unknown.',
        );
    }

    $entries = session('hf_undo');

    expect($entries)->toHaveCount(10)
        ->and($entries)->not->toHaveKey($tokens[0])
        ->and($entries)->not->toHaveKey($tokens[1])
        ->and($entries)->toHaveKey($tokens[11]);
});

it('returns a warning for an unknown token', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $result = app(UndoService::class)->execute('not-a-real-token', $organizer);

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toBe('That undo is no longer available.');
});
```

The test imports `UndoRegistry` only so the future binding is visible; remove the unused import now.

**Step 2:** Run the test and confirm it fails because `UndoService` does not exist.

```powershell
cd src
php artisan test --compact tests/Feature/Undo/UndoTokenTest.php
cd ..
```

**Step 3:** Create `src/app/Services/Undo/UndoService.php`.

```php
<?php

namespace App\Services\Undo;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class UndoService
{
    public const SESSION_KEY = 'hf_undo';

    public const MAX_TOKENS = 10;

    public const TTL_SECONDS = 600;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function remember(
        User $actor,
        string $action,
        array $payload,
        string $title,
        string $message,
        ?string $returnTo = null,
    ): string {
        $now = now()->timestamp;
        $token = Str::random(40);

        $entries = $this->entries();

        $entries = array_filter(
            $entries,
            fn (mixed $entry): bool => is_array($entry) && (int) ($entry['expires_at'] ?? 0) >= $now,
        );

        $entries[$token] = [
            'user_id' => $actor->id,
            'action' => $action,
            'payload' => $payload,
            'title' => $title,
            'message' => $message,
            'return_to' => $returnTo,
            'created_at' => $now,
            'expires_at' => $now + self::TTL_SECONDS,
        ];

        uasort(
            $entries,
            fn (array $left, array $right): int => (int) $right['created_at'] <=> (int) $left['created_at'],
        );

        session()->put(self::SESSION_KEY, array_slice($entries, 0, self::MAX_TOKENS, true));

        return $token;
    }

    public function execute(string $token, User $actor): UndoResult
    {
        $entries = $this->entries();
        $entry = $entries[$token] ?? null;

        if (! is_array($entry)) {
            return UndoResult::warning('Undo unavailable', 'That undo is no longer available.');
        }

        unset($entries[$token]);
        session()->put(self::SESSION_KEY, $entries);

        $returnTo = is_string($entry['return_to'] ?? null) ? $entry['return_to'] : null;

        if ((int) ($entry['user_id'] ?? 0) !== $actor->id) {
            return UndoResult::warning('Undo unavailable', 'That undo is no longer available.', $returnTo);
        }

        if ((int) ($entry['expires_at'] ?? 0) < now()->timestamp) {
            return UndoResult::warning('Undo expired', 'That undo expired. The change is permanent.', $returnTo);
        }

        $action = is_string($entry['action'] ?? null) ? $entry['action'] : '';
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        $handler = app(UndoRegistry::class)->for($action);

        if ($handler === null) {
            return UndoResult::warning('Undo unavailable', 'That undo is no longer available.', $returnTo);
        }

        try {
            $handler->authorize($actor, $action, $payload);
            $message = $handler->apply($actor, $action, $payload);
        } catch (AuthorizationException) {
            return UndoResult::warning('Undo blocked', "You don't have permission to undo that action.", $returnTo);
        } catch (UndoException $exception) {
            return UndoResult::warning('Nothing changed', $exception->getMessage(), $returnTo);
        }

        return UndoResult::success('Undone', $message, $returnTo);
    }

    /**
     * @return array<string, mixed>
     */
    private function entries(): array
    {
        $entries = session()->get(self::SESSION_KEY, []);

        return is_array($entries) ? $entries : [];
    }
}
```

`UndoRegistry` does not exist yet. That is fine: PHP resolves it at runtime, and the tests cover only tokens with an unsupported action until Task 4. However, `execute()` calls `app(UndoRegistry::class)` before checking whether the action is supported. Task 4 creates the class. To keep this task independently green, create the registry skeleton in Task 3 and fill the handlers later.

**Step 4:** Create `src/app/Services/Undo/UndoRegistry.php` as a skeleton.

```php
<?php

namespace App\Services\Undo;

use App\Services\Undo\Contracts\UndoHandler;

class UndoRegistry
{
    /**
     * @param  iterable<int, UndoHandler>  $handlers
     */
    public function __construct(private readonly iterable $handlers = [])
    {
    }

    public function for(string $action): ?UndoHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($action)) {
                return $handler;
            }
        }

        return null;
    }
}
```

**Step 5:** Create `src/app/Services/Undo/Contracts/UndoHandler.php`.

```php
<?php

namespace App\Services\Undo\Contracts;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

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
     * @throws \App\Services\Undo\UndoException
     */
    public function apply(User $actor, string $action, array $payload): string;
}
```

**Step 6:** Run the token test.

```powershell
cd src
php artisan test --compact tests/Feature/Undo/UndoTokenTest.php
cd ..
```

Expected: all six tests pass.

**Step 7:** Commit.

```powershell
git add src/app/Services/Undo src/tests/Feature/Undo/UndoTokenTest.php
git commit -m "feat(undo): add session token service and handler contract"
```

---

### Task 4: Bind the registry and create the invokable endpoint

**Files:**
- Modify: `src/app/Providers/AppServiceProvider.php`
- Create: `src/app/Http/Controllers/UndoController.php`
- Modify: `src/routes/web.php`
- Test: `src/tests/Feature/Undo/UndoEndpointTest.php`

**Step 1:** Write the failing endpoint tests. They cover transport behavior only; real handlers are added later, so the first assertion uses an unsupported action.

```php
<?php

use App\Models\Tournament;
use App\Models\User;
use App\Services\Undo\UndoService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('redirects back with a warning for an unknown token', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer)
        ->from('/tournaments')
        ->post('/undo/not-a-real-token')
        ->assertRedirect('/tournaments')
        ->assertSessionHas('hf_toast', function (array $toast): bool {
            return $toast['tone'] === 'warning'
                && $toast['message'] === 'That undo is no longer available.';
        });
});

it('redirects to the recorded return path when one exists', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $service = app(UndoService::class);
    $token = $service->remember(
        $organizer,
        'unknown.action',
        [],
        'Unknown',
        'Unknown.',
        '/tournaments',
    );

    $this->actingAs($organizer)
        ->post('/undo/'.$token)
        ->assertRedirect('/tournaments');
});

it('refuses a token generated for another user', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $service = app(UndoService::class);
    $token = $service->remember($organizer, 'unknown.action', [], 'Unknown', 'Unknown.');

    $this->actingAs($coach)
        ->from('/dashboard')
        ->post('/undo/'.$token)
        ->assertRedirect('/dashboard')
        ->assertSessionHas('hf_toast', function (array $toast): bool {
            return $toast['tone'] === 'warning'
                && $toast['message'] === 'That undo is no longer available.';
        });
});

it('requires authentication', function () {
    $this->post('/undo/anything')->assertRedirect('/login');
});
```

Remove the unused `Tournament` import.

**Step 2:** Run the tests and confirm they fail (404 because the route does not exist).

```powershell
cd src
php artisan test --compact tests/Feature/Undo/UndoEndpointTest.php
cd ..
```

**Step 3:** Bind the registry in `src/app/Providers/AppServiceProvider.php`.

Add imports:

```php
use App\Services\Undo\Handlers\AnnouncementUndo;
use App\Services\Undo\Handlers\PaymentUndo;
use App\Services\Undo\Handlers\PlayerUndo;
use App\Services\Undo\Handlers\RegistrationUndo;
use App\Services\Undo\Handlers\TournamentUndo;
use App\Services\Undo\UndoRegistry;
```

In `register()`:

```php
$this->app->singleton(UndoRegistry::class, function ($app): UndoRegistry {
    return new UndoRegistry([
        $app->make(TournamentUndo::class),
        $app->make(AnnouncementUndo::class),
        $app->make(PlayerUndo::class),
        $app->make(PaymentUndo::class),
        $app->make(RegistrationUndo::class),
    ]);
});
```

The handler classes do not exist until Phase 2. To keep this task green, add temporary empty classes now at the five paths from the spec. Each class implements `UndoHandler` and returns `false` from `supports()`. Phase 2 replaces them.

`src/app/Services/Undo/Handlers/TournamentUndo.php` (temporary):

```php
<?php

namespace App\Services\Undo\Handlers;

use App\Models\User;
use App\Services\Undo\Contracts\UndoHandler;

class TournamentUndo implements UndoHandler
{
    public function supports(string $action): bool
    {
        return false;
    }

    public function authorize(User $actor, string $action, array $payload): void
    {
    }

    public function apply(User $actor, string $action, array $payload): string
    {
        return '';
    }
}
```

Repeat the same temporary shape for `AnnouncementUndo`, `PlayerUndo`, `PaymentUndo`, and `RegistrationUndo`. Phase 2 replaces each file completely.

**Step 4:** Create `src/app/Http/Controllers/UndoController.php`.

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Undo\UndoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UndoController extends Controller
{
    public function __invoke(Request $request, string $token, UndoService $undo): RedirectResponse
    {
        $actor = $request->user();

        abort_unless($actor instanceof User, 403);

        $result = $undo->execute($token, $actor);

        $request->session()->flash('hf_toast', $result->toToast());

        if ($result->returnTo !== null
            && str_starts_with($result->returnTo, '/')
            && ! str_starts_with($result->returnTo, '//')) {
            return redirect($result->returnTo);
        }

        return back();
    }
}
```

**Step 5:** Register the route in `src/routes/web.php`.

Add the import near the other controller imports:

```php
use App\Http\Controllers\UndoController;
```

Add the route after the logout route:

```php
Route::post('/undo/{token}', UndoController::class)->name('undo')->middleware('auth');
```

**Step 6:** Run the endpoint tests.

```powershell
cd src
php artisan test --compact tests/Feature/Undo/UndoEndpointTest.php tests/Feature/Undo/UndoTokenTest.php
cd ..
```

Expected: all tests pass.

**Step 7:** Run Pint and Larastan on the new files.

```powershell
cd src
vendor/bin/pint --test app/Services/Undo app/Http/Controllers/UndoController.php app/Providers/AppServiceProvider.php
vendor/bin/phpstan analyse --memory-limit=512M
cd ..
```

Fix any style or type issues before committing.

**Step 8:** Commit.

```powershell
git add src/app/Providers/AppServiceProvider.php src/app/Http/Controllers/UndoController.php src/app/Services/Undo src/routes/web.php src/tests/Feature/Undo
git commit -m "feat(undo): add registry binding and undo endpoint"
```

---

## Phase 2 — Soft deletes and handlers

### Task 5: Add soft deletes to announcements and players

**Files:**
- Create: `src/database/migrations/2026_11_15_000001_add_soft_deletes_to_announcements_table.php`
- Create: `src/database/migrations/2026_11_15_000002_add_soft_deletes_to_players_table.php`
- Modify: `src/app/Models/Announcement.php`
- Modify: `src/app/Models/Player.php`
- Modify: `src/tests/Feature/Pages/AnnouncementsTest.php`
- Modify: `src/tests/Feature/Pages/TeamsTest.php`

**Step 1:** Add the failing assertions first.

In `src/tests/Feature/Pages/AnnouncementsTest.php`, replace:

```php
$this->assertModelMissing($announcement);
```

with:

```php
$this->assertSoftDeleted('announcements', ['id' => $announcement->id]);
```

In `src/tests/Feature/Pages/TeamsTest.php`, replace:

```php
$this->assertDatabaseMissing('players', ['id' => $player->id]);
```

with:

```php
$this->assertSoftDeleted('players', ['id' => $player->id]);
```

Run the two tests and confirm they fail because the columns do not exist.

```powershell
cd src
php artisan test --compact tests/Feature/Pages/AnnouncementsTest.php tests/Feature/Pages/TeamsTest.php
cd ..
```

**Step 2:** Create the announcements migration.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
```

**Step 3:** Create the players migration with the same shape, replacing `announcements` with `players`.

**Step 4:** Add `SoftDeletes` to the models.

In `src/app/Models/Announcement.php`, add:

```php
use Illuminate\Database\Eloquent\SoftDeletes;
```

and change the trait use line from:

```php
use BelongsToOrganization, HasFactory;
```

to:

```php
use BelongsToOrganization, HasFactory, SoftDeletes;
```

In `src/app/Models/Player.php`, add the same import and change:

```php
use HasFactory;
```

to:

```php
use HasFactory, SoftDeletes;
```

**Step 5:** Run the changed page tests and the full suite for the affected areas.

```powershell
cd src
php artisan test --compact tests/Feature/Pages/AnnouncementsTest.php tests/Feature/Pages/TeamsTest.php
php artisan test --compact
cd ..
```

Expected: all green. If any test counted removed rows without `withTrashed()`, update that test to count active rows only; do not change production queries that already rely on Eloquent scoping.

**Step 6:** Commit.

```powershell
git add src/database/migrations src/app/Models/Announcement.php src/app/Models/Player.php src/tests/Feature/Pages/AnnouncementsTest.php src/tests/Feature/Pages/TeamsTest.php
git commit -m "feat(data): soft delete announcements and players"
```

---

### Task 6: Implement the tournament handler

**Files:**
- Replace: `src/app/Services/Undo/Handlers/TournamentUndo.php`
- Test: `src/tests/Feature/Undo/UndoHandlersTest.php` (create)

**Step 1:** Create `src/tests/Feature/Undo/UndoHandlersTest.php` with the tournament cases.

```php
<?php

use App\Models\Tournament;
use App\Models\User;
use App\Services\Undo\UndoException;
use App\Services\Undo\UndoRegistry;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
    $this->undo = app(UndoRegistry::class);
});

function applyUndo(string $action, User $actor, array $payload): string
{
    $handler = app(UndoRegistry::class)->for($action);

    expect($handler)->not->toBeNull();

    $handler->authorize($actor, $action, $payload);

    return $handler->apply($actor, $action, $payload);
}

it('undoes a tournament archive', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $tournament->update(['archived_at' => now()]);

    $message = applyUndo('tournament.archive', $organizer, ['id' => $tournament->id]);

    expect($tournament->fresh()->archived_at)->toBeNull()
        ->and($message)->toContain('back in the default list');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'tournament.archive.undo',
        'entity_type' => 'tournament',
        'entity_id' => $tournament->id,
    ]);
});

it('undoes a tournament restore', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $tournament->update(['archived_at' => null]);

    applyUndo('tournament.restore', $organizer, ['id' => $tournament->id]);

    expect($tournament->fresh()->archived_at)->not->toBeNull();
});

it('undoes a tournament delete', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $tournament->delete();

    applyUndo('tournament.delete', $organizer, ['id' => $tournament->id]);

    expect($tournament->fresh()->trashed())->toBeFalse();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'tournament.delete.undo',
        'entity_id' => $tournament->id,
    ]);
});

it('undoes a tournament create only when it has no activity', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::factory()->create([
        'organization_id' => $organizer->organization_id,
        'created_by' => $organizer->id,
    ]);

    applyUndo('tournament.create', $organizer, ['id' => $tournament->id]);

    expect($tournament->fresh()->trashed())->toBeTrue();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'tournament.create.undo',
        'entity_id' => $tournament->id,
    ]);
});

it('refuses to undo a tournament create once it has registrations', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    expect(fn () => applyUndo('tournament.create', $organizer, ['id' => $tournament->id]))
        ->toThrow(UndoException::class, "That tournament already has activity, so it can't be removed.");
});

it('refuses a stale archive undo', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $tournament->update(['archived_at' => null]);

    expect(fn () => applyUndo('tournament.archive', $organizer, ['id' => $tournament->id]))
        ->toThrow(UndoException::class, 'Someone already changed this. Nothing was overwritten.');
});
```

`applyUndo` and `$this->undo` are declarations at the top of a Pest file. If Pest complains about a global function redeclaration when more files are added, inline `app(UndoRegistry::class)` instead. Keep the helper only in this file.

**Step 2:** Run the test and confirm all tournament cases fail.

```powershell
cd src
php artisan test --compact tests/Feature/Undo/UndoHandlersTest.php
cd ..
```

**Step 3:** Replace `src/app/Services/Undo/Handlers/TournamentUndo.php`.

```php
<?php

namespace App\Services\Undo\Handlers;

use App\Models\Tournament;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Undo\Contracts\UndoHandler;
use App\Services\Undo\UndoException;
use App\Services\Undo\UndoPayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TournamentUndo implements UndoHandler
{
    /** @var array<int, string> */
    private const ACTIONS = [
        'tournament.archive',
        'tournament.restore',
        'tournament.delete',
        'tournament.create',
    ];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function supports(string $action): bool
    {
        return in_array($action, self::ACTIONS, true);
    }

    public function authorize(User $actor, string $action, array $payload): void
    {
        $tournament = $this->find($payload);

        if (! $tournament instanceof Tournament) {
            throw new UndoException('There is nothing left to undo.');
        }

        $ability = match ($action) {
            'tournament.archive' => 'archive',
            'tournament.restore' => 'unarchive',
            default => 'delete',
        };

        Gate::forUser($actor)->authorize($ability, $tournament);
    }

    public function apply(User $actor, string $action, array $payload): string
    {
        return DB::transaction(function () use ($actor, $action, $payload): string {
            $tournament = $this->find($payload);

            if (! $tournament instanceof Tournament) {
                throw new UndoException('There is nothing left to undo.');
            }

            return match ($action) {
                'tournament.archive' => $this->undoArchive($actor, $tournament),
                'tournament.restore' => $this->undoRestore($actor, $tournament),
                'tournament.delete' => $this->undoDelete($actor, $tournament),
                'tournament.create' => $this->undoCreate($actor, $tournament),
                default => throw new UndoException('That undo is no longer available.'),
            };
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function find(array $payload): ?Tournament
    {
        return Tournament::withTrashed()->find(UndoPayload::int($payload, 'id'));
    }

    private function undoArchive(User $actor, Tournament $tournament): string
    {
        if ($tournament->archived_at === null) {
            throw new UndoException('Someone already changed this. Nothing was overwritten.');
        }

        $before = ['archived_at' => $tournament->archived_at->toIso8601String()];
        $tournament->update(['archived_at' => null]);

        $this->audit->log(
            'tournament.archive.undo',
            'tournament',
            $tournament->id,
            $before,
            ['archived_at' => null],
            $tournament->organization_id,
            $actor->id,
        );

        return $tournament->name.' is back in the default list.';
    }

    private function undoRestore(User $actor, Tournament $tournament): string
    {
        if ($tournament->archived_at !== null) {
            throw new UndoException('Someone already changed this. Nothing was overwritten.');
        }

        $tournament->update(['archived_at' => now()]);

        $this->audit->log(
            'tournament.restore.undo',
            'tournament',
            $tournament->id,
            ['archived_at' => null],
            ['archived_at' => $tournament->archived_at?->toIso8601String()],
            $tournament->organization_id,
            $actor->id,
        );

        return $tournament->name.' is archived again.';
    }

    private function undoDelete(User $actor, Tournament $tournament): string
    {
        if (! $tournament->trashed()) {
            throw new UndoException('Someone already changed this. Nothing was overwritten.');
        }

        $deletedAt = $tournament->deleted_at?->toIso8601String();
        $tournament->restore();

        $this->audit->log(
            'tournament.delete.undo',
            'tournament',
            $tournament->id,
            ['deleted_at' => $deletedAt],
            ['deleted_at' => null],
            $tournament->organization_id,
            $actor->id,
        );

        return $tournament->name.' was restored.';
    }

    private function undoCreate(User $actor, Tournament $tournament): string
    {
        if ($tournament->trashed()) {
            throw new UndoException('There is nothing left to undo.');
        }

        if ($tournament->registrations()->exists() || $tournament->matches()->exists()) {
            throw new UndoException("That tournament already has activity, so it can't be removed.");
        }

        $tournament->delete();

        $this->audit->log(
            'tournament.create.undo',
            'tournament',
            $tournament->id,
            ['deleted_at' => null],
            ['deleted_at' => $tournament->deleted_at?->toIso8601String()],
            $tournament->organization_id,
            $actor->id,
        );

        return $tournament->name.' was removed.';
    }
}
```

**Step 4:** Run the tests.

```powershell
cd src
php artisan test --compact tests/Feature/Undo/UndoHandlersTest.php
cd ..
```

Expected: all tournament cases pass. Fix the factory state if `Tournament::factory()` requires extra fields.

**Step 5:** Commit.

```powershell
git add src/app/Services/Undo/Handlers/TournamentUndo.php src/tests/Feature/Undo/UndoHandlersTest.php
git commit -m "feat(undo): handle tournament lifecycle reversals"
```

---

### Task 7: Implement the announcement handler

**Files:**
- Replace: `src/app/Services/Undo/Handlers/AnnouncementUndo.php`
- Modify: `src/tests/Feature/Undo/UndoHandlersTest.php`

**Step 1:** Append these cases to `src/tests/Feature/Undo/UndoHandlersTest.php`.

```php
it('undoes an announcement delete', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $announcement = Announcement::query()->where('status', 'published')->firstOrFail();
    $announcement->delete();

    applyUndo('announcement.delete', $organizer, ['id' => $announcement->id]);

    expect($announcement->fresh()->trashed())->toBeFalse();
});

it('undoes an announcement pin', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $announcement = Announcement::query()->where('status', 'published')->firstOrFail();
    $announcement->update(['pinned' => false]);
    $announcement->update(['pinned' => true]);

    applyUndo('announcement.pin', $organizer, [
        'id' => $announcement->id,
        'before' => ['pinned' => false],
        'after' => ['pinned' => true],
    ]);

    expect($announcement->fresh()->pinned)->toBeFalse();
});

it('refuses a stale announcement pin', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $announcement = Announcement::query()->where('status', 'published')->firstOrFail();
    $announcement->update(['pinned' => false]);

    expect(fn () => applyUndo('announcement.pin', $organizer, [
        'id' => $announcement->id,
        'before' => ['pinned' => false],
        'after' => ['pinned' => true],
    ]))->toThrow(UndoException::class, 'Someone already changed this. Nothing was overwritten.');
});

it('undoes an announcement publish', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $announcement = Announcement::query()->where('status', 'published')->firstOrFail();

    applyUndo('announcement.publish', $organizer, ['id' => $announcement->id]);

    expect($announcement->fresh()->trashed())->toBeTrue();
});
```

Add the `use App\Models\Announcement;` import.

**Step 2:** Run and confirm the new cases fail.

```powershell
cd src
php artisan test --compact tests/Feature/Undo/UndoHandlersTest.php
cd ..
```

**Step 3:** Replace `src/app/Services/Undo/Handlers/AnnouncementUndo.php`.

```php
<?php

namespace App\Services\Undo\Handlers;

use App\Models\Announcement;
use App\Models\User;
use App\Services\Undo\Contracts\UndoHandler;
use App\Services\Undo\UndoException;
use App\Services\Undo\UndoPayload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class AnnouncementUndo implements UndoHandler
{
    /** @var array<int, string> */
    private const ACTIONS = [
        'announcement.delete',
        'announcement.pin',
        'announcement.publish',
    ];

    public function supports(string $action): bool
    {
        return in_array($action, self::ACTIONS, true);
    }

    public function authorize(User $actor, string $action, array $payload): void
    {
        if (! $actor->hasRole('organizer')) {
            throw new AuthorizationException('Only organizers can undo announcement changes.');
        }
    }

    public function apply(User $actor, string $action, array $payload): string
    {
        return DB::transaction(function () use ($action, $payload): string {
            $announcement = Announcement::withTrashed()->find(UndoPayload::int($payload, 'id'));

            if (! $announcement instanceof Announcement) {
                throw new UndoException('There is nothing left to undo.');
            }

            return match ($action) {
                'announcement.delete' => $this->undoDelete($announcement),
                'announcement.pin' => $this->undoPin($announcement, $payload),
                'announcement.publish' => $this->undoPublish($announcement),
                default => throw new UndoException('That undo is no longer available.'),
            };
        });
    }

    private function undoDelete(Announcement $announcement): string
    {
        if (! $announcement->trashed()) {
            throw new UndoException('Someone already changed this. Nothing was overwritten.');
        }

        $announcement->restore();

        return 'The announcement is back on the feed.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function undoPin(Announcement $announcement, array $payload): string
    {
        $before = UndoPayload::array($payload, 'before');
        $after = UndoPayload::array($payload, 'after');
        $pinned = UndoPayload::bool($after, 'pinned');

        if ((bool) $announcement->pinned !== $pinned) {
            throw new UndoException('Someone already changed this. Nothing was overwritten.');
        }

        $announcement->update(['pinned' => UndoPayload::bool($before, 'pinned')]);

        return $announcement->pinned ? 'The announcement is pinned again.' : 'The announcement is unpinned again.';
    }

    private function undoPublish(Announcement $announcement): string
    {
        if ($announcement->trashed()) {
            throw new UndoException('There is nothing left to undo.');
        }

        $announcement->delete();

        return 'The announcement was unpublished.';
    }
}
```

**Step 4:** Run the handler tests.

```powershell
cd src
php artisan test --compact tests/Feature/Undo/UndoHandlersTest.php
cd ..
```

Expected: green.

**Step 5:** Commit.

```powershell
git add src/app/Services/Undo/Handlers/AnnouncementUndo.php src/tests/Feature/Undo/UndoHandlersTest.php
git commit -m "feat(undo): handle announcement reversals"
```

---

### Task 8: Implement the player handler

**Files:**
- Replace: `src/app/Services/Undo/Handlers/PlayerUndo.php`
- Modify: `src/tests/Feature/Undo/UndoHandlersTest.php`

**Step 1:** Append these cases to the handler test. Add the imports shown.

```php
use App\Models\EligibilityDocument;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Team;
use App\Services\EligibilityService;
use App\Services\Undo\Handlers\PlayerUndo;

it('undoes a player create', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $team = Team::where('coach_user_id', $coach->id)->firstOrFail();
    $team->registrations()->update(['status' => 'submitted', 'roster_locked_at' => null]);

    $player = Player::factory()->create([
        'team_id' => $team->id,
        'first_name' => 'Undo',
        'last_name' => 'Rookie',
    ]);

    applyUndo('player.create', $coach, ['id' => $player->id, 'team_id' => $team->id]);

    $this->assertSoftDeleted('players', ['id' => $player->id]);
});

it('undoes a player update', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $team = Team::where('coach_user_id', $coach->id)->firstOrFail();
    $team->registrations()->update(['status' => 'submitted', 'roster_locked_at' => null]);
    $player = $team->players()->firstOrFail();
    $before = PlayerUndo::playerState($player);

    $player->update([
        'first_name' => 'Changed',
        'jersey_number' => 99,
        'position' => 'Center (C)',
    ]);

    $after = PlayerUndo::playerState($player);

    applyUndo('player.update', $coach, [
        'id' => $player->id,
        'team_id' => $team->id,
        'before' => $before,
        'after' => $after,
    ]);

    expect(PlayerUndo::playerState($player->fresh()))->toBe($before);
});

it('undoes a player remove', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $team = Team::where('coach_user_id', $coach->id)->firstOrFail();
    $team->registrations()->update(['status' => 'submitted', 'roster_locked_at' => null]);
    $player = $team->players()->firstOrFail();
    $player->delete();

    applyUndo('player.remove', $coach, ['id' => $player->id, 'team_id' => $team->id]);

    expect($player->fresh()->trashed())->toBeFalse();
});

it('undoes a consent change', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $player = Player::query()->where('consent_status', 'pending')->firstOrFail();
    $before = PlayerUndo::consentState($player);
    app(EligibilityService::class)->recordConsent($player, 'verified', $organizer);
    $after = PlayerUndo::consentState($player->fresh());

    applyUndo('player.consent', $organizer, [
        'player_id' => $player->id,
        'before' => $before,
        'after' => $after,
    ]);

    expect(PlayerUndo::consentState($player->fresh()))->toBe($before);
});

it('undoes a document review', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $player = Player::query()->where('consent_status', 'verified')->firstOrFail();
    $before = PlayerUndo::documentState($player);
    app(EligibilityService::class)->reviewDocument($player, 'missing', $organizer);
    $after = PlayerUndo::documentState($player->fresh());

    applyUndo('player.document', $organizer, [
        'player_id' => $player->id,
        'document_existed' => true,
        'before' => $before,
        'after' => $after,
    ]);

    expect(PlayerUndo::documentState($player->fresh()))->toBe($before);
});

it('deletes a document that the review created', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $player = Player::query()->where('consent_status', 'verified')->firstOrFail();
    $player->eligibilityDocuments()->delete();
    $before = PlayerUndo::documentState($player->fresh());
    app(EligibilityService::class)->reviewDocument($player, 'approved', $organizer);
    $after = PlayerUndo::documentState($player->fresh());

    applyUndo('player.document', $organizer, [
        'player_id' => $player->id,
        'document_existed' => false,
        'before' => $before,
        'after' => $after,
    ]);

    expect($player->eligibilityDocuments()->count())->toBe(0);
});

it('refuses a player change while the roster is locked', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $team = Team::where('coach_user_id', $coach->id)->firstOrFail();
    $team->registrations()->update(['status' => 'approved', 'roster_locked_at' => now()]);
    $player = $team->players()->firstOrFail();

    expect(fn () => applyUndo('player.create', $coach, ['id' => $player->id, 'team_id' => $team->id]))
        ->toThrow(UndoException::class, "The roster is locked, so this can't be undone.");
});
```

The `documentState` expectation uses strict array comparison. The snapshot must be deterministic (same key order and scalar casts) for `toBe` to pass.

**Step 2:** Run and confirm the new cases fail.

```powershell
cd src
php artisan test --compact tests/Feature/Undo/UndoHandlersTest.php
cd ..
```

**Step 3:** Replace `src/app/Services/Undo/Handlers/PlayerUndo.php`.

```php
<?php

namespace App\Services\Undo\Handlers;

use App\Models\EligibilityDocument;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Team;
use App\Models\User;
use App\Services\EligibilityService;
use App\Services\Undo\Contracts\UndoHandler;
use App\Services\Undo\UndoException;
use App\Services\Undo\UndoPayload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class PlayerUndo implements UndoHandler
{
    /** @var array<int, string> */
    private const ACTIONS = [
        'player.create',
        'player.update',
        'player.remove',
        'player.consent',
        'player.document',
    ];

    public function __construct(private readonly EligibilityService $eligibility)
    {
    }

    public function supports(string $action): bool
    {
        return in_array($action, self::ACTIONS, true);
    }

    public function authorize(User $actor, string $action, array $payload): void
    {
        if (in_array($action, ['player.consent', 'player.document'], true)) {
            if (! $actor->hasRole('organizer')) {
                throw new AuthorizationException('Only organizers can undo eligibility changes.');
            }

            return;
        }

        $team = Team::query()->find(UndoPayload::int($payload, 'team_id'));

        if (! $team instanceof Team || $team->coach_user_id !== $actor->id) {
            throw new AuthorizationException('You can only undo changes to your own team.');
        }
    }

    public function apply(User $actor, string $action, array $payload): string
    {
        return DB::transaction(function () use ($action, $payload): string {
            return match ($action) {
                'player.create' => $this->undoCreate($payload),
                'player.update' => $this->undoUpdate($payload),
                'player.remove' => $this->undoRemove($payload),
                'player.consent' => $this->undoConsent($payload),
                'player.document' => $this->undoDocument($payload),
                default => throw new UndoException('That undo is no longer available.'),
            };
        });
    }

    /**
     * @return array{first_name: string, last_name: string, birthdate: string|null, jersey_number: int, position: string|null}
     */
    public static function playerState(Player $player): array
    {
        return [
            'first_name' => (string) $player->first_name,
            'last_name' => (string) $player->last_name,
            'birthdate' => $player->birthdate?->format('Y-m-d'),
            'jersey_number' => (int) $player->jersey_number,
            'position' => $player->position,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function consentState(Player $player): array
    {
        return [
            'consent_status' => $player->consent_status,
            'consent_at' => $player->consent_at?->toIso8601String(),
            'consent_recorded_by' => $player->consent_recorded_by,
            'consent_recorded_at' => $player->consent_recorded_at?->toIso8601String(),
            'eligibility_status' => $player->eligibility_status,
            'verified_by' => $player->verified_by,
            'verified_at' => $player->verified_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function documentState(Player $player): array
    {
        $document = $player->eligibilityDocuments()
            ->where('type', EligibilityService::DOCUMENT_TYPE)
            ->first();

        return [
            'document' => $document instanceof EligibilityDocument
                ? [
                    'status' => $document->status,
                    'reviewed_by' => $document->reviewed_by,
                    'reviewed_at' => $document->reviewed_at?->toIso8601String(),
                    'notes' => $document->notes,
                    'file_url' => $document->file_url,
                ]
                : null,
            'eligibility_status' => $player->eligibility_status,
            'verified_by' => $player->verified_by,
            'verified_at' => $player->verified_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function undoCreate(array $payload): string
    {
        $player = $this->findPlayer($payload);

        if (! $player instanceof Player || $player->trashed()) {
            throw new UndoException('There is nothing left to undo.');
        }

        $this->assertTeam($player, $payload);
        $this->assertRosterUnlocked($player->team_id);

        $player->delete();

        return $player->first_name.' was removed from the roster.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function undoUpdate(array $payload): string
    {
        $player = $this->findPlayer($payload);

        if (! $player instanceof Player || $player->trashed()) {
            throw new UndoException('There is nothing left to undo.');
        }

        $this->assertTeam($player, $payload);
        $this->assertRosterUnlocked($player->team_id);

        $before = UndoPayload::array($payload, 'before');
        $after = UndoPayload::array($payload, 'after');

        if (self::playerState($player) !== $after) {
            throw new UndoException('Someone already changed this. Nothing was overwritten.');
        }

        $player->update($before);

        return $player->first_name.' was restored.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function undoRemove(array $payload): string
    {
        $player = $this->findPlayer($payload);

        if (! $player instanceof Player || ! $player->trashed()) {
            throw new UndoException('There is nothing left to undo.');
        }

        $this->assertTeam($player, $payload);
        $this->assertRosterUnlocked($player->team_id);

        $player->restore();

        return $player->first_name.' is back on the roster.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function undoConsent(array $payload): string
    {
        $player = Player::query()->find(UndoPayload::int($payload, 'player_id'));

        if (! $player instanceof Player) {
            throw new UndoException('There is nothing left to undo.');
        }

        $before = UndoPayload::array($payload, 'before');
        $after = UndoPayload::array($payload, 'after');

        if (self::consentState($player) !== $after) {
            throw new UndoException('Someone already changed this. Nothing was overwritten.');
        }

        $player->update($before);

        return 'The consent status was restored.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function undoDocument(array $payload): string
    {
        $player = Player::query()->find(UndoPayload::int($payload, 'player_id'));

        if (! $player instanceof Player) {
            throw new UndoException('There is nothing left to undo.');
        }

        $before = UndoPayload::array($payload, 'before');
        $after = UndoPayload::array($payload, 'after');

        if (self::documentState($player) !== $after) {
            throw new UndoException('Someone already changed this. Nothing was overwritten.');
        }

        $document = $player->eligibilityDocuments()
            ->where('type', EligibilityService::DOCUMENT_TYPE)
            ->first();

        $documentBefore = is_array($before['document'] ?? null) ? $before['document'] : null;
        $existed = UndoPayload::bool($payload, 'document_existed');

        if ($existed && $document instanceof EligibilityDocument && $documentBefore !== null) {
            $document->update($documentBefore);
        } elseif (! $existed && $document instanceof EligibilityDocument) {
            $document->delete();
        } elseif ($existed && ! $document instanceof EligibilityDocument) {
            throw new UndoException('There is nothing left to undo.');
        }

        $player->update([
            'eligibility_status' => $before['eligibility_status'] ?? $player->eligibility_status,
            'verified_by' => $before['verified_by'] ?? null,
            'verified_at' => $before['verified_at'] ?? null,
        ]);

        return 'The document review was restored.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findPlayer(array $payload): ?Player
    {
        return Player::withTrashed()->find(UndoPayload::int($payload, 'id'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertTeam(Player $player, array $payload): void
    {
        if ($player->team_id !== UndoPayload::int($payload, 'team_id')) {
            throw new UndoException('There is nothing left to undo.');
        }
    }

    private function assertRosterUnlocked(int $teamId): void
    {
        $registration = Registration::query()
            ->where('team_id', $teamId)
            ->orderByDesc('id')
            ->first();

        if ($registration instanceof Registration
            && ($registration->roster_locked_at !== null || $registration->status === 'approved')) {
            throw new UndoException("The roster is locked, so this can't be undone.");
        }
    }
}
```

**Step 4:** Run the tests.

```powershell
cd src
php artisan test --compact tests/Feature/Undo/UndoHandlersTest.php
cd ..
```

Expected: green. If `documentState($player->fresh())` differs because the relation cache is stale, use `$player->fresh()` before the call in the test (already done for the `after` snapshot) and `$player->refresh()` inside the handler before comparing.

**Step 5:** Commit.

```powershell
git add src/app/Services/Undo/Handlers/PlayerUndo.php src/tests/Feature/Undo/UndoHandlersTest.php
git commit -m "feat(undo): handle player, consent, and document reversals"
```

---

### Task 9: Implement the payment handler

**Files:**
- Replace: `src/app/Services/Undo/Handlers/PaymentUndo.php`
- Modify: `src/tests/Feature/Undo/UndoHandlersTest.php`

**Step 1:** Append these cases.

```php
use App\Models\Payment;
use App\Services\Undo\Handlers\PaymentUndo;

it('undoes a payment verification', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $payment = Payment::query()->where('status', 'verified')->firstOrFail();
    $before = PaymentUndo::paymentState($payment);

    $payment->update([
        'status' => 'partial',
        'reference_no' => 'UNDO-TEST-1',
        'amount' => 100,
        'paid_at' => null,
    ]);

    $after = PaymentUndo::paymentState($payment->fresh());

    applyUndo('payment.verify', $organizer, [
        'payment_id' => $payment->id,
        'before' => $before,
        'after' => $after,
    ]);

    expect(PaymentUndo::paymentState($payment->fresh()))->toBe($before);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'payment.verify.undo',
        'entity_id' => $payment->registration->team_id,
    ]);
});

it('undoes a payment reference change', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $team = Team::where('coach_user_id', $coach->id)->firstOrFail();
    $registration = $team->registrations()->firstOrFail();
    $payment = $registration->payment()->firstOrFail();
    $before = PaymentUndo::paymentState($payment);

    $payment->update([
        'method' => 'Maya',
        'reference_no' => 'MAYA-UNDO-1',
        'status' => 'pending',
        'paid_at' => null,
    ]);

    $after = PaymentUndo::paymentState($payment->fresh());

    applyUndo('payment.reference', $coach, [
        'payment_id' => $payment->id,
        'registration_id' => $registration->id,
        'team_id' => $team->id,
        'before' => $before,
        'after' => $after,
    ]);

    expect(PaymentUndo::paymentState($payment->fresh()))->toBe($before);
});

it('deletes a payment that the reference action created', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $team = Team::where('coach_user_id', $coach->id)->firstOrFail();
    $registration = $team->registrations()->firstOrFail();
    $payment = $registration->payment()->create([
        'amount' => 1500,
        'currency' => 'PHP',
        'method' => 'GCash',
        'reference_no' => 'GCASH-NEW-1',
        'status' => 'pending',
        'recorded_by' => $coach->id,
    ]);
    $after = PaymentUndo::paymentState($payment);

    applyUndo('payment.reference', $coach, [
        'payment_id' => $payment->id,
        'registration_id' => $registration->id,
        'team_id' => $team->id,
        'before' => null,
        'after' => $after,
    ]);

    $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
});
```

**Step 2:** Run and confirm the new cases fail.

```powershell
cd src
php artisan test --compact tests/Feature/Undo/UndoHandlersTest.php
cd ..
```

**Step 3:** Replace `src/app/Services/Undo/Handlers/PaymentUndo.php`.

```php
<?php

namespace App\Services\Undo\Handlers;

use App\Models\Payment;
use App\Models\Registration;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Undo\Contracts\UndoHandler;
use App\Services\Undo\UndoException;
use App\Services\Undo\UndoPayload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class PaymentUndo implements UndoHandler
{
    /** @var array<int, string> */
    private const ACTIONS = [
        'payment.verify',
        'payment.reference',
    ];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function supports(string $action): bool
    {
        return in_array($action, self::ACTIONS, true);
    }

    public function authorize(User $actor, string $action, array $payload): void
    {
        if ($action === 'payment.verify') {
            if (! $actor->hasRole('organizer')) {
                throw new AuthorizationException('Only organizers can undo payment verification.');
            }

            return;
        }

        $team = Team::query()->find(UndoPayload::int($payload, 'team_id'));

        if (! $team instanceof Team || $team->coach_user_id !== $actor->id) {
            throw new AuthorizationException('You can only undo changes to your own team.');
        }
    }

    public function apply(User $actor, string $action, array $payload): string
    {
        return DB::transaction(function () use ($actor, $action, $payload): string {
            return match ($action) {
                'payment.verify' => $this->undoVerify($actor, $payload),
                'payment.reference' => $this->undoReference($payload),
                default => throw new UndoException('That undo is no longer available.'),
            };
        });
    }

    /**
     * @return array{method: string, reference_no: string|null, amount: float, status: string, paid_at: string|null, recorded_by: int|null}
     */
    public static function paymentState(Payment $payment): array
    {
        return [
            'method' => (string) $payment->method,
            'reference_no' => $payment->reference_no,
            'amount' => (float) $payment->amount,
            'status' => (string) $payment->status,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'recorded_by' => $payment->recorded_by,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function undoVerify(User $actor, array $payload): string
    {
        $payment = Payment::query()->with('registration.team')->find(UndoPayload::int($payload, 'payment_id'));

        if (! $payment instanceof Payment) {
            throw new UndoException('There is nothing left to undo.');
        }

        $before = UndoPayload::array($payload, 'before');
        $after = UndoPayload::array($payload, 'after');

        if (self::paymentState($payment) !== $after) {
            throw new UndoException('Someone already changed this. Nothing was overwritten.');
        }

        $payment->update($before);

        $this->audit->log(
            'payment.verify.undo',
            'team',
            $payment->registration?->team_id,
            $after,
            self::paymentState($payment),
            $payment->registration?->team?->organization_id,
            $actor->id,
        );

        return 'The payment is back to its previous state.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function undoReference(array $payload): string
    {
        $payment = Payment::query()->with('registration')->find(UndoPayload::int($payload, 'payment_id'));

        if (! $payment instanceof Payment) {
            throw new UndoException('There is nothing left to undo.');
        }

        $registrationId = UndoPayload::int($payload, 'registration_id');
        $teamId = UndoPayload::int($payload, 'team_id');

        if ($payment->registration_id !== $registrationId || $payment->registration?->team_id !== $teamId) {
            throw new UndoException('There is nothing left to undo.');
        }

        $before = UndoPayload::nullableArray($payload, 'before');
        $after = UndoPayload::array($payload, 'after');

        if (self::paymentState($payment) !== $after) {
            throw new UndoException('Someone already changed this. Nothing was overwritten.');
        }

        if ($before === null) {
            $payment->delete();

            return 'The payment reference was removed.';
        }

        $payment->update($before);

        return 'The previous payment reference was restored.';
    }
}
```

Remove the unused `Registration` import.

**Step 4:** Run the handler tests.

```powershell
cd src
php artisan test --compact tests/Feature/Undo/UndoHandlersTest.php
cd ..
```

Expected: green.

**Step 5:** Commit.

```powershell
git add src/app/Services/Undo/Handlers/PaymentUndo.php src/tests/Feature/Undo/UndoHandlersTest.php
git commit -m "feat(undo): handle payment verification and references"
```

---

### Task 10: Implement the registration handler

**Files:**
- Replace: `src/app/Services/Undo/Handlers/RegistrationUndo.php`
- Modify: `src/tests/Feature/Undo/UndoHandlersTest.php`

**Step 1:** Append these cases.

```php
use App\Services\Undo\Handlers\RegistrationUndo;

it('undoes a registration approval', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $registration = Registration::query()->where('status', 'submitted')->firstOrFail();
    $before = RegistrationUndo::registrationState($registration);

    $registration->update([
        'status' => 'approved',
        'roster_locked_at' => now(),
        'decided_by' => $organizer->id,
        'decided_at' => now(),
    ]);

    $after = RegistrationUndo::registrationState($registration->fresh());

    applyUndo('registration.approve', $organizer, [
        'registration_id' => $registration->id,
        'before' => $before,
        'after' => $after,
    ]);

    expect(RegistrationUndo::registrationState($registration->fresh()))->toBe($before);
});

it('undoes a registration rejection', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $registration = Registration::query()->where('status', 'submitted')->firstOrFail();
    $before = RegistrationUndo::registrationState($registration);

    $registration->update([
        'status' => 'rejected',
        'rejection_reason' => 'Needs a complete roster.',
        'decided_by' => $organizer->id,
        'decided_at' => now(),
    ]);

    $after = RegistrationUndo::registrationState($registration->fresh());

    applyUndo('registration.reject', $organizer, [
        'registration_id' => $registration->id,
        'before' => $before,
        'after' => $after,
    ]);

    expect(RegistrationUndo::registrationState($registration->fresh()))->toBe($before);
});

it('refuses to undo a registration decision after another change', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $registration = Registration::query()->where('status', 'submitted')->firstOrFail();
    $before = RegistrationUndo::registrationState($registration);

    $registration->update(['status' => 'approved']);

    expect(fn () => applyUndo('registration.reject', $organizer, [
        'registration_id' => $registration->id,
        'before' => $before,
        'after' => ['status' => 'rejected', 'decided_by' => null, 'decided_at' => null, 'rejection_reason' => null, 'roster_locked_at' => null],
    ]))->toThrow(UndoException::class, 'Someone already changed this. Nothing was overwritten.');
});
```

**Step 2:** Run and confirm the new cases fail.

```powershell
cd src
php artisan test --compact tests/Feature/Undo/UndoHandlersTest.php
cd ..
```

**Step 3:** Replace `src/app/Services/Undo/Handlers/RegistrationUndo.php`.

```php
<?php

namespace App\Services\Undo\Handlers;

use App\Models\Registration;
use App\Models\User;
use App\Services\Undo\Contracts\UndoHandler;
use App\Services\Undo\UndoException;
use App\Services\Undo\UndoPayload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class RegistrationUndo implements UndoHandler
{
    /** @var array<int, string> */
    private const ACTIONS = [
        'registration.approve',
        'registration.reject',
    ];

    public function supports(string $action): bool
    {
        return in_array($action, self::ACTIONS, true);
    }

    public function authorize(User $actor, string $action, array $payload): void
    {
        if (! $actor->hasRole('organizer')) {
            throw new AuthorizationException('Only organizers can undo registration decisions.');
        }
    }

    public function apply(User $actor, string $action, array $payload): string
    {
        return DB::transaction(function () use ($action, $payload): string {
            $registration = Registration::query()->find(UndoPayload::int($payload, 'registration_id'));

            if (! $registration instanceof Registration) {
                throw new UndoException('There is nothing left to undo.');
            }

            $before = UndoPayload::array($payload, 'before');
            $after = UndoPayload::array($payload, 'after');

            if (self::registrationState($registration) !== $after) {
                throw new UndoException('Someone already changed this. Nothing was overwritten.');
            }

            $registration->update($before);

            return $action === 'registration.approve'
                ? 'The approval was undone. The team is back in review.'
                : 'The rejection was undone. The team is back in review.';
        });
    }

    /**
     * @return array{status: string, decided_by: int|null, decided_at: string|null, rejection_reason: string|null, roster_locked_at: string|null}
     */
    public static function registrationState(Registration $registration): array
    {
        return [
            'status' => (string) $registration->status,
            'decided_by' => $registration->decided_by,
            'decided_at' => $registration->decided_at?->toIso8601String(),
            'rejection_reason' => $registration->rejection_reason,
            'roster_locked_at' => $registration->roster_locked_at?->toIso8601String(),
        ];
    }
}
```

**Step 4:** Run the handler tests.

```powershell
cd src
php artisan test --compact tests/Feature/Undo/UndoHandlersTest.php
cd ..
```

Expected: green.

**Step 5:** Commit.

```powershell
git add src/app/Services/Undo/Handlers/RegistrationUndo.php src/tests/Feature/Undo/UndoHandlersTest.php
git commit -m "feat(undo): handle registration decisions"
```

---

### Task 11: Add authorization and audit coverage for handlers

**Files:**
- Create: `src/tests/Feature/Undo/UndoAuthorizationTest.php`
- Create: `src/tests/Feature/Undo/UndoAuditTest.php`

**Step 1:** Write `UndoAuthorizationTest.php`.

```php
<?php

use App\Models\Payment;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Undo\UndoRegistry;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('forbids a coach from undoing tournament lifecycle actions', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $tournament->update(['archived_at' => now()]);
    $handler = app(UndoRegistry::class)->for('tournament.archive');

    expect(fn () => $handler->authorize($coach, 'tournament.archive', ['id' => $tournament->id]))
        ->toThrow(AuthorizationException::class);
});

it('forbids a coach from undoing another team player change', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $otherTeam = Team::query()->whereNull('coach_user_id')->firstOrFail();
    $player = $otherTeam->players()->firstOrFail();
    $handler = app(UndoRegistry::class)->for('player.create');

    expect(fn () => $handler->authorize($coach, 'player.create', [
        'id' => $player->id,
        'team_id' => $otherTeam->id,
    ]))->toThrow(AuthorizationException::class);
});

it('forbids a coach from undoing another team payment reference', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $otherTeam = Team::query()->whereNull('coach_user_id')->firstOrFail();
    $registration = $otherTeam->registrations()->firstOrFail();
    $payment = $registration->payment()->firstOrFail();
    $handler = app(UndoRegistry::class)->for('payment.reference');

    expect(fn () => $handler->authorize($coach, 'payment.reference', [
        'payment_id' => $payment->id,
        'registration_id' => $registration->id,
        'team_id' => $otherTeam->id,
    ]))->toThrow(AuthorizationException::class);
});

it('forbids a coach from undoing organizer-only eligibility changes', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $player = Player::query()->firstOrFail();
    $handler = app(UndoRegistry::class)->for('player.consent');

    expect(fn () => $handler->authorize($coach, 'player.consent', [
        'player_id' => $player->id,
        'before' => [],
        'after' => [],
    ]))->toThrow(AuthorizationException::class);
});
```

Remove the unused `Payment` import.

**Step 2:** Write `UndoAuditTest.php`.

```php
<?php

use App\Models\Announcement;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Undo\UndoRegistry;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('writes no audit row when a routine undo succeeds', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $announcement = Announcement::query()->where('status', 'published')->firstOrFail();
    $announcement->delete();
    $before = DB::table('audit_logs')->count();

    app(UndoRegistry::class)->for('announcement.delete')->apply($organizer, 'announcement.delete', [
        'id' => $announcement->id,
    ]);

    expect(DB::table('audit_logs')->count())->toBe($before);
});

it('writes exactly one audit row for a tournament undo', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $tournament->update(['archived_at' => now()]);
    $before = DB::table('audit_logs')->count();

    app(UndoRegistry::class)->for('tournament.archive')->apply($organizer, 'tournament.archive', [
        'id' => $tournament->id,
    ]);

    expect(DB::table('audit_logs')->count())->toBe($before + 1);
});

it('writes exactly one audit row for a payment verification undo', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $payment = Payment::query()->where('status', 'verified')->firstOrFail();
    $beforeState = [
        'method' => 'GCash',
        'reference_no' => 'ORIGINAL-1',
        'amount' => 1500.0,
        'status' => 'verified',
        'paid_at' => now()->toIso8601String(),
        'recorded_by' => $organizer->id,
    ];
    $payment->update($beforeState);
    $afterState = [
        'method' => 'Maya',
        'reference_no' => 'CHANGED-1',
        'amount' => 500.0,
        'status' => 'partial',
        'paid_at' => null,
        'recorded_by' => $organizer->id,
    ];
    $payment->update($afterState);
    $before = DB::table('audit_logs')->count();

    app(UndoRegistry::class)->for('payment.verify')->apply($organizer, 'payment.verify', [
        'payment_id' => $payment->id,
        'before' => $beforeState,
        'after' => [
            'method' => 'Maya',
            'reference_no' => 'CHANGED-1',
            'amount' => 500.0,
            'status' => 'partial',
            'paid_at' => null,
            'recorded_by' => $organizer->id,
        ],
    ]);

    expect(DB::table('audit_logs')->count())->toBe($before + 1);
});
```

`DB` is a global facade alias; import `Illuminate\Support\Facades\DB` if Pint or Larastan complains.

**Step 3:** Run both files.

```powershell
cd src
php artisan test --compact tests/Feature/Undo/UndoAuthorizationTest.php tests/Feature/Undo/UndoAuditTest.php
cd ..
```

Expected: green. The payment test's `beforeState` `paid_at` timestamp may differ from the model's serialization; if the strict comparison fails, build the arrays with `PaymentUndo::paymentState()` instead of hand-writing them.

**Step 4:** Commit.

```powershell
git add src/tests/Feature/Undo/UndoAuthorizationTest.php src/tests/Feature/Undo/UndoAuditTest.php
git commit -m "test(undo): cover authorization and audit rules"
```

---

## Phase 3 — Transport and shared UI

### Task 12: Add the undo toast trait

**Files:**
- Create: `src/app/Livewire/Concerns/HasUndoToasts.php`
- Test: covered by every component task in Phase 4; no standalone test.

**Step 1:** Create the trait.

```php
<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Services\Undo\UndoService;

trait HasUndoToasts
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function toastWithUndo(
        string $action,
        array $payload,
        string $title,
        string $message,
        ?string $returnTo = null,
    ): void {
        $toast = $this->undoToast($action, $payload, $title, $message, $returnTo);

        if ($toast === null) {
            return;
        }

        $this->dispatch('hf-toast', ...$toast);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function flashUndoToast(
        string $action,
        array $payload,
        string $title,
        string $message,
        ?string $returnTo = null,
    ): void {
        $toast = $this->undoToast($action, $payload, $title, $message, $returnTo);

        if ($toast === null) {
            return;
        }

        session()->flash('hf_toast', $toast);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{title: string, message: string, tone: string, actionLabel: string, actionMethod: string, actionUrl: string, duration: int}|null
     */
    private function undoToast(
        string $action,
        array $payload,
        string $title,
        string $message,
        ?string $returnTo,
    ): ?array {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return null;
        }

        $token = app(UndoService::class)->remember($actor, $action, $payload, $title, $message, $returnTo);

        return [
            'title' => $title,
            'message' => $message,
            'tone' => 'success',
            'actionLabel' => 'Undo',
            'actionMethod' => 'POST',
            'actionUrl' => route('undo', ['token' => $token], absolute: false),
            'duration' => 9000,
        ];
    }
}
```

**Step 2:** Run Pint and Larastan.

```powershell
cd src
vendor/bin/pint --test app/Livewire/Concerns/HasUndoToasts.php
vendor/bin/phpstan analyse --memory-limit=512M
cd ..
```

**Step 3:** Commit.

```powershell
git add src/app/Livewire/Concerns/HasUndoToasts.php
git commit -m "feat(undo): add toast helpers for Livewire components"
```

---

### Task 13: Support POST actions in toasts

**Files:**
- Modify: `src/resources/js/ui/toasts.js`
- Create: `src/tests/js/toasts.test.js`
- Modify: `src/resources/views/layouts/public.blade.php`
- Modify: `src/resources/css/hf/_components.scss`

**Step 1:** Write the failing JS test at `src/tests/js/toasts.test.js`.

```js
import assert from 'node:assert/strict';
import test from 'node:test';
import { normalizeToast } from '../../resources/js/ui/toasts.js';

test('defaults an action to GET and keeps the href fallback', () => {
    const toast = normalizeToast({
        actionLabel: 'Retry',
        actionHref: '/payments?retry=1',
    });

    assert.equal(toast.actionMethod, 'GET');
    assert.equal(toast.actionUrl, '');
    assert.equal(toast.actionHref, '/payments?retry=1');
});

test('keeps a POST action URL', () => {
    const toast = normalizeToast({
        actionLabel: 'Undo',
        actionMethod: 'POST',
        actionUrl: '/undo/token',
        duration: 9000,
    });

    assert.equal(toast.actionMethod, 'POST');
    assert.equal(toast.actionUrl, '/undo/token');
    assert.equal(toast.duration, 9000);
});

test('rejects an unsupported method', () => {
    const toast = normalizeToast({ actionMethod: 'DELETE' });

    assert.equal(toast.actionMethod, 'GET');
});
```

**Step 2:** Run and confirm the import fails.

```powershell
cd src
node --test tests/js
cd ..
```

**Step 3:** Refactor `src/resources/js/ui/toasts.js` to export `normalizeToast`.

```js
const MAX_TOASTS = 3;
const DEFAULT_DURATION = 4200;

export function normalizeToast(detail = {}) {
    const duration = Number(detail.duration) || DEFAULT_DURATION;

    return {
        id: `${Date.now()}-${Math.random()}`,
        title: detail.title || '',
        message: detail.message || '',
        tone: ['success', 'warning', 'danger', 'info'].includes(detail.tone) ? detail.tone : 'info',
        actionLabel: detail.actionLabel || '',
        actionHref: detail.actionHref || '',
        actionMethod: detail.actionMethod === 'POST' ? 'POST' : 'GET',
        actionUrl: detail.actionUrl || '',
        duration,
        paused: false,
        remaining: duration,
        startedAt: performance.now(),
        timer: null,
    };
}

export function registerToasts() {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('hfToasts', () => ({
            toasts: [],

            push(detail = {}) {
                const toast = normalizeToast(detail);

                this.toasts.push(toast);
                if (this.toasts.length > MAX_TOASTS) this.toasts.shift();
                this.schedule(toast);
            },

            schedule(toast) {
                toast.startedAt = performance.now();
                toast.timer = window.setTimeout(() => this.dismiss(toast.id), toast.remaining);
            },

            dismiss(id) {
                const toast = this.toasts.find((item) => item.id === id);
                if (toast?.timer) window.clearTimeout(toast.timer);
                this.toasts = this.toasts.filter((item) => item.id !== id);
            },

            pause(id) {
                const toast = this.toasts.find((item) => item.id === id);
                if (!toast || toast.paused) return;
                toast.paused = true;
                window.clearTimeout(toast.timer);
                toast.remaining = Math.max(0, toast.remaining - (performance.now() - toast.startedAt));
            },

            resume(id) {
                const toast = this.toasts.find((item) => item.id === id);
                if (!toast || !toast.paused) return;
                toast.paused = false;
                this.schedule(toast);
            },
        }));
    });
}
```

**Step 4:** Change the flash source and action markup in `src/resources/views/layouts/public.blade.php`.

Replace:

```blade
    @php
        $flashToast = session('announcement_toast');
    @endphp
```

with:

```blade
    @php
        $flashToast = session('hf_toast') ?? session('announcement_toast');
    @endphp
```

Replace this single line inside the toast template:

```blade
                    <a x-show="toast.actionLabel" class="hf-toast__action" :href="toast.actionHref" x-text="toast.actionLabel"></a>
```

with:

```blade
                    <template x-if="toast.actionLabel && toast.actionMethod === 'POST'">
                        <form method="POST" :action="toast.actionUrl" class="hf-toast__action-form">
                            @csrf
                            <button type="submit" class="hf-toast__action" x-text="toast.actionLabel"></button>
                        </form>
                    </template>
                    <template x-if="toast.actionLabel && toast.actionMethod !== 'POST'">
                        <a class="hf-toast__action" :href="toast.actionHref || toast.actionUrl" x-text="toast.actionLabel"></a>
                    </template>
```

**Step 5:** Add the button reset next to the existing `.hf-toast__action` rule in `src/resources/css/hf/_components.scss`.

```scss
.hf-toast__action-form {
  display: inline;
  margin: 0;
}
.hf-toast__action-form .hf-toast__action {
  padding: 0;
  border: 0;
  background: transparent;
  cursor: pointer;
  font-family: inherit;
  font-size: 12px;
  font-weight: 700;
  text-decoration: underline;
  text-underline-offset: 2px;
}
```

**Step 6:** Run the JS tests and the build.

```powershell
cd src
node --test tests/js
npm run build
cd ..
```

Expected: `toasts.test.js` and `calendar.test.js` pass; Vite build succeeds.

**Step 7:** Commit.

```powershell
git add src/resources/js/ui/toasts.js src/tests/js/toasts.test.js src/resources/views/layouts/public.blade.php src/resources/css/hf/_components.scss
git commit -m "feat(ui): render POST actions inside toasts"
```

---

### Task 14: Add the shared confirmation dialog and migrate four dialogs

**Files:**
- Create: `src/resources/views/components/confirm-dialog.blade.php`
- Modify: `src/resources/views/livewire/organizer/tournaments-index.blade.php`
- Modify: `src/resources/views/livewire/organizer/tournament-show.blade.php`
- Modify: `src/resources/views/livewire/organizer/announcements-index.blade.php`
- Modify: `src/resources/views/livewire/coach/my-team.blade.php`

**Step 1:** Create the component.

```blade
@props([
    'open' => false,
    'title' => 'Confirm action',
    'message' => '',
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'tone' => 'primary',
    'confirm' => 'confirm',
    'cancel' => 'cancel',
    'dialogId' => 'hf-confirm-dialog',
    'testid' => 'confirm-dialog',
])

@if ($open)
    <dialog
        {{ $attributes->merge([
            'class' => 'hf-dialog',
            'id' => $dialogId,
            'data-testid' => $testid,
            'aria-labelledby' => $dialogId.'-title',
        ]) }}
        x-data
        x-init="if (! $el.open) $el.showModal()"
        x-on:cancel.prevent="$wire.{{ $cancel }}()"
        x-on:click="if ($event.target === $el) $wire.{{ $cancel }}()"
    >
        <div class="hf-dialog__head">
            <h2 class="hf-dialog__title" id="{{ $dialogId }}-title">{{ $title }}</h2>
            <button type="button" class="hf-icon-btn" wire:click="{{ $cancel }}" aria-label="Close dialog" data-testid="dialog-close">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
        <div class="hf-dialog__body">
            <p class="hf-mb-0">{{ $message }}</p>
        </div>
        <div class="hf-dialog__foot">
            <button type="button" class="hf-btn" wire:click="{{ $cancel }}" data-testid="confirm-cancel" autofocus>{{ $cancelLabel }}</button>
            <button type="button" class="hf-btn hf-btn--{{ $tone }}" wire:click="{{ $confirm }}" data-testid="confirm-ok" wire:loading.attr="disabled" wire:loading.class="is-loading">{{ $confirmLabel }}</button>
        </div>
    </dialog>
@endif
```

**Step 2:** Migrate `tournaments-index.blade.php`.

Replace the entire `@if ($showConfirm) ... @endif` dialog block with:

```blade
    <x-confirm-dialog
        :open="$showConfirm"
        :title="$confirmTitle"
        :message="$confirmMessage"
        :confirm-label="$confirmLabel"
        :tone="$confirmTone"
        confirm="confirm"
        cancel="cancelConfirm"
        dialog-id="tournament-index-confirm-dialog"
        testid="tournament-confirm-dialog"
        wire:key="tournament-index-confirm-dialog" />
```

**Step 3:** Migrate `tournament-show.blade.php`.

Replace its confirm dialog with the same component, using:

```blade
    <x-confirm-dialog
        :open="$showConfirm"
        :title="$confirmTitle"
        :message="$confirmMessage"
        :confirm-label="$confirmLabel"
        :tone="$confirmTone"
        confirm="confirm"
        cancel="cancelConfirm"
        dialog-id="tournament-show-confirm-dialog"
        testid="tournament-confirm-dialog"
        wire:key="tournament-show-confirm-dialog" />
```

If the view's heading id was `tournamentConfirmDialogTitle`, keep the new id; no test asserts the id.

**Step 4:** Migrate `announcements-index.blade.php`.

Replace the `@if ($confirmingDelete && $viewing) ... @endif` block with:

```blade
    <x-confirm-dialog
        :open="$confirmingDelete && $viewing"
        title="Delete announcement?"
        message="This removes the announcement."
        confirm-label="Delete announcement"
        tone="danger"
        confirm="deleteAnnouncement"
        cancel="cancelDelete"
        dialog-id="announcement-delete-dialog"
        testid="confirm-dialog"
        wire:key="announcement-delete-dialog" />
```

The copy changes from "permanently" to "This removes the announcement." because undo now exists.

**Step 5:** Migrate `my-team.blade.php`.

Replace the `@if ($showRemoveDialog) ... @endif` remove-player dialog with:

```blade
    <x-confirm-dialog
        :open="$showRemoveDialog"
        :title="'Remove '.$removingPlayerName.'?'"
        message="The player is removed from the roster immediately. You can undo this from the toast."
        confirm-label="Remove player"
        tone="danger"
        confirm="removePlayer"
        cancel="cancelRemovePlayer"
        dialog-id="my-team-remove-dialog"
        testid="my-team-remove-dialog"
        wire:key="my-team-remove-dialog" />
```

**Step 6:** Run the accessibility and page tests that touch these dialogs.

```powershell
cd src
php artisan test --compact tests/Feature/Pages/TournamentsTest.php tests/Feature/Pages/AnnouncementsTest.php tests/Feature/Pages/TeamsTest.php tests/Feature/Accessibility/FormsTest.php
cd ..
```

Expected: green. If a test asserts the old dialog title id, update it to the new deterministic id; do not remove `data-testid` attributes.

**Step 7:** Commit.

```powershell
git add src/resources/views/components/confirm-dialog.blade.php src/resources/views/livewire/organizer/tournaments-index.blade.php src/resources/views/livewire/organizer/tournament-show.blade.php src/resources/views/livewire/organizer/announcements-index.blade.php src/resources/views/livewire/coach/my-team.blade.php
git commit -m "feat(ui): add a shared confirmation dialog"
```

---

### Task 15: Add the global logout dialog

**Files:**
- Modify: `src/resources/views/components/user-menu.blade.php`
- Modify: `src/resources/views/layouts/public.blade.php`

**Step 1:** Change the rail logout button in `src/resources/views/components/user-menu.blade.php`.

```blade
            <button type="submit" data-testid="logout" x-on:click.prevent="$dispatch('hf-logout-confirm')"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Log out</button>
```

Keep the surrounding `<form method="POST" action="{{ route('logout') }}">` and `@csrf` unchanged. Without JavaScript the form still submits.

**Step 2:** Change the mobile sheet logout button in `src/resources/views/layouts/public.blade.php`.

```blade
                                <button type="submit" data-testid="logout" x-on:click.prevent="$dispatch('hf-logout-confirm')"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Log out</button>
```

**Step 3:** Add the dialog inside the `@auth` section, after the closing `</dialog>` of `hf-more-sheet` and before `<main>`.

```blade
            <dialog
                id="hf-logout-dialog"
                class="hf-dialog"
                x-data
                x-on:hf-logout-confirm.window="if (! $el.open) $el.showModal()"
                x-on:cancel.prevent="$el.close()"
                x-on:click="if ($event.target === $el) $el.close()"
                aria-labelledby="hf-logout-title"
                aria-describedby="hf-logout-body"
                data-testid="logout-dialog">
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title" id="hf-logout-title">Log out of HoopFlow?</h2>
                    <button type="button" class="hf-icon-btn" x-on:click="$el.closest('dialog').close()" aria-label="Close dialog" data-testid="dialog-close">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="hf-dialog__body" id="hf-logout-body">
                    <p class="hf-mb-0">You'll need to sign in again to manage tournaments or your team.</p>
                </div>
                <div class="hf-dialog__foot">
                    <button type="button" class="hf-btn" x-on:click="$el.closest('dialog').close()" data-testid="logout-cancel" autofocus>Stay signed in</button>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="hf-btn hf-btn--danger" data-testid="logout-confirm">Log out</button>
                    </form>
                </div>
            </dialog>
```

**Step 4:** Add a structural assertion to `src/tests/Feature/Accessibility/FormsTest.php`.

Append:

```php
it('renders one labelled logout dialog in the authenticated shell', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $html = $this->actingAs($organizer)->get('/dashboard')->getContent();

    expect($html)->toContain('id="hf-logout-dialog"')
        ->and($html)->toContain('aria-labelledby="hf-logout-title"')
        ->and($html)->toContain('aria-describedby="hf-logout-body"')
        ->and($html)->toContain('data-testid="logout-confirm"');
});
```

**Step 5:** Run the test.

```powershell
cd src
php artisan test --compact tests/Feature/Accessibility/FormsTest.php
cd ..
```

Expected: green.

**Step 6:** Commit.

```powershell
git add src/resources/views/components/user-menu.blade.php src/resources/views/layouts/public.blade.php src/tests/Feature/Accessibility/FormsTest.php
git commit -m "feat(ui): confirm logout with one shared dialog"
```

---

## Phase 4 — Wire actions to undo

### Task 16: Wire tournaments

**Files:**
- Modify: `src/app/Livewire/Organizer/TournamentsIndex.php`
- Modify: `src/app/Livewire/Organizer/TournamentShow.php`
- Modify: `src/app/Livewire/Organizer/TournamentCreate.php`
- Modify: `src/tests/Feature/Pages/TournamentsTest.php`

**Step 1:** Add failing assertions for the undo payload.

In `src/tests/Feature/Pages/TournamentsTest.php`, after the existing archive assertion, add:

```php
    ->assertDispatched('hf-toast', actionLabel: 'Undo', actionMethod: 'POST');
```

Do the same for the restore and delete index tests. For the create wizard test, change the final assertion to capture the flashed toast:

```php
    $component->assertRedirect(route('tournamentShow', ['tournament' => $tournament->id]));

    expect(session('hf_toast'))->toMatchArray([
        'title' => 'Tournament created',
        'actionLabel' => 'Undo',
        'actionMethod' => 'POST',
    ]);
```

**Step 2:** Add the trait to `src/app/Livewire/Organizer/TournamentsIndex.php`.

Imports:

```php
use App\Livewire\Concerns\HasUndoToasts;
```

Class body:

```php
    use HasUndoToasts;
```

Replace the final `match ($action)` block in `confirm()` with:

```php
        match ($action) {
            'archive-tournament' => $this->toastWithUndo(
                'tournament.archive',
                ['id' => $tournament->id],
                'Tournament archived',
                $tournament->name.' is out of the default list. Restore it from the Archived filter.',
            ),
            'restore-tournament' => $this->toastWithUndo(
                'tournament.restore',
                ['id' => $tournament->id],
                'Tournament restored',
                $tournament->name.' is back in the default list.',
            ),
            default => $this->toastWithUndo(
                'tournament.delete',
                ['id' => $tournament->id],
                'Tournament deleted',
                $tournament->name.' is hidden from every list.',
            ),
        };
```

**Step 3:** Wire `src/app/Livewire/Organizer/TournamentShow.php`.

Add the trait import and `use HasUndoToasts;`.

Replace the delete branch:

```php
        if ($action === 'delete-tournament') {
            $this->flashUndoToast(
                'tournament.delete',
                ['id' => $tournament->id],
                'Tournament deleted',
                $tournament->name.' is hidden from every list.',
            );

            $this->redirectRoute('tournaments', navigate: true);

            return;
        }
```

Replace the final `match ($action)` block with:

```php
        match ($action) {
            'archive-tournament' => $this->toastWithUndo(
                'tournament.archive',
                ['id' => $tournament->id],
                'Tournament archived',
                $tournament->name.' is out of the default list. Restore it from the Archived filter.',
            ),
            default => $this->toastWithUndo(
                'tournament.restore',
                ['id' => $tournament->id],
                'Tournament restored',
                $tournament->name.' is back in the default list.',
            ),
        };
```

**Step 4:** Wire `src/app/Livewire/Organizer/TournamentCreate.php`.

Add the trait import and `use HasUndoToasts;`.

Replace the `$this->dispatch('hf-toast', ...)` block after the transaction with:

```php
        $this->flashUndoToast(
            'tournament.create',
            ['id' => $tournament->id],
            'Tournament created',
            'Registration is open. Share the tournament desk with coaches.',
            route('tournaments', absolute: false),
        );
```

**Step 5:** Run the tournament tests.

```powershell
cd src
php artisan test --compact tests/Feature/Pages/TournamentsTest.php
cd ..
```

Expected: green. If `assertDispatched` fails because the toast uses named parameters, inspect `$component->effects['dispatches']` once and adjust the assertion keys; the implementation must keep `actionLabel`, `actionMethod`, and `actionUrl`.

**Step 6:** Commit.

```powershell
git add src/app/Livewire/Organizer/TournamentsIndex.php src/app/Livewire/Organizer/TournamentShow.php src/app/Livewire/Organizer/TournamentCreate.php src/tests/Feature/Pages/TournamentsTest.php
git commit -m "feat(undo): wire tournament actions"
```

---

### Task 17: Wire announcements

**Files:**
- Modify: `src/app/Livewire/Organizer/AnnouncementsIndex.php`
- Modify: `src/app/Livewire/Organizer/AnnouncementCreate.php`
- Modify: `src/resources/views/livewire/organizer/announcement-create.blade.php`
- Modify: `src/tests/Feature/Pages/AnnouncementsTest.php`

**Step 1:** Add failing tests.

In `src/tests/Feature/Pages/AnnouncementsTest.php`, add:

```php
it('offers undo after deleting an announcement', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $announcement = Announcement::query()->where('status', 'published')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(AnnouncementsIndex::class)
        ->call('openAnnouncement', $announcement->id)
        ->call('confirmDelete')
        ->call('deleteAnnouncement')
        ->assertDispatched('hf-toast', actionLabel: 'Undo', actionMethod: 'POST');

    $this->assertSoftDeleted('announcements', ['id' => $announcement->id]);
});

it('offers undo after pinning an announcement', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $announcement = Announcement::query()->where('status', 'published')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(AnnouncementsIndex::class)
        ->call('openAnnouncement', $announcement->id)
        ->call('togglePin')
        ->assertDispatched('hf-toast', actionLabel: 'Undo', actionMethod: 'POST');
});
```

Update the existing publish test by changing `->call('save')` to:

```php
        ->call('requestPublish')
        ->assertSet('showConfirm', true)
        ->call('confirmPublish')
        ->assertHasNoErrors();
```

Then assert:

```php
    expect(session('hf_toast'))->toMatchArray([
        'title' => 'Announcement published',
        'actionLabel' => 'Undo',
        'actionMethod' => 'POST',
    ]);
```

**Step 2:** Wire `AnnouncementsIndex`.

Add the trait import and `use HasUndoToasts;`.

In `togglePin`, capture the old value before the service call and replace the toast:

```php
        $beforePinned = (bool) $announcement->pinned;
        $pinned = ! $beforePinned;

        app(AnnouncementService::class)->setPinned($announcement, $pinned, $this->actor());

        $this->closeAnnouncement();
        $this->toastWithUndo(
            'announcement.pin',
            [
                'id' => $announcement->id,
                'before' => ['pinned' => $beforePinned],
                'after' => ['pinned' => $pinned],
            ],
            $pinned ? 'Announcement pinned' : 'Announcement unpinned',
            $pinned ? 'Pinned.' : 'Unpinned.',
        );
```

In `deleteAnnouncement`, replace the toast:

```php
        $this->toastWithUndo(
            'announcement.delete',
            ['id' => $announcement->id],
            'Announcement deleted',
            'The announcement is off the feed.',
        );
```

**Step 3:** Update `AnnouncementCreate`.

Add the trait import and `use HasUndoToasts;`.

Add properties:

```php
    public bool $showConfirm = false;

    public string $confirmMessage = '';
```

Add methods before `save()`:

```php
    public function requestPublish(): void
    {
        $this->resetValidation();
        $this->validate();

        $audience = Announcement::AUDIENCES[$this->audience] ?? $this->audience;
        $this->confirmMessage = '"'.trim($this->title).'" will be published to '.$audience.'. You can undo right after publishing.';
        $this->showConfirm = true;
    }

    public function confirmPublish(): void
    {
        $this->showConfirm = false;
        $this->save();
    }

    public function cancelPublish(): void
    {
        $this->showConfirm = false;
    }
```

Replace the `session()->flash('announcement_toast', ...)` block with:

```php
        $this->flashUndoToast(
            'announcement.publish',
            ['id' => $announcement->id],
            'Announcement published',
            'The bulletin is live for its audience.',
        );
```

Keep `save()` public and unchanged apart from that block so existing direct callers still work.

**Step 4:** Update the composer view.

Change the form tag to:

```blade
<form wire:submit="requestPublish" id="announcementForm" novalidate data-testid="announcement-create-form">
```

Add the dialog immediately before `@endsection` at the end of `announcement-create.blade.php`:

```blade
    <x-confirm-dialog
        :open="$showConfirm"
        title="Publish this announcement?"
        :message="$confirmMessage"
        confirm-label="Publish announcement"
        confirm="confirmPublish"
        cancel="cancelPublish"
        dialog-id="announcement-publish-dialog"
        testid="announcement-publish-dialog"
        wire:key="announcement-publish-dialog" />
```

**Step 5:** Run the announcement tests.

```powershell
cd src
php artisan test --compact tests/Feature/Pages/AnnouncementsTest.php
cd ..
```

Expected: green.

**Step 6:** Commit.

```powershell
git add src/app/Livewire/Organizer/AnnouncementsIndex.php src/app/Livewire/Organizer/AnnouncementCreate.php src/resources/views/livewire/organizer/announcement-create.blade.php src/tests/Feature/Pages/AnnouncementsTest.php
git commit -m "feat(undo): wire announcement actions and publish confirmation"
```

---

### Task 18: Wire the coach team page

**Files:**
- Modify: `src/app/Livewire/Coach/MyTeam.php`
- Modify: `src/tests/Feature/Pages/TeamsTest.php`

**Step 1:** Add failing assertions.

In the remove-player test, add `->assertDispatched('hf-toast', actionLabel: 'Undo', actionMethod: 'POST')` to the `removePlayer` chain. In the add-player test, do the same after `savePlayer`. In the payment-reference test, do the same after `savePayment`.

**Step 2:** Update `src/app/Livewire/Coach/MyTeam.php`.

Add imports:

```php
use App\Livewire\Concerns\HasUndoToasts;
use App\Services\Undo\Handlers\PaymentUndo;
use App\Services\Undo\Handlers\PlayerUndo;
```

Add `use HasUndoToasts;` inside the class.

Replace the whole `savePlayer()` method with:

```php
    public function savePlayer(): void
    {
        $team = $this->team;

        if (! $team instanceof Team) {
            return;
        }

        if ($this->rosterLocked) {
            $this->closePlayerDialog();
            $this->dispatch('hf-toast', title: 'Roster locked', message: 'The organizer verified this roster. Contact the committee to make a change.', tone: 'warning');

            return;
        }

        $this->validate($this->playerRules(), $this->playerMessages());

        $editing = $this->editingPlayerId !== null;
        $player = $editing
            ? $team->players->firstWhere('id', $this->editingPlayerId)
            : null;

        if ($editing && ! $player instanceof Player) {
            return;
        }

        if (! $editing && $team->players->count() >= 12) {
            $this->dispatch('hf-toast', title: 'Roster full', message: 'A roster can have at most 12 players.', tone: 'warning');

            return;
        }

        $name = trim($this->playerName);
        $parts = preg_split('/\s+/', $name, 2) ?: [];
        $firstName = $parts[0] ?? $name;
        $lastName = $parts[1] ?? '';
        $birthdate = $this->playerBirthdate;
        $number = (int) $this->playerNumber;
        $birthdateChanged = $player instanceof Player
            && $player->birthdate?->format('Y-m-d') !== $birthdate;

        /** @var array{player: Player, before: array<string, mixed>|null, created: bool} $saved */
        $saved = DB::transaction(function () use ($team, $player, $firstName, $lastName, $name, $number, $birthdate, $birthdateChanged): array {
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
                $undoBefore = PlayerUndo::playerState($player);

                $player->update($attributes);

                if ($birthdateChanged) {
                    $eligibility->resetDocumentReview($player);
                }

                $this->writeAudit($team, 'team.roster.update', $before, [
                    'name' => $name,
                    'number' => $number,
                    'birthdate' => $birthdate,
                ]);

                return ['player' => $player, 'before' => $undoBefore, 'created' => false];
            }

            $createdPlayer = $team->players()->create($attributes);
            $eligibility->initializePlayer($createdPlayer);

            $this->writeAudit($team, 'team.roster.add', null, [
                'name' => $name,
                'number' => $number,
                'birthdate' => $birthdate,
            ]);

            return ['player' => $createdPlayer, 'before' => null, 'created' => true];
        });

        $savedPlayer = $saved['player'];

        unset($this->team, $this->registration, $this->tournament, $this->payment, $this->nextMatch);

        $this->closePlayerDialog();

        if ($saved['created']) {
            $this->toastWithUndo(
                'player.create',
                ['id' => $savedPlayer->id, 'team_id' => $team->id],
                'Roster updated',
                $name.' added to the roster.',
            );

            return;
        }

        $this->toastWithUndo(
            'player.update',
            [
                'id' => $savedPlayer->id,
                'team_id' => $team->id,
                'before' => $saved['before'],
                'after' => PlayerUndo::playerState($savedPlayer),
            ],
            'Roster updated',
            $name.' updated.',
        );
    }
```

Replace the final toast in `removePlayer()` with:

```php
        $this->toastWithUndo(
            'player.remove',
            ['id' => $player->id, 'team_id' => $team->id],
            'Roster updated',
            $name.' removed from the roster.',
        );
```

Replace the whole `savePayment()` method with:

```php
    public function savePayment(): void
    {
        $team = $this->team;
        $registration = $this->registration;
        $payment = $this->payment;

        if (! $team instanceof Team || ! $registration instanceof Registration) {
            return;
        }

        if ($payment instanceof Payment && in_array($payment->status, ['verified', 'paid'], true)) {
            $this->closePaymentDialog();
            $this->dispatch('hf-toast', title: 'Payment verified', message: 'A verified payment reference cannot be changed.', tone: 'warning');

            return;
        }

        $this->validate([
            'paymentMethod' => ['required', 'string', 'in:'.implode(',', self::PAYMENT_METHODS)],
            'paymentReference' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{6,24}$/'],
        ], [
            'paymentReference.required' => 'Use 6–24 letters, numbers, or dashes.',
            'paymentReference.regex' => 'Use 6–24 letters, numbers, or dashes.',
        ]);

        $method = $this->paymentMethod;
        $reference = trim($this->paymentReference);
        $amount = (float) ($this->tournament?->entryFee() ?? $payment?->amount ?? 0);
        $auditBefore = $payment instanceof Payment
            ? ['method' => $payment->method, 'reference' => $payment->reference_no, 'status' => $payment->status]
            : null;
        $undoBefore = $payment instanceof Payment ? PaymentUndo::paymentState($payment) : null;

        $savedPayment = DB::transaction(function () use ($team, $registration, $payment, $method, $reference, $amount, $auditBefore): Payment {
            $attributes = [
                'amount' => $amount,
                'currency' => $payment->currency ?? 'PHP',
                'method' => $method,
                'reference_no' => $reference,
                'status' => 'pending',
                'paid_at' => null,
                'recorded_by' => auth()->id(),
            ];

            if ($payment instanceof Payment) {
                $payment->update($attributes);
                $saved = $payment;
            } else {
                $saved = $registration->payment()->create($attributes);
            }

            $this->writeAudit($team, 'payment.submit', $auditBefore, [
                'method' => $method,
                'reference' => $reference,
                'status' => 'pending',
            ]);

            return $saved;
        });

        unset($this->team, $this->registration, $this->tournament, $this->payment);

        $this->closePaymentDialog();
        $this->toastWithUndo(
            'payment.reference',
            [
                'payment_id' => $savedPayment->id,
                'registration_id' => $registration->id,
                'team_id' => $team->id,
                'before' => $undoBefore,
                'after' => PaymentUndo::paymentState($savedPayment),
            ],
            'Reference saved',
            'Payment reference submitted for verification.',
        );
    }
```

**Step 3:** Run the team tests.

```powershell
cd src
php artisan test --compact tests/Feature/Pages/TeamsTest.php
cd ..
```

Expected: green. The existing remove test now asserts `assertSoftDeleted` from Task 5.

**Step 4:** Commit.

```powershell
git add src/app/Livewire/Coach/MyTeam.php src/tests/Feature/Pages/TeamsTest.php
git commit -m "feat(undo): wire roster and payment reference actions"
```

---

### Task 19: Wire submissions

**Files:**
- Modify: `src/app/Livewire/Organizer/SubmissionsIndex.php`
- Modify: `src/tests/Feature/Pages/SubmissionsPaymentsTest.php`

**Step 1:** Add failing assertions to the approve, reject, consent, and document tests: chain `->assertDispatched('hf-toast', actionLabel: 'Undo', actionMethod: 'POST')`.

**Step 2:** Update `src/app/Livewire/Organizer/SubmissionsIndex.php`.

Add imports:

```php
use App\Livewire\Concerns\HasUndoToasts;
use App\Services\Undo\Handlers\PlayerUndo;
use App\Services\Undo\Handlers\RegistrationUndo;
```

Add `use HasUndoToasts;` inside the class.

In `doApprove()`, capture snapshots and replace the toast:

```php
        $before = RegistrationUndo::registrationState($registration);

        $registration->update([
            'status' => 'approved',
            'roster_locked_at' => now(),
            'decided_by' => auth()->id(),
            'decided_at' => now(),
        ]);

        $after = RegistrationUndo::registrationState($registration);

        app(AuditLogger::class)->log(
            'team.approve',
            'team',
            $registration->team_id,
            ['status' => 'submitted'],
            ['status' => 'approved'],
            $registration->team->organization_id,
        );

        unset($this->registrations, $this->kpis);
        $this->toastWithUndo(
            'registration.approve',
            [
                'registration_id' => $registration->id,
                'before' => $before,
                'after' => $after,
            ],
            'Team approved',
            'The team is eligible for seeding and its roster locks for the coach.',
        );
```

In `doReject()`, do the same with `registration.reject`:

```php
        $before = RegistrationUndo::registrationState($registration);

        $registration->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'decided_by' => auth()->id(),
            'decided_at' => now(),
        ]);

        $after = RegistrationUndo::registrationState($registration);
```

and the toast:

```php
        $this->toastWithUndo(
            'registration.reject',
            [
                'registration_id' => $registration->id,
                'before' => $before,
                'after' => $after,
            ],
            'Team rejected',
            'The reason is shown to the coach and written to the audit log.',
        );
```

In `updateConsent()`, capture `$before = PlayerUndo::consentState($player);` before `recordConsent`, then `$after = PlayerUndo::consentState($player);` after it. Replace the final dispatch with:

```php
        $this->toastWithUndo(
            'player.consent',
            [
                'player_id' => $player->id,
                'before' => $before,
                'after' => $after,
            ],
            'Consent updated',
            'The player consent status is now '.$status.'.',
        );
```

In `updateDocument()`, capture `$before = PlayerUndo::documentState($player);` before `reviewDocument`, then `$after = PlayerUndo::documentState($player);` after it. Replace the final dispatch with:

```php
        $this->toastWithUndo(
            'player.document',
            [
                'player_id' => $player->id,
                'document_existed' => $document instanceof \App\Models\EligibilityDocument,
                'before' => $before,
                'after' => $after,
            ],
            'Document updated',
            'The document status is now '.$status.'.',
        );
```

`$document` is already assigned before the before-state in the existing method, so `document_existed` reflects the pre-review state.

**Step 3:** Run the submission tests.

```powershell
cd src
php artisan test --compact tests/Feature/Pages/SubmissionsPaymentsTest.php
cd ..
```

Expected: green.

**Step 4:** Commit.

```powershell
git add src/app/Livewire/Organizer/SubmissionsIndex.php src/tests/Feature/Pages/SubmissionsPaymentsTest.php
git commit -m "feat(undo): wire approvals, rejections, consent, and documents"
```

---

### Task 20: Wire payment verification

**Files:**
- Modify: `src/app/Livewire/Organizer/PaymentsIndex.php`
- Modify: `src/tests/Feature/Pages/SubmissionsPaymentsTest.php`

**Step 1:** Add `->assertDispatched('hf-toast', actionLabel: 'Undo', actionMethod: 'POST')` to the verify test.

**Step 2:** Update `src/app/Livewire/Organizer/PaymentsIndex.php`.

Add imports:

```php
use App\Livewire\Concerns\HasUndoToasts;
use App\Services\Undo\Handlers\PaymentUndo;
```

Add `use HasUndoToasts;` inside the class.

In `saveVerify()`, replace the snapshot and update block:

```php
        $before = PaymentUndo::paymentState($payment);

        $payment->update([
            'method' => $this->verifyMethod,
            'reference_no' => $this->verifyRef,
            'amount' => $this->verifyAmount,
            'status' => $this->verifyStatus === 'paid' ? 'verified' : 'partial',
            'paid_at' => $this->verifyStatus === 'paid' ? now() : $payment->paid_at,
            'recorded_by' => auth()->id(),
        ]);

        $after = PaymentUndo::paymentState($payment);
```

Keep the existing audit log block, then replace `$this->dispatch('hf-toast', ...)` with:

```php
        $this->toastWithUndo(
            'payment.verify',
            [
                'payment_id' => $payment->id,
                'before' => $before,
                'after' => $after,
            ],
            'Payment verified',
            'Payment recorded and written to the audit trail.',
        );
```

The existing audit entry uses `$before` as `['status' => ..., 'ref' => ..., 'amount' => ...]`. Rename the new snapshot variable to `$undoBefore` and keep a separate `$auditBefore` shaped like the old array:

```php
        $auditBefore = [
            'status' => $payment->status,
            'ref' => $payment->reference_no,
            'amount' => (float) $payment->amount,
        ];
        $undoBefore = PaymentUndo::paymentState($payment);
```

Use `$auditBefore` in the `AuditLogger` call and `$undoBefore` in the payload.

**Step 3:** Run the payment tests.

```powershell
cd src
php artisan test --compact tests/Feature/Pages/SubmissionsPaymentsTest.php
cd ..
```

Expected: green.

**Step 4:** Commit.

```powershell
git add src/app/Livewire/Organizer/PaymentsIndex.php src/tests/Feature/Pages/SubmissionsPaymentsTest.php
git commit -m "feat(undo): wire payment verification"
```

---

### Task 21: Confirm team registration before submit

**Files:**
- Modify: `src/app/Livewire/Coach/TeamRegister.php`
- Modify: `src/resources/views/livewire/coach/team-register.blade.php`
- Modify: `src/tests/Feature/Pages/TeamRegisterTest.php`

**Step 1:** Add failing tests to `src/tests/Feature/Pages/TeamRegisterTest.php`.

```php
it('asks for confirmation before submitting a registration', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();

    Livewire::actingAs($coach)
        ->test(TeamRegister::class, ['tournament_id' => $tournament->id])
        ->set('name', 'Confirmville Titans')
        ->set('shortName', 'CVT')
        ->set('city', 'Confirmville')
        ->set('coach', 'Coach Confirm')
        ->set('avatarColor', '#123456')
        ->set('players', teamRegisterPlayers())
        ->set('paymentMethod', 'GCash')
        ->set('paymentRef', 'CONFIRM123')
        ->call('requestSave')
        ->assertSet('showConfirm', true)
        ->assertSet('step', 3)
        ->call('cancelSave')
        ->assertSet('showConfirm', false);

    expect(Team::where('name', 'Confirmville Titans')->exists())->toBeFalse();
});

it('submits the registration from the confirmation dialog', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();

    Livewire::actingAs($coach)
        ->test(TeamRegister::class, ['tournament_id' => $tournament->id])
        ->set('name', 'Confirmville Titans')
        ->set('shortName', 'CVT')
        ->set('city', 'Confirmville')
        ->set('coach', 'Coach Confirm')
        ->set('avatarColor', '#123456')
        ->set('players', teamRegisterPlayers())
        ->set('paymentMethod', 'GCash')
        ->set('paymentRef', 'CONFIRM123')
        ->call('requestSave')
        ->call('confirmSave')
        ->assertHasNoErrors();

    expect(Team::where('name', 'Confirmville Titans')->exists())->toBeTrue();
});
```

Extract the existing player fixture into `teamRegisterPlayers()` in the same file, or reuse the existing `$players` array from the first test.

**Step 2:** Add properties and methods to `TeamRegister`.

```php
    public bool $showConfirm = false;

    public string $confirmMessage = '';
```

Add before `save()`:

```php
    public function requestSave(): void
    {
        $this->resetValidation();

        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            $this->step = 0;
            $this->addError('tournament_id', 'Choose an open tournament from the directory.');

            return;
        }

        if ($tournament->status !== 'registration-open') {
            $this->step = 0;
            $this->addError('tournament_id', 'Registration is not open for this tournament.');

            return;
        }

        if ($this->existingTeam instanceof Team) {
            $this->step = 0;
            $this->addError('name', 'Your team is already registered for this tournament.');

            return;
        }

        $division = $tournament->divisions()->first();

        $this->confirmMessage = sprintf(
            '%s enters the %s (%s) with %d players. The entry fee is PHP %s. You can still edit the roster until the organizer approves it.',
            trim($this->name) !== '' ? trim($this->name) : 'Your team',
            $tournament->name,
            $division?->name ?? 'Open division',
            count($this->players),
            number_format($tournament->entryFee()),
        );

        $this->showConfirm = true;
    }

    public function confirmSave(): void
    {
        $this->showConfirm = false;
        $this->save();
    }

    public function cancelSave(): void
    {
        $this->showConfirm = false;
    }
```

The cheap guards are now duplicated in `save()`. Keep `save()` unchanged so direct callers and the final transaction still enforce them.

**Step 3:** Change the form submit target in `team-register.blade.php`.

Replace:

```blade
            <form wire:submit="save" class="hf-card hf-wizard__form" novalidate data-testid="register-form">
```

with:

```blade
            <form wire:submit="requestSave" class="hf-card hf-wizard__form" novalidate data-testid="register-form">
```

Add the dialog immediately before `@endsection` at the end of the view:

```blade
    <x-confirm-dialog
        :open="$showConfirm"
        title="Submit this entry?"
        :message="$confirmMessage"
        confirm-label="Submit registration"
        confirm="confirmSave"
        cancel="cancelSave"
        dialog-id="team-register-confirm-dialog"
        testid="register-confirm-dialog"
        wire:key="team-register-confirm-dialog" />
```

**Step 4:** Run the registration tests.

```powershell
cd src
php artisan test --compact tests/Feature/Pages/TeamRegisterTest.php
cd ..
```

Expected: green. The existing `save()` tests still pass because `save()` is unchanged.

**Step 5:** Commit.

```powershell
git add src/app/Livewire/Coach/TeamRegister.php src/resources/views/livewire/coach/team-register.blade.php src/tests/Feature/Pages/TeamRegisterTest.php
git commit -m "feat(ui): confirm team registration before submit"
```

---

## Phase 5 — Password strength

### Task 22: Enforce the server password policy

**Files:**
- Create: `src/app/Rules/NotCommonPassword.php`
- Modify: `src/app/Http/Controllers/AuthController.php`
- Create: `src/tests/Feature/Auth/PasswordStrengthTest.php`

**Step 1:** Write the failing tests.

```php
<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('rejects passwords shorter than eight characters', function () {
    $this->post('/register', [
        'name' => 'Short Password',
        'email' => 'short@example.com',
        'password' => 'short',
        'role' => 'coach',
    ])->assertSessionHasErrors([
        'password' => 'Use at least 8 characters.',
    ]);

    expect(User::where('email', 'short@example.com')->exists())->toBeFalse();
});

it('rejects a common password', function () {
    $this->post('/register', [
        'name' => 'Common Password',
        'email' => 'common@example.com',
        'password' => 'password',
        'role' => 'coach',
    ])->assertSessionHasErrors([
        'password' => 'That password is too common. Add a word only you would use.',
    ]);
});

it('rejects a common password with a numeric suffix', function () {
    $this->post('/register', [
        'name' => 'Common Suffix',
        'email' => 'common-suffix@example.com',
        'password' => 'qwerty123',
        'role' => 'coach',
    ])->assertSessionHasErrors('password');
});

it('accepts the existing secret123 fixture', function () {
    $this->post('/register', [
        'name' => 'Secret Fixture',
        'email' => 'secret-fixture@example.com',
        'password' => 'secret123',
        'role' => 'coach',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    expect(User::where('email', 'secret-fixture@example.com')->exists())->toBeTrue();
});
```

**Step 2:** Run and confirm the common-password tests fail.

```powershell
cd src
php artisan test --compact tests/Feature/Auth/PasswordStrengthTest.php
cd ..
```

**Step 3:** Create `src/app/Rules/NotCommonPassword.php`.

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotCommonPassword implements ValidationRule
{
    /** @var array<int, string> */
    private const COMMON = [
        '123456',
        '12345678',
        '123456789',
        '1234567890',
        '000000',
        '111111',
        '654321',
        'abc123',
        'admin',
        'admin123',
        'baseball',
        'batman',
        'dragon',
        'football',
        'freedom',
        'hello123',
        'hunter',
        'iloveyou',
        'jennifer',
        'letmein',
        'master',
        'michael',
        'monkey',
        'passw0rd',
        'password',
        'password1',
        'password123',
        'princess',
        'qazwsx',
        'qwerty',
        'qwerty123',
        '1q2w3e4r',
        'shadow',
        'starwars',
        'sunshine',
        'superman',
        'trustno1',
        'welcome',
        'welcome1',
        'whatever',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $password = mb_strtolower(trim($value));

        if (in_array($password, self::COMMON, true)
            || preg_match('/^(password|qwerty|letmein|welcome|admin|iloveyou)\d*$/', $password) === 1) {
            $fail('That password is too common. Add a word only you would use.');
        }
    }
}
```

`secret123` is deliberately absent from the list and does not match the pattern.

**Step 4:** Update `src/app/Http/Controllers/AuthController.php`.

Add the import:

```php
use App\Rules\NotCommonPassword;
```

Replace the password rule:

```php
            'password' => ['required', 'string', 'min:8', 'max:255', new NotCommonPassword],
```

Replace the password messages:

```php
            'password.required' => 'Use at least 8 characters.',
            'password.min' => 'Use at least 8 characters.',
            'password.max' => 'Keep the password under 255 characters.',
```

**Step 5:** Run the auth tests.

```powershell
cd src
php artisan test --compact tests/Feature/Auth
cd ..
```

Expected: green. `RegistrationTest`'s `secret123` cases and its `short` rejection both still pass.

**Step 6:** Commit.

```powershell
git add src/app/Rules/NotCommonPassword.php src/app/Http/Controllers/AuthController.php src/tests/Feature/Auth/PasswordStrengthTest.php
git commit -m "feat(auth): require eight characters and reject common passwords"
```

---

### Task 23: Add the password strength module

**Files:**
- Create: `src/resources/js/ui/password-strength.js`
- Create: `src/tests/js/password-strength.test.js`
- Modify: `src/resources/js/app.js`

**Step 1:** Write the failing JS tests.

```js
import assert from 'node:assert/strict';
import test from 'node:test';
import { evaluatePassword } from '../../resources/js/ui/password-strength.js';

test('an empty password has no label and the empty hint', () => {
    const result = evaluatePassword('');

    assert.equal(result.score, 0);
    assert.equal(result.label, '');
    assert.equal(result.met, 0);
    assert.equal(result.missing.length, 5);
    assert.match(result.hint, /at least 8 characters/i);
});

test('a lowercase-only password scores very weak', () => {
    const result = evaluatePassword('abcdefgh');

    assert.equal(result.checks.length, true);
    assert.equal(result.checks.lowercase, true);
    assert.equal(result.checks.uppercase, false);
    assert.equal(result.score, 1);
    assert.equal(result.label, 'Very weak');
});

test('three classes score fair', () => {
    const result = evaluatePassword('Abcdefgh');

    assert.equal(result.met, 3);
    assert.equal(result.score, 3);
    assert.equal(result.label, 'Fair');
});

test('four classes score strong', () => {
    const result = evaluatePassword('Abcdefg1');

    assert.equal(result.met, 4);
    assert.equal(result.score, 4);
    assert.equal(result.label, 'Strong');
});

test('all five classes label the password very strong', () => {
    const result = evaluatePassword('Abcdefg1!');

    assert.equal(result.met, 5);
    assert.equal(result.score, 4);
    assert.equal(result.label, 'Very strong');
});

test('twelve characters add one score point without claiming all classes', () => {
    const result = evaluatePassword('Abcdefghijkl');

    assert.equal(result.met, 3);
    assert.equal(result.score, 4);
    assert.equal(result.label, 'Strong');
});

test('missing checks stay in display order', () => {
    const result = evaluatePassword('abc');

    assert.deepEqual(result.missing, [
        'At least 8 characters',
        'An uppercase letter',
        'A number',
        'A symbol',
    ]);
});

test('the hint lists the missing checks by name', () => {
    const result = evaluatePassword('abcdefgh');

    assert.match(result.hint, /uppercase letter/i);
    assert.match(result.hint, /number/i);
    assert.match(result.hint, /symbol/i);
});
```

**Step 2:** Run and confirm the module import fails.

```powershell
cd src
node --test tests/js
cd ..
```

**Step 3:** Create `src/resources/js/ui/password-strength.js`.

```js
const CHECKS = [
    { key: 'length', label: 'At least 8 characters', met: (password) => password.length >= 8 },
    { key: 'lowercase', label: 'A lowercase letter', met: (password) => /[a-z]/.test(password) },
    { key: 'uppercase', label: 'An uppercase letter', met: (password) => /[A-Z]/.test(password) },
    { key: 'number', label: 'A number', met: (password) => /\d/.test(password) },
    { key: 'symbol', label: 'A symbol', met: (password) => /[^A-Za-z0-9]/.test(password) },
];

const LABELS = ['', 'Very weak', 'Weak', 'Fair', 'Strong'];

export function evaluatePassword(password = '') {
    const value = String(password);
    const checks = {};
    const missing = [];

    for (const check of CHECKS) {
        const met = check.met(value);
        checks[check.key] = met;
        if (!met) missing.push(check.label);
    }

    const met = CHECKS.filter((check) => checks[check.key]).length;

    if (value.length === 0) {
        return {
            checks,
            met: 0,
            score: 0,
            label: '',
            hint: 'Use at least 8 characters. A longer, mixed password is harder to guess.',
            missing,
        };
    }

    const score = Math.min(4, met + (value.length >= 12 ? 1 : 0));
    const label = met === CHECKS.length ? 'Very strong' : LABELS[score];
    const hint = met === CHECKS.length
        ? 'Strong password. This one is hard to guess.'
        : `Still missing: ${missing.map((item) => item.toLowerCase()).join(', ')}.`;

    return { checks, met, score, label, hint, missing };
}

export function registerPasswordMeter() {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('hfPasswordMeter', () => ({
            password: '',
            showPassword: false,
            focused: false,

            get evaluation() {
                return evaluatePassword(this.password);
            },

            toggle() {
                this.showPassword = !this.showPassword;
            },
        }));
    });
}
```

**Step 4:** Register the module in `src/resources/js/app.js`.

Add the import after `initValidation`:

```js
import { registerPasswordMeter } from './ui/password-strength';
```

Add the call after `registerToasts();`:

```js
registerPasswordMeter();
```

**Step 5:** Run the JS tests.

```powershell
cd src
node --test tests/js
cd ..
```

Expected: `password-strength.test.js`, `toasts.test.js`, and `calendar.test.js` all pass.

**Step 6:** Commit.

```powershell
git add src/resources/js/ui/password-strength.js src/tests/js/password-strength.test.js src/resources/js/app.js
git commit -m "feat(auth): add password strength evaluation and Alpine meter"
```

---

### Task 24: Add the meter to the registration page

**Files:**
- Modify: `src/resources/views/auth/register.blade.php`
- Modify: `src/resources/css/hf/_components.scss`
- Modify: `src/tests/Feature/Auth/PasswordStrengthTest.php`

**Step 1:** Add the failing markup test to `PasswordStrengthTest.php`.

```php
it('renders the password meter, checklist, and show toggle', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('hfPasswordMeter', false)
        ->assertSee('register-password-toggle', false)
        ->assertSee('Password requirements')
        ->assertSee('role="meter"', false)
        ->assertSee('aria-valuemax="4"', false);
});
```

**Step 2:** Run and confirm it fails.

```powershell
cd src
php artisan test --compact tests/Feature/Auth/PasswordStrengthTest.php
cd ..
```

**Step 3:** In `src/resources/views/auth/register.blade.php`, add the Alpine component to the card.

Replace:

```blade
        <div class="hf-auth-card" data-testid="register-card">
```

with:

```blade
        <div class="hf-auth-card" data-testid="register-card" x-data="hfPasswordMeter">
```

**Step 4:** Replace the password field block (the `<div class="hf-field">` containing `registerPassword`) with:

```blade
                    <div class="hf-field hf-field--full">
                        <label class="hf-label" for="registerPassword">Password</label>
                        <div class="hf-inline-form">
                            <input
                                class="hf-input"
                                id="registerPassword"
                                name="password"
                                type="password"
                                x-bind:type="showPassword ? 'text' : 'password'"
                                x-model="password"
                                x-init="$el.value && (password = $el.value)"
                                x-on:focus="focused = true"
                                x-on:blur="focused = false"
                                autocomplete="new-password"
                                data-testid="register-password"
                                required
                                @error('password') aria-invalid="true" aria-describedby="registerPassword-error" @enderror
                            >
                            <button
                                type="button"
                                class="hf-btn hf-btn--sm"
                                x-on:click="toggle()"
                                x-bind:aria-pressed="showPassword ? 'true' : 'false'"
                                x-text="showPassword ? 'Hide' : 'Show'"
                                data-testid="register-password-toggle"
                            >Show</button>
                        </div>
                        @error('password')
                            <div class="hf-field-error" id="registerPassword-error">{{ $message }}</div>
                        @enderror

                        <div class="hf-password-meter" :class="'hf-score-'+evaluation.score" x-show="focused || password.length > 0" x-cloak>
                            <div
                                class="hf-password-bars"
                                role="meter"
                                aria-label="Password strength"
                                aria-valuemin="0"
                                aria-valuemax="4"
                                :aria-valuenow="evaluation.score"
                                :aria-valuetext="evaluation.label || 'Empty'">
                                <template x-for="index in 4" :key="index">
                                    <span class="hf-password-bar" :class="{ 'is-filled': index <= evaluation.score }"></span>
                                </template>
                            </div>
                            <p class="hf-password-hint" role="status" aria-live="polite" x-text="evaluation.hint"></p>
                            <ul class="hf-password-checks" aria-label="Password requirements">
                                @foreach ([
                                    'length' => 'At least 8 characters',
                                    'lowercase' => 'A lowercase letter',
                                    'uppercase' => 'An uppercase letter',
                                    'number' => 'A number',
                                    'symbol' => 'A symbol',
                                ] as $key => $label)
                                    <li class="hf-password-check" :class="{ 'is-met': evaluation.checks['{{ $key }}'] }">
                                        <span class="hf-sr-only" x-text="evaluation.checks['{{ $key }}'] ? 'Met: ' : 'Not met: '"></span>
                                        <i class="bi" :class="evaluation.checks['{{ $key }}'] ? 'bi-check-circle-fill' : 'bi-circle'" aria-hidden="true"></i>
                                        <span>{{ $label }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
```

**Step 5:** Move the role field to a full row.

Replace:

```blade
                    <div class="hf-field">
                        <label class="hf-label" for="registerRole">Role</label>
```

with:

```blade
                    <div class="hf-field hf-field--full">
                        <label class="hf-label" for="registerRole">Role</label>
```

**Step 6:** Add the meter styles after the existing `.hf-meter` rule in `src/resources/css/hf/_components.scss`.

```scss
.hf-password-meter {
  display: grid;
  gap: 8px;
  margin-top: 10px;
}

.hf-password-bars {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 4px;
}

.hf-password-bar {
  height: 5px;
  border-radius: 999px;
  background: var(--hf-border);
  transition: background-color 120ms ease;
}

.hf-password-bar.is-filled {
  background: var(--hf-danger-ink);
}

.hf-score-2 .hf-password-bar.is-filled {
  background: var(--hf-warning-ink);
}

.hf-score-3 .hf-password-bar.is-filled,
.hf-score-4 .hf-password-bar.is-filled {
  background: var(--hf-success-ink);
}

.hf-password-hint {
  margin: 0;
  color: var(--hf-muted);
  font-size: 12px;
}

.hf-password-checks {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px 12px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.hf-password-check {
  display: flex;
  gap: 6px;
  align-items: center;
  color: var(--hf-muted);
  font-size: 12px;
}

.hf-password-check.is-met {
  color: var(--hf-success-ink);
}

@media (max-width: 520px) {
  .hf-password-checks {
    grid-template-columns: 1fr;
  }
}
```

**Step 7:** Run the auth tests and the production build.

```powershell
cd src
php artisan test --compact tests/Feature/Auth/PasswordStrengthTest.php tests/Feature/Auth/RegistrationTest.php
npm run build
cd ..
```

Expected: all auth tests pass and Vite compiles the new styles.

**Step 8:** Commit.

```powershell
git add src/resources/views/auth/register.blade.php src/resources/css/hf/_components.scss src/tests/Feature/Auth/PasswordStrengthTest.php
git commit -m "feat(auth): show password strength guidance on registration"
```

---

## Phase 6 — Final verification

### Task 25: Prove the end-to-end undo path

**Files:**
- Modify: `src/tests/Feature/Undo/UndoEndpointTest.php`

**Step 1:** Add these cases to the endpoint test.

```php
it('restores state from a posted token', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $announcement = Announcement::query()->where('status', 'published')->firstOrFail();
    $announcement->delete();

    $token = app(UndoService::class)->remember(
        $organizer,
        'announcement.delete',
        ['id' => $announcement->id],
        'Announcement deleted',
        'The announcement is off the feed.',
    );

    $this->actingAs($organizer)
        ->from('/announcements')
        ->post('/undo/'.$token)
        ->assertRedirect('/announcements')
        ->assertSessionHas('hf_toast', function (array $toast): bool {
            return $toast['tone'] === 'success'
                && $toast['title'] === 'Undone';
        });

    expect($announcement->fresh()->trashed())->toBeFalse();
});

it('returns a permission warning when the handler rejects the actor', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $tournament->update(['archived_at' => now()]);

    $token = app(UndoService::class)->remember(
        $coach,
        'tournament.archive',
        ['id' => $tournament->id],
        'Tournament archived',
        'Archived.',
    );

    $this->actingAs($coach)
        ->from('/tournaments')
        ->post('/undo/'.$token)
        ->assertRedirect('/tournaments')
        ->assertSessionHas('hf_toast', function (array $toast): bool {
            return $toast['tone'] === 'warning'
                && $toast['message'] === "You don't have permission to undo that action.";
        });

    expect($tournament->fresh()->archived_at)->not->toBeNull();
});

it('consumes a token so a replay changes nothing', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $tournament->update(['archived_at' => now()]);

    $token = app(UndoService::class)->remember(
        $organizer,
        'tournament.archive',
        ['id' => $tournament->id],
        'Tournament archived',
        'Archived.',
    );

    $this->actingAs($organizer)->post('/undo/'.$token)->assertRedirect();
    $tournament->update(['archived_at' => now()]);

    $this->actingAs($organizer)
        ->from('/tournaments')
        ->post('/undo/'.$token)
        ->assertRedirect('/tournaments')
        ->assertSessionHas('hf_toast', function (array $toast): bool {
            return $toast['message'] === 'That undo is no longer available.';
        });
});
```

Add the `Announcement` import and keep the existing `Tournament` import.

**Step 2:** Run the whole undo suite.

```powershell
cd src
php artisan test --compact tests/Feature/Undo
cd ..
```

Expected: green.

**Step 3:** Commit.

```powershell
git add src/tests/Feature/Undo/UndoEndpointTest.php
git commit -m "test(undo): cover the end-to-end endpoint path"
```

---

### Task 26: Run the full gates and finish

**Step 1:** Run the mandatory quality gate and capture the actual output.

```powershell
cd src
composer quality
cd ..
```

This runs Pint, Larastan level 6, and Pest. Fix every failure caused by this branch. Name environment-only failures explicitly (for example a Postgres-only scheduling test on SQLite) in the final report; do not silently skip them.

**Step 2:** Run the production build and the JS tests.

```powershell
cd src
npm run build
node --test tests/js
cd ..
```

**Step 3:** Add a short note to `src/app/Services/README.md`.

Append:

```markdown
## Application services

`Undo/` is an application service, not a domain service. It reads and writes the session and
orchestrates Eloquent-backed handlers, so it is intentionally excluded from the "plain PHP" rule
above. Handler classes re-check authorization and run their writes inside a database transaction.
```

**Step 4:** Manual smoke checks. Run the app and verify:

1. Register with `short` → inline server error says "Use at least 8 characters."
2. Register with `password` → inline server error says the password is too common.
3. Type `secret123` → meter shows a hint and checklist without blocking.
4. Type a 12+ mixed password → all five checklist items are met and the label is "Very strong".
5. Archive a tournament from the index → toast shows Undo → click Undo → the tournament returns.
6. Delete an announcement → toast Undo restores it; the feed shows it again.
7. Remove a player → toast Undo restores the row.
8. Submit a payment reference → toast Undo restores the previous reference.
9. Click Log out in the rail and the mobile sheet → one dialog opens; Cancel keeps the session; Log out posts.
10. Tab through a toast and activate Undo with the keyboard.

**Step 5:** Commit the README note.

```powershell
git add src/app/Services/README.md
git commit -m "docs(services): document the undo application service"
```

**Step 6:** Report the actual gate output to sir. Include the commands, pass/fail status, and any environment-only failure names. Do not claim completion without the pasted/observed output.

---

## Self-review checklist

Before considering the plan complete, verify:

- [ ] Every action in the spec's action matrix has a confirmation, an undo, or both exactly as specified.
- [ ] Undo tokens are 40 characters, user-bound, single-use, expire after 10 minutes, and keep the newest 10.
- [ ] `POST /undo/{token}` is inside `auth` and CSRF-protected.
- [ ] Tournament create undo redirects to `/tournaments`; every other undo uses `back()`.
- [ ] Routine undo writes no audit row; tournament lifecycle and payment verification write exactly one.
- [ ] `players` and `announcements` are soft-deletable; existing removal assertions use `assertSoftDeleted`.
- [ ] The four simple dialogs use `<x-confirm-dialog>` with their original `data-testid` values.
- [ ] The logout dialog is global, labelled, and both logout controls open it.
- [ ] The registration password rule is `min:8` plus `NotCommonPassword`; `secret123` stays valid.
- [ ] The meter exposes `role="meter"`, `aria-valuemin/max/now/valuetext`, a polite hint, and five checklist items with "Met"/"Not met" text.
- [ ] `actionHref` still works for existing GET toasts and `feedback.js`.
- [ ] No Tailwind, Bootstrap JavaScript, Vue, React, or new packages were added.
- [ ] `composer quality`, `npm run build`, and `node --test tests/js` have been run and their actual output reported.


