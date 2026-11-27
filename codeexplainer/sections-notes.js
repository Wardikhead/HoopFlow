/* Short inline notes for the code snippets.
   Keys are `${section.id}:${blockIndex}`; each value maps a 0-based line offset
   inside that block's excerpt to a one-line note. Offsets match the rendered
   gutter, so a note on offset 4 sits on source line startLine + 4. */

const SNIPPET_NOTES = {
  'pipeline:0': {
    1: 'short route alias for role checks',
    9: 'throttle runs before any guard work',
    10: 'suspended accounts are stopped first',
    11: 'user-agent fingerprint check',
    12: 'idle timeout runs last',
    15: 'prepended: wraps the whole stack',
  },

  'login-throttle:0': {
    1: 'normalize + lowercase before validation',
    4: 'check the shape before touching the DB',
    13: 'one bucket per email + IP',
    14: 'limit comes from config, never 0',
    17: 'blocked clients never reach hashing',
    21: 'tell the user how long to wait',
    25: '"status" joins the credential check',
    26: 'reset the counter on success',
    27: 'new session ID defeats fixation',
    28: 'record fingerprint + activity time',
    33: 'only failures count against the limit',
    35: 'same generic message for every failure',
  },

  'login-throttle:1': {
    1: 'key = email | IP',
  },

  'logout:0': {
    0: 'drop the authenticated user',
    2: 'destroy the session data',
    3: 'fresh CSRF token for the next visitor',
  },

  'logout:1': {
    1: 'sha256 of the user agent',
    2: 'timestamp the idle check reads',
  },

  'hashing:0': {
    4: 'cast hashes the password on write',
  },

  'hashing:1': {
    2: 'never the plaintext',
    4: 'hash verifies the password',
    5: 'current algorithm: no rehash needed',
  },

  'registration:0': {
    2: 'unique: rule blocks duplicate accounts',
    3: 'min 8 chars + not-common-password rule',
    4: 'only coach or fan may be chosen',
  },

  'registration:1': {
    0: 'user + role written in one transaction',
    7: 'hashed by the cast on save',
    8: 'new accounts start active',
    13: 'role row created once',
    18: 'attach with its organization scope',
  },

  'fingerprint:0': {
    2: 'guests have nothing to compare',
    6: 'same hash the login path stored',
    9: 'first request after login: seed it',
    10: 'store a missing fingerprint',
    15: 'constant-time compare',
    16: 'a stolen cookie is not enough',
    18: 'throw the old session away',
    21: 'JSON and Livewire get a 401',
    25: 'full pages redirect to login',
  },

  'idle-timeout:0': {
    6: 'config minutes → seconds',
    15: 'expired: compare with now',
    16: 'log out before doing any work',
    21: 'Livewire gets 401 so the UI reacts',
    30: 'refresh the clock on every request',
  },

  'inactive:0': {
    2: 'suspended or pending → blocked',
    3: 'revoke the session immediately',
    8: 'Livewire and XHR get 403 JSON',
    14: 'pages are sent back to login',
  },

  'middleware-order:0': {
    2: 'throttle first: cheapest rejection',
    3: 'then the active-account guard',
    4: 'then the fingerprint comparison',
    5: 'timeout check is last',
    8: 'prepended so it owns the response',
  },

  'role-helpers:0': {
    5: 'pivot table user_roles',
    16: 'exists(): one cheap query',
    27: 'whereIn for several keys',
    37: 'status compared case-insensitively',
  },

  'role-middleware:0': {
    2: 'no user or inactive → 403',
    6: 'any allowed role passes',
    10: 'falling through means deny',
  },

  'secure-component:0': {
    5: 'each component declares its roles',
    12: 'boot() runs on every Livewire request',
    16: 'abort_unless: no user, no access',
    18: 'inactive accounts fail here too',
    19: 'role check stays on the server',
  },

  'component-declarations:0': {
    2: 'organizer-only page',
  },

  'component-declarations:1': {
    2: 'coach-only page',
  },

  'session-registry:0': {
    0: 'current session ID for the device flag',
    3: 'only this user\'s rows',
    4: 'most recent sessions first',
    7: 'user agent → readable device label',
    12: 'constant-time compare marks this row',
  },

  'session-registry:1': {
    2: 'no session ID: nothing to revoke',
    8: 'every session except this one',
    9: 'delete returns the affected count',
  },

  'profile-endpoints:0': {
    3: 'rows scoped to the signed-in user',
  },

  'profile-endpoints:1': {
    0: 'revoke, then read the count',
    4: 'message matches what happened',
  },

  'csrf-revocation:0': {
    0: 'DELETE route behind auth middleware',
  },

  'csrf-revocation:1': {
    2: '@csrf: the request must carry the token',
    3: 'method spoofing sends DELETE',
    5: 'plain button: no JS needed',
  },

  'headers:0': {
    0: 'run the rest of the stack first',
    2: 'stop MIME sniffing',
    3: 'no framing: blocks clickjacking',
    4: 'do not leak full referrer URLs',
    5: 'camera, mic, geolocation off',
    6: 'CSP built per environment',
    8: 'signed-in responses are not cacheable',
    9: 'private + no-store overrides defaults',
    12: 'HSTS only over HTTPS or in production',
    16: 'decorated response goes back out',
  },

  'headers:1': {
    0: 'dev origins only outside production',
    4: 'one directive list, joined with ;',
    7: 'no plugins or objects',
    8: 'refuse to be framed',
    10: 'Alpine needs inline/eval for now',
    16: 'dev socket allowed in connect-src',
  },

  'blade-escaping:0': {
    2: '{{ }} escapes the device string',
    3: 'same for IP and last-active text',
    5: 'flag comes from the server, not the client',
  },

  'blade-escaping:1': {
    1: 'a payload stored as a name',
    8: 'raw payload must not appear',
    9: 'escaped form must appear',
  },

  'parameterized-query:0': {
    0: 'query builder, not string concat',
    1: '? placeholder bound as a parameter',
    2: 'scoped to this tournament',
    6: 'duplicate found: back to step 1',
  },

  'session-config:0': {
    0: 'session payload encrypted at rest',
  },

  'session-config:1': {
    0: 'cookie only over HTTPS in production',
  },

  'session-config:2': {
    0: 'JavaScript cannot read the cookie',
  },

  'session-config:3': {
    0: 'lax: not sent on cross-site POSTs',
  },

  'session-config:4': {
    0: 'JSON serialization, no PHP objects',
  },

  'auth-config:0': {
    1: 'five tries per email + IP',
    2: 'one minute decay window',
  },

  'auth-config:1': {
    1: '60 idle minutes, read from env',
  },

  'demo-gating:0': {
    0: 'quick-fill accounts off in production',
  },

  'demo-gating:1': {
    0: 'rendered only when the flag is on',
    5: 'fills the form; login is still real',
    31: '@endif closes the demo block',
  },

  'feature-bracket:0': {
    2: 'reject sizes that are not powers of two',
    6: 'seed 1 vs seed 2 in the final',
    9: 'complement pairs a high seed with a low',
    13: 'keep the existing order',
    14: 'mirror seed = bracket size + 1 - seed',
  },

  'feature-bracket:1': {
    0: 'the format picks the builder',
    2: 'single elimination: 2/4/8/16/32',
    7: 'double elimination starts at 4',
    11: 'anything else is round robin',
    17: 'plan is written in one transaction',
  },

  'feature-bracket:2': {
    3: 'home slot, then away slot',
    7: 'winners/losers feed from a match',
    8: 'anchor row id becomes the source',
    12: 'one row per bracket slot',
    18: 'seed, winner, or loser',
    21: 'seed teams are placed now',
    33: 'write only when something changed',
    37: 'series keeps its team set in sync',
  },

  'feature-bracket:3': {
    0: 'slots that feed from this match',
    2: 'legacy brackets have no slot rows',
    9: 'loser slots take the loser',
    14: 'find the target game by position',
    18: 'row gone: skip quietly',
    22: 'never touch a finished game',
    29: 'top or bottom of the next game',
  },

  'feature-standings:0': {
    0: 'only completed games count',
    2: 'a bye has no opponent',
    12: 'which side is this team on',
    19: 'game winner, else series winner',
    21: 'match winner earns a win',
    37: 'points equal wins in this format',
    40: 'diff feeds the tiebreak',
    42: 'rank filled in after sorting',
  },

  'feature-standings:1': {
    0: 'sort with head-to-head first',
    2: 'head-to-head result leads',
    3: 'then point differential',
    4: 'then points scored',
    5: 'better seed wins the last tiebreak',
  },

  'feature-standings:2': {
    2: 'playoff_teams must be numeric',
    6: 'configured cut of 2/4/8/16',
    9: 'never more than approved teams',
    13: 'fall back to configured slots',
    16: 'or the biggest division',
    20: 'or the approved registrations',
    30: 'half the field qualifies',
  },

  'feature-schedule:0': {
    2: 'only games still open to scheduling',
    3: 'remaining games have no slots yet',
    6: 'byes are never scheduled',
  },

  'feature-schedule:1': {
    1: 'round robin keeps its round',
    2: 'winners bracket interleaves',
    3: 'losers bracket sits between',
    4: 'grand final after everything',
    5: 'reset game comes last',
  },

  'feature-schedule:2': {
    0: 'slot plus rest = spacing',
    3: 'stop at the end of the day',
    10: 'lane already used that day',
    17: 'existing slot overlaps the window',
    30: 'team already booked nearby',
    32: 'break out of every loop',
    38: 'lane is free on every day',
    44: 'reserve the team time',
    49: 'first valid lane wins',
    53: 'try the next time slot',
  },

  'feature-schedule:3': {
    0: 'validate before deleting anything',
    2: 'invalid plan: throw, change nothing',
    10: 'all writes in one transaction',
    11: 'old slots are replaced atomically',
    21: 'one row per scheduled game',
    26: 'end = start + slot length',
    27: 'published to players',
    37: 'audit the publish with its counts',
  },

  'feature-schedule:4': {
    0: 'same team twice in the window?',
    3: 'name the team and the gap',
    6: 'court already booked?',
    7: 'refuse rather than double-book',
  },

  'feature-announcements:0': {
    2: 'role decides which audiences are visible',
    6: 'allowed audiences per role',
    7: 'organizers see everything',
    8: 'coaches see all, coaches, fans',
    9: 'fans see public bulletins only',
    16: 'targeted post: check the id list',
    19: 'only the named users see it',
    22: 'otherwise the audience must be allowed',
    24: 'category filter',
    25: 'search across title, body, author',
  },

  'feature-announcements:1': {
    0: 'validated input only',
    5: 'targets only when audience is specific',
    9: 'service does the write and the audit',
    10: 'cast to int: never trust the form',
    19: 'toast confirms for the author',
  },

  'feature-announcements:2': {
    0: 'insert + audit are atomic',
    6: 'one row per announcement',
    15: 'created already published',
    17: 'null actor for system posts',
    20: 'audit trail of the publish',
  },

  'feature-announcements:3': {
    0: 'five drafts per user per window',
    1: 'key is scoped to this user',
    9: 'archived tournaments are not loadable',
    17: 'the AI call happens inside the limiter',
    22: 'a failed draft still counts',
    33: '60-second window',
    36: 'limit hit: show a friendly message',
  },

  'feature-announcements:4': {
    0: 'fixed persona + format rules',
    3: 'facts only: no invented details',
    4: 'organizer notes are data, not orders',
    5: 'JSON contract with two fields',
    6: 'title bounds',
    7: 'body bounds',
    9: 'emoji rule appended at the end',
  },
};
