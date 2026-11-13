function animateValue(element) {
    if (element.dataset.counted === 'true') return;
    element.dataset.counted = 'true';

    const original = element.textContent.trim();
    const match = original.match(/-?\d[\d,]*/);
    if (!match || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const start = 0;
    const end = Number(match[0].replace(/,/g, ''));
    if (!Number.isFinite(end) || end === 0) return;

    const prefix = original.slice(0, match.index);
    const suffix = original.slice((match.index ?? 0) + match[0].length);
    const duration = 520;
    const started = performance.now();

    const tick = (now) => {
        const progress = Math.min(1, (now - started) / duration);
        const eased = 1 - Math.pow(1 - progress, 3);
        const value = Math.round(start + (end - start) * eased);
        element.textContent = `${prefix}${value.toLocaleString('en-PH')}${suffix}`;
        if (progress < 1) requestAnimationFrame(tick);
    };

    requestAnimationFrame(tick);
}

export function initCountUp() {
    document.querySelectorAll('[data-countup]').forEach(animateValue);
}
