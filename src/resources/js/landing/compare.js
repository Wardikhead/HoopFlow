// Front-end script for Compare.

export default function initCompare() {
    const section = document.querySelector('[data-compare]');

    if (!section) {
        return;
    }

    const range = section.querySelector('[data-compare-range]');
    const frame = section.querySelector('.hf-lp-compare__frame');

    if (!range || !frame) {
        return;
    }

    const apply = () => {
        frame.style.setProperty('--split', `${range.value}%`);
    };

    range.addEventListener('input', apply);
    range.addEventListener('change', apply);
    apply();
}
