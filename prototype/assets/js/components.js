import {
  escapeHtml,
  initials,
  formatDate,
  formatTime,
  statusMeta,
} from './ui.js';

export function createComponents({ store, ui }) {
  function shield(team, size = '') {
    const label = team?.shortName || initials(team?.name || 'TBD');
    const color = team?.avatarColor || 'var(--hf-slate)';
    return `<span class="hf-shield ${size ? `hf-shield--${size}` : ''}" style="--team-color: ${escapeHtml(color)}" aria-hidden="true">${escapeHtml(label)}</span>`;
  }

  function teamName(team) {
    return `<span class="hf-match-name">${escapeHtml(team?.name || 'TBD')}</span>`;
  }

  function matchCard(match, { compact = false, href = null, testid = null } = {}) {
    const team1 = match.team1Id ? store.select.team(match.team1Id) : null;
    const team2 = match.team2Id ? store.select.team(match.team2Id) : null;
    const tournament = store.select.tournament(match.tournamentId);
    const court = tournament?.courts.find((item) => item.id === match.courtId);
    const winner1 = Boolean(match.winnerId && match.winnerId === match.team1Id);
    const winner2 = Boolean(match.winnerId && match.winnerId === match.team2Id);
    const score1 = match.isBye ? 'BYE' : match.score1 ?? '–';
    const score2 = match.isBye ? '–' : match.score2 ?? '–';
    const when = match.scheduledAt
      ? `${formatDate(match.scheduledAt)} · ${formatTime(match.scheduledAt)}`
      : 'Time TBD';
    const body = `
      <div class="hf-match-head">
        <span>${escapeHtml(match.roundLabel)}${court ? ` · ${escapeHtml(court.name)}` : ''}</span>
        ${ui.statusChip(match.status)}
      </div>
      <div class="hf-match-team ${winner1 ? 'is-winner' : match.status !== 'scheduled' ? 'is-loser' : ''}">
        ${shield(team1)}
        ${teamName(team1)}
        <span class="hf-score ${match.score1 == null && !match.isBye ? 'hf-score--muted' : ''}">${escapeHtml(String(score1))}</span>
      </div>
      <div class="hf-match-team ${winner2 ? 'is-winner' : match.status !== 'scheduled' ? 'is-loser' : ''}">
        ${shield(team2)}
        ${teamName(team2)}
        <span class="hf-score ${match.score2 == null ? 'hf-score--muted' : ''}">${escapeHtml(String(score2))}</span>
      </div>
      ${compact ? '' : `<div class="hf-match-head"><span>${escapeHtml(when)}</span><span>${match.isBye ? 'Bye' : escapeHtml(match.roundLabel)}</span></div>`}
    `;
    const tag = href ? 'a' : 'article';
    const hrefAttr = href ? ` href="${escapeHtml(href)}"` : '';
    return `<${tag} class="hf-match-card hf-match-card--${escapeHtml(match.status)}" data-match-id="${escapeHtml(match.id)}" data-testid="${escapeHtml(testid || `match-card-${match.id}`)}"${hrefAttr}>${body}</${tag}>`;
  }

  function bracketMatch(match) {
    const team1 = match.team1Id ? store.select.team(match.team1Id) : null;
    const team2 = match.team2Id ? store.select.team(match.team2Id) : null;
    const winner1 = Boolean(match.winnerId && match.winnerId === match.team1Id);
    const winner2 = Boolean(match.winnerId && match.winnerId === match.team2Id);
    const label = `${match.roundLabel} slot ${match.bracketSlot}`;
    return `
      <div class="hf-bracket-match hf-bracket-match--${escapeHtml(match.status)}"
           data-match-id="${escapeHtml(match.id)}"
           data-testid="bracket-match-${escapeHtml(match.id)}"
           role="button" tabindex="0" aria-label="Open ${escapeHtml(label)}">
        <div class="hf-bracket-match__slot">${escapeHtml(match.roundLabel)} · Slot ${match.bracketSlot}</div>
        <div class="hf-match-team ${winner1 ? 'is-winner' : match.status !== 'scheduled' ? 'is-loser' : ''}">
          ${shield(team1)}${teamName(team1)}
          <span class="hf-score ${match.score1 == null && !match.isBye ? 'hf-score--muted' : ''}">${escapeHtml(match.isBye ? 'BYE' : String(match.score1 ?? '–'))}</span>
        </div>
        <div class="hf-match-team ${winner2 ? 'is-winner' : match.status !== 'scheduled' ? 'is-loser' : ''}">
          ${shield(team2)}${teamName(team2)}
          <span class="hf-score ${match.score2 == null ? 'hf-score--muted' : ''}">${escapeHtml(String(match.score2 ?? '–'))}</span>
        </div>
      </div>
    `;
  }

  function kpiTile({ label, value, hint = '', href = null, tone = '', testid = null }) {
    const tag = href ? 'a' : 'div';
    const hrefAttr = href ? ` href="${escapeHtml(href)}"` : '';
    const slug = label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    return `
      <${tag} class="hf-kpi ${tone ? `hf-kpi--${tone}` : ''}"
             data-mark="${escapeHtml(String(value))}"
             data-testid="${escapeHtml(testid || `kpi-${slug}`)}"${hrefAttr}>
        <span class="hf-kpi-label">${escapeHtml(label)}</span>
        <span class="hf-kpi-value">${escapeHtml(String(value))}</span>
        ${hint ? `<span class="hf-kpi-hint">${escapeHtml(hint)}</span>` : ''}
      </${tag}>
    `;
  }

  function pageHeader({ eyebrow, title, description = '', actions = '', meta = '' }) {
    return `
      <header class="hf-page-head">
        <div>
          <span class="hf-eyebrow">${escapeHtml(eyebrow)}</span>
          <h1 class="hf-page-title">${escapeHtml(title)}</h1>
          ${description ? `<p class="hf-page-desc">${escapeHtml(description)}</p>` : ''}
          ${meta ? `<div class="hf-page-meta">${meta}</div>` : ''}
        </div>
        ${actions ? `<div class="hf-page-actions">${actions}</div>` : ''}
      </header>
    `;
  }

  function sectionHeader({ title, action = '' }) {
    return `
      <div class="hf-section-head">
        <h2 class="hf-section-title">${escapeHtml(title)}</h2>
        ${action}
      </div>
    `;
  }

  function announcementCard(item, { compact = false } = {}) {
    return `
      <article class="hf-card hf-card--flat" data-testid="announcement-${escapeHtml(item.id)}">
        <div class="hf-between">
          <span class="hf-chip hf-chip--slate">${escapeHtml(item.category)}</span>
          ${item.pinned ? '<span class="hf-chip hf-chip--gold">Pinned</span>' : ''}
        </div>
        <h3 class="hf-section-title hf-mt-1">${escapeHtml(item.title)}</h3>
        <p class="hf-muted hf-small hf-mb-0">${escapeHtml(item.body)}</p>
        <div class="hf-match-head hf-mt-2">
          <span>${escapeHtml(item.author)}</span>
          <span>${escapeHtml(formatDate(item.createdAt))}</span>
        </div>
        ${compact ? '' : `<div class="hf-match-head"><span>Audience: ${escapeHtml(item.audience)}</span><span>${escapeHtml(item.tournamentId)}</span></div>`}
      </article>
    `;
  }

  function standingsTable(tournamentId, { limit = null } = {}) {
    const rows = store.select.standings(tournamentId);
    if (!rows.length) {
      return ui.emptyState({
        icon: 'bar-chart',
        title: 'No standings yet',
        message: 'Standings appear as soon as approved teams have recorded results.',
      });
    }
    const visible = limit ? rows.slice(0, limit) : rows;
    const lastQualified = visible.filter((row) => row.qualified).length;
    const table = `
      <div class="hf-table-wrap">
        <table class="hf-table hf-table--standings">
          <thead>
            <tr>
              <th scope="col">#</th>
              <th scope="col">Team</th>
              <th scope="col" class="is-num">W</th>
              <th scope="col" class="is-num">L</th>
              <th scope="col" class="is-num">PF</th>
              <th scope="col" class="is-num">PA</th>
              <th scope="col" class="is-num">+/-</th>
              <th scope="col" class="is-num">Seed</th>
            </tr>
          </thead>
          <tbody>
            ${visible
              .map(
                (row, index) => `
                  <tr class="${row.qualified ? 'is-qualified' : ''}" data-testid="standings-row-${escapeHtml(row.teamId)}">
                    <td class="is-num">${row.rank}</td>
                    <td>
                      <a class="hf-flex hf-items-center hf-gap-1" href="../teams/show.html?id=${encodeURIComponent(row.teamId)}">
                        ${shield(row.team)}
                        <strong>${escapeHtml(row.team.name)}</strong>
                      </a>
                    </td>
                    <td class="is-num">${row.wins}</td>
                    <td class="is-num">${row.losses}</td>
                    <td class="is-num">${row.pointsFor}</td>
                    <td class="is-num">${row.pointsAgainst}</td>
                    <td class="is-num">${row.diff > 0 ? '+' : ''}${row.diff}</td>
                    <td class="is-num">${row.seed ?? '–'}</td>
                  </tr>
                  ${lastQualified === index + 1 && !limit ? '<tr class="hf-qual-line"><td colspan="8"></td></tr>' : ''}
                `
              )
              .join('')}
          </tbody>
        </table>
      </div>
    `;
    const cards = `
      <div class="hf-standings-cards">
        ${visible
          .map(
            (row) => `
              <a class="hf-card hf-card--flat" href="../teams/show.html?id=${encodeURIComponent(row.teamId)}" data-testid="standings-card-${escapeHtml(row.teamId)}">
                <div class="hf-between">
                  <span class="hf-flex hf-items-center hf-gap-1">
                    <span class="hf-kpi-label">#${row.rank}</span>
                    ${shield(row.team)}
                    <strong>${escapeHtml(row.team.name)}</strong>
                  </span>
                  ${row.qualified ? '<span class="hf-chip hf-chip--gold">Playoff cut</span>' : ''}
                </div>
                <div class="hf-flex hf-gap-3 hf-mt-1 hf-small">
                  <span><strong>${row.wins}</strong> W</span>
                  <span><strong>${row.losses}</strong> L</span>
                  <span><strong>${row.diff > 0 ? '+' : ''}${row.diff}</strong> DIFF</span>
                </div>
              </a>
            `
          )
          .join('')}
      </div>
    `;
    return table + cards;
  }

  function scoreboard(match) {
    const team1 = match.team1Id ? store.select.team(match.team1Id) : null;
    const team2 = match.team2Id ? store.select.team(match.team2Id) : null;
    const tournament = store.select.tournament(match.tournamentId);
    const court = tournament?.courts.find((item) => item.id === match.courtId);
    const row = (team, score, winner) => `
      <div class="hf-flex hf-items-center hf-between hf-gap-2 ${winner ? 'is-winner' : ''}">
        <span class="hf-flex hf-items-center hf-gap-2">
          ${shield(team, 'lg')}
          <strong class="hf-page-title hf-mb-0" style="font-size: clamp(20px, 3vw, 34px)">${escapeHtml(team?.name || 'TBD')}</strong>
        </span>
        <span class="hf-score" style="font-size: clamp(34px, 6vw, 64px)">${escapeHtml(String(score ?? '–'))}</span>
      </div>
    `;
    return `
      <section class="hf-hero" data-testid="scoreboard-${escapeHtml(match.id)}">
        <span class="hf-eyebrow" style="color: var(--hf-gold)">${escapeHtml(match.roundLabel)} · ${escapeHtml(match.status)}</span>
        <div class="hf-mt-2">
          ${row(team1, match.score1, match.winnerId === match.team1Id)}
          <div class="hf-stat-strip"><div><span>Court</span><strong>${escapeHtml(court?.name || 'TBD')}</strong></div><div><span>Tip-off</span><strong>${match.scheduledAt ? escapeHtml(formatTime(match.scheduledAt)) : 'TBD'}</strong></div><div><span>Date</span><strong>${match.scheduledAt ? escapeHtml(formatDate(match.scheduledAt)) : 'TBD'}</strong></div></div>
          ${row(team2, match.score2, match.winnerId === match.team2Id)}
        </div>
      </section>
    `;
  }

  return {
    shield,
    matchCard,
    bracketMatch,
    kpiTile,
    pageHeader,
    sectionHeader,
    announcementCard,
    standingsTable,
    scoreboard,
    statusChip: ui.statusChip,
    emptyState: ui.emptyState,
  };
}
