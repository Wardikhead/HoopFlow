# First-Party Code Comments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give every first-party file in `src/` a purpose comment and every named function/method a docblock with accurate type tags, without touching generated or third-party code.

**Architecture:** A throwaway PHP script walks an explicit whitelist of first-party directories. It uses `token_get_all()` for PHP files (finding named `T_FUNCTION` declarations, modifiers, attributes, parameters and native return types) and small line scanners for JS, Blade and SCSS. It inserts only missing comments, never overwrites existing docblocks, and is idempotent. A short manual pass improves wording in `app/Services/`. Quality is verified with Pint, Larastan and Pest via `composer quality`.

**Tech Stack:** PHP 8.5.2, Laravel 13, PHP tokenizer, JSDoc/PHPDoc, Pint (Laravel preset), Larastan level 6, Pest.

## Global Constraints

- Product code lives in `src/`; the spec is `docs/superpowers/specs/2026-11-25-first-party-code-comments-design.md`.
- Whitelist only: `src/app`, `src/bootstrap`, `src/config`, `src/database`, `src/public`, `src/routes`, `src/tests`, `src/resources/views`, `src/resources/js`, `src/resources/css`, `src/tests/js`.
- Never modify: `src/vendor`, `src/node_modules`, `src/storage/framework/views`, `src/storage/logs`, `.env*`, lockfiles, binaries.
- PHPDoc format required by Pint: `@param  <type>  $<name>  Description`; `@return <type> Description`; blank line before `@throws`. No leading backslash in `@throws` (Pint rewrites FQNs), so `@throws` is added by hand in Task 4 only where the class is already imported.
- No new dependency, no permanent script, no Pint/Larastan config change.
- `composer quality` must pass and its real output reported before claiming completion.
- Work from PowerShell in `C:\Users\JewardMahinay\Downloads\hoopflow development`; temporary files go under `C:\Users\JewardMahinay\AppData\Local\Temp\opencode`.

---

### Task 1: Build and validate the generator in a temp sandbox

**Files:**
- Create: `C:\Users\JewardMahinay\AppData\Local\Temp\opencode\document.php`
- Sandbox copies only (not repo files): `doc-test\app\Services\BracketService.php`, `doc-test\resources\views\status-select.blade.php`, `doc-test\tests\js\calendar.test.js`

**Interfaces:**
- Consumes: nothing; this is the first task.
- Produces: a validated script invoked as `php document.php [--apply] [root]`; later tasks call it with the real `src` root.

- [ ] **Step 1: Write the generator script**

Create `C:\Users\JewardMahinay\AppData\Local\Temp\opencode\document.php` with exactly this content:

```php
<?php

/**
 * Add missing purpose comments and docblocks to first-party HoopFlow files.
 * Usage: php document.php [--apply] [root]
 */

$apply = in_array('--apply', $argv, true);
$rootArgs = array_values(array_filter($argv, static fn (string $a): bool => $a !== '--apply' && $a !== $argv[0]));
$root = rtrim($rootArgs[0] ?? 'C:\\Users\\JewardMahinay\\Downloads\\hoopflow development\\src', '\\/');

$phpDirs = ['app', 'bootstrap', 'config', 'database', 'public', 'routes', 'tests'];
$jsDirs = ['resources/js', 'tests/js'];
$styleDirs = ['resources/css'];
$viewDir = 'resources/views';

$humanize = static function (string $name): string {
    $words = preg_replace('/(?<!^)[A-Z]/', ' $0', $name) ?? $name;
    $words = str_replace(['_', '-'], ' ', $words);
    $words = preg_replace('/\s+/', ' ', trim($words)) ?? $words;

    return ucfirst(strtolower($words));
};

$summary = static function (string $name) use ($humanize): string {
    $magic = [
        '__construct' => 'Initialize the instance.',
        '__destruct' => 'Clean up the instance.',
        '__invoke' => 'Invoke the instance as a callable.',
        '__toString' => 'Return the string representation.',
    ];

    return $magic[$name] ?? $humanize($name).'.';
};

$purpose = static function (string $rel) use ($humanize): string {
    $rel = str_replace('/', '\\', $rel);
    $parts = explode('\\', $rel);
    $base = (string) array_pop($parts);
    $name = $humanize((string) preg_replace('/\.(blade\.php|php|js|scss|css)$/', '', $base));
    $dir = implode('\\', $parts);

    return match (true) {
        str_starts_with($dir, 'app\\Services') => "Domain service for {$name}.",
        str_starts_with($dir, 'app\\Http\\Controllers') => "HTTP controller for {$name}.",
        str_starts_with($dir, 'app\\Livewire') => "Livewire component for {$name}.",
        str_starts_with($dir, 'app\\Models') => "Eloquent model for {$name}.",
        str_starts_with($dir, 'app\\Policies') => "Authorization policy for {$name}.",
        str_starts_with($dir, 'app\\Rules') => "Validation rule for {$name}.",
        str_starts_with($dir, 'app\\Enums') => "Enumeration for {$name}.",
        str_starts_with($dir, 'app\\') => "Application class {$name}.",
        str_starts_with($dir, 'config') => "Configuration for {$name}.",
        str_starts_with($dir, 'database\\migrations') => "Database migration {$name}.",
        str_starts_with($dir, 'database\\seeders') => "Database seeder {$name}.",
        str_starts_with($dir, 'database') => "Database support for {$name}.",
        str_starts_with($dir, 'routes') => "Route definitions for {$name}.",
        str_starts_with($dir, 'bootstrap') => "Application bootstrap for {$name}.",
        str_starts_with($dir, 'tests\\js') => "Front-end tests for {$name}.",
        str_starts_with($dir, 'tests') => "Tests for {$name}.",
        str_starts_with($dir, 'resources\\views') => "Blade view for {$name}.",
        str_starts_with($dir, 'resources\\js') => "Front-end script for {$name}.",
        str_starts_with($dir, 'resources\\css') => "Styles for {$name}.",
        default => "{$name}.",
    };
};

$files = [];
foreach (['php' => $phpDirs, 'js' => $jsDirs, 'style' => $styleDirs, 'view' => [$viewDir]] as $kind => $dirs) {
    foreach ($dirs as $dir) {
        $path = $root.'\\'.str_replace('/', '\\', $dir);
        if (! is_dir($path)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (! $f->isFile()) {
                continue;
            }
            $rel = substr($f->getPathname(), strlen($root) + 1);
            $ext = $f->getExtension();
            $ok = match ($kind) {
                'php' => $ext === 'php',
                'js' => $ext === 'js',
                'style' => in_array($ext, ['css', 'scss'], true),
                'view' => str_ends_with($rel, '.blade.php'),
            };
            if ($ok) {
                $files[] = ['kind' => $kind, 'path' => $f->getPathname(), 'rel' => $rel];
            }
        }
    }
}

$modifiers = [T_PUBLIC, T_PRIVATE, T_PROTECTED, T_STATIC, T_FINAL, T_ABSTRACT, T_READONLY];
$headers = 0;
$docblocks = 0;
$edited = 0;
$samples = [];

foreach ($files as $file) {
    $src = (string) file_get_contents($file['path']);
    $insertions = [];

    if ($file['kind'] === 'php') {
        $tokens = token_get_all($src);
        $lengths = [];
        $running = 0;
        foreach ($tokens as $idx => $tok) {
            $lengths[$idx] = $running;
            $running += is_array($tok) ? strlen($tok[1]) : strlen($tok);
        }

        $open = null;
        foreach ($tokens as $i => $t) {
            if (is_array($t) && $t[0] === T_OPEN_TAG) {
                $open = $i;
                break;
            }
        }
        if ($open !== null) {
            $j = $open + 1;
            while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            if (! isset($tokens[$j]) || ! is_array($tokens[$j]) || ! in_array($tokens[$j][0], [T_DOC_COMMENT, T_COMMENT], true)) {
                $insertions[$lengths[$open] + strlen($tokens[$open][1])] = "\n/** {$purpose($file['rel'])} */\n";
                $headers++;
            }
        }

        foreach ($tokens as $i => $t) {
            if (! is_array($t) || $t[0] !== T_FUNCTION) {
                continue;
            }
            $j = $i + 1;
            while (isset($tokens[$j]) && ($tokens[$j] === '&' || (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE))) {
                $j++;
            }
            if (! isset($tokens[$j]) || ! is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING) {
                continue;
            }
            $name = $tokens[$j][1];

            $s = $i - 1;
            while (isset($tokens[$s]) && is_array($tokens[$s]) && $tokens[$s][0] === T_WHITESPACE) {
                $s--;
            }
            while (isset($tokens[$s]) && is_array($tokens[$s]) && in_array($tokens[$s][0], $modifiers, true)) {
                $s--;
                while (isset($tokens[$s]) && is_array($tokens[$s]) && $tokens[$s][0] === T_WHITESPACE) {
                    $s--;
                }
            }
            while (isset($tokens[$s]) && $tokens[$s] === ']') {
                $depth = 0;
                $k = $s;
                while ($k >= 0) {
                    if ($tokens[$k] === ']') {
                        $depth++;
                    }
                    if ($tokens[$k] === '[' || (is_array($tokens[$k]) && $tokens[$k][0] === T_ATTRIBUTE)) {
                        $depth--;
                        if ($depth === 0) {
                            break;
                        }
                    }
                    $k--;
                }
                if ($k < 0) {
                    break;
                }
                $s = $k - 1;
                while (isset($tokens[$s]) && is_array($tokens[$s]) && $tokens[$s][0] === T_WHITESPACE) {
                    $s--;
                }
            }

            $p = $s;
            while (isset($tokens[$p]) && is_array($tokens[$p]) && $tokens[$p][0] === T_WHITESPACE) {
                $p--;
            }
            if (isset($tokens[$p]) && is_array($tokens[$p]) && in_array($tokens[$p][0], [T_DOC_COMMENT, T_COMMENT], true)) {
                continue;
            }

            $declIdx = $s + 1;
            $declPos = $lengths[$declIdx] ?? 0;
            if (isset($tokens[$declIdx]) && is_array($tokens[$declIdx]) && $tokens[$declIdx][0] === T_WHITESPACE) {
                $declPos += strspn($tokens[$declIdx][1], "\r\n");
            }
            $lineStart = strrpos(substr($src, 0, $declPos), "\n");
            $lineStart = $lineStart === false ? 0 : $lineStart + 1;
            preg_match('/^[ \t]*/', substr($src, $lineStart), $im);
            $indent = $im[0];

            $openParen = null;
            $closeParen = null;
            $depth = 0;
            for ($k = $j + 1; $k < count($tokens); $k++) {
                if ($tokens[$k] === '(') {
                    if ($openParen === null) {
                        $openParen = $k;
                    }
                    $depth++;
                    continue;
                }
                if ($tokens[$k] === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $closeParen = $k;
                        break;
                    }
                }
            }

            $params = [];
            if ($openParen !== null && $closeParen !== null) {
                $buf = '';
                $paramName = '';
                $paramType = '';
                $inDefault = false;
                $innerDepth = 0;
                for ($k = $openParen + 1; $k < $closeParen; $k++) {
                    $tk = $tokens[$k];
                    $char = is_array($tk) ? null : $tk;
                    if ($char === '(' || $char === '[') {
                        $innerDepth++;
                    }
                    if ($char === ')' || $char === ']') {
                        $innerDepth--;
                    }
                    if ($innerDepth === 0 && $char === ',') {
                        if ($paramName !== '') {
                            $params[] = [$paramType, $paramName];
                        }
                        $buf = '';
                        $paramName = '';
                        $paramType = '';
                        $inDefault = false;
                        continue;
                    }
                    if (is_array($tk) && $tk[0] === T_VARIABLE && $paramName === '') {
                        $paramName = $tk[1];
                        $paramType = trim(preg_replace('/\s+/', '', $buf) ?? '');
                        $buf = '';
                        continue;
                    }
                    if ($char === '=' && ! $inDefault) {
                        $inDefault = true;
                        continue;
                    }
                    if (! $inDefault && is_array($tk) && $tk[0] !== T_WHITESPACE) {
                        $buf .= $tk[1];
                    }
                }
                if ($paramName !== '') {
                    $params[] = [$paramType, $paramName];
                }
            }

            $returnType = '';
            if ($closeParen !== null) {
                $k = $closeParen + 1;
                while (isset($tokens[$k]) && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                    $k++;
                }
                if (isset($tokens[$k]) && $tokens[$k] === ':') {
                    $k++;
                    while (isset($tokens[$k]) && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                        $k++;
                    }
                    while (isset($tokens[$k]) && $tokens[$k] !== '{' && $tokens[$k] !== ';' && ! (is_array($tokens[$k]) && $tokens[$k][0] === T_CURLY_OPEN)) {
                        if (! is_array($tokens[$k]) || $tokens[$k][0] !== T_WHITESPACE) {
                            $returnType .= is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
                        }
                        $k++;
                    }
                }
            }

            $lines = [$indent.'/**', $indent.' * '.$summary($name)];
            if ($params !== [] || $returnType !== '') {
                $lines[] = $indent.' *';
            }
            foreach ($params as [$type, $var]) {
                $lines[] = $indent.' * @param  '.($type !== '' ? $type : 'mixed').'  '.$var.'  The '.lcfirst($humanize(ltrim($var, '$'))).'.';
            }
            if ($returnType !== '') {
                $lines[] = $indent.' * @return '.$returnType.($returnType === 'void' ? ' Nothing.' : ' The result.');
            }
            $lines[] = $indent.' */';
            $insertions[$declPos] = implode("\n", $lines)."\n";
            $docblocks++;
        }
    } elseif ($file['kind'] === 'view') {
        if (! str_starts_with(ltrim($src), '{{--')) {
            $insertions[0] = '{{-- '.$purpose($file['rel']).' --}}'."\n";
            $headers++;
        }
    } else {
        if (! str_starts_with(ltrim($src), '//') && ! str_starts_with(ltrim($src), '/*')) {
            $insertions[0] = '// '.$purpose($file['rel'])."\n\n";
            $headers++;
        }
        if ($file['kind'] === 'js') {
            $linesWithOffsets = preg_split('/\R/', $src, -1, PREG_SPLIT_OFFSET_CAPTURE) ?: [];
            foreach ($linesWithOffsets as $lineNo => $lineParts) {
                $line = $lineParts[0];
                if (! preg_match('/^(\s*)(?:export\s+)?(?:async\s+)?function\s+([A-Za-z_$][\w$]*)\s*\(([^)]*)\)/', $line, $m)) {
                    continue;
                }
                $prev = $lineNo - 1;
                while ($prev >= 0 && trim($linesWithOffsets[$prev][0]) === '') {
                    $prev--;
                }
                if ($prev >= 0 && (str_starts_with(trim($linesWithOffsets[$prev][0]), '/**') || trim($linesWithOffsets[$prev][0]) === '*/')) {
                    continue;
                }
                $offset = $lineParts[1];
                $args = array_filter(array_map('trim', explode(',', $m[3])), static fn (string $p): bool => $p !== '');
                $block = [$m[1].'/**', $m[1].' * '.$summary($m[2])];
                if ($args !== []) {
                    $block[] = $m[1].' *';
                    foreach ($args as $arg) {
                        $argName = ltrim(explode('=', $arg)[0], ' ');
                        $block[] = $m[1].' * @param {*} '.$argName.' The '.lcfirst($humanize($argName)).'.';
                    }
                }
                $block[] = $m[1].' * @returns {*} The result.';
                $block[] = $m[1].' */';
                $insertions[$offset] = implode("\n", $block)."\n";
                $docblocks++;
            }
        }
    }

    if ($insertions === []) {
        continue;
    }
    krsort($insertions);
    $out = $src;
    foreach ($insertions as $offset => $text) {
        $out = substr_replace($out, $text, $offset, 0);
    }
    $edited++;
    if (count($samples) < 3) {
        $samples[] = $file['rel'];
    }
    if ($apply) {
        file_put_contents($file['path'], $out);
    }
}

printf("%s: %d files, %d headers, %d docblocks (samples: %s)\n", $apply ? 'APPLIED' : 'DRY RUN', $edited, $headers, $docblocks, implode(', ', $samples));
```

- [ ] **Step 2: Check PHP syntax**

Run: `php -l "C:\Users\JewardMahinay\AppData\Local\Temp\opencode\document.php"`
Expected: `No syntax errors detected in ...document.php`

- [ ] **Step 3: Build the sandbox**

Run in PowerShell:

```powershell
$t = "C:\Users\JewardMahinay\AppData\Local\Temp\opencode\doc-test"
New-Item -ItemType Directory -Force -Path "$t\app\Services","$t\resources\views","$t\tests\js" | Out-Null
Copy-Item "C:\Users\JewardMahinay\Downloads\hoopflow development\src\app\Services\BracketService.php" "$t\app\Services\BracketService.php"
Copy-Item "C:\Users\JewardMahinay\Downloads\hoopflow development\src\resources\views\components\status-select.blade.php" "$t\resources\views\status-select.blade.php"
Copy-Item "C:\Users\JewardMahinay\Downloads\hoopflow development\src\tests\js\calendar.test.js" "$t\tests\js\calendar.test.js"
```

Expected: three files exist under `$t`.

- [ ] **Step 4: Dry-run, apply, and re-run in the sandbox**

Run:

```powershell
php "C:\Users\JewardMahinay\AppData\Local\Temp\opencode\document.php" $t
php "C:\Users\JewardMahinay\AppData\Local\Temp\opencode\document.php" --apply $t
php "C:\Users\JewardMahinay\AppData\Local\Temp\opencode\document.php" $t
```

Expected: first line `DRY RUN: 3 files, 3 headers, 25 docblocks`; second `APPLIED: 3 files, 3 headers, 25 docblocks`; third `DRY RUN: 0 files, 0 headers, 0 docblocks`. Header counts are 3 because the copied JS test and copied Blade/PHP files have no opening comment; the exact docblock count may vary by 1–2 if source files change, but the third run must always be zero.

- [ ] **Step 5: Inspect generated PHP layout and Pint compatibility**

Run:

```powershell
$s = Get-Content "$t\app\Services\BracketService.php" -Raw
$s.Contains("     */`n    public function syncSeriesTeams")   # must be True
cd "C:\Users\JewardMahinay\Downloads\hoopflow development\src"
./vendor/bin/pint --test "$t\app\Services\BracketService.php"
```

Expected: `True` and Pint `passed`. This proves docblocks land on their own lines with correct indentation and survive Pint's Laravel preset.

No repo commit for this task: only temp files were created.

---

### Task 2: Dry-run against the real source tree

**Files:**
- Uses: the temp script from Task 1
- Modifies: nothing

**Interfaces:**
- Consumes: `document.php` from Task 1.
- Produces: a reviewed dry-run report used to authorize Task 3.

- [ ] **Step 1: Run the dry-run against `src`**

Run: `php "C:\Users\JewardMahinay\AppData\Local\Temp\opencode\document.php"`

Expected (current tree): `DRY RUN: 437 files, 435 headers, 913 docblocks (samples: app\Console\Commands\EnsurePartitions.php, ...)`. If the count differs slightly, that is acceptable; a count near zero means the whitelist is wrong — stop and fix before continuing.

- [ ] **Step 2: Confirm exclusions and clean tree**

Run:

```powershell
cd "C:\Users\JewardMahinay\Downloads\hoopflow development"
git status --short
```

Expected: only the two spec/plan docs added so far; no `src/vendor`, `src/node_modules`, or `src/storage/framework/views` paths appear. The script's `$phpDirs`, `$jsDirs`, `$styleDirs` and `$viewDir` arrays are the only roots it walks, so excluded trees cannot be touched.

- [ ] **Step 3: Spot-check three generated samples**

Inspect the dry-run output for `app/Console/Commands/EnsurePartitions.php`, an `app/Livewire` component and a `resources/views` Blade file. Expected: one-line purpose comment on each; PHP method docblocks include `@param`/`@return` copied from native signatures; Blade gets a `{{-- purpose --}}` header and no function blocks.

No commit for this task.

---

### Task 3: Apply comments to all first-party files

**Files:**
- Modify: all whitelisted first-party files under `src/` (437 files in the current tree)

**Interfaces:**
- Consumes: validated `document.php` from Task 1.
- Produces: documented source tree; Task 4 refines wording in `src/app/Services`.

- [ ] **Step 1: Apply**

Run: `php "C:\Users\JewardMahinay\AppData\Local\Temp\opencode\document.php" --apply`

Expected: `APPLIED: <same counts as Task 2 Step 1>`.

- [ ] **Step 2: Verify idempotency**

Run: `php "C:\Users\JewardMahinay\AppData\Local\Temp\opencode\document.php"`
Expected: `DRY RUN: 0 files, 0 headers, 0 docblocks`.

- [ ] **Step 3: Verify only whitelisted paths changed**

Run:

```powershell
cd "C:\Users\JewardMahinay\Downloads\hoopflow development"
git status --short | Where-Object { $_ -match 'vendor|node_modules|storage\\framework\\views' }
```

Expected: no output.

- [ ] **Step 4: Commit**

Run:

```powershell
git add src
git commit -m "docs: add purpose comments and docblocks to first-party code"
```

Expected: a commit with roughly 437 changed files.

---

### Task 4: Hand-refine the domain services

**Files:**
- Modify: `src/app/Services/*.php` and `src/app/Services/**/*.php`
- Test: `src/tests/Unit/Services/**` (unchanged; only used for verification)

**Interfaces:**
- Consumes: generated docblocks from Task 3.
- Produces: accurate summaries in `app/Services`; optional `@throws` tags where a body throws an imported exception.

- [ ] **Step 1: List the files to refine**

Run: `Get-ChildItem "src\app\Services" -Recurse -File -Filter *.php | Select-Object -ExpandProperty FullName`
Expected: the service classes and bracket/scheduling/calendar subdirectories.

- [ ] **Step 2: Replace generic summaries in `src/app/Services/BracketService.php`**

Use `edit` to turn the generated `generate()` block into a specific one. The existing docblock must keep its array-shape return type:

```php
    /**
     * Generate a tournament bracket and persist its matches.
     *
     * @param  Tournament  $tournament  The tournament the bracket belongs to
     * @param  string  $format  One of single-elimination, round-robin or double-elimination
     * @param  bool  $shuffle  Whether to randomly order the seeds
     * @return array{matches: int, games: int, byes: int} The generated counts
     */
```

Run: `cd src; ./vendor/bin/pint --test app/Services/BracketService.php`
Expected: `passed`. If Pint reports `phpdoc_align` or `phpdoc_separation`, run `./vendor/bin/pint app/Services/BracketService.php` and re-test.

- [ ] **Step 3: Refine the remaining service summaries**

For every other generated docblock in `src/app/Services`, replace the name-derived summary with a one-line statement of intent (for example, `Resolve structural byes.` → `Create or clear placeholder matches for bracket byes.`). Keep every `@param`/`@return` tag unchanged. Add `@throws <ImportedClass> <reason>` on a separate group (blank line before it) only where the body throws that imported class; do not use a leading backslash.

- [ ] **Step 4: Format only changed files and commit**

Run:

```powershell
cd "C:\Users\JewardMahinay\Downloads\hoopflow development\src"
./vendor/bin/pint --dirty
cd ..
git add src/app/Services
git commit -m "docs: clarify domain service docblocks"
```

Expected: Pint exits without errors; a second commit is created.

---

### Task 5: Quality gate and cleanup

**Files:**
- Modifies: only files needed to fix failures from `composer quality`
- Removes: temp files under `C:\Users\JewardMahinay\AppData\Local\Temp\opencode` (outside the repo)

**Interfaces:**
- Consumes: documented tree from Tasks 3 and 4.
- Produces: verified source tree and a reported `composer quality` result.

- [ ] **Step 1: Run the full quality gate**

Run:

```powershell
cd "C:\Users\JewardMahinay\Downloads\hoopflow development\src"
composer quality
```

Expected: Pint check passes, Larastan level 6 reports no errors, Pest passes. Record the actual output (pass/fail counts) for the final reply.

- [ ] **Step 2: Fix any comment-induced failure**

If Pint fails, run `./vendor/bin/pint --dirty` and re-run `composer quality`. If Larastan reports a docblock type mismatch, edit that docblock's `@param`/`@return` type to match the native signature exactly and re-run. Do not weaken code or add suppressions.

- [ ] **Step 3: Commit fixes, if any**

Run:

```powershell
cd "C:\Users\JewardMahinay\Downloads\hoopflow development"
git add src
git commit -m "fix: align generated docblocks with quality gates"
```

Expected: a commit only if Step 2 changed files; otherwise no commit.

- [ ] **Step 4: Remove temporary artifacts**

Run:

```powershell
Remove-Item -Recurse -Force "C:\Users\JewardMahinay\AppData\Local\Temp\opencode\document.php"
Remove-Item -Recurse -Force "C:\Users\JewardMahinay\AppData\Local\Temp\opencode\doc-test*" -ErrorAction SilentlyContinue
```

Expected: no generator or sandbox files remain; no repo files are affected.
