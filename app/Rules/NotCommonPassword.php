<?php

/** Validation rule for Not common password. */

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

    /**
     * Validate.
     *
     * @param  string  $attribute  The attribute.
     * @param  mixed  $value  The value.
     * @param  Closure  $fail  The fail.
     * @return void Nothing.
     */
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
