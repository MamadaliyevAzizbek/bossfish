function formatGrouped(value, maxFractionDigits) {
  const n = Number(value);
  if (!Number.isFinite(n)) return '—';

  const factor = 10 ** maxFractionDigits;
  const rounded = Math.round(n * factor) / factor;
  const negative = rounded < 0;
  const abs = Math.abs(rounded);

  const [intPart, decPart = ''] = abs.toFixed(maxFractionDigits).split('.');
  const groupedInt = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  const trimmedDec = decPart.replace(/0+$/, '');
  const prefix = negative ? '-' : '';

  if (trimmedDec) {
    return `${prefix}${groupedInt},${trimmedDec}`;
  }

  return `${prefix}${groupedInt}`;
}

/** Summa va narx: 3 500 yoki 478 428,5 */
export function formatMoney(value) {
  return formatGrouped(value, 2);
}

/** Miqdor: 38 yoki 34,5 */
export function formatQty(value) {
  return formatGrouped(value, 3);
}

/** Excel dagidek: 152 кг, 268 кг 400 гр */
export function formatKgLabelPrint(kg) {
  const n = Number(kg);
  if (!Number.isFinite(n)) return '—';
  const whole = Math.floor(n);
  const grams = Math.round((n - whole) * 1000);

  if (grams <= 0) {
    return `${whole} кг`;
  }

  if (grams >= 1000) {
    return `${Math.round(n * 1000) / 1000} кг`;
  }

  return `${whole} кг ${grams} гр`;
}
