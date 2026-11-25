// Front-end script for Cards.

export default function initCards() {
    const cards = [...document.querySelectorAll('[data-hf-card]')];

    if (!cards.length) {
        return;
    }

    const finePointer = window.matchMedia('(pointer: fine)').matches;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const tilts = finePointer && !reduced;

    cards.forEach((card) => {
        if (!card.querySelector(':scope > .hf-lp-fx')) {
            const fx = document.createElement('span');
            fx.className = 'hf-lp-fx';
            fx.setAttribute('aria-hidden', 'true');
            card.append(fx);
        }

        let frame = 0;
        let nextEvent = null;

        const paint = () => {
            frame = 0;

            if (!nextEvent) {
                return;
            }

            const rect = card.getBoundingClientRect();
            const x = nextEvent.clientX - rect.left;
            const y = nextEvent.clientY - rect.top;

            card.style.setProperty('--mx', `${x.toFixed(1)}px`);
            card.style.setProperty('--my', `${y.toFixed(1)}px`);

            if (!tilts) {
                return;
            }

            const rx = (y / rect.height - 0.5) * -5;
            const ry = (x / rect.width - 0.5) * 6;

            card.style.setProperty('--rx', `${rx.toFixed(2)}deg`);
            card.style.setProperty('--ry', `${ry.toFixed(2)}deg`);
        };

        card.addEventListener(
            'pointermove',
            (event) => {
                nextEvent = event;

                if (!frame) {
                    frame = requestAnimationFrame(paint);
                }
            },
            { passive: true },
        );

        card.addEventListener('pointerleave', () => {
            nextEvent = null;
            card.style.removeProperty('--rx');
            card.style.removeProperty('--ry');
        });
    });
}
