# Phase 1 — Shell & Theme Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the prototype shell (rail, topbar, mobile bottom nav) into the Laravel Blade layout with real session auth, theme toggle, and Bootstrap 5.3 compiled from source.

**Architecture:** `layouts/public.blade.php` becomes the shell with role-gated nav. Auth via standard `AuthController` (session). Role check via `RoleMiddleware` + `User::hasRole()`. Theme persisted in localStorage via Alpine + inline head script (FOUC prevention). SCSS ported from prototype CSS into `_hoopflow.scss`.

**Tech Stack:** Laravel 13, Blade, Livewire 4, Alpine (bundled), Bootstrap 5.3 SCSS, Bootstrap Icons, Vite.

## Global Constraints

- Bootstrap 5.3 compiled from source (app.scss); no Bootstrap JS (ADR-0001).
- No Tailwind, no SPA framework.
- Custom CSS for layout/responsiveness; Bootstrap for variables/icons only.
- `composer quality` must pass before claiming done.
- No domain logic in controllers; auth is infra.

## File Structure

- Modify: `src/resources/css/_hoopflow.scss` (extend port).
- Modify: `src/resources/views/layouts/public.blade.php` (shell).
- Create: `src/resources/views/auth/login.blade.php`.
- Create: `src/app/Http/Controllers/AuthController.php`.
- Create: `src/app/Http/Middleware/RoleMiddleware.php`.
- Modify: `src/app/Models/User.php` (hasRole).
- Modify: `src/routes/web.php` (auth routes + protected home).
- Modify: `src/resources/js/app.js` (theme init).
- Create: `src/public/img/logo.png` (copy).
- Create: `src/tests/Feature/Auth/LoginTest.php`.

---

### Task 1: User::hasRole + RoleMiddleware

**Files:** Modify `src/app/Models/User.php`; Create `src/app/Http/Middleware/RoleMiddleware.php`; Modify `src/bootstrap/app.php` (register middleware alias).

- [ ] **Step 1: Add hasRole to User**

```php
public function hasRole(string $key): bool
{
    return $this->roles()->where('key', $key)->exists();
}
// add roles() relation: belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
```

- [ ] **Step 2: Create RoleMiddleware**

```php
public function handle(Request $request, Closure $next, string ...$roles): Response
{
    $user = $request->user();
    if (! $user) abort(403);
    foreach ($roles as $role) {
        if ($user->hasRole($role)) return $next($request);
    }
    abort(403);
}
```

- [ ] **Step 3: Register alias `role` in bootstrap/app.php.**

- [ ] **Step 4: Run lint + tests. Commit "feat(auth): role middleware and User::hasRole".**

---

### Task 2: AuthController + routes

**Files:** Create `src/app/Http/Controllers/AuthController.php`; Modify `src/routes/web.php`.

- [ ] **Step 1: AuthController**

```php
public function showLogin() { return view('auth.login'); }
public function login(Request $request) {
    $credentials = $request->validate(['email'=>'required|email','password'=>'required']);
    if (Auth::attempt($credentials, $request->boolean('remember'))) {
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }
    return back()->withErrors(['email'=>'Invalid credentials.'])->onlyInput('email');
}
public function logout(Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login');
}
```

- [ ] **Step 2: Routes**

```php
Route::get('/login', [AuthController::class,'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class,'login'])->middleware('guest');
Route::post('/logout', [AuthController::class,'logout'])->name('logout')->middleware('auth');
Route::get('/', fn() => view('pages.home'))->name('home');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn() => view('pages.dashboard'))->name('dashboard');
});
```

- [ ] **Step 3: Run lint + tests. Commit.**

---

### Task 3: Login view

**Files:** Create `src/resources/views/auth/login.blade.php`.

- [ ] **Step 1: Login page** extending `layouts.public` (auth shell variant) with email/password form POSTing to login, plus 3 demo account buttons (form with hidden email/password) for one-click sign-in.

- [ ] **Step 2: Commit.**

---

### Task 4: Shell layout

**Files:** Modify `src/resources/views/layouts/public.blade.php`.

- [ ] **Step 1: Replace with prototype shell markup** (rail, topbar, bottom nav) gated by `@auth`, role-based nav via config, theme toggle, user chip + logout. Guest variant: simple topbar + content.

- [ ] **Step 2: Commit.**

---

### Task 5: Theme toggle + app.js

**Files:** Modify `src/resources/js/app.js`; modify `layouts/public.blade.php` head.

- [ ] **Step 1: Inline head script** sets `data-theme` from localStorage before paint.
- [ ] **Step 2: app.js** toggle handler + sync.
- [ ] **Step 3: Commit.**

---

### Task 6: SCSS port + logo

**Files:** Modify `src/resources/css/_hoopflow.scss`; copy logo.

- [ ] **Step 1: Append rail/topbar/bottom-nav/auth-topbar styles** ported from prototype CSS.
- [ ] **Step 2: Copy logo.png to public/img/logo.png.**
- [ ] **Step 3: Run `npm run build` (vite). Commit.**

---

### Task 7: Tests

**Files:** Create `src/tests/Feature/Auth/LoginTest.php`.

- [ ] **Step 1: Write failing tests** (login page loads, valid login redirects to dashboard, invalid shows error, logout works, guest redirected from dashboard).

- [ ] **Step 2: Run tests to verify pass. Commit.**

---

### Task 8: Quality gate

- [ ] **Step 1: Run `composer quality`. Fix any issues. Commit.**

## Self-Review Notes

- Spec coverage: shell (Task 4), auth (Tasks 1-3), theme (Task 5), assets (Task 6), tests (Task 7), quality (Task 8).
- No placeholders; code blocks provided.
- Type consistency: `hasRole(string): bool`, `RoleMiddleware::handle(...$roles)`.
