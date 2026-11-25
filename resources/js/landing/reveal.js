// Front-end script for Reveal.

export default function initReveal() {
    const elements = [...document.querySelectorAll('.hf-lp-reveal, .hf-lp-wipe')];
    const bracket = document.querySelector('[data-bracket]');
    const targets = bracket ? [...elements, bracket] : elements;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!targets.length) {
        return;
    }

    if (reduced || !('IntersectionObserver' in window)) {
        targets.forEach((element) => element.classList.add('is-in'));
        return;
    }

    const pending = new Set(targets);
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-in');
                    pending.delete(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.12, rootMargin: '0px 0px -8% 0px' },
    );

    targets.forEach((element) => observer.observe(element));

    // Anchor jumps and fast flicks can carry elements past the observer between
    // frames, leaving them hidden. Reveal anything that reached or passed the viewport.
    const sweep = () => {
        const limit = window.innerHeight * 0.92;

        pending.forEach((element) => {
            if (element.getBoundingClientRect().top < limit) {
                element.classList.add('is-in');
                pending.delete(element);
                observer.unobserve(element);
            }
        });
    };

    window.addEventListener('scroll', sweep, { passive: true });
    window.addEventListener('resize', sweep, { passive: true });
    requestAnimationFrame(sweep);
}
