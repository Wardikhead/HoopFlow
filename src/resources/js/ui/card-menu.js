// Placement for the anchored "Manage" card menus.
//
// The panel is anchored to the button's right edge. On narrow screens the
// button can sit on a wrapped line at the card's left edge, which pushes the
// fixed-width panel off the left side of the viewport. `shift` moves the panel
// back inside the viewport while keeping it attached to the button.

const DEFAULT_PANEL_WIDTH = 200;
const DEFAULT_PANEL_HEIGHT = 96;
const VIEWPORT_GUTTER = 8;
const ANCHOR_GAP = 12;

export function cardMenuLayout({
    open,
    button,
    panelWidth = DEFAULT_PANEL_WIDTH,
    panelHeight = DEFAULT_PANEL_HEIGHT,
    viewportWidth,
    viewportHeight,
    reserve = 0,
    gutter = VIEWPORT_GUTTER,
    gap = ANCHOR_GAP,
}) {
    if (!open) {
        return { up: false, shift: 0 };
    }

    const panelLeft = button.right - panelWidth;
    const maxLeft = Math.max(gutter, viewportWidth - panelWidth - gutter);
    const shift = Math.round(Math.min(Math.max(panelLeft, gutter), maxLeft) - panelLeft);
    const opensPastViewport = button.bottom + panelHeight + gap > viewportHeight - reserve;

    return { up: opensPastViewport, shift };
}

/**
 * Init card menus.
 * @returns {*} The result.
 */
export function initCardMenus() {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('hfCardMenu', () => ({
            up: false,

            place() {
                const panel = this.$el.querySelector('.hf-card-menu__panel');
                const nav = document.querySelector('.hf-bottom-nav');
                const reserve = nav && getComputedStyle(nav).display !== 'none' ? nav.offsetHeight : 0;
                const rect = this.$el.getBoundingClientRect();
                const { up, shift } = cardMenuLayout({
                    open: this.$el.open,
                    button: { right: rect.right, bottom: rect.bottom },
                    panelWidth: panel?.offsetWidth ?? DEFAULT_PANEL_WIDTH,
                    panelHeight: panel?.offsetHeight ?? DEFAULT_PANEL_HEIGHT,
                    viewportWidth: window.innerWidth,
                    viewportHeight: window.innerHeight,
                    reserve,
                });

                this.up = up;
                this.$el.style.setProperty('--hf-menu-shift', `${shift}px`);
            },
        }));
    });
}
