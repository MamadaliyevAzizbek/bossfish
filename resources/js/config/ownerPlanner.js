export const PLANNER_DAYS = [
    { key: 'mon', labelKey: 'plannerDayMon', shortLabelKey: 'plannerDayMonShort' },
    { key: 'tue', labelKey: 'plannerDayTue', shortLabelKey: 'plannerDayTueShort' },
    { key: 'wed', labelKey: 'plannerDayWed', shortLabelKey: 'plannerDayWedShort' },
    { key: 'thu', labelKey: 'plannerDayThu', shortLabelKey: 'plannerDayThuShort' },
    { key: 'fri', labelKey: 'plannerDayFri', shortLabelKey: 'plannerDayFriShort' },
    { key: 'sat', labelKey: 'plannerDaySat', shortLabelKey: 'plannerDaySatShort' },
    { key: 'sun', labelKey: 'plannerDaySun', shortLabelKey: 'plannerDaySunShort' },
];

export const PLANNER_MONTHS = [
    { key: '1', labelKey: 'plannerMonthJan', shortLabelKey: 'plannerMonthJanShort' },
    { key: '2', labelKey: 'plannerMonthFeb', shortLabelKey: 'plannerMonthFebShort' },
    { key: '3', labelKey: 'plannerMonthMar', shortLabelKey: 'plannerMonthMarShort' },
    { key: '4', labelKey: 'plannerMonthApr', shortLabelKey: 'plannerMonthAprShort' },
    { key: '5', labelKey: 'plannerMonthMay', shortLabelKey: 'plannerMonthMayShort' },
    { key: '6', labelKey: 'plannerMonthJun', shortLabelKey: 'plannerMonthJunShort' },
    { key: '7', labelKey: 'plannerMonthJul', shortLabelKey: 'plannerMonthJulShort' },
    { key: '8', labelKey: 'plannerMonthAug', shortLabelKey: 'plannerMonthAugShort' },
    { key: '9', labelKey: 'plannerMonthSep', shortLabelKey: 'plannerMonthSepShort' },
    { key: '10', labelKey: 'plannerMonthOct', shortLabelKey: 'plannerMonthOctShort' },
    { key: '11', labelKey: 'plannerMonthNov', shortLabelKey: 'plannerMonthNovShort' },
    { key: '12', labelKey: 'plannerMonthDec', shortLabelKey: 'plannerMonthDecShort' },
];

export const PLANNER_PRAYERS = [
    { key: 'bomdod', labelKey: 'plannerPrayerBomdod', beforeKey: 'before_bomdod', afterKey: 'after_bomdod' },
    { key: 'peshin', labelKey: 'plannerPrayerPeshin', beforeKey: 'before_peshin', afterKey: 'after_peshin' },
    { key: 'asr', labelKey: 'plannerPrayerAsr', beforeKey: 'before_asr', afterKey: 'after_asr' },
    { key: 'shom', labelKey: 'plannerPrayerShom', beforeKey: 'before_shom', afterKey: 'after_shom' },
    { key: 'xufton', labelKey: 'plannerPrayerXufton', beforeKey: 'before_xufton', afterKey: 'after_xufton' },
];

export const PLANNER_SLOT_KEYS = PLANNER_PRAYERS.flatMap((prayer) => [prayer.beforeKey, prayer.afterKey]);

/** Ranglar: bo‘sh qator rangsiz, yozilganlar ajralib turadi, qizil = xato */
export const PLANNER_TONES = ['sky', 'mint', 'sand', 'lilac', 'red', 'ocean'];

export function emptyCell() {
    return { text: '', color: null };
}

export function emptySlotCells() {
    return [emptyCell()];
}

export function normalizeCell(item, fallbackTone = null) {
    if (item && typeof item === 'object' && !Array.isArray(item)) {
        const text = typeof item.text === 'string' ? item.text : '';
        let color = typeof item.color === 'string' && PLANNER_TONES.includes(item.color) ? item.color : null;
        if (text.trim() === '') {
            color = null;
        } else if (!color && fallbackTone) {
            color = fallbackTone;
        }

        return { text, color };
    }

    if (typeof item === 'string') {
        const text = item;
        return {
            text,
            color: text.trim() === '' ? null : (fallbackTone || 'sky'),
        };
    }

    return emptyCell();
}

export function normalizeSlotCells(value, slotIndex = 0) {
    if (Array.isArray(value)) {
        const cells = value.map((item, index) => normalizeCell(item, PLANNER_TONES[(slotIndex + index) % PLANNER_TONES.length]));

        return cells.length ? cells : emptySlotCells();
    }

    if (typeof value === 'string') {
        return value.trim() === '' ? emptySlotCells() : [normalizeCell(value, 'sky')];
    }

    return emptySlotCells();
}

export function isCellEmpty(cell) {
    return !cell?.text || cell.text.trim() === '';
}

export function nextTone(current) {
    if (!current) {
        return PLANNER_TONES[0];
    }

    const index = PLANNER_TONES.indexOf(current);
    if (index === -1) {
        return PLANNER_TONES[0];
    }

    return PLANNER_TONES[(index + 1) % PLANNER_TONES.length];
}

export function emptyPlannerContent() {
    const days = {};
    for (const day of PLANNER_DAYS) {
        days[day.key] = emptyDaySlots();
    }
    return { days };
}

export function emptyDaySlots() {
    const day = {};
    for (const slotKey of PLANNER_SLOT_KEYS) {
        day[slotKey] = emptySlotCells();
    }
    return day;
}

export function normalizeDaySlots(payload = {}) {
    if (Array.isArray(payload)) {
        const day = emptyDaySlots();
        day.after_bomdod = normalizeSlotCells(payload);
        return day;
    }

    const day = emptyDaySlots();
    PLANNER_SLOT_KEYS.forEach((slotKey, index) => {
        day[slotKey] = normalizeSlotCells(payload?.[slotKey], index);
    });
    return day;
}

export function mergePlannerContent(target, incoming) {
    const payload = incoming?.days || {};
    for (const day of PLANNER_DAYS) {
        if (!target.days[day.key]) {
            target.days[day.key] = emptyDaySlots();
        }
        const normalized = normalizeDaySlots(payload[day.key] || {});
        for (const slotKey of PLANNER_SLOT_KEYS) {
            target.days[day.key][slotKey] = normalized[slotKey];
        }
    }
}

export function lineToneClass(cell) {
    if (isCellEmpty(cell) || !cell.color) {
        return 'owner-planner-line--empty';
    }

    return `owner-planner-line--tone-${cell.color}`;
}

/** API: YYYY-MM-DD → ko‘rinish: DD.MM.YYYY */
export function formatPlannerDate(iso) {
    if (!iso || typeof iso !== 'string') return '';
    const [y, m, d] = iso.split('-');
    if (!y || !m || !d) return iso;
    return `${d}.${m}.${y}`;
}

export function toIsoDate(date = new Date()) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

export function emptyMonthlyContent() {
    return { days: {} };
}

export function mergeMonthlyContent(target, incoming) {
    const payload = incoming?.days || {};
    Object.keys(target.days || {}).forEach((k) => delete target.days[k]);
    if (!target.days) target.days = {};
    Object.entries(payload).forEach(([day, cells]) => {
        target.days[String(Number(day))] = normalizeSlotCells(cells);
    });
}

export function newLongTermId() {
    return `lt_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
}

