const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

function initMagnetic() {
    const buttons = [...document.querySelectorAll('[data-magnetic]')];

    if (!buttons.length || !window.matchMedia('(pointer: fine)').matches) {
        return;
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    buttons.forEach((button) => {
        button.addEventListener('pointermove', (event) => {
            const rect = button.getBoundingClientRect();
            const x = event.clientX - rect.left - rect.width / 2;
            const y = event.clientY - rect.top - rect.height / 2;

            button.style.setProperty('--mag-x', `${clamp(x * 0.16, -6, 6).toFixed(2)}px`);
            button.style.setProperty('--mag-y', `${clamp(y * 0.22, -5, 5).toFixed(2)}px`);
        });

        button.addEventListener('pointerleave', () => {
            button.style.removeProperty('--mag-x');
            button.style.removeProperty('--mag-y');
        });
    });
}

function initHeroParallax() {
    const hero = document.querySelector('.hf-lp-hero');
    const layers = [...document.querySelectorAll('[data-parallax]')];

    if (!hero || !layers.length || !window.matchMedia('(pointer: fine)').matches) {
        return;
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    let frame = 0;
    let last = null;

    const paint = () => {
        frame = 0;

        if (!last) {
            return;
        }

        const x = last.clientX / window.innerWidth - 0.5;
        const y = last.clientY / window.innerHeight - 0.5;

        layers.forEach((layer) => {
            const depth = Number(layer.dataset.parallax) || 1;
            layer.style.setProperty('--px', `${(x * depth * 14).toFixed(2)}px`);
            layer.style.setProperty('--py', `${(y * depth * 10).toFixed(2)}px`);
        });
    };

    hero.addEventListener(
        'pointermove',
        (event) => {
            last = event;

            if (!frame) {
                frame = requestAnimationFrame(paint);
            }
        },
        { passive: true },
    );

    hero.addEventListener('pointerleave', () => {
        last = null;
        layers.forEach((layer) => {
            layer.style.removeProperty('--px');
            layer.style.removeProperty('--py');
        });
    });
}

function initWireVelocity() {
    const track = document.querySelector('.hf-lp-wire__track');

    if (!track || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    let lastY = window.scrollY;
    let frame = 0;

    const paint = () => {
        frame = 0;
        const delta = Math.abs(window.scrollY - lastY);
        const duration = clamp(44 - delta * 1.6, 14, 44);

        track.style.setProperty('--wire-duration', `${duration.toFixed(1)}s`);
    };

    window.addEventListener(
        'scroll',
        () => {
            if (frame) {
                return;
            }

            frame = requestAnimationFrame(() => {
                paint();
                lastY = window.scrollY;
            });
        },
        { passive: true },
    );
}

function initSectionProgress() {
    const sections = [...document.querySelectorAll('main > section')];
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!sections.length || reduced) {
        return;
    }

    const bars = sections.map((section) => {
        const bar = document.createElement('span');
        bar.className = 'hf-lp-sec-progress';
        bar.setAttribute('aria-hidden', 'true');
        section.prepend(bar);

        return { section, bar };
    });

    let frame = 0;

    const paint = () => {
        frame = 0;
        const viewport = window.innerHeight || 1;

        bars.forEach(({ section, bar }) => {
            const rect = section.getBoundingClientRect();
            const span = Math.max(rect.height - viewport, 1);
            const progress = clamp(-rect.top / span, 0, 1);
            bar.style.setProperty('--section-progress', progress.toFixed(3));
        });
    };

    const schedule = () => {
        if (!frame) {
            frame = requestAnimationFrame(paint);
        }
    };

    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule, { passive: true });
    requestAnimationFrame(paint);
}

function initScramble() {
    const targets = [...document.querySelectorAll('[data-scramble]')];

    if (!targets.length) {
        return;
    }

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduced || !('IntersectionObserver' in window)) {
        return;
    }

    const glyphs = '▚▞◣◥0123456789AZ';

    const run = (element) => {
        const text = element.dataset.scrambleOriginal || element.textContent;
        element.dataset.scrambleOriginal = text;
        const start = performance.now();
        const duration = 720;

        const tick = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const settled = Math.floor(text.length * progress);
            let output = '';

            for (let i = 0; i < text.length; i++) {
                if (i < settled || text[i] === ' ') {
                    output += text[i];
                } else {
                    output += glyphs[Math.floor(Math.random() * glyphs.length)];
                }
            }

            element.textContent = output;

            if (progress < 1) {
                requestAnimationFrame(tick);
            } else {
                element.textContent = text;
            }
        };

        requestAnimationFrame(tick);
    };

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    run(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.6 },
    );

    targets.forEach((target) => observer.observe(target));
}

export default function initEffects() {
    initMagnetic();
    initHeroParallax();
    initWireVelocity();
    initSectionProgress();
    initScramble();
}
