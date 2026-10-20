/** Dona ↔ o‘rtacha vazn (g) ↔ umumiy massa (kg) bog‘lanishi (direktor kabineti bilan bir xil). */

export function parsePositive(value) {
  const n = Number(value);
  return Number.isFinite(n) && n > 0 ? n : null;
}

export function roundKg(value) {
  return Math.round(Number(value) * 1000) / 1000;
}

export function roundGrams(value) {
  return Math.round(Number(value) * 10) / 10;
}

/**
 * @param {object} row - reactive row with quantity/pieces, average_weight_g, total_weight_kg keys
 * @param {'pieces'|'average_weight_g'|'total_weight_kg'} field - just edited field
 * @param {string[]} editOrder - last edited fields for this block
 * @param {{ piecesKey?: string, avgKey?: string, kgKey?: string }} [keys]
 * @returns {string[]} updated edit order
 */
export function syncLinkedMass(row, field, editOrder = [], keys = {}) {
  const piecesKey = keys.piecesKey || 'pieces';
  const avgKey = keys.avgKey || 'average_weight_g';
  const kgKey = keys.kgKey || 'total_weight_kg';

  const nextOrder = [...editOrder.filter((item) => item !== field), field].slice(-2);

  const qty = parsePositive(row[piecesKey]);
  const grams = parsePositive(row[avgKey]);
  const kg = parsePositive(row[kgKey]);
  const filled = {
    [piecesKey]: qty,
    [avgKey]: grams,
    [kgKey]: kg,
  };
  const known = Object.keys(filled).filter((key) => filled[key] != null);
  if (known.length < 2) return nextOrder;

  let sources = nextOrder.filter((key) => filled[key] != null);
  if (sources.length < 2) {
    sources = known.slice(0, 2);
  } else {
    sources = sources.slice(-2);
  }

  const missing = [piecesKey, avgKey, kgKey].find((key) => !sources.includes(key));
  if (!missing) return nextOrder;

  if (missing === kgKey && qty != null && grams != null) {
    row[kgKey] = String(roundKg(qty * (grams / 1000)));
  } else if (missing === avgKey && qty != null && kg != null) {
    row[avgKey] = String(roundGrams((kg / qty) * 1000));
  } else if (missing === piecesKey && grams != null && kg != null) {
    row[piecesKey] = String(Math.max(1, Math.round(kg / (grams / 1000))));
  }

  return nextOrder;
}

export function avgGramsFrom(pieces, weightKg) {
  const qty = parsePositive(pieces);
  const kg = parsePositive(weightKg);
  if (qty == null || kg == null) return '';
  return String(roundGrams((kg / qty) * 1000));
}
