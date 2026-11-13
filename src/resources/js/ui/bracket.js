let scale = 1;
let initialized = false;

function apply() {
    document.querySelectorAll('[data-bracket-canvas]').forEach((canvas) => {
        canvas.style.transform = `scale(${scale})`;
        canvas.style.transformOrigin = 'top left';
    });
}

export function initBracketZoom() {
    if (initialized) return;
    initialized = true;

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-bracket-zoom]');
        if (!button) return;

        const action = button.dataset.bracketZoom;
        if (action === 'in') scale = Math.min(1.6, scale + 0.1);
        if (action === 'out') scale = Math.max(0.6, scale - 0.1);
        if (action === 'reset') scale = 1;
        apply();
    });
}
