export function localDigits(value = '') {
  let digits = String(value).replace(/\D+/g, '');
  if (digits.startsWith('998')) {
    digits = digits.slice(3);
  }
  return digits.slice(0, 9);
}

export function toFullPhone(value = '') {
  return `+998${localDigits(value)}`;
}

export function isCompletePhone(value = '') {
  return /^\+998\d{9}$/.test(toFullPhone(value));
}

/** 901234567 → 90 123 45 67 */
export function formatLocalPhone(value = '') {
  const digits = localDigits(value);
  if (!digits.length) return '';

  let formatted = digits.slice(0, 2);
  if (digits.length > 2) formatted += ` ${digits.slice(2, 5)}`;
  if (digits.length > 5) formatted += ` ${digits.slice(5, 7)}`;
  if (digits.length > 7) formatted += ` ${digits.slice(7, 9)}`;
  return formatted;
}

/** +998901234567 → +998 90 123 45 67 */
export function formatPhoneDisplay(value = '') {
  const digits = localDigits(value);
  if (!digits.length) return '+998';
  return `+998 ${formatLocalPhone(digits)}`.trim();
}
