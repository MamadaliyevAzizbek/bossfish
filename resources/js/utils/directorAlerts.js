const STORAGE_PREFIX = 'bossfish.director.alerts.read.';

function storageKey(userId) {
  return `${STORAGE_PREFIX}${userId || 'guest'}`;
}

function readMap(userId) {
  try {
    const raw = localStorage.getItem(storageKey(userId));
    if (!raw) return {};
    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch (e) {
    return {};
  }
}

function writeMap(userId, map) {
  try {
    localStorage.setItem(storageKey(userId), JSON.stringify(map));
  } catch (e) {
    /* ignore quota / private mode */
  }
}

/** Barqaror kalit — bir xil ogohlantirish qayta chiqmasin */
export function alertIdentity(alert) {
  if (!alert) return '';
  if (alert.id) return String(alert.id);
  const parts = [alert.type || 'alert'];
  if (alert.farmId != null) parts.push(`farm:${alert.farmId}`);
  if (alert.itemId != null) parts.push(`item:${alert.itemId}`);
  if (alert.detail) parts.push(String(alert.detail));
  return parts.join('|');
}

export function getReadAlertIds(userId) {
  return readMap(userId);
}

export function isAlertRead(userId, alert) {
  const id = alertIdentity(alert);
  if (!id) return false;
  return !!readMap(userId)[id];
}

export function markAlertRead(userId, alert) {
  const id = alertIdentity(alert);
  if (!id) return;
  const map = readMap(userId);
  map[id] = Date.now();
  writeMap(userId, map);
}

export function filterUnreadAlerts(userId, alerts) {
  const map = readMap(userId);
  return (alerts || []).filter((alert) => !map[alertIdentity(alert)]);
}
