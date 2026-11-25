// Front-end script for Story.

export default function initStory() {
    const story = document.querySelector('[data-story]');

    if (!story) {
        return;
    }

    const stage = story.querySelector('[data-story-stage]');
    const steps = [...story.querySelectorAll('[data-story-step]')];

    if (!stage || !steps.length) {
        return;
    }

    const setStep = (index) => {
        stage.dataset.step = String(index);

        steps.forEach((step, i) => {
            step.classList.toggle('is-active', i === index);
            step.classList.toggle('is-done', i < index);
        });
    };

    setStep(0);

    if (!('IntersectionObserver' in window)) {
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    setStep(Number(entry.target.dataset.storyStep));
                }
            });
        },
        { rootMargin: '-45% 0px -45% 0px', threshold: 0 },
    );

    steps.forEach((step) => observer.observe(step));
}
