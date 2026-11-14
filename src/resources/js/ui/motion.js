export function initMotion() {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (reduce.matches) return;

    document.querySelectorAll('[data-reveal]').forEach((element, index) => {
        element.classList.add('hf-reveal');
        element.style.setProperty('--hf-reveal-delay', `${Math.min(index * 40, 200)}ms`);
    });

    document.addEventListener('hf:theme-changed', () => {
        document.documentElement.classList.add('hf-theme-transition');
        window.setTimeout(() => {
            document.documentElement.classList.remove('hf-theme-transition');
        }, 220);
    });
}
