function normalize(value) {
  if (value === null || value === undefined || value === '') return 0;
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

/** Kiritish maydonidan API uchun toza raqam matni (nuqta bilan). */
export function parseFormattedNumber(value) {
  if (value === null || value === undefined) return '';
  let text = String(value).trim().replace(/\s/g, '');
  if (text === '' || text === '-') return text;

  const negative = text.startsWith('-');
  if (negative) text = text.slice(1);

  const sepIndex = Math.max(text.lastIndexOf(','), text.lastIndexOf('.'));
  let intPart = '';
  let decPart = '';
  let trailingSep = false;

  if (sepIndex >= 0) {
    intPart = text.slice(0, sepIndex).replace(/\D/g, '');
    decPart = text.slice(sepIndex + 1).replace(/\D/g, '');
    trailingSep = sepIndex === text.length - 1;
  } else {
    intPart = text.replace(/\D/g, '');
  }

  if (!intPart && !decPart && !trailingSep) {
    return negative ? '-' : '';
  }

  const prefix = negative ? '-' : '';
  if (trailingSep) {
    return `${prefix}${intPart || '0'}.`;
  }
  if (decPart) {
    return `${prefix}${intPart || '0'}.${decPart}`;
  }
  return `${prefix}${intPart}`;
}

/** Kiritish maydonida ko‘rsatish — 123 456 789 yoki 1 234,56 */
export function formatNumberInput(value, { maxDecimals = 2, integerOnly = false } = {}) {
  const raw = parseFormattedNumber(value);
  if (raw === '' || raw === '-') return raw;

  const negative = raw.startsWith('-');
  const unsigned = negative ? raw.slice(1) : raw;
  const trailingDot = unsigned.endsWith('.');
  const [intPartRaw, decPartRaw = ''] = trailingDot ? [unsigned.slice(0, -1), ''] : unsigned.split('.');
  const intPart = intPartRaw.replace(/^0+(?=\d)/, '') || (intPartRaw ? '0' : '0');
  const decPart = integerOnly ? '' : decPartRaw.slice(0, maxDecimals);
  const grouped = (intPart || '0').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  const prefix = negative ? '-' : '';

  if (integerOnly) {
    return `${prefix}${grouped}`;
  }
  if (decPart) {
    return `${prefix}${grouped},${decPart}`;
  }
  if (trailingDot) {
    return `${prefix}${grouped},`;
  }
  return `${prefix}${grouped}`;
}

/** Kiritilgan qiymatni formatlab qayta ishlash. */
export function sanitizeFormattedNumberInput(value, options = {}) {
  const raw = parseFormattedNumber(value);
  const display = formatNumberInput(raw, options);
  return { raw: raw === '-' ? '' : raw, display: display === '-' ? '' : display };
}

function roundTo(value, decimals) {
  const factor = 10 ** decimals;
  return Math.round(value * factor) / factor;
}

/** Minglik ajratgich — bo‘sh joy (vergul emas). */
export function formatNumber(value, { decimals = null, maxDecimals = 2 } = {}) {
  const n = normalize(value);
  let rounded = n;

  if (decimals !== null) {
    rounded = roundTo(n, decimals);
  } else if (maxDecimals !== null) {
    rounded = roundTo(n, maxDecimals);
  }

  const fixed = decimals !== null
    ? rounded.toFixed(decimals)
    : (Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(maxDecimals).replace(/\.?0+$/, ''));

  const [intPart, decPart] = fixed.split('.');
  const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

  return decPart ? `${grouped},${decPart}` : grouped;
}

export function formatInt(value) {
  return formatNumber(value, { decimals: 0 });
}

/** Grammlar butun son — o‘nliksiz. */
export function formatGrams(value) {
  return formatInt(Math.round(normalize(value)));
}

/** Kg — katta qiymatlarda yaxlit. */
export function formatKg(value) {
  const n = normalize(value);
  if (n >= 1000) return formatInt(Math.round(n));
  if (n >= 100) return formatNumber(n, { decimals: 0 });
  if (n >= 10) return formatNumber(n, { decimals: 1 });
  return formatNumber(n, { decimals: 2 });
}

/** Umumiy massa — gramm qismi yaxlitlanadi. */
export function formatKgMass(value) {
  const n = normalize(value);
  if (n >= 10) return formatInt(Math.round(n));
  if (n >= 1) return formatNumber(n, { decimals: 1 });
  return formatNumber(n, { decimals: 2 });
}

export function kgToGrams(value) {
  return Math.round(normalize(value) * 1000);
}

/** Pul — so‘m, minglik ajratgich. */
export function formatMoney(value) {
  return `${formatNumber(value, { decimals: 0 })} so‘m`;
}
