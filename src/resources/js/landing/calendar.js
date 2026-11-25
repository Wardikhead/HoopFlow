// Front-end script for Calendar.

export default function initCalendar() {
    const root = document.querySelector('[data-calendar]');

    if (!root) {
        return;
    }

    const dayButtons = [...root.querySelectorAll('[data-calendar-day]')];
    const groups = [...root.querySelectorAll('[data-calendar-day-games]')];
    const empty = root.querySelector('[data-calendar-empty]');

    if (!dayButtons.length) {
        return;
    }

    // Default to the first match day.
    const firstMatch = dayButtons.find((button) => button.dataset.calendarHasGames === 'true');
    let selected = firstMatch ? firstMatch.dataset.calendarDay : dayButtons[0].dataset.calendarDay;

    const apply = () => {
        groups.forEach((group) => {
            group.hidden = group.dataset.calendarDayGames !== selected;
        });

        if (empty) {
            const hasVisible = groups.some((group) => !group.hidden);
            empty.hidden = hasVisible;
        }

        dayButtons.forEach((button) => {
            const active = button.dataset.calendarDay === selected;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });
    };

    dayButtons.forEach((button) => {
        button.addEventListener('click', () => {
            selected = button.dataset.calendarDay;
            apply();
        });
    });

    apply();
}