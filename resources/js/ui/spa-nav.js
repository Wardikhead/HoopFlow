function hasClickHandler(link) {
    return [...link.attributes].some((attribute) => {
        const name = attribute.name;

        return name === 'wire:click'
            || name.startsWith('wire:click.')
            || name === '@click'
            || name.startsWith('@click.')
            || name === 'x-on:click'
            || name.startsWith('x-on:click.');
    });
}

function hasDynamicHref(link) {
    return [...link.attributes].some((attribute) => {
        const name = attribute.name;

        return name === ':href' || name === 'x-bind:href';
    });
}

function hasWireNavigate(link) {
    return [...link.attributes].some((attribute) => attribute.name.startsWith('wire:navigate'));
}

function descriptorFor(link) {
    return {
        href: link.getAttribute('href') ?? '',
        target: link.getAttribute('target') ?? '',
        download: link.hasAttribute('download'),
        role: link.getAttribute('role') ?? '',
        hasWireNavigate: hasWireNavigate(link),
        hasClickHandler: hasClickHandler(link),
        hasDynamicHref: hasDynamicHref(link),
        isOptedOut: link.closest('[data-no-navigate]') !== null,
        shell: link.closest('.hf-app') !== null,
    };
}

export function shouldInterceptLink(link, currentUrl) {
    if (!link || !link.shell) return false;
    if (link.hasWireNavigate) return false;
    if (link.hasClickHandler) return false;
    if (link.hasDynamicHref) return false;
    if (link.isOptedOut) return false;
    if (link.download) return false;
    if (link.role === 'button') return false;
    if (link.target && link.target !== '_self') return false;

    const raw = String(link.href ?? '').trim();
    if (raw === '' || raw.startsWith('#')) return false;

    let url;
    let current;

    try {
        url = new URL(raw, currentUrl);
        current = new URL(currentUrl);
    } catch {
        return false;
    }

    if (url.protocol !== 'http:' && url.protocol !== 'https:') return false;
    if (url.origin !== current.origin) return false;
    if (url.href === current.href) return false;
    if (url.pathname === current.pathname && url.search === current.search && url.hash) return false;

    return true;
}

export function initSpaNavigation() {
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented) return;
        if (event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link) return;
        if (!shouldInterceptLink(descriptorFor(link), window.location.href)) return;
        if (!window.Livewire?.navigate) return;

        const url = new URL(link.href, window.location.href);

        event.preventDefault();
        window.Livewire.navigate(url.href);
    });
}
