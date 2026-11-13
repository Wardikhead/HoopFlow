let initialized = false;

export function initScoreConsole() {
    if (initialized) return;
    initialized = true;

    document.addEventListener('keydown', (event) => {
        const dialog = document.querySelector('dialog[data-score-console][open]');
        if (!dialog) return;
        if (event.target.matches('input, select, textarea')) return;

        const active = dialog.querySelector('[data-score-team].is-active') ?? dialog.querySelector('[data-score-team="1"]');

        if (['1', '2', '3'].includes(event.key) && active) {
            event.preventDefault();
            active.querySelector(`[data-points="${event.key}"]`)?.click();
        }

        if (event.key.toLowerCase() === 'u') {
            event.preventDefault();
            dialog.querySelector('[data-testid="score-undo"]')?.click();
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            dialog.querySelector('[data-testid="score-finalize"]')?.click();
        }

        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            const teams = [...dialog.querySelectorAll('[data-score-team]')];
            const index = teams.indexOf(active);
            const next = event.key === 'ArrowRight' ? (index + 1) % teams.length : (index - 1 + teams.length) % teams.length;
            teams.forEach((team) => team.classList.remove('is-active'));
            teams[next]?.classList.add('is-active');
        }
    });

    document.addEventListener('click', (event) => {
        const panel = event.target.closest('dialog[data-score-console] [data-score-team]');
        if (!panel) return;
        panel.closest('dialog').querySelectorAll('[data-score-team]').forEach((team) => team.classList.remove('is-active'));
        panel.classList.add('is-active');
    });
}
