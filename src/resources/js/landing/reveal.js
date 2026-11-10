export default function initReveal() {
    const elements = [...document.querySelectorAll('.hf-lp-reveal')];
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!elements.length) {
        return;
    }

    if (reduced || !('IntersectionObserver' in window)) {
        elements.forEach((element) => element.classList.add('is-in'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-in');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.12, rootMargin: '0px 0px -8% 0px' },
    );

    elements.forEach((element) => observer.observe(element));
}
