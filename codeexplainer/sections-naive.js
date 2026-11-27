/* HoopFlow security explainer — illustrative naive versions.
   IMPORTANT: nothing in this file exists in src/. These snippets are written
   purely to show what each guard replaces, and every card that renders one is
   labelled "Illustrative · not in src/". */

const NAIVE_SNIPPETS = {
  'login-throttle': {
    label: 'AuthController@login with no limiter',
    explain: 'Without the limiter every failure is a free guess, and the loop is bounded only by the network. Dropping status => active from the credentials also lets a suspended account sign in.',
    code: String.raw`$credentials = $request->validate([
    'email' => ['required', 'email'],
    'password' => ['required'],
]);

if (Auth::attempt($credentials, $request->boolean('remember'))) {
    return redirect()->intended(route('dashboard'));
}

return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');`,
  },

  hashing: {
    label: 'User model without the hashed cast',
    explain: 'The column keeps whatever the form sent, so one database dump or log line exposes every password, and two users who chose the same password are visibly identical.',
    code: String.raw`protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
    ];
}

$user = User::create([
    'password' => $data['password'],
]);`,
  },

  fingerprint: {
    label: 'Session without the user-agent binding',
    explain: 'A copied cookie works from any browser until the session lifetime ends, because nothing ties the session to the client that created it.',
    code: String.raw`public function handle($request, Closure $next)
{
    return $next($request);
}`,
  },

  'idle-timeout': {
    label: 'Session without the idle check',
    explain: 'An unattended laptop or a stolen cookie stays signed in for the full SESSION_LIFETIME, no matter how long it sits unused.',
    code: String.raw`public function handle($request, Closure $next)
{
    return $next($request);
}`,
  },

  inactive: {
    label: 'Web group with no active-account guard',
    explain: 'Suspension only affects the next sign-in. A coach suspended mid-season keeps working from the session already in their browser.',
    code: String.raw`$middleware->web(
    append: [
        'throttle:web',
        VerifySessionFingerprint::class,
        EnforceSessionTimeout::class,
    ],
);`,
  },

  'role-middleware': {
    label: 'A role-gated page with only auth',
    explain: 'auth proves someone is signed in, not who. Any fan with an account reaches the organizer screen, and the Livewire component is the only thing left in the way.',
    code: String.raw`Route::get('/payments', PaymentsIndex::class)
    ->name('payments')
    ->middleware('auth');`,
  },

  'secure-component': {
    label: 'A Livewire component without boot()',
    explain: 'The page route still has role:organizer, but /livewire/update never re-runs it, so a crafted component request reaches the public methods with no role check at all.',
    code: String.raw`class PaymentsIndex extends Component
{
    public function render(): View
    {
        return view('livewire.organizer.payments-index');
    }
}`,
  },

  'csrf-revocation': {
    label: 'The same revoke form without @csrf',
    explain: 'Any page that can POST here can sign the user out of every other device, and a cross-site form can do it without reading a byte of the page.',
    code: String.raw`<form method="POST" action="{{ route('profile.sessions.destroyOthers') }}">
    @method('DELETE')
    <button type="submit" class="hf-btn hf-btn--sm">Sign out other sessions</button>
</form>`,
  },

  headers: {
    label: 'A response with no hardening headers',
    explain: 'The browser is told nothing about framing, MIME sniffing, referrers, or caching, so clickjacking and cached authenticated pages both become reachable.',
    code: String.raw`public function handle(Request $request, Closure $next): Response
{
    return $next($request);
}`,
  },

  'blade-escaping': {
    label: 'The same values rendered raw',
    explain: 'A user-agent containing <script> now executes in the page that lists sessions, with the victim\'s session and CSRF token in reach.',
    code: String.raw`<li>
    <div>
        <strong>{!! $session['device'] !!}</strong>
        <span>{!! $session['ip'] !!}</span>
    </div>
</li>`,
  },

  'parameterized-query': {
    label: 'The duplicate check with string interpolation',
    explain: 'A team name of q\' OR 1=1 -- closes the quote and rewrites the predicate, so the check answers on command and the rest of the query becomes attacker-controlled text.',
    code: String.raw`$duplicateName = Team::query()
    ->whereRaw("LOWER(name) = '".mb_strtolower(trim($this->name))."'")
    ->whereHas('registrations', fn ($query) => $query->where('tournament_id', $tournament->id))
    ->exists();`,
  },

  'session-config': {
    label: 'Cookie flags left at the permissive defaults',
    explain: 'Script can read the cookie, it travels over plain HTTP, and it is attached to cross-site requests, which hands an XSS or a network attacker a ready-made session.',
    code: String.raw`'secure' => false,
'http_only' => false,
'same_site' => null,
'encrypt' => false,`,
  },

  'demo-gating': {
    label: 'Seeded credentials always on the sign-in screen',
    explain: 'Every visitor to the production login page is handed working organizer credentials, and the only thing between them and the tournament data is remembering to delete the seeder.',
    code: String.raw`<p class="hf-demo-title hf-mt-3">Quick-fill accounts</p>
<div class="hf-quick-accounts">
    <button type="button" x-on:click="email = 'organizer@hoopflow.ph'; password = 'password'">
        <strong>Organizer</strong>
        <span>Tournament ops</span>
    </button>
</div>`,
  },
};
