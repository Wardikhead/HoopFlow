const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

export default function initDevelopers() {
    const hangs = [...document.querySelectorAll('[data-builder-hang]')];

    if (!hangs.length) {
        return;
    }

    const finePointer = window.matchMedia('(pointer: fine)').matches;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    hangs.forEach((hang) => {
        const card = hang.querySelector('[data-builder-card]');

        if (!card) {
            return;
        }

        const state = {
            angle: 0,
            velocity: 0,
            target: 0,
            startAngle: 0,
            startX: 0,
            lastX: 0,
            raf: 0,
            last: 0,
            hovering: false,
            dragging: false,
            pointerId: null,
        };

        const paint = () => {
            hang.style.setProperty('--swing', `${state.angle.toFixed(2)}deg`);
        };

        const stop = () => {
            if (state.raf) {
                cancelAnimationFrame(state.raf);
            }

            state.raf = 0;
            state.last = 0;
        };

        const tick = (now) => {
            const dt = state.last ? Math.min(now - state.last, 40) : 16.67;
            const step = dt / 16.67;
            state.last = now;

            state.velocity += (state.target - state.angle) * 0.11 * step;
            state.velocity *= Math.pow(0.9, step);
            state.angle += state.velocity * step;

            paint();

            const settled =
                Math.abs(state.velocity) < 0.04 && Math.abs(state.target - state.angle) < 0.06;

            if (settled && !state.dragging) {
                state.angle = state.target;
                state.velocity = 0;
                paint();
                stop();

                if (!state.hovering) {
                    hang.classList.remove('is-swung');
                }

                return;
            }

            state.raf = requestAnimationFrame(tick);
        };

        const run = () => {
            if (state.raf) {
                return;
            }

            state.raf = requestAnimationFrame(tick);
        };

        const swingTo = (degrees) => {
            if (reduced) {
                return;
            }

            hang.classList.add('is-swung');
            state.target = clamp(degrees, -13, 13);
            run();
        };

        card.addEventListener('pointerenter', () => {
            state.hovering = true;
        });

        card.addEventListener('pointermove', (event) => {
            const rect = card.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            if (finePointer) {
                card.style.setProperty('--mx', `${x}px`);
                card.style.setProperty('--my', `${y}px`);
            }

            const nx = x / rect.width - 0.5;
            const ny = y / rect.height - 0.5;

            if (state.dragging) {
                swingTo(state.startAngle + (event.clientX - state.startX) * 0.26);
                state.velocity = clamp((event.clientX - state.lastX) * 0.18, -6, 6);
                state.lastX = event.clientX;

                return;
            }

            if (finePointer) {
                card.style.setProperty('--ry', `${(nx * 8).toFixed(2)}deg`);
                card.style.setProperty('--rx', `${(-ny * 6).toFixed(2)}deg`);
            }

            swingTo(nx * 14);
        });

        card.addEventListener('pointerleave', () => {
            state.hovering = false;

            card.style.removeProperty('--rx');
            card.style.removeProperty('--ry');

            if (!state.dragging) {
                swingTo(0);
            }
        });

        card.addEventListener('pointerdown', (event) => {
            if (reduced || event.button !== 0) {
                return;
            }

            state.dragging = true;
            state.pointerId = event.pointerId;
            state.startX = event.clientX;
            state.lastX = event.clientX;
            state.startAngle = state.angle;
            state.target = state.angle;
            state.velocity = 0;

            hang.classList.add('is-swung', 'is-dragging');
            card.classList.add('is-dragging');

            if (card.setPointerCapture) {
                card.setPointerCapture(event.pointerId);
            }

            stop();
        });

        const release = () => {
            if (!state.dragging) {
                return;
            }

            state.dragging = false;
            state.hovering = false;

            hang.classList.remove('is-dragging');
            card.classList.remove('is-dragging');
            card.style.removeProperty('--rx');
            card.style.removeProperty('--ry');

            if (state.pointerId !== null && card.hasPointerCapture?.(state.pointerId)) {
                card.releasePointerCapture(state.pointerId);
            }

            state.pointerId = null;
            state.target = 0;
            run();
        };

        card.addEventListener('pointerup', release);
        card.addEventListener('pointercancel', release);
    });
}
