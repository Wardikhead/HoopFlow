export default function initSchedule() {
    const root = document.querySelector('[data-schedule]');

    if (!root) {
        return;
    }

    const dayButtons = [...root.querySelectorAll('[data-schedule-day]')];
    const courtButtons = [...root.querySelectorAll('[data-schedule-court]')];
    const groups = [...root.querySelectorAll('[data-schedule-group]')];
    const matches = [...root.querySelectorAll('[data-schedule-match]')];
    const empty = root.querySelector('[data-schedule-empty]');

    let day = dayButtons[0]?.dataset.scheduleDay ?? 'day1';
    let court = 'all';

    const apply = () => {
        let visible = 0;

        groups.forEach((group) => {
            group.hidden = group.dataset.scheduleGroup !== day;
        });

        matches.forEach((match) => {
            const show = match.dataset.day === day && (court === 'all' || match.dataset.court === court);
            match.hidden = !show;

            if (show) {
                visible += 1;
            }
        });

        if (empty) {
            empty.hidden = visible > 0;
        }

        dayButtons.forEach((button) => {
            const active = button.dataset.scheduleDay === day;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });

        courtButtons.forEach((button) => {
            const active = button.dataset.scheduleCourt === court;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });
    };

    dayButtons.forEach((button) => {
        button.addEventListener('click', () => {
            day = button.dataset.scheduleDay;
            apply();
        });
    });

    courtButtons.forEach((button) => {
        button.addEventListener('click', () => {
            court = button.dataset.scheduleCourt;
            apply();
        });
    });

    apply();
}
