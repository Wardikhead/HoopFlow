export function initShell() {
    document.addEventListener('click', (event) => {
        document.querySelectorAll('details.hf-user-menu[open]').forEach((menu) => {
            if (!menu.contains(event.target)) menu.removeAttribute('open');
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        document.querySelectorAll('details.hf-user-menu[open]').forEach((menu) => {
            menu.removeAttribute('open');
            menu.querySelector('summary')?.focus();
        });
    });
}

export function initMoreSheet() {
    const sheet = document.querySelector('[data-more-sheet]');
    if (!sheet) return;

    let trigger = null;

    document.querySelectorAll('[data-more-sheet-open]').forEach((button) => {
        button.addEventListener('click', () => {
            trigger = button;
            sheet.showModal();
        });
    });

    document.querySelectorAll('[data-more-sheet-close]').forEach((button) => {
        button.addEventListener('click', () => sheet.close());
    });

    sheet.addEventListener('click', (event) => {
        if (event.target === sheet) sheet.close();
    });

    sheet.addEventListener('close', () => {
        trigger?.focus({ preventScroll: true });
    });

    sheet.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => sheet.close());
    });
}
