export default function initReveal() {
    const elements = [...document.querySelectorAll('.hf-lp-reveal')];
    const bracket = document.querySelector('[data-bracket]');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const canObserve = !reduced && 'IntersectionObserver' in window;

    if (elements.length) {
        if (!canObserve) {
            elements.forEach((element) => element.classList.add('is-in'));
        } else {
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
    }

    if (!bracket) {
        return;
    }

    if (!canObserve) {
        bracket.classList.add('is-in');
        return;
    }

    const bracketObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-in');
                    bracketObserver.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.2 },
    );

    bracketObserver.observe(bracket);
}
