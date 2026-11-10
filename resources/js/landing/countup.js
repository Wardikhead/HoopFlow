function formatValue(element, value) {
    if (element.dataset.format !== 'signed') {
        return String(value);
    }

    if (value > 0) {
        return `+${value}`;
    }

    if (value < 0) {
        return `−${Math.abs(value)}`;
    }

    return '0';
}

export default function initCountUp() {
    const counters = [...document.querySelectorAll('[data-count]')];

    if (!counters.length) {
        return;
    }

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const run = (element) => {
        const target = Number(element.dataset.count);

        if (!Number.isFinite(target) || reduced) {
            element.textContent = formatValue(element, target);
            return;
        }

        const duration = 900;
        const start = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = formatValue(element, Math.round(target * eased));

            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        };

        requestAnimationFrame(tick);
    };

    if (!('IntersectionObserver' in window)) {
        counters.forEach(run);
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    run(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.5 },
    );

    counters.forEach((counter) => observer.observe(counter));
}
