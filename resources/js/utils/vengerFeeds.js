export const VENGER_FEED_TYPES = [
  { id: 'extruder', group: 'extruder', labelKey: 'vengerFeedExtruder' },
  { id: 'granula', group: 'granula', labelKey: 'vengerFeedGranula' },
];

export function normalizeFeedType(type) {
  if (!type) return null;
  if (String(type).startsWith('extruder')) return 'extruder';
  if (String(type).startsWith('granula')) return 'granula';
  return VENGER_FEED_TYPES.some((f) => f.id === type) ? type : null;
}

export function emptyFeedMap() {
  return Object.fromEntries(VENGER_FEED_TYPES.map((f) => [f.id, '']));
}

export function feedsFromPayload(rows) {
  const map = emptyFeedMap();
  (rows || []).forEach((row) => {
    const type = normalizeFeedType(row?.type);
    if (type) {
      map[type] = row.kg ?? '';
    }
  });
  return map;
}

export function feedsToPayload(map) {
  return VENGER_FEED_TYPES
    .map((f) => ({ type: f.id, kg: Number(map[f.id] || 0) }))
    .filter((row) => row.kg > 0);
}

export function feedsTotalKg(map) {
  return VENGER_FEED_TYPES.reduce((sum, f) => sum + Number(map[f.id] || 0), 0);
}
