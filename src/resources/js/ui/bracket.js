// Front-end script for Bracket.

const MIN_ZOOM = 0.55;
const MAX_ZOOM = 1.5;
const ZOOM_STEP = 0.1;

let scale = 1;
let initialized = false;
let refreshFrame = null;
let observer = null;
let autoFitDone = false;

const traceState = new WeakMap();
const boundCanvases = new WeakSet();
const observedTrees = new Set();

const reducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

/**
 * Canvases.
 * @returns {*} The result.
 */
function canvases() {
    return [...document.querySelectorAll('[data-bracket-canvas]')];
}

/**
 * Visible canvases.
 * @returns {*} The result.
 */
function visibleCanvases() {
    return canvases().filter(
        (canvas) => canvas.offsetParent !== null && canvas.clientWidth > 0,
    );
}

/**
 * Tree of.
 *
 * @param {*} canvas The canvas.
 * @returns {*} The result.
 */
function treeOf(canvas) {
    return canvas.querySelector('[data-bracket-tree]');
}

/**
 * Current scale.
 *
 * @param {*} tree The tree.
 * @returns {*} The result.
 */
function currentScale(tree) {
    const value = Number.parseFloat(tree.style.zoom);

    return Number.isFinite(value) && value > 0 ? value : 1;
}

/**
 * Apply scale.
 *
 * @param {*} nextScale The next scale.
 * @returns {*} The result.
 */
function applyScale(nextScale) {
    const clamped = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, nextScale));
    scale = Math.round(clamped * 100) / 100;

    canvases().forEach((canvas) => {
        const tree = treeOf(canvas);
        if (!tree) return;

        tree.style.zoom = scale === 1 ? '' : String(scale);
    });

    requestAnimationFrame(() => {
        drawAll();
        updateFades();
    });
}

// Fit every board, not just the first one: double elimination renders three
// trees and they share one zoom level.
/**
 * Fit scale.
 * @returns {*} The result.
 */
function fitScale() {
    const visible = visibleCanvases();
    if (visible.length === 0) return 1;

    let fit = 1;

    visible.forEach((canvas) => {
        const tree = treeOf(canvas);
        if (!tree) return;

        const activeScale = currentScale(tree);
        const baseWidth = tree.getBoundingClientRect().width / activeScale;
        const available = canvas.clientWidth - 12;

        if (baseWidth > 0 && available > 0 && baseWidth > canvas.clientWidth) {
            fit = Math.min(fit, available / baseWidth);
        }
    });

    return Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, fit));
}

/**
 * Draw all.
 * @returns {*} The result.
 */
function drawAll() {
    canvases().forEach(drawCanvas);
}

/**
 * Local rect.
 *
 * @param {*} element The element.
 * @param {*} tree The tree.
 * @param {*} treeRect The tree rect.
 * @param {*} activeScale The active scale.
 * @returns {*} The result.
 */
function localRect(element, tree, treeRect, activeScale) {
    const rect = element.getBoundingClientRect();

    return {
        left: (rect.left - treeRect.left) / activeScale,
        right: (rect.right - treeRect.left) / activeScale,
        top: (rect.top - treeRect.top) / activeScale,
        centerY: (rect.top + rect.height / 2 - treeRect.top) / activeScale,
    };
}

// Feeders are mapped by ratio: eight cards into four quarters, four into two
// semis, two into the final. When a round fans out instead of narrowing, the
// same ratio maps each next card back to one source card so the board does not
// explode into an all-to-all mesh.
/**
 * Feeder range.
 *
 * @param {*} currentCount The current count.
 * @param {*} nextCount The next count.
 * @param {*} nextIndex The next index.
 * @returns {*} The result.
 */
function feederRange(currentCount, nextCount, nextIndex) {
    const start = Math.floor((nextIndex * currentCount) / nextCount);
    const end = Math.max(
        start + 1,
        Math.ceil(((nextIndex + 1) * currentCount) / nextCount),
    );

    return [start, Math.min(currentCount, end)];
}

/**
 * Draw canvas.
 *
 * @param {*} canvas The canvas.
 * @returns {*} The result.
 */
function drawCanvas(canvas) {
    const tree = treeOf(canvas);
    if (!tree) return;

    const svg = tree.querySelector('[data-bracket-lines]');
    if (!svg) return;

    // Livewire's morph can drop the inline zoom even though the shared scale
    // survived. Re-assert it before measuring so the board never snaps to 1x.
    if (currentScale(tree) !== scale) {
        tree.style.zoom = scale === 1 ? '' : String(scale);
    }

    // CSS `zoom` scales the rendered box, but a percentage min-width would
    // shrink the layout box to compensate and hide the extra width from the
    // scroller. Pin the tree to its natural column width (or the viewport when
    // it fits) so zooming magnifies the board and the scroll area grows.
    const rounds = [...tree.querySelectorAll('[data-bracket-round]')];
    const treeStyle = getComputedStyle(tree);
    const naturalWidth = rounds.reduce(
        (total, round, index) =>
            total
            + (Number.parseFloat(getComputedStyle(round).flexBasis) || 0)
            + (index > 0 ? Number.parseFloat(treeStyle.columnGap) || 0 : 0),
        (Number.parseFloat(treeStyle.paddingLeft) || 0)
            + (Number.parseFloat(treeStyle.paddingRight) || 0),
    );
    const layoutWidth = Math.ceil(Math.max(naturalWidth, canvas.clientWidth));

    tree.style.width = `${layoutWidth}px`;

    const activeScale = currentScale(tree);
    const treeRect = tree.getBoundingClientRect();
    const width = treeRect.width / activeScale;
    const height = treeRect.height / activeScale;

    if (width <= 0 || height <= 0) return;

    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('width', String(width));
    svg.setAttribute('height', String(height));
    svg.style.width = `${width}px`;
    svg.style.height = `${height}px`;

    let markup = '';

    for (let index = 0; index < rounds.length - 1; index += 1) {
        const currentCards = [...rounds[index].querySelectorAll('.hf-bracket-match')];
        const nextCards = [...rounds[index + 1].querySelectorAll('.hf-bracket-match')];

        if (currentCards.length === 0 || nextCards.length === 0) continue;

        const startX = localRect(rounds[index], tree, treeRect, activeScale).right;
        const endX = localRect(rounds[index + 1], tree, treeRect, activeScale).left;
        const midX = startX + (endX - startX) / 2;

        nextCards.forEach((nextCard, nextIndex) => {
            const to = nextCard.dataset.matchId;
            if (!to) return;

            const target = localRect(nextCard, tree, treeRect, activeScale);
            const [start, end] = feederRange(
                currentCards.length,
                nextCards.length,
                nextIndex,
            );

            currentCards.slice(start, end).forEach((currentCard) => {
                const from = currentCard.dataset.matchId;
                if (!from) return;

                const source = localRect(currentCard, tree, treeRect, activeScale);
                const path = `M${startX} ${source.centerY} H${midX} V${target.centerY} H${endX}`;

                markup += `<path class="hf-bracket-path" data-from="${from}" data-to="${to}" d="${path}" />`;
            });
        });
    }

    svg.innerHTML = markup;
    reapplyTrace(canvas);
}

/**
 * Build graph.
 *
 * @param {*} tree The tree.
 * @returns {*} The result.
 */
function buildGraph(tree) {
    const forward = new Map();
    const backward = new Map();

    tree.querySelectorAll('.hf-bracket-path').forEach((path) => {
        const from = path.dataset.from;
        const to = path.dataset.to;
        if (!from || !to) return;

        if (!forward.has(from)) forward.set(from, new Set());
        if (!backward.has(to)) backward.set(to, new Set());
        forward.get(from).add(to);
        backward.get(to).add(from);
    });

    return { forward, backward };
}

/**
 * Collect chain.
 *
 * @param {*} graph The graph.
 * @param {*} start The start.
 * @returns {*} The result.
 */
function collectChain(graph, start) {
    const seen = new Set([start]);
    const queue = [start];

    while (queue.length > 0) {
        const current = queue.shift();

        graph.get(current)?.forEach((neighbor) => {
            if (seen.has(neighbor)) return;

            seen.add(neighbor);
            queue.push(neighbor);
        });
    }

    return seen;
}

/**
 * Trace.
 *
 * @param {*} canvas The canvas.
 * @param {*} card The card.
 * @returns {*} The result.
 */
function trace(canvas, card) {
    const tree = treeOf(canvas);
    const matchId = card?.dataset.matchId;
    if (!tree || !matchId) return;

    if (!tree.querySelector('.hf-bracket-path')) {
        clearTrace(canvas);
        return;
    }

    traceState.set(canvas, matchId);

    const { forward, backward } = buildGraph(tree);
    const pathIds = new Set([
        ...collectChain(forward, matchId),
        ...collectChain(backward, matchId),
    ]);

    tree.classList.add('is-tracing');
    tree.querySelectorAll('.hf-bracket-match').forEach((element) => {
        element.classList.toggle('is-path', pathIds.has(element.dataset.matchId));
    });
    tree.querySelectorAll('.hf-bracket-path').forEach((element) => {
        element.classList.toggle(
            'is-hot',
            pathIds.has(element.dataset.from) && pathIds.has(element.dataset.to),
        );
    });
}

/**
 * Clear trace.
 *
 * @param {*} canvas The canvas.
 * @returns {*} The result.
 */
function clearTrace(canvas) {
    traceState.delete(canvas);

    const tree = treeOf(canvas);
    if (!tree) return;

    tree.classList.remove('is-tracing');
    tree.querySelectorAll('.is-path').forEach((element) => element.classList.remove('is-path'));
    tree.querySelectorAll('.hf-bracket-path.is-hot').forEach((element) => element.classList.remove('is-hot'));
}

/**
 * Reapply trace.
 *
 * @param {*} canvas The canvas.
 * @returns {*} The result.
 */
function reapplyTrace(canvas) {
    const traced = traceState.get(canvas);
    if (!traced) return;

    const tree = treeOf(canvas);
    const card = tree?.querySelector(`.hf-bracket-match[data-match-id="${traced}"]`);
    if (!card) {
        clearTrace(canvas);
        return;
    }

    trace(canvas, card);
}

/**
 * Update fade.
 *
 * @param {*} canvas The canvas.
 * @returns {*} The result.
 */
function updateFade(canvas) {
    const viewport = canvas.closest('[data-bracket-viewport]');
    if (!viewport) return;

    const scrollable = canvas.scrollWidth > canvas.clientWidth + 2;
    const atEnd = canvas.scrollLeft + canvas.clientWidth >= canvas.scrollWidth - 2;

    viewport.classList.toggle('is-scrollable', scrollable);
    viewport.classList.toggle('is-at-end', scrollable && atEnd);
}

/**
 * Update fades.
 * @returns {*} The result.
 */
function updateFades() {
    canvases().forEach(updateFade);
}

/**
 * Bind canvas.
 *
 * @param {*} canvas The canvas.
 * @returns {*} The result.
 */
function bindCanvas(canvas) {
    if (boundCanvases.has(canvas)) return;
    boundCanvases.add(canvas);

    canvas.addEventListener('scroll', () => updateFade(canvas), { passive: true });

    canvas.addEventListener('pointerover', (event) => {
        if (event.pointerType !== 'mouse') return;

        const card = event.target.closest?.('.hf-bracket-match');
        if (!card || !canvas.contains(card)) {
            clearTrace(canvas);
            return;
        }

        if (traceState.get(canvas) === card.dataset.matchId) return;

        trace(canvas, card);
    });

    canvas.addEventListener('pointerleave', () => clearTrace(canvas));

    canvas.addEventListener('focusin', (event) => {
        const card = event.target.closest?.('.hf-bracket-match');
        if (card && canvas.contains(card)) trace(canvas, card);
    });

    canvas.addEventListener('focusout', (event) => {
        const next = event.relatedTarget;
        if (next instanceof Element && canvas.contains(next)) return;

        clearTrace(canvas);
    });
}

/**
 * Observe trees.
 * @returns {*} The result.
 */
function observeTrees() {
    if (!observer) return;

    observedTrees.forEach((tree) => {
        if (tree.isConnected) return;

        observer.unobserve(tree);
        observedTrees.delete(tree);
    });

    document.querySelectorAll('[data-bracket-tree]').forEach((tree) => {
        if (observedTrees.has(tree)) return;

        observer.observe(tree);
        observedTrees.add(tree);
    });
}

/**
 * Refresh.
 * @returns {*} The result.
 */
function refresh() {
    canvases().forEach(bindCanvas);
    observeTrees();
    drawAll();
    updateFades();
}

/**
 * Schedule refresh.
 * @returns {*} The result.
 */
function scheduleRefresh() {
    if (refreshFrame !== null) return;

    refreshFrame = requestAnimationFrame(() => {
        refreshFrame = null;
        refresh();
    });
}

/**
 * Auto fit.
 * @returns {*} The result.
 */
function autoFit() {
    if (autoFitDone) return;
    autoFitDone = true;

    const overflowing = visibleCanvases().some(
        (canvas) => canvas.scrollWidth > canvas.clientWidth + 2,
    );

    if (overflowing) applyScale(fitScale());
}

/**
 * Hook livewire.
 * @returns {*} The result.
 */
function hookLivewire() {
    const register = () => {
        window.Livewire?.hook?.('morphed', () => scheduleRefresh());
    };

    if (window.Livewire) {
        register();
    } else {
        document.addEventListener('livewire:init', register, { once: true });
    }
}

/**
 * Jump to final.
 * @returns {*} The result.
 */
function jumpToFinal() {
    const visible = visibleCanvases();
    const canvas = visible[visible.length - 1];
    if (!canvas) return;

    const rounds = [...canvas.querySelectorAll('[data-bracket-round]')];
    const finalCard = rounds[rounds.length - 1]?.querySelector('.hf-bracket-match');
    if (!finalCard) return;

    finalCard.scrollIntoView({
        behavior: reducedMotion() ? 'auto' : 'smooth',
        block: 'center',
        inline: 'center',
    });
    finalCard.focus({ preventScroll: true });
    trace(canvas, finalCard);
}

/**
 * Init bracket zoom.
 * @returns {*} The result.
 */
export function initBracketZoom() {
    if (initialized) return;
    initialized = true;

    document.addEventListener('click', (event) => {
        const zoomButton = event.target.closest('[data-bracket-zoom]');

        if (zoomButton) {
            const action = zoomButton.dataset.bracketZoom;

            if (action === 'in') applyScale(scale + ZOOM_STEP);
            if (action === 'out') applyScale(scale - ZOOM_STEP);
            if (action === 'reset') applyScale(fitScale());

            return;
        }

        if (event.target.closest('[data-bracket-jump="final"]')) {
            jumpToFinal();
        }
    });

    if (typeof ResizeObserver !== 'undefined') {
        observer = new ResizeObserver(() => scheduleRefresh());
    }

    document.addEventListener('livewire:navigated', () => {
        autoFitDone = false;
        scale = 1;

        requestAnimationFrame(() => {
            refresh();
            autoFit();
        });
    });

    hookLivewire();
    refresh();

    requestAnimationFrame(() => requestAnimationFrame(refresh));

    if (document.fonts?.ready) {
        document.fonts.ready.then(() => {
            refresh();
            autoFit();
        });
    }

    window.addEventListener('resize', scheduleRefresh, { passive: true });
}
