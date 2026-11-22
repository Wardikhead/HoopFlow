export default function initDevelopers() {
    const cards = [...document.querySelectorAll('[data-builder-card]')];

    if (!cards.length) {
        return;
    }

    const finePointer = window.matchMedia('(pointer: fine)').matches;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    cards.forEach((card) => {
        card.addEventListener('pointermove', (event) => {
            const rect = card.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            card.style.setProperty('--mx', `${x}px`);
            card.style.setProperty('--my', `${y}px`);

            if (!finePointer || reduced) {
                return;
            }

            const rx = (y / rect.height - 0.5) * -4;
            const ry = (x / rect.width - 0.5) * 5;

            card.style.setProperty('--rx', `${rx.toFixed(2)}deg`);
            card.style.setProperty('--ry', `${ry.toFixed(2)}deg`);
        });

        card.addEventListener('pointerleave', () => {
            card.style.removeProperty('--rx');
            card.style.removeProperty('--ry');
        });
    });
}
