const SORTABLE = new Set(['number', 'name', 'points', 'assists', 'rebounds']);

export function boxScoreRows(match, team) {
  if (!match || !team) return [];
  const side = match.team1Id === team.id ? 'team1' : match.team2Id === team.id ? 'team2' : null;
  if (!side) return [];
  const entries = match.boxScore?.[side] || [];
  const roster = new Map((team.players || []).map((player) => [player.id, player]));
  return entries.map((entry) => {
    const player = roster.get(entry.playerId);
    return {
      playerId: entry.playerId,
      name: player?.name || 'Unknown player',
      number: player?.number ?? null,
      position: player?.position || '',
      points: Number(entry.points) || 0,
      assists: Number(entry.ast ?? entry.assists) || 0,
      rebounds: Number(entry.reb ?? entry.rebounds) || 0,
    };
  });
}

export function sortBoxScore(rows, key = 'points', direction = 'desc') {
  const column = SORTABLE.has(key) ? key : 'points';
  const factor = direction === 'asc' ? 1 : -1;
  return [...rows].sort((a, b) => {
    if (column === 'name') return a.name.localeCompare(b.name) * factor;
    const first = Number(a[column] ?? 0);
    const second = Number(b[column] ?? 0);
    return (first - second) * factor || a.name.localeCompare(b.name);
  });
}

export function boxScoreTotals(rows) {
  return rows.reduce(
    (totals, row) => ({
      points: totals.points + row.points,
      assists: totals.assists + row.assists,
      rebounds: totals.rebounds + row.rebounds,
    }),
    { points: 0, assists: 0, rebounds: 0 }
  );
}