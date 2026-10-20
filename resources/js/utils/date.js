const UZ_MONTHS = [
  'yanvar',
  'fevral',
  'mart',
  'aprel',
  'may',
  'iyun',
  'iyul',
  'avgust',
  'sentabr',
  'oktabr',
  'noyabr',
  'dekabr',
];

export function formatUzLongDate(value) {
  if (!value) return '';

  const iso = typeof value === 'string' ? value.slice(0, 10) : '';
  const match = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);

  if (match) {
    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    return `${day} ${UZ_MONTHS[month - 1]} ${year}`;
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';

  return `${date.getDate()} ${UZ_MONTHS[date.getMonth()]} ${date.getFullYear()}`;
}

/** Masalan: 13 aprel */
export function formatUzDayMonth(value) {
  if (!value) return '';

  const iso = typeof value === 'string' ? value.slice(0, 10) : '';
  const match = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);

  if (match) {
    const month = Number(match[2]);
    const day = Number(match[3]);
    return `${day} ${UZ_MONTHS[month - 1]}`;
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';

  return `${date.getDate()} ${UZ_MONTHS[date.getMonth()]}`;
}
