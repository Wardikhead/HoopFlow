let initialized = false;

export function initCalendar() {
    if (initialized) return;
    initialized = true;

    document.addEventListener('keydown', (event) => {
        const cell = event.target.closest?.('[data-calendar-day]');
        if (!cell) return;

        const calendar = cell.closest('[data-testid="schedule-calendar"]');
        if (!calendar) return;

        const cells = [...calendar.querySelectorAll('[data-calendar-day]')];
        const index = cells.indexOf(cell);
        if (index === -1) return;

        // Enter and Space must reach the button so a day can be selected.
        if (!['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp'].includes(event.key)) return;

        const columns = 7;
        let next = index;
        if (event.key === 'ArrowRight') next = index + 1;
        if (event.key === 'ArrowLeft') next = index - 1;
        if (event.key === 'ArrowDown') next = index + columns;
        if (event.key === 'ArrowUp') next = index - columns;
        if (next < 0 || next >= cells.length) return;

        event.preventDefault();
        cells[next].focus();
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest?.('[data-print-page]')) window.print();
    });
}
