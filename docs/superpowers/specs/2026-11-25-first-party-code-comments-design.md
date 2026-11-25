# First-party code comments — design spec

Date: 2026-11-25

## Context

The HoopFlow product is the Laravel application in `src/`. The user wants the
code to be maintainable by giving every first-party file a purpose comment and
every named function/method a docblock. `src/` also contains generated and
third-party trees: 10,764 Composer `vendor/` files, `node_modules/`, and ~200
compiled Blade views in `storage/framework/views/`. Those are overwritten by
`composer install`, `npm install`, or a template build, so commenting them is
throwaway work.

## Goals

- Every first-party file under `src/` has a one-line purpose comment.
- Every named function or method in first-party code has a docblock with a
  one-line purpose, `@param` for each parameter, and `@return` where a native
  return type exists; `@throws` only where the body actually throws.
- Comments do not break `composer quality` (Pint, Larastan level 6, Pest).
- The work is repeatable: a second pass produces no changes.

## Non-goals

- No changes to `src/vendor/`, `src/node_modules/`, or
  `src/storage/framework/views/`.
- No changes to `.env`, lockfiles, binary assets, or generated caches.
- No new dependency, no permanent script, no config change to Pint/Larastan.
- No inline closures, arrow functions, or Pest `test()` callbacks get docblocks.

## Scope

Included file sets:

| Path | Treatment |
|---|---|
| `src/app/**/*.php` | file header + docblocks on named methods |
| `src/bootstrap/**/*.php`, `src/config/**/*.php` | file header + docblocks on named methods |
| `src/database/**/*.php` | file header + docblocks on named methods |
| `src/routes/**/*.php` | file header (route files declare no functions) |
| `src/public/index.php` | file header |
| `src/tests/**/*.php` | file header + docblocks on named methods |
| `src/resources/**/*.blade.php` | `{{-- purpose --}}` header only |
| `src/resources/**/*.js` | file header + JSDoc on named functions |
| `src/resources/**/*.scss` | `// purpose` header only |

Excluded: `src/vendor/`, `src/node_modules/`, `src/storage/framework/views/`,
`src/storage/logs/`, `.env*`, `composer.lock`, `package-lock.json`, images and
other binaries.

## Conventions

### PHP

- File header: a single `/** Purpose. */` or `// Purpose.` line immediately
  after `<?php`, before `namespace`/`declare`/`use` statements, matching the
  surrounding style.
- Function docblock: one-line summary, then `@param <type> $<name>` for each
  parameter and `@return <type>` when the signature declares a return type.
  Types come from the native signature; no guessed or widened types.
- `@throws` is added only when the function body contains a `throw` statement
  and the exception class can be read from it.
- Existing docblocks are never replaced or trimmed. Larastan array-shape tags
  such as `@return array{matches: int, games: int}` are preserved verbatim.
- Methods that already have any docblock are skipped entirely.
- Closures and arrow functions are skipped.

### JavaScript

- File header `// Purpose.` at the top.
- JSDoc with summary and `@param`/`@returns` for named function declarations
  and named function expressions assigned to a variable.

### Blade and SCSS

- Blade: `{{-- Purpose. --}}` as the first line.
- SCSS: `// Purpose.` as the first line.
- These templates declare no named functions, so no per-function blocks.

## Approach

1. Generate mechanically with a throwaway script placed in the OpenCode temp
   folder (not committed). The script:
   - walks only the whitelisted paths;
   - uses `token_get_all()` for PHP files to find `T_FUNCTION` declarations,
     read names, parameters, return types, and preceding docblocks;
   - uses small line scanners for JS, Blade, and SCSS headers;
   - inserts only what is missing and never edits an existing docblock;
   - is idempotent, so a second run yields a zero diff.
2. Dry-run the generator, review the diff, then apply.
3. Hand-refine wording in `src/app/Services/**` (bracket, scheduling,
   standings, eligibility, announcements, audit logging) where a name-derived
   summary does not explain intent.
4. Delete the temporary script after the final verification run.

## Verification

Run in order and report actual output, per `src/AGENTS.md`:

1. `git diff --stat` and a spot-check of representative files from each file type.
2. Re-run the generator; `git diff` must be unchanged (idempotency).
3. `cd src && composer quality` — Pint check, Larastan level 6, Pest. Fix any
   comment-induced failure (bad `@param` type, superfluous tag, formatting)
   at the source before claiming completion.

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| Name-derived summaries are generic | Hand-refine `app/Services`; keep summaries factual and short |
| Generated type tags conflict with native types or Larastan | Copy types from signatures only; preserve existing docblocks; run `composer quality` |
| Pint reformats inserted headers | Run `pint --test`; adjust header placement to match file style |
| Script touches generated files | Hard-coded whitelist, no recursive `src/` walk |

## Acceptance criteria

- No file outside the whitelist is modified.
- Every first-party file has a purpose comment.
- Every named first-party function/method has a docblock (unless it already had one).
- A second generator run produces no diff.
- `composer quality` passes, with actual output reported.
