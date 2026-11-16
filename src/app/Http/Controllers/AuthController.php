<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Rules\NotCommonPassword;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->merge([
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

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'organization' => trim((string) $request->input('organization')),
        ]);

        $data = $request->validate([
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
        ]);

        $user = DB::transaction(function () use ($data): User {
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
        });

        Auth::login($user);
        $request->session()->regenerate();
        $this->recordSessionSecurity($request);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower((string) $request->input('email')).'|'.$request->ip()
        );
    }

    private function recordSessionSecurity(Request $request): void
    {
        $request->session()->put([
            'auth.fingerprint' => hash('sha256', (string) $request->userAgent()),
            'auth.last_activity' => time(),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = explode(' ', Str::squish($name), 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    private function resolveOrganization(?string $name): ?Organization
    {
        if ($name === null || $name === '') {
            return null;
        }

        $slug = Str::slug($name) ?: 'organization-'.Str::lower(Str::random(8));

        return Organization::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name],
        );
    }
}
