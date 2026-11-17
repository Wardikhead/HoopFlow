const DEFAULT_INTERVAL = 20000;
const MIN_INTERVAL = 5000;

export function normalizeSnapshot(payload) {
    const data = payload && typeof payload === 'object' && payload.data ? payload.data : payload;

    if (!data || !Array.isArray(data.matches)) {
        return { matches: [], serverTime: null };
    }

    return {
        matches: data.matches,
        serverTime: data.server_time ?? null,
    };
}

export function pollInterval(value) {
    const parsed = Number(value);

    return Number.isFinite(parsed) && parsed >= MIN_INTERVAL ? parsed : DEFAULT_INTERVAL;
}

function el(tag, className, text) {
    const node = document.createElement(tag);

    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = String(text);

    return node;
}

function teamBlock(team, score, away, leading) {
    const wrap = el('div', `hf-live-match__team${away ? ' hf-live-match__team--away' : ''}${leading ? ' is-leading' : ''}`);
    wrap.append(
        el('span', 'hf-live-match__name', team?.name ?? 'TBD'),
        el('strong', 'hf-live-match__score', score ?? '–'),
    );

    return wrap;
}

function buildRow(match) {
    const isLive = match.status === 'in_progress';
    const hasScore = match.home_score !== null && match.away_score !== null;
    const homeLeading = hasScore && match.home_score > match.away_score;
    const awayLeading = hasScore && match.away_score > match.home_score;

    const row = el('article', `hf-live-match${isLive ? ' is-live' : ''}`);
    row.dataset.liveMatch = String(match.id ?? '');

    const meta = el('div', 'hf-live-match__meta');
    meta.append(
        el('span', `hf-chip ${isLive ? 'hf-chip--primary' : 'hf-chip--slate'}`, match.status_label ?? ''),
        el('span', 'hf-live-match__round', `${match.round ?? 'Match'} · Game ${match.match_number ?? '–'}`),
    );

    const board = el('div', 'hf-live-match__board');
    board.append(
        teamBlock(match.home, match.home_score, false, homeLeading),
        el('span', 'hf-live-match__dash', '–'),
        teamBlock(match.away, match.away_score, true, awayLeading),
    );

    row.append(meta, board);

    return row;
}

function render(list, matches) {
    if (!matches.length) {
        list.replaceChildren(el('p', 'hf-empty', 'No matches have been scheduled yet.'));

        return;
    }

    const fragment = document.createDocumentFragment();
    matches.forEach((match) => fragment.append(buildRow(match)));
    list.replaceChildren(fragment);
}

let active = null;

export function initLiveScores() {
    active?.stop();

    const root = document.querySelector('[data-live-scores]');

    if (!root) return null;

    const endpoint = root.dataset.liveScores;
    const list = root.querySelector('[data-live-list]');
    const status = root.querySelector('[data-live-status]');
    const updated = root.querySelector('[data-live-updated]');

    if (!endpoint || !list) return null;

    const interval = pollInterval(root.dataset.liveInterval);
    let timer = null;
    let controller = null;
    let generation = 0;

    const setState = (state, message) => {
        root.dataset.liveState = state;

        if (status) status.textContent = message;
    };

    const schedule = (myGeneration) => {
        clearTimeout(timer);

        if (document.hidden || myGeneration !== generation) return;

        timer = setTimeout(poll, interval);
    };

    async function poll() {
        if (document.hidden) return;

        const myGeneration = ++generation;
        controller?.abort();
        controller = new AbortController();

        try {
            const response = await fetch(endpoint, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });

            if (!response.ok) throw new Error(`Live score request failed with ${response.status}`);

            const snapshot = normalizeSnapshot(await response.json());

            render(list, snapshot.matches);
            setState('live', 'Live');

            if (updated) {
                updated.textContent = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
            }
        } catch (error) {
            if (error.name !== 'AbortError') setState('stale', 'Reconnecting…');
        } finally {
            schedule(myGeneration);
        }
    }

    const onVisibilityChange = () => {
        if (document.hidden) {
            clearTimeout(timer);

            return;
        }

        clearTimeout(timer);
        poll();
    };

    document.addEventListener('visibilitychange', onVisibilityChange);

    poll();

    const instance = {
        stop() {
            generation++;
            clearTimeout(timer);
            controller?.abort();
            document.removeEventListener('visibilitychange', onVisibilityChange);

            if (active === instance) active = null;
        },
    };

    active = instance;

    return instance;
}
