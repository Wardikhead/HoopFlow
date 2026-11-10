export default function initGlow() {
    const lab = document.querySelector('[data-landing-lab]');

    if (!lab || !window.matchMedia('(pointer: fine)').matches) {
        return;
    }

    lab.addEventListener('pointermove', (event) => {
        const rect = lab.getBoundingClientRect();
        lab.style.setProperty('--mx', `${event.clientX - rect.left}px`);
        lab.style.setProperty('--my', `${event.clientY - rect.top}px`);
    });
}
