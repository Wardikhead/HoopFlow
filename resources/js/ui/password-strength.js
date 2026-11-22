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
            submitting: false,

            get evaluation() {
                return evaluatePassword(this.password);
            },

            toggle() {
                this.showPassword = !this.showPassword;
            },
        }));
    });
}
