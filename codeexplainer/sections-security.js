/* HoopFlow security explainer — the 21 security changes.
   Every snippet below is an exact excerpt from the file in `path`.
   Indentation is trimmed to the excerpt's own left margin; nothing else is changed. */

const SECURITY_SECTIONS = [
  {
    id: 'pipeline',
    num: 0,
    group: 'Start here',
    tag: 'Foundations',
    title: 'The request pipeline',
    file: 'src/bootstrap/app.php',
    tldr: 'Every request walks the same ordered stack: security headers, session and CSRF, the web throttle, three session guards, then the route gate, the controller or Livewire component, and finally Blade output.',
    steps: [
      'SecurityHeaders is prepended, so it sits outside the whole web group. It calls $next($request) first and decorates the response on the way back out, which is why redirects and error pages still get the headers.',
      'Laravel session and CSRF middleware run before the appended guards, so those guards can read the session, the authenticated user, and the CSRF token. A guard that ran earlier would see an empty session.',
      'The three session guards are appended in a deliberate order: active account, then fingerprint, then idle timeout. A suspended user is stopped before the cheaper timestamp check runs.',
      'Route middleware (auth, then role:...) is the coarse gate for a page. Livewire components repeat the check in boot(), because a Livewire update never re-runs the page route.',
      'The controller or service does the work with bound SQL parameters and transactions, and Blade escapes {{ }} values on the way to the browser.',
    ],
    why: 'Defense in depth. Each layer assumes the one before it can fail, so a route that forgets its role middleware still hits the session guards, and a forged Livewire call still hits SecureComponent::boot().',
    caveat: 'Order is behavior, not decoration. SecurityHeaders can see the authenticated user only because it reads the response after $next() has returned. Moving a guard between the append and prepend lists changes what it can read.',
    code: [
      {
        path: 'src/bootstrap/app.php',
        label: 'withMiddleware()',
        lang: 'php',
        startLine: 31,
        explain: 'The alias block gives routes short middleware names. The web() block then appends the three guards after Laravel session middleware and prepends SecurityHeaders so it wraps everything. Notice that throttle:web is first in the append list: it runs before the guards do any work.',
        code: String.raw`$middleware->alias([
    'role' => RoleMiddleware::class,
    'active' => EnsureUserIsActive::class,
    'session.fingerprint' => VerifySessionFingerprint::class,
    'session.timeout' => EnforceSessionTimeout::class,
]);

$middleware->web(
    append: [
        'throttle:web',
        EnsureUserIsActive::class,
        VerifySessionFingerprint::class,
        EnforceSessionTimeout::class,
    ],
    prepend: [
        SecurityHeaders::class,
    ],
);`,
      },
    ],
  },

  {
    id: 'login-throttle',
    num: 1,
    group: 'Identity',
    tag: 'Authentication',
    title: 'Login throttling',
    file: 'src/app/Http/Controllers/AuthController.php',
    tldr: 'Normalize the email, count failures per email + IP, and make brute force slow before the password is ever hashed.',
    steps: [
      'Str::lower(trim(...)) is merged back into the request before validation, so "Coach@Example.com " and "coach@example.com" are one account and one throttle bucket.',
      'Validation runs before any credential check: a malformed email or a missing password is rejected without touching the limiter or the database.',
      'throttleKey() builds the key from the normalized email, a pipe, and the client IP, then transliterates it. Both limits are read from config and clamped with max(1, ...).',
      'tooManyAttempts() runs before Auth::attempt(), so a blocked client never reaches the password hash, and availableIn() gives the exact seconds left to show the user.',
      'Auth::attempt() adds status => active to the credentials, so a suspended account fails through the same code path as a wrong password. On success the counter is cleared, the session ID is regenerated, and the fingerprint plus activity time are recorded.',
      'On failure the counter is incremented and the response is the same generic "Invalid credentials." message with only the email repopulated.',
    ],
    why: 'It turns a fast password guesser into a slow one, and because the success and failure paths look identical apart from the limiter, the response cannot be used to enumerate accounts.',
    caveat: 'The IP is part of the key, so an attacker cannot lock a victim out globally. A botnet still gets five tries per IP per email, which is why the web, register, and password-reset limiters exist as well.',
    code: [
      {
        path: 'src/app/Http/Controllers/AuthController.php',
        label: 'login()',
        lang: 'php',
        startLine: 49,
        explain: 'Read it top to bottom as a funnel. First the email is normalized and validated. Then a limiter key is built and checked before any password work happens. Only after that does Auth::attempt() run, and every exit from the guard either clears the counter or records a failure. The generic error on the last line is what stops the response from revealing whether the email exists.',
        code: String.raw`$request->merge([
    'email' => Str::lower(trim((string) $request->input('email'))),
]);

$credentials = $request->validate([
    'email' => ['required', 'email'],
    'password' => ['required'],
], [
    'email.required' => 'Enter your email address.',
    'email.email' => 'Enter a valid email address.',
    'password.required' => 'Enter your password.',
]);

$throttleKey = $this->throttleKey($request);
$maxAttempts = max(1, (int) config('auth.login.max_attempts', 5));
$decaySeconds = max(1, (int) config('auth.login.decay_seconds', 60));

if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
    $seconds = RateLimiter::availableIn($throttleKey);

    return back()
        ->withErrors(['email' => "Too many login attempts. Try again in {$seconds} seconds."])
        ->onlyInput('email');
}

if (Auth::attempt([...$credentials, 'status' => 'active'], $request->boolean('remember'))) {
    RateLimiter::clear($throttleKey);
    $request->session()->regenerate();
    $this->recordSessionSecurity($request);

    return redirect()->intended(route('dashboard'));
}

RateLimiter::hit($throttleKey, $decaySeconds);

return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');`,
      },
      {
        path: 'src/app/Http/Controllers/AuthController.php',
        label: 'throttleKey()',
        lang: 'php',
        startLine: 178,
        explain: 'The key is the email plus the IP, transliterated so non-ASCII characters cannot create visually different keys that map to the same inbox. Same email from a different IP, or a different email from the same IP, is a different bucket, so one attacker cannot exhaust the budget for everyone.',
        code: String.raw`return Str::transliterate(
    Str::lower((string) $request->input('email')).'|'.$request->ip()
);`,
      },
    ],
  },

  {
    id: 'logout',
    num: 2,
    group: 'Identity',
    tag: 'Authentication',
    title: 'Logout and session helpers',
    file: 'src/app/Http/Controllers/AuthController.php',
    tldr: 'Logout destroys the server-side session and rotates the CSRF token; the helper records the fingerprint and activity time after a successful sign-in.',
    steps: [
      'Auth::logout() removes the authenticated user from the web guard for the rest of this request.',
      'invalidate() flushes the session data and issues a new session ID, so the old ID no longer maps to anything.',
      'regenerateToken() rotates the CSRF token, so a token captured before logout cannot be replayed.',
      'recordSessionSecurity() stores sha256(user-agent) and the current unix timestamp, seeding the two guards that run on every later request.',
    ],
    why: 'A copied cookie or a stale CSRF token stops working the moment the user signs out, and the two session keys exist before the next request needs them.',
    caveat: 'invalidate() is what kills the data. Deleting the cookie in the browser alone would leave the server-side row alive until it is swept.',
    code: [
      {
        path: 'src/app/Http/Controllers/AuthController.php',
        label: 'logout()',
        lang: 'php',
        startLine: 162,
        explain: 'Three calls, three different jobs. Auth::logout() clears the guard, invalidate() throws away the session payload and rotates the ID, regenerateToken() replaces the CSRF token. The redirect afterwards is just UX; the security work already happened.',
        code: String.raw`Auth::logout();

$request->session()->invalidate();
$request->session()->regenerateToken();

return redirect()->route('login');`,
      },
      {
        path: 'src/app/Http/Controllers/AuthController.php',
        label: 'recordSessionSecurity()',
        lang: 'php',
        startLine: 191,
        explain: 'This is the write side of the two guards. The user-agent is hashed so the raw string is never stored, and last_activity is a unix timestamp so the timeout check is a plain integer subtraction. Both are stored in the session, which means they travel with the session row and die with it.',
        code: String.raw`$request->session()->put([
    'auth.fingerprint' => hash('sha256', (string) $request->userAgent()),
    'auth.last_activity' => time(),
]);`,
      },
    ],
  },

  {
    id: 'hashing',
    num: 3,
    group: 'Identity',
    tag: 'Authentication',
    title: 'One-way password hashing',
    file: 'src/app/Models/User.php',
    tldr: 'The hashed cast turns a plaintext assignment into a salted one-way hash before it ever reaches the database.',
    steps: [
      'User::casts() declares password => hashed next to the datetime cast.',
      'Laravel hashes the value on assignment, and its hash checker detects a value that is already a hash, so passing an existing hash does not hash it twice.',
      'The registration test checks three separate claims: the stored value is not the plaintext, Hash::check() accepts the original password, and Hash::needsRehash() is false so the algorithm and cost are current.',
    ],
    why: 'A database dump does not expose passwords, two users with the same password get different hashes because each one is salted, and verification stays a constant-time comparison inside Hash::check().',
    caveat: 'Hashing is not encryption. There is no key and no way back; the only operation is "does this candidate match this hash?". That is exactly why password reset exists instead of password recovery.',
    code: [
      {
        path: 'src/app/Models/User.php',
        label: 'casts()',
        lang: 'php',
        startLine: 28,
        explain: 'A single cast entry is the whole defence. Because it lives on the model, every write path (registration, seeder, factory, password change) gets hashing for free, and no caller has to remember to call Hash::make().',
        code: String.raw`protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
}`,
      },
      {
        path: 'src/tests/Feature/Security/AuthenticationHardeningTest.php',
        label: 'password is stored hashed',
        lang: 'php',
        startLine: 123,
        explain: 'The test reads the row back from the database and asserts the three properties that matter: the plaintext is gone, the hash verifies, and it is not stale. needsRehash() being false also means the current driver settings (bcrypt cost, or argon parameters) were used, so no silent downgrade slipped in.',
        code: String.raw`$user = User::where('email', 'hash-check@example.com')->firstOrFail();

expect($user->password)
    ->not->toBe('secret123')
    ->and(Hash::check('secret123', $user->password))->toBeTrue()
    ->and(Hash::needsRehash($user->password))->toBeFalse();`,
      },
    ],
  },

  {
    id: 'registration',
    num: 4,
    group: 'Identity',
    tag: 'Authentication',
    title: 'Registration validation',
    file: 'src/app/Http/Controllers/AuthController.php',
    tldr: 'Validate at the trust boundary, allow only coach or fan self-registration, and create the user, organization, role, and pivot in one transaction.',
    steps: [
      'Name, email, password, role, and the optional organization are validated before any write. The email is unique in the users table at the database level, not just in a form hint.',
      'role is an explicit allow-list of coach and fan, so a crafted POST cannot self-assign organizer. Organizers are created by seeding or by an existing organizer.',
      'The password rule keeps min:8, adds max:255 to bound the hashing cost, and rejects common passwords through the NotCommonPassword rule object.',
      'Inside DB::transaction(), the name is split, the user is created with status active, the organization is resolved by slug, and the role row is created and attached with its pivot columns.',
      'If any insert fails, the whole block rolls back, so a half-created account with no role cannot survive an error.',
    ],
    why: 'Two failures are prevented at once: privilege escalation through a forged role field, and orphaned accounts where the user exists but the role pivot does not.',
    caveat: 'The organization is resolved by slug, so two different names that slug the same way (for example "San Antonio" and "san-antonio") share one organization row.',
    code: [
      {
        path: 'src/app/Http/Controllers/AuthController.php',
        label: 'register() · validation',
        lang: 'php',
        startLine: 100,
        explain: 'The rules array is the security surface: unique:users,email blocks duplicates, in:coach,fan blocks self-promotion, and max:255 on the password bounds work for an attacker. The second array only customizes the messages, so it does not change what is accepted.',
        code: String.raw`$data = $request->validate([
    'name' => ['required', 'string', 'min:2', 'max:120'],
    'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
    'password' => ['required', 'string', 'min:8', 'max:255', new NotCommonPassword],
    'role' => ['required', 'in:coach,fan'],
    'organization' => ['nullable', 'string', 'max:120'],
], [
    'name.required' => 'Enter your full name.',
    'name.min' => 'Enter your full name.',
    'email.required' => 'Enter a valid email address.',
    'email.email' => 'Enter a valid email address.',
    'email.unique' => 'An account with this email already exists.',
    'password.required' => 'Use at least 8 characters.',
    'password.min' => 'Use at least 8 characters.',
    'password.max' => 'Keep the password under 255 characters.',
    'role.in' => 'Choose a valid role.',
    'organization.max' => 'Keep the organization name under 120 characters.',
]);`,
      },
      {
        path: 'src/app/Http/Controllers/AuthController.php',
        label: 'register() · transaction',
        lang: 'php',
        startLine: 119,
        explain: 'Everything that must agree lives inside one closure: the user, the organization, the role, and the pivot row with its organization_id, scope, and timestamps. The closure returns the user, so the transaction result is the record the caller needs. Because the password is assigned as plaintext here, the model cast from the previous section is what hashes it.',
        code: String.raw`$user = DB::transaction(function () use ($data): User {
    [$firstName, $lastName] = $this->splitName($data['name']);

    $user = User::create([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $data['email'],
        'password' => $data['password'],
        'status' => 'active',
    ]);

    $organization = $this->resolveOrganization($data['organization'] ?? null);

    $role = Role::firstOrCreate(
        ['key' => $data['role']],
        ['name' => ucfirst($data['role'])],
    );

    $user->roles()->attach($role->id, [
        'organization_id' => $organization?->id,
        'scope' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
});`,
      },
    ],
  },

  {
    id: 'fingerprint',
    num: 5,
    group: 'Session guards',
    tag: 'Sessions',
    title: 'Session fingerprint',
    file: 'src/app/Http/Middleware/VerifySessionFingerprint.php',
    tldr: 'Bind the session to a hash of the browser user-agent; a mismatch means the cookie is being used somewhere else.',
    steps: [
      'No authenticated user: nothing to bind, so the request passes through.',
      'No stored fingerprint yet: store the current hash and continue. This is trust on first use and covers sessions created before the middleware existed.',
      'Stored hash differs from the current hash: log out, invalidate the session, and rotate the CSRF token.',
      'JSON and Livewire requests get a 401 with a message instead of a redirect, because a fetch() or Livewire update cannot usefully follow a redirect to HTML.',
      'Normal web requests redirect to login with an error message. The comparison itself uses hash_equals() so it runs in constant time.',
    ],
    why: 'A stolen session cookie replayed from a different browser or operating system is rejected on its first request instead of staying valid for the rest of the session lifetime.',
    caveat: 'The user-agent is attacker-controlled and changes on browser updates, so this is a detection layer rather than a cryptographic binding. HttpOnly, Secure, SameSite, and the idle timeout are the primary defences; this one raises the cost of a naive replay.',
    code: [
      {
        path: 'src/app/Http/Middleware/VerifySessionFingerprint.php',
        label: 'handle()',
        lang: 'php',
        startLine: 23,
        explain: 'Four exits, one per case: guest, first use, mismatch, match. The mismatch branch does the full teardown (guard, session, token) before choosing a response type, so the session is already dead before the client is told anything. hash_equals() compares the two digests in constant time, which matters because a byte-by-byte early exit would leak how much of the hash matched.',
        code: String.raw`$user = $request->user();

if ($user === null) {
    return $next($request);
}

$fingerprint = hash('sha256', (string) $request->userAgent());
$stored = $request->session()->get('auth.fingerprint');

if (! is_string($stored) || $stored === '') {
    $request->session()->put('auth.fingerprint', $fingerprint);

    return $next($request);
}

if (! hash_equals($stored, $fingerprint)) {
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    if ($request->expectsJson() || $request->is('livewire/*')) {
        return response()->json(['message' => 'Your session ended for security reasons.'], 401);
    }

    return redirect()
        ->route('login')
        ->withErrors(['email' => 'Your session ended for security reasons. Sign in again.']);
}

return $next($request);`,
      },
    ],
  },

  {
    id: 'idle-timeout',
    num: 6,
    group: 'Session guards',
    tag: 'Sessions',
    title: 'Idle-session timeout',
    file: 'src/app/Http/Middleware/EnforceSessionTimeout.php',
    tldr: 'If the session has been idle longer than the configured window, end it and send the user back to sign-in.',
    steps: [
      'The window is max(1, auth.session.idle_timeout) minutes converted to seconds, so a zero or negative config value cannot disable the guard.',
      'No numeric timestamp yet: seed it with now and continue, the same trust-on-first-use pattern as the fingerprint.',
      'Elapsed time greater than or equal to the window: log out, invalidate, rotate the token. JSON and Livewire get a 401.',
      'Otherwise the timestamp is refreshed, which makes the window sliding rather than absolute: any request resets the countdown.',
    ],
    why: 'An unattended device does not stay signed in forever, and a cookie copied from that device expires on its own without the user doing anything.',
    caveat: 'This is an idle limit, not an absolute lifetime. SESSION_LIFETIME still applies separately, and a script that polls any page keeps the idle window open indefinitely.',
    code: [
      {
        path: 'src/app/Http/Middleware/EnforceSessionTimeout.php',
        label: 'handle()',
        lang: 'php',
        startLine: 23,
        explain: 'The comparison is a plain integer subtraction against a window in seconds. The final put() is the sliding part: every request that survives pushes last_activity forward, so only a genuinely idle session reaches the logout branch. The teardown is identical to the fingerprint guard, which keeps a session death consistent no matter which guard caught it.',
        code: String.raw`$user = $request->user();

if ($user === null) {
    return $next($request);
}

$timeout = max(1, (int) config('auth.session.idle_timeout', 60)) * 60;
$lastActivity = $request->session()->get('auth.last_activity');

if (! is_numeric($lastActivity)) {
    $request->session()->put('auth.last_activity', time());

    return $next($request);
}

if (time() - (int) $lastActivity >= $timeout) {
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    if ($request->expectsJson() || $request->is('livewire/*')) {
        return response()->json(['message' => 'Your session expired due to inactivity.'], 401);
    }

    return redirect()
        ->route('login')
        ->withErrors(['email' => 'Your session expired due to inactivity. Sign in again.']);
}

$request->session()->put('auth.last_activity', time());

return $next($request);`,
      },
    ],
  },

  {
    id: 'inactive',
    num: 7,
    group: 'Session guards',
    tag: 'Sessions',
    title: 'Inactive-account enforcement',
    file: 'src/app/Http/Middleware/EnsureUserIsActive.php',
    tldr: 'A suspended account loses its open session on the very next request, not at the next sign-in.',
    steps: [
      'The middleware runs on every web request and reads the freshly loaded user from the guard.',
      'isActive() is false: log out, invalidate the session, and rotate the CSRF token.',
      'Livewire and JSON requests receive a 403 with a message; normal requests are redirected to login with an explanation.',
      'Active users pass through untouched, so the guard costs one string comparison per request.',
    ],
    why: 'Suspension becomes immediate. Without this, an organizer could suspend a coach and the coach would keep working for as long as the session lived.',
    caveat: 'This complements the status => active condition in Auth::attempt(); both paths are needed. The guard reads status from the database on each request, so it does cost a user lookup.',
    code: [
      {
        path: 'src/app/Http/Middleware/EnsureUserIsActive.php',
        label: 'handle()',
        lang: 'php',
        startLine: 23,
        explain: 'The whole guard is one condition: the user exists and isActive() is false. The teardown happens before the response-type split, so the session is destroyed whether the caller is a browser or a fetch(). A guest never enters the branch, which is why the middleware is safe to append globally instead of only to authenticated routes.',
        code: String.raw`$user = $request->user();

if ($user !== null && ! $user->isActive()) {
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    if ($request->expectsJson() || $request->is('livewire/*')) {
        return response()->json(['message' => 'Your account is inactive.'], 403);
    }

    return redirect()
        ->route('login')
        ->withErrors(['email' => 'Your account is inactive. Contact an organizer.']);
}

return $next($request);`,
      },
    ],
  },

  {
    id: 'middleware-order',
    num: 8,
    group: 'Session guards',
    tag: 'Sessions',
    title: 'Web middleware registration',
    file: 'src/bootstrap/app.php',
    tldr: 'Append the session guards after the session middleware, and prepend SecurityHeaders so it wraps the whole group.',
    steps: [
      'append: runs after Laravel session and CSRF middleware. That is what lets the guards read auth.fingerprint and auth.last_activity and call $request->user().',
      'throttle:web is first in the append list, so a flooding client is rejected before the guards touch the session.',
      'prepend: puts SecurityHeaders outside everything, so its headers land on redirects, 403s, and 500s as well as normal pages.',
      'There is one registration point, so a new route in the auth group inherits every guard without repeating anything.',
    ],
    why: 'Registering the protection once removes the possibility of a route quietly missing a guard, and it makes the effective order readable in a single file.',
    caveat: 'Middleware order is behavior. Reordering the append list changes which guard runs first, and moving a guard into prepend would run it before the session exists, so it would see no user and silently do nothing.',
    code: [
      {
        path: 'src/bootstrap/app.php',
        label: 'web middleware group',
        lang: 'php',
        startLine: 38,
        explain: 'This is the single line of truth for the web stack. Read the append array as an execution order and the prepend array as the wrapper around it. Nothing here filters by route, so even the public landing page passes through the headers and throttle.',
        code: String.raw`$middleware->web(
    append: [
        'throttle:web',
        EnsureUserIsActive::class,
        VerifySessionFingerprint::class,
        EnforceSessionTimeout::class,
    ],
    prepend: [
        SecurityHeaders::class,
    ],
);`,
      },
    ],
  },

  {
    id: 'role-helpers',
    num: 9,
    group: 'Authorization',
    tag: 'Authorization',
    title: 'Role and account-state helpers',
    file: 'src/app/Models/User.php',
    tldr: 'One place defines role membership and what "active" means, so middleware, components, and tests cannot drift apart.',
    steps: [
      'roles() is a belongsToMany through the user_roles pivot, mapping user_id to role_id.',
      'hasRole() asks the relation for one key and uses exists(), so the database answers yes or no instead of loading every role.',
      'hasAnyRole() takes a variadic list of keys and uses whereIn, which is one query for several allowed roles.',
      'isActive() normalizes status with strtolower(trim(...)) before comparing, so " Active " from an import or a seeder still counts.',
    ],
    why: 'RoleMiddleware, SecureComponent, the Livewire components, and the policy classes all call these helpers, so "organizer", "coach", and "active" mean exactly the same thing everywhere.',
    caveat: 'hasRole() ignores the pivot organization_id and scope columns, so it answers "has this role anywhere". A multi-tenant deployment would need a scoped variant before relying on it for per-organization access.',
    code: [
      {
        path: 'src/app/Models/User.php',
        label: 'roles() · hasRole() · hasAnyRole() · isActive()',
        lang: 'php',
        startLine: 57,
        explain: 'Each helper is intentionally tiny so the query stays in the database. exists() compiles to a SELECT EXISTS instead of hydrating models, and whereIn handles a whole allow-list in one round trip. isActive() is the only place that decides what the stored status string means, which is why the middleware can trust it.',
        code: String.raw`/**
 * @return BelongsToMany<Role, $this>
 */
public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
}

/**
 * Has role.
 *
 * @param  string  $key  The key.
 * @return bool The result.
 */
public function hasRole(string $key): bool
{
    return $this->roles()->where('key', $key)->exists();
}

/**
 * Has any role.
 *
 * @param  string...  $keys  The keys.
 * @return bool The result.
 */
public function hasAnyRole(string ...$keys): bool
{
    return $this->roles()->whereIn('key', $keys)->exists();
}

/**
 * Is active.
 *
 * @return bool The result.
 */
public function isActive(): bool
{
    return strtolower(trim((string) $this->status)) === 'active';
}`,
      },
    ],
  },

  {
    id: 'role-middleware',
    num: 10,
    group: 'Authorization',
    tag: 'Authorization',
    title: 'Role middleware',
    file: 'src/app/Http/Middleware/RoleMiddleware.php',
    tldr: 'Reject guests and inactive users, then accept only one of the roles the route asked for.',
    steps: [
      'No user, or an inactive user: abort(403) immediately.',
      'hasAnyRole(...$roles): the route parameters arrive as strings, so role:organizer or role:organizer,coach both work.',
      'Anything else: abort(403). There is no fall-through path.',
      'The 403 is deliberate. These routes live inside the auth group, so a guest is already redirected to login before this middleware runs.',
    ],
    why: 'A single reusable gate for pages such as payments, audit, submissions, and tournament creation, declared on the route instead of inside each controller.',
    caveat: 'This guards the page request only. A Livewire update is a separate POST to /livewire/update and never re-runs the route middleware, which is exactly why SecureComponent exists.',
    code: [
      {
        path: 'src/app/Http/Middleware/RoleMiddleware.php',
        label: 'handle()',
        lang: 'php',
        startLine: 23,
        explain: 'The variadic $roles parameter is filled from the route string after the colon, so the middleware itself has no hard-coded role names. Note the double check: a user who is inactive is rejected here as well, which keeps the fail-closed behavior even if a route is reached in an unusual order.',
        code: String.raw`$user = $request->user();

if (! $user || ! $user->isActive()) {
    abort(403);
}

if ($user->hasAnyRole(...$roles)) {
    return $next($request);
}

abort(403);`,
      },
    ],
  },

  {
    id: 'secure-component',
    num: 11,
    group: 'Authorization',
    tag: 'Authorization',
    title: 'Livewire authorization base',
    file: 'src/app/Livewire/SecureComponent.php',
    tldr: 'Every Livewire boot and hydrate re-checks authentication, active status, and the allowed roles.',
    steps: [
      'SecureComponent is abstract and declares allowedRoles(), so a component cannot extend it without stating who may use it.',
      'boot() runs on the initial render and again on every subsequent component request, which is what closes the /livewire/update gap.',
      'The check is a single aborted expression: user not null, isActive(), and hasAnyRole(...allowedRoles()).',
      'abort_unless() fails closed. A missing role, a suspended account, or a guest all produce the same 403.',
    ],
    why: 'A Livewire update is a POST to /livewire/update. The page route that originally rendered the component never runs again, so without this hook a forged update could call any public method on any component.',
    caveat: 'boot() is a gate, not a replacement for validating the action input. Each action still validates its own payload, and per-record ownership still belongs in the action or a policy.',
    code: [
      {
        path: 'src/app/Livewire/SecureComponent.php',
        label: 'SecureComponent',
        lang: 'php',
        startLine: 9,
        explain: 'The abstract method is the contract, and boot() is the enforcement. Because boot() runs on every component request, the authorization decision is repeated rather than inherited from the page load. The nested && short-circuits, so a guest never reaches isActive().',
        code: String.raw`abstract class SecureComponent extends Component
{
    /**
     * @return array<int, string>
     */
    abstract protected function allowedRoles(): array;

    /**
     * Boot.
     *
     * @return void Nothing.
     */
    public function boot(): void
    {
        $user = auth()->user();

        abort_unless(
            $user !== null
                && $user->isActive()
                && $user->hasAnyRole(...$this->allowedRoles()),
            403,
        );
    }
}`,
      },
    ],
  },

  {
    id: 'component-declarations',
    num: 12,
    group: 'Authorization',
    tag: 'Authorization',
    title: 'Component role declarations',
    file: 'src/app/Livewire/Organizer/PaymentsIndex.php',
    tldr: 'Each sensitive component declares its allow-list in one small method, which makes the whole authorization surface reviewable at a glance.',
    steps: [
      'PaymentsIndex allows organizer.',
      'MyTeam and TeamRegister allow coach.',
      'The same pattern covers submissions, audit, announcements creation, tournament creation, and game management.',
      'AnnouncementsIndex is intentionally shared across signed-in roles, and it filters by audience instead of by role.',
    ],
    why: 'The role policy for a screen sits next to the screen. Reviewing access control becomes grepping for allowedRoles() instead of tracing a route file and hoping nothing was missed.',
    caveat: 'The allow-list is role-only. Two organizers on different organizations both pass, so per-organization scoping has to live in the query or a policy, not in this method.',
    code: [
      {
        path: 'src/app/Livewire/Organizer/PaymentsIndex.php',
        label: 'allowedRoles()',
        lang: 'php',
        startLine: 33,
        explain: 'One role, no wildcards. Because the base class aborts unless one of these keys matches, the payment screen is closed to coaches and fans even if they forge a component request directly.',
        code: String.raw`protected function allowedRoles(): array
{
    return ['organizer'];
}`,
      },
      {
        path: 'src/app/Livewire/Coach/MyTeam.php',
        label: 'allowedRoles()',
        lang: 'php',
        startLine: 47,
        explain: 'The same contract on the coach side. The symmetry is the point: reading two of these tells you how every component declares its audience.',
        code: String.raw`protected function allowedRoles(): array
{
    return ['coach'];
}`,
      },
    ],
  },

  {
    id: 'session-registry',
    num: 13,
    group: 'Session control',
    tag: 'Sessions',
    title: 'Session registry service',
    file: 'src/app/Services/SessionRegistry.php',
    tldr: 'Read the sessions table, label each device, and let the user revoke every session except the current one.',
    steps: [
      'forUser() filters by user_id, orders by last_activity descending, and maps each row into device, ip, last_active, and current.',
      'deviceLabel() parses the user-agent with match(true) and checks Edge and Opera before Chrome, because both of their user-agents also contain "Chrome".',
      'current is a hash_equals() comparison between the stored session id and the current session id.',
      'revokeOthers() deletes every row for the user except the current one and returns the number of deleted rows.',
      'table() reads config(session.table), so the service follows a renamed sessions table instead of hard-coding it.',
    ],
    why: 'Users can spot and kill a session they do not recognize, and the same registry is reused on the password-change path to sign out other devices.',
    caveat: 'This only works because the session driver is database. Deleting rows in a file or cookie driver would not invalidate anything.',
    code: [
      {
        path: 'src/app/Services/SessionRegistry.php',
        label: 'forUser()',
        lang: 'php',
        startLine: 20,
        explain: 'The query returns raw rows, and the map turns them into the four fields the Blade view renders. The ip fallback avoids showing an empty cell, lastActiveLabel() converts a unix timestamp into "2 hours ago", and the id comparison uses hash_equals() because session ids are secrets that should not be compared with a short-circuiting operator.',
        code: String.raw`$currentId = (string) $request->session()->getId();

return DB::table($this->table())
    ->where('user_id', $user->id)
    ->orderByDesc('last_activity')
    ->get()
    ->map(fn (object $session): array => [
        'device' => $this->deviceLabel($session->user_agent),
        'ip' => is_string($session->ip_address) && $session->ip_address !== ''
            ? $session->ip_address
            : 'Unknown network',
        'last_active' => $this->lastActiveLabel((int) $session->last_activity),
        'current' => $currentId !== '' && hash_equals((string) $session->id, $currentId),
    ]);`,
      },
      {
        path: 'src/app/Services/SessionRegistry.php',
        label: 'revokeOthers()',
        lang: 'php',
        startLine: 45,
        explain: 'The empty-id guard matters: if the current session id could not be read, "delete everything except the current one" would delete everything, including the user who is asking. The deletion is scoped to user_id as well, so one account can never remove another account\'s sessions.',
        code: String.raw`$currentId = (string) $request->session()->getId();

if ($currentId === '') {
    return 0;
}

return DB::table($this->table())
    ->where('user_id', $user->id)
    ->where('id', '!=', $currentId)
    ->delete();`,
      },
    ],
  },

  {
    id: 'profile-endpoints',
    num: 14,
    group: 'Session control',
    tag: 'Sessions',
    title: 'Profile session endpoints',
    file: 'src/app/Http/Controllers/ProfileController.php',
    tldr: 'The profile page receives the session list, and the revoke action reports how many sessions were signed out.',
    steps: [
      'show() injects SessionRegistry and passes activeSessions to the view, so the controller stays a thin adapter.',
      'destroyOtherSessions() calls revokeOthers() and redirects back to the profile page.',
      'The flash message adapts to zero, one, or many sessions using match(), so the user gets an accurate result instead of a generic "done".',
    ],
    why: 'A small UX detail that makes the security feature trustworthy: the user can see the count and confirm that the action actually did something.',
    caveat: 'destroyOtherSessions() relies on the current session id being readable. If the session has already been invalidated, revokeOthers() returns 0 and the message says so instead of deleting everything.',
    code: [
      {
        path: 'src/app/Http/Controllers/ProfileController.php',
        label: 'show()',
        lang: 'php',
        startLine: 27,
        explain: 'Dependency injection gives the controller the registry and the current request. The view receives a ready-made collection, so the Blade template does no querying and no user-agent parsing of its own.',
        code: String.raw`public function show(Request $request, SessionRegistry $sessions): View
{
    return view('pages.profile', [
        'activeSessions' => $sessions->forUser($request->user(), $request),
    ]);
}`,
      },
      {
        path: 'src/app/Http/Controllers/ProfileController.php',
        label: 'destroyOtherSessions()',
        lang: 'php',
        startLine: 54,
        explain: 'The count comes back from the delete statement, which makes the message honest. match() with a default arm covers every number above one, so the wording never falls out of date when several sessions were open.',
        code: String.raw`$count = $sessions->revokeOthers($request->user(), $request);

return redirect()
    ->route('profile')
    ->with('status', match ($count) {
        0 => 'No other sessions were signed in.',
        1 => 'Signed out 1 other session.',
        default => "Signed out {$count} other sessions.",
    });`,
      },
    ],
  },

  {
    id: 'csrf-revocation',
    num: 15,
    group: 'Session control',
    tag: 'Sessions',
    title: 'CSRF-protected revocation',
    file: 'src/resources/views/pages/profile.blade.php',
    tldr: 'A POST form carrying a CSRF token and a DELETE method override, inside the authenticated route group.',
    steps: [
      'The route is DELETE /profile/sessions, declared inside middleware(auth), so a guest is redirected before the controller runs.',
      'Blade emits @csrf for the token and @method(DELETE) for the verb override, because HTML forms can only send GET and POST.',
      'The button renders only when other sessions exist, so the form is never offered when there is nothing to revoke.',
      'The form posts to route(profile.sessions.destroyOthers), so the URL is generated from the named route rather than typed by hand.',
    ],
    why: 'A cross-site page cannot read the token, so it cannot forge the request, and SameSite=lax adds a second layer by keeping the session cookie off most cross-site POSTs.',
    caveat: 'SameSite=lax is a second layer, not a replacement for the token. A browser that ignores the attribute, or a same-site injection point, would still need the CSRF check.',
    code: [
      {
        path: 'src/routes/web.php',
        label: 'profile session route',
        lang: 'php',
        startLine: 77,
        explain: 'The verb is DELETE and the route sits inside the auth group, so the request must be authenticated and must use the intended HTTP method. The name is what the Blade form and the controller redirect both reference.',
        code: String.raw`Route::delete('/profile/sessions', [ProfileController::class, 'destroyOtherSessions'])->name('profile.sessions.destroyOthers');`,
      },
      {
        path: 'src/resources/views/pages/profile.blade.php',
        label: 'revoke form',
        lang: 'blade',
        startLine: 128,
        explain: 'The @if keeps the form out of the DOM when there is nothing to revoke. Inside it, @csrf renders the hidden token field and @method(DELETE) renders a hidden _method field that Laravel reads to treat the POST as a DELETE. The button itself is a plain submit, so it works without JavaScript.',
        code: String.raw`@if ($otherSessions->isNotEmpty())
    <form method="POST" action="{{ route('profile.sessions.destroyOthers') }}" class="hf-mt-3">
        @csrf
        @method('DELETE')
        <button type="submit" class="hf-btn hf-btn--sm" data-testid="profile-revoke-sessions">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sign out other sessions
        </button>
    </form>
@endif`,
      },
    ],
  },

  {
    id: 'headers',
    num: 16,
    group: 'Browser & data safety',
    tag: 'Headers',
    title: 'Security headers and CSP',
    file: 'src/app/Http/Middleware/SecurityHeaders.php',
    tldr: 'Every response gets browser hardening headers, and authenticated responses are marked as not cacheable.',
    steps: [
      'X-Content-Type-Options: nosniff stops the browser from guessing a MIME type. X-Frame-Options: DENY plus frame-ancestors none stops framing.',
      'Referrer-Policy trims referrer leakage to the origin, and Permissions-Policy turns off camera, microphone, and geolocation.',
      'The CSP restricts scripts, styles, images, fonts, media, workers, and connections, with localhost dev origins added outside production.',
      'Authenticated responses get Cache-Control: no-store, private so a shared cache or the back button cannot replay a signed-in page.',
      'HSTS is added for HTTPS requests or in production, which keeps the browser on HTTPS for a year including subdomains.',
    ],
    why: 'It reduces the impact of XSS, clickjacking, MIME confusion, and protocol downgrade, and it is the one layer that still applies to error pages and redirects.',
    caveat: 'script-src allows unsafe-inline and unsafe-eval because Livewire and Alpine need them, so the CSP is not the primary XSS defence. Blade escaping is. The dev origins are only added when the app is not in production.',
    code: [
      {
        path: 'src/app/Http/Middleware/SecurityHeaders.php',
        label: 'handle()',
        lang: 'php',
        startLine: 22,
        explain: 'The first line is the whole trick: call the rest of the stack, then decorate the response that comes back. That is why the headers appear on redirects and error pages too. The user check happens after $next() as well, so it sees the authenticated user the guards established.',
        code: String.raw`$response = $next($request);

$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('X-Frame-Options', 'DENY');
$response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
$response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
$response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

if ($request->user() !== null) {
    $response->headers->set('Cache-Control', 'no-store, private');
}

if ($request->isSecure() || app()->isProduction()) {
    $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
}

return $response;`,
      },
      {
        path: 'src/app/Http/Middleware/SecurityHeaders.php',
        label: 'contentSecurityPolicy()',
        lang: 'php',
        startLine: 48,
        explain: 'Each directive is one policy decision: default-src is the fallback, object-src none blocks plugins, form-action self stops the page posting elsewhere, and frame-ancestors none is the modern equivalent of X-Frame-Options. $devOrigins is empty in production, so the Vite dev server origins never reach a live response.',
        code: String.raw`$devOrigins = app()->isProduction()
    ? ''
    : ' http://localhost:5173 http://127.0.0.1:5173 ws://localhost:5173 ws://127.0.0.1:5173';

return implode('; ', [
    "default-src 'self'",
    "base-uri 'self'",
    "object-src 'none'",
    "frame-ancestors 'none'",
    "form-action 'self'",
    "script-src 'self' 'unsafe-inline' 'unsafe-eval'".$devOrigins,
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: blob:",
    "font-src 'self' data:",
    "media-src 'self'",
    "worker-src 'self' blob:",
    "connect-src 'self'".$devOrigins,
]);`,
      },
    ],
  },

  {
    id: 'blade-escaping',
    num: 17,
    group: 'Browser & data safety',
    tag: 'Input/Output',
    title: 'Escaped Blade output',
    file: 'src/resources/views/pages/profile.blade.php',
    tldr: '{{ $value }} compiles to e($value), which HTML-escapes the value before it reaches the page.',
    steps: [
      'Blade escapes the ampersand, angle brackets, and quotes by default. {!! !!} is the opt-out, and it is not used here.',
      'The test creates a user whose first name is literally <script>alert(1)</script>.',
      'The profile page must contain the escaped entities and must not contain the raw tag, so the assertion checks both directions.',
      'Escaping happens at render time, which means the same name is still plain text in the database and in the API.',
    ],
    why: 'User-controlled names, team names, and search terms cannot inject markup, and the test locks that behavior in place so a later refactor cannot silently switch to raw output.',
    caveat: 'Escaping is context-specific. HTML-escaping is correct for text nodes and quoted attributes, but not for a URL, a CSS value, or JavaScript. Those need their own encoding.',
    code: [
      {
        path: 'src/resources/views/pages/profile.blade.php',
        label: 'session list item',
        lang: 'blade',
        startLine: 116,
        explain: 'Every interpolation in this block is the escaped form. Device and IP both come from the session table, which an attacker can influence through the user-agent header, and they are rendered as text nodes, where e() is the right encoding.',
        code: String.raw`<li>
    <div>
        <strong>{{ $session['device'] }}</strong>
        <span class="hf-small hf-muted">{{ $session['ip'] }} · {{ $session['last_active'] }}</span>
    </div>
    @if ($session['current'])
        <span class="hf-chip hf-chip--primary">This device</span>
    @endif
</li>`,
      },
      {
        path: 'src/tests/Feature/Security/InputOutputSecurityTest.php',
        label: 'escapes user-controlled names',
        lang: 'php',
        startLine: 16,
        explain: 'The two assertions are complementary: assertDontSee with false checks the raw string is absent, and assertSee with false checks the escaped entities are present. Testing both is what proves the value was escaped rather than simply dropped from the page.',
        code: String.raw`$user = User::factory()->coach()->create([
    'first_name' => '<script>alert(1)</script>',
    'last_name' => 'Tester',
]);

$this->actingAs($user)
    ->get('/profile')
    ->assertOk()
    ->assertDontSee('<script>alert(1)</script>', false)
    ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);`,
      },
    ],
  },

  {
    id: 'parameterized-query',
    num: 18,
    group: 'Browser & data safety',
    tag: 'Input/Output',
    title: 'Parameterized duplicate-name query',
    file: 'src/app/Livewire/Coach/TeamRegister.php',
    tldr: 'The team name is passed as a bound parameter, never concatenated into the SQL string.',
    steps: [
      'whereRaw(LOWER(name) = ?, [mb_strtolower(trim($this->name))]) keeps the value out of the SQL text. The ? is the only variable part.',
      'whereHas scopes the check to registrations for the same tournament, so the same team name can exist in two different events.',
      'exists() turns the query into a yes/no check, so no rows are hydrated.',
      'The codebase rule behind this: raw SQL may contain constants, cast integers, or ? placeholders only.',
    ],
    why: 'A name such as \' OR 1=1 -- is compared as literal text, so it cannot change the shape of the query or return other teams.',
    caveat: 'The existing SQL-metacharacter test targets the audit-log search, which filters in PHP. This query has no direct test, so the claim is covered by code review rather than by an assertion.',
    code: [
      {
        path: 'src/app/Livewire/Coach/TeamRegister.php',
        label: 'save() · duplicate check',
        lang: 'php',
        startLine: 437,
        explain: 'The LOWER() call is in the SQL string because it is a constant function name, while the name itself is in the bindings array. mb_strtolower() normalizes the input the same way the database function does, so the match is case-insensitive in both directions. The whereHas then keeps the scope to one tournament.',
        code: String.raw`$duplicateName = Team::query()
    ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($this->name))])
    ->whereHas('registrations', fn ($query) => $query->where('tournament_id', $tournament->id))
    ->exists();

if ($duplicateName) {
    $this->step = 0;
    $this->addError('name', 'A team with that name is already registered.');

    return;
}`,
      },
    ],
  },

  {
    id: 'session-config',
    num: 19,
    group: 'Configuration',
    tag: 'Config',
    title: 'Hardened session configuration',
    file: 'src/config/session.php',
    tldr: 'Encrypt the payload, force HttpOnly, mark the cookie Secure in production, and keep SameSite=lax with JSON serialization.',
    steps: [
      'encrypt defaults to true, so the session payload is encrypted before it is written to storage.',
      'secure defaults to true when APP_ENV is production, so the cookie is only sent over HTTPS there. It can still be overridden by SESSION_SECURE_COOKIE.',
      'http_only is hard-coded true, so JavaScript cannot read the cookie even during an XSS attempt.',
      'same_site defaults to lax, which keeps the cookie off most cross-site POSTs while still allowing normal top-level navigation.',
      'serialization is json, which avoids PHP object gadget chains if the APP_KEY ever leaks.',
    ],
    why: 'Cookie theft, session tampering, and CSRF all get harder, and the defaults are safe without any extra environment variables.',
    caveat: 'The driver defaults to database, which is what makes SessionRegistry work. A file or cookie driver would leave the registry deleting rows that do not exist.',
    code: [
      {
        path: 'src/config/session.php',
        label: 'encrypt',
        lang: 'php',
        startLine: 52,
        explain: 'The second argument to env() is the default, so an environment with no SESSION_ENCRYPT set still encrypts. Decryption is transparent to the application, so nothing else has to change.',
        code: String.raw`'encrypt' => env('SESSION_ENCRYPT', true),`,
      },
      {
        path: 'src/config/session.php',
        label: 'secure',
        lang: 'php',
        startLine: 174,
        explain: 'The nested env() reads APP_ENV with production as the fallback, so an unset environment is treated as production and gets Secure cookies. That is a fail-safe default: the strict case is the one you get by accident, not the one you have to remember.',
        code: String.raw`'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV', 'production') === 'production'),`,
      },
      {
        path: 'src/config/session.php',
        label: 'http_only',
        lang: 'php',
        startLine: 187,
        explain: 'This one is not configurable. There is no env() call, so no deployment can accidentally expose the session cookie to document.cookie.',
        code: String.raw`'http_only' => true,`,
      },
      {
        path: 'src/config/session.php',
        label: 'same_site',
        lang: 'php',
        startLine: 204,
        explain: 'lax is the balance point: the cookie travels on top-level GET navigation, which keeps normal links working, but not on cross-site POSTs, which is where CSRF lives.',
        code: String.raw`'same_site' => env('SESSION_SAME_SITE', 'lax'),`,
      },
      {
        path: 'src/config/session.php',
        label: 'serialization',
        lang: 'php',
        startLine: 233,
        explain: 'JSON can only represent scalars, arrays, and plain objects, so a crafted session value cannot instantiate a PHP class. That removes the gadget-chain class of attack that php serialization enables.',
        code: String.raw`'serialization' => 'json',`,
      },
    ],
  },

  {
    id: 'auth-config',
    num: 20,
    group: 'Configuration',
    tag: 'Config',
    title: 'Configurable auth limits',
    file: 'src/config/auth.php',
    tldr: 'The throttle window and the idle timeout come from config, and both call sites clamp them with max(1, ...).',
    steps: [
      'auth.login.max_attempts and auth.login.decay_seconds are read from env with defaults of 5 and 60.',
      'auth.session.idle_timeout is read from env with a default of 60 minutes.',
      'The controller and the middleware both apply max(1, (int) ...) at the call site, so a zero or a negative value cannot disable the protection.',
      'Changing these values needs a config cache refresh to take effect on a cached deployment.',
    ],
    why: 'Operations can tune the strictness per environment without a code change, and a bad value degrades to the safest interpretation instead of turning the check off.',
    caveat: 'Casting with (int) means a typo becomes 0, which the clamp then turns into 1: the strictest possible setting, not an error. That is safe, but it can look like a broken login if you expected 60.',
    code: [
      {
        path: 'src/config/auth.php',
        label: 'login limits',
        lang: 'php',
        startLine: 130,
        explain: 'Two numbers, both cast to int at the config boundary so the call site never has to reason about strings. The defaults match what the controller uses when the keys are missing entirely.',
        code: String.raw`'login' => [
    'max_attempts' => (int) env('AUTH_LOGIN_MAX_ATTEMPTS', 5),
    'decay_seconds' => (int) env('AUTH_LOGIN_DECAY_SECONDS', 60),
],`,
      },
      {
        path: 'src/config/auth.php',
        label: 'session idle timeout',
        lang: 'php',
        startLine: 146,
        explain: 'A value in minutes, not seconds. EnforceSessionTimeout multiplies it by 60 and clamps it, so this file stays readable while the middleware works in the unit it compares against.',
        code: String.raw`'session' => [
    'idle_timeout' => (int) env('AUTH_SESSION_IDLE_TIMEOUT', 60),
],`,
      },
    ],
  },

  {
    id: 'demo-gating',
    num: 21,
    group: 'Configuration',
    tag: 'Config',
    title: 'Production demo-account gating',
    file: 'src/config/app.php',
    tldr: 'The quick-fill credential buttons are hidden unless demo accounts are explicitly enabled.',
    steps: [
      'app.demo_accounts defaults to true outside production and false in production.',
      'The login view wraps the whole quick-fill block in @if (config(app.demo_accounts)), so nothing is rendered when it is off.',
      'The buttons are Alpine x-on:click handlers that only fill the form fields, so they do not bypass the normal login route or its limiter.',
      'DEMO_ACCOUNTS can force the block on or off regardless of the environment.',
    ],
    why: 'Seeded credentials are not advertised on a live deployment, which removes an easy starting point for an attacker.',
    caveat: 'This hides the UI; it does not disable the accounts. If the seeder runs in production, the credentials still work, so the seeder and the flag have to be managed together.',
    code: [
      {
        path: 'src/config/app.php',
        label: 'demo_accounts',
        lang: 'php',
        startLine: 44,
        explain: 'The default expression is inverted on purpose: the safe answer is "off", and you get it by leaving the environment unset. Only an explicit DEMO_ACCOUNTS=true or a non-production APP_ENV turns it on.',
        code: String.raw`'demo_accounts' => (bool) env('DEMO_ACCOUNTS', env('APP_ENV', 'production') !== 'production'),`,
      },
      {
        path: 'src/resources/views/auth/login.blade.php',
        label: 'quick-fill accounts',
        lang: 'blade',
        startLine: 141,
        explain: 'The @if is the gate, so the HTML for the buttons is never sent when the flag is false. Each button only sets the email and password on the Alpine component; the form still posts to the login route and still hits the limiter. The coach and fan buttons follow the organizer pattern with their own addresses.',
        code: String.raw`@if (config('app.demo_accounts'))
    <p class="hf-demo-title hf-mt-3">Quick-fill accounts</p>
    <div class="hf-quick-accounts">
        <button
            type="button"
            x-on:click="email = 'organizer@hoopflow.ph'; password = 'password'"
            data-testid="quick-login-organizer"
        >
            <i class="bi bi-kanban" aria-hidden="true"></i>
            <strong>Organizer</strong>
            <span>Tournament ops</span>
        </button>
        <button
            type="button"
            x-on:click="email = 'coach.marcus@hoopflow.ph'; password = 'password'"
            data-testid="quick-login-coach"
        >
            <i class="bi bi-people" aria-hidden="true"></i>
            <strong>Coach</strong>
            <span>San Antonio Vipers</span>
        </button>
        <button
            type="button"
            x-on:click="email = 'fan.juan@hoopflow.ph'; password = 'password'"
            data-testid="quick-login-fan"
        >
            <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
            <strong>Fan</strong>
            <span>Public view</span>
        </button>
    </div>
@endif`,
      },
    ],
  },
];
