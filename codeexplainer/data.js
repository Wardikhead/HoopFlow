/* HoopFlow security explainer — shared content and composition.
   Section bodies live in sections-security.js and sections-features.js.
   Every snippet is an exact excerpt from the file named in its `path`. */

const SECTIONS = [...SECURITY_SECTIONS, ...FEATURE_SECTIONS];
const FEATURES = FEATURE_SECTIONS;

/* Illustrative insecure counterparts live in sections-naive.js. They are shown
   only on request and are never presented as source. */
for (const section of SECTIONS) {
  if (NAIVE_SNIPPETS[section.id]) section.naive = NAIVE_SNIPPETS[section.id];
}

const PIPELINE = [
  { id: 'browser', label: 'Browser', detail: 'Sends the session cookie with every request.' },
  { id: 'headers', label: 'SecurityHeaders', detail: 'Prepended: wraps the whole stack and sets headers on the way out.' },
  { id: 'session', label: 'Session + CSRF', detail: 'Laravel starts the session, decrypts cookies, and checks the token.' },
  { id: 'throttle', label: 'throttle:web', detail: 'The named web rate limiter from config/rate_limits.php, keyed per user or per IP.' },
  { id: 'active', label: 'EnsureUserIsActive', detail: 'Suspended account? Log out immediately.' },
  { id: 'fingerprint', label: 'VerifySessionFingerprint', detail: 'User-agent hash mismatch? Treat the cookie as stolen.' },
  { id: 'timeout', label: 'EnforceSessionTimeout', detail: 'Idle longer than the window? End the session.' },
  { id: 'route', label: 'auth + role', detail: 'Route-level gate: signed in, then the required role.' },
  { id: 'livewire', label: 'SecureComponent', detail: 'Runs again on every Livewire boot and hydrate.' },
  { id: 'controller', label: 'Controller / Service', detail: 'Business logic, bound SQL parameters, transactions.' },
  { id: 'blade', label: 'Blade output', detail: '{{ }} escapes values before they reach the HTML.' },
];

const ATTACKS = [
  {
    id: 'brute',
    label: 'Brute force',
    nodes: ['throttle', 'controller'],
    story: 'The login limiter checks the email + IP key before Auth::attempt. Five failures in the window stop the attempt, and every failure returns the same generic message. The broader throttle:web limit catches spraying across many accounts.',
  },
  {
    id: 'cookie',
    label: 'Stolen cookie',
    nodes: ['session', 'fingerprint', 'timeout'],
    story: 'The cookie is HttpOnly, Secure in production, and SameSite=lax, so script and cross-site reads are limited. If it is replayed from another browser, the user-agent hash no longer matches and the session is destroyed. An idle copy also expires.',
  },
  {
    id: 'fixation',
    label: 'Session fixation',
    nodes: ['session', 'route'],
    story: 'The attacker plants a known session ID before sign-in. On success, session()->regenerate() issues a new ID, so the planted value is useless. The old CSRF token is rotated as well.',
  },
  {
    id: 'livewire',
    label: 'Forged Livewire call',
    nodes: ['route', 'livewire'],
    story: 'The page route has role:organizer, but a Livewire update is a POST to /livewire/update and does not re-run that route. SecureComponent::boot() runs on every component request and checks user, active status, and allowedRoles() again.',
  },
  {
    id: 'sqli',
    label: 'SQL injection',
    nodes: ['controller'],
    story: 'The duplicate-name check passes the coach name as a bound ? parameter. The database compares it as a value, not as SQL. A payload such as \' OR 1=1 -- is just a strange team name.',
  },
  {
    id: 'xss',
    label: 'XSS payload',
    nodes: ['blade', 'headers'],
    story: 'Blade escapes the value with e() before it reaches the HTML, so a name containing <script> renders as text. The CSP is a second layer: it limits where scripts can load from, though it allows inline/eval for Livewire and Alpine.',
  },
  {
    id: 'csrf',
    label: 'CSRF',
    nodes: ['session', 'route'],
    story: 'The revoke form carries a CSRF token and a DELETE method override. A cross-site page cannot read the token, and SameSite=lax keeps the session cookie off most cross-site POSTs. The route is also inside the auth group.',
  },
  {
    id: 'suspended',
    label: 'Suspended account',
    nodes: ['active', 'controller'],
    story: 'At sign-in, Auth::attempt includes status = active, so a suspended account fails the same credential check as a wrong password. If the account is suspended while already signed in, EnsureUserIsActive ends the session on the next request.',
  },
];

const QUIZ = [
  {
    q: 'What does session()->regenerate() prevent?',
    options: ['Session fixation', 'SQL injection', 'Clickjacking', 'Password reuse'],
    answer: 0,
    why: 'It issues a new session ID after sign-in, so a session ID planted before login cannot become authenticated.',
  },
  {
    q: 'Which two values form the login throttle key?',
    options: ['Username and password', 'Normalized email and client IP', 'Session ID and user agent', 'Role and organization'],
    answer: 1,
    why: 'throttleKey() uses Str::lower(email) + "|" + $request->ip(), transliterated. Case and spaces cannot create a new bucket.',
  },
  {
    q: 'Why does Auth::attempt include status => active?',
    options: ['To speed up the query', 'To make suspended accounts fail the same credential check', 'To create the user if missing', 'To skip the password hash'],
    answer: 1,
    why: 'A suspended account and a wrong password produce the same generic error, so the response does not reveal account state.',
  },
  {
    q: 'What does the hashed cast do on assignment?',
    options: ['Encrypts and decrypts the password', 'Hashes the value once and avoids double-hashing', 'Stores the password in plain text', 'Compresses the password'],
    answer: 1,
    why: 'Laravel hashes on set and detects an already-hashed value, so assigning a hash again does not hash it twice.',
  },
  {
    q: 'Why use hash_equals() instead of === for the fingerprint?',
    options: ['It is faster', 'It compares in constant time', 'It works only on integers', 'It ignores case'],
    answer: 1,
    why: 'Constant-time comparison avoids leaking how many leading characters matched.',
  },
  {
    q: 'Why does SecureComponent::boot() exist if routes already have role: middleware?',
    options: ['To cache the user', 'Because Livewire updates do not re-run the page route', 'To replace CSRF', 'To load CSS'],
    answer: 1,
    why: 'A Livewire update is a POST to /livewire/update. boot() re-checks authentication, active status, and role on every component request.',
  },
  {
    q: 'Which session driver makes SessionRegistry revocation work?',
    options: ['file', 'cookie', 'database', 'array'],
    answer: 2,
    why: 'SessionRegistry reads and deletes rows in the sessions table. With the file driver, deleting a row would not invalidate the session.',
  },
  {
    q: 'Why is whereRaw(LOWER(name) = ?, [...]) safe?',
    options: ['The name is escaped with htmlspecialchars', 'The name is a bound parameter, not SQL text', 'The query is cached', 'LOWER() removes dangerous characters'],
    answer: 1,
    why: 'The ? placeholder binds the value. The database treats it as data, even if it contains quotes or SQL keywords.',
  },
  {
    q: 'Why does the CSP allow unsafe-inline and unsafe-eval?',
    options: ['To make the page faster', 'Because Livewire 4 and Alpine need them', 'To allow any CDN', 'To disable HTTPS'],
    answer: 1,
    why: 'Livewire and Alpine rely on inline scripts and eval. Blade escaping is still the primary XSS defense.',
  },
  {
    q: 'What does app.demo_accounts default to in production?',
    options: ['true', 'false', 'It is not defined', 'It depends on the role'],
    answer: 1,
    why: 'The default is true outside production and false in production, so the quick-fill buttons are hidden on a live deployment.',
  },
];

const DOC_FIXES = [
  'Two figures are labelled "Figure 1.0" and there is no Figure 2.0. Renumber the sequence.',
  'The Figure 17 paragraph is a copy of Figure 18. It describes the whereRaw duplicate-name check, not escaped Blade output.',
  'Stray Word layout numbers (right41275000, 28343431736800, and similar) leaked into the text. Delete them.',
  'The Figure 9 caption contains an image anchor number before the title.',
  'Fix the grammar in Figure 19: "The figure above is the Session payloads are encrypted..."',
  'The Figure 18 caption says the SQL metacharacter test covers the duplicate-name query. That test actually targets the audit-log search, which filters in PHP. Reword it or add a direct test.',
];
